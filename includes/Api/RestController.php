<?php
/**
 * REST API végpontok + aszinkron háttérfeladat-feldolgozó.
 *
 * Async flow:
 *   POST /generate-async → {job_id}  (azonnali 202)
 *   GET  /job-status/{id} → {status, message}  (polling)
 *   wp_ajax aie_bg_generate → tényleges generálás
 *
 * @package AIE\Api
 */

namespace AIE\Api;

defined( 'ABSPATH' ) || exit;

use AIE\AI\ClaudeClient;
use AIE\AI\GroqClient;
use AIE\AI\PromptBuilder;
use AIE\AI\TemplateAssembler;
use AIE\AI\TemplateLibrary;
use AIE\Elementor\DataManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class RestController {

    private const NAMESPACE = 'ai-builder/v1';

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, '/generate', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_generate' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => $this->get_generate_args(),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/generate-async', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_start_async' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => $this->get_generate_args(),
            ],
        ] );

        register_rest_route( self::NAMESPACE, '/job-status/(?P<job_id>[a-zA-Z0-9_-]+)', [
            [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_job_status' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => [
                    'job_id' => [
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ],
        ] );
    }

    // ── Jogosultság ───────────────────────────────────────────────────────────

    public function check_permissions( WP_REST_Request $request ): bool|WP_Error {
        if ( ! is_user_logged_in() ) {
            return new WP_Error( 'aie_unauthorized', __( 'Ehhez be kell jelentkezned.', 'ai-elementor-builder' ), [ 'status' => 401 ] );
        }
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error( 'aie_forbidden', __( 'Nincs jogosultságod ehhez a művelethez.', 'ai-elementor-builder' ), [ 'status' => 403 ] );
        }
        $post_id = (int) $request->get_param( 'post_id' );
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error( 'aie_forbidden_post', __( 'Nincs jogod szerkeszteni ezt az oldalt.', 'ai-elementor-builder' ), [ 'status' => 403 ] );
        }
        return true;
    }

    // ── Argumentumok ──────────────────────────────────────────────────────────

    private function get_generate_args(): array {
        return [
            'post_id' => [
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
            ],
            'prompt' => [
                'required'          => true,
                'type'              => 'string',
                'minLength'         => 5,
                'maxLength'         => 2000,
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'mode' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => 'auto',
                'enum'              => [ 'auto', 'create', 'modify' ],
                'sanitize_callback' => 'sanitize_key',
            ],
            'reference_images' => [
                'required'          => false,
                'type'              => 'array',
                'default'           => [],
            ],
        ];
    }

    // ── Szinkron kezelő ───────────────────────────────────────────────────────

    public function handle_generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id          = (int) $request->get_param( 'post_id' );
        $prompt           = (string) $request->get_param( 'prompt' );
        $mode             = (string) $request->get_param( 'mode' );
        $reference_images = (array) $request->get_param( 'reference_images' );

        $data_manager  = new DataManager();
        $current_json  = $data_manager->get_elementor_data( $post_id );
        $global_styles = $data_manager->get_global_styles();

        if ( 'auto' === $mode ) {
            $mode = ( ! empty( $current_json ) && '[]' !== $current_json ) ? 'modify' : 'create';
        }

        $new_data = ( 'modify' === $mode )
            ? $this->run_modify( $prompt, $current_json, $global_styles )
            : $this->run_create( $prompt, $global_styles, $post_id, $reference_images );

        if ( is_wp_error( $new_data ) ) {
            return $new_data;
        }

        $save_result = $data_manager->save_elementor_data( $post_id, $new_data );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        $data_manager->clear_elementor_cache( $post_id );

        return new WP_REST_Response( [
            'success' => true,
            'mode'    => $mode,
            'message' => __( 'Az oldal sikeresen generálva.', 'ai-elementor-builder' ),
        ], 200 );
    }

    // ── Aszinkron indítás ─────────────────────────────────────────────────────

    public function handle_start_async( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id          = (int) $request->get_param( 'post_id' );
        $prompt           = (string) $request->get_param( 'prompt' );
        $mode             = (string) $request->get_param( 'mode' );
        $reference_images = array_map( 'absint', (array) $request->get_param( 'reference_images' ) );

        $job_id = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 24 );

        $job_data = [
            'status'           => 'processing',
            'user_id'          => get_current_user_id(),
            'post_id'          => $post_id,
            'prompt'           => $prompt,
            'mode'             => $mode,
            'reference_images' => $reference_images,
            'created'          => time(),
            'message'          => __( 'AI tervezi az oldalt...', 'ai-elementor-builder' ),
        ];
        set_transient( 'aie_job_' . $job_id, $job_data, 2 * HOUR_IN_SECONDS );

        $response_body = wp_json_encode( [
            'job_id'  => $job_id,
            'status'  => 'pending',
            'message' => __( 'Generálás elindítva.', 'ai-elementor-builder' ),
        ] );

        // ── 1. megközelítés: fastcgi_finish_request() ─────────────────────────
        // PHP-FPM környezetben: küldi a választ a kliensnek, majd folytatja a
        // feldolgozást a háttérben. Nincs szükség loopback kérésre.
        if ( function_exists( 'fastcgi_finish_request' ) ) {
            // Output pufferek törlése (WordPress ob_start-jai)
            while ( ob_get_level() > 0 ) {
                ob_end_clean();
            }

            // Válasz küldése a kliensnek
            http_response_code( 202 );
            header( 'Content-Type: application/json; charset=utf-8' );
            header( 'Content-Length: ' . strlen( $response_body ) );
            header( 'Connection: close' );
            echo $response_body;

            fastcgi_finish_request(); // ← kliens megkapta a választ, PHP tovább fut

            // Háttérben fut tovább
            @ignore_user_abort( true );
            @set_time_limit( 600 );

            try {
                $this->execute_job( $job_id, $job_data );
            } catch ( \Throwable $e ) {
                $job_data['status']  = 'error';
                $job_data['message'] = 'Fatális hiba: ' . $e->getMessage();
                set_transient( 'aie_job_' . $job_id, $job_data, HOUR_IN_SECONDS );
            }
            exit();
        }

        // ── 2. megközelítés: loopback + WP-Cron fallback ──────────────────────
        // Ha fastcgi_finish_request() nem elérhető (mod_php, CGI stb.)
        $bg_token = wp_generate_password( 32, false, false );
        $job_data['bg_token'] = wp_hash( $bg_token );
        $job_data['status']   = 'pending';
        set_transient( 'aie_job_' . $job_id, $job_data, 2 * HOUR_IN_SECONDS );

        // Loopback (nem-blokkoló)
        wp_remote_post(
            admin_url( 'admin-ajax.php' ),
            [
                'timeout'   => 0.01,
                'blocking'  => false,
                'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
                'body'      => [
                    'action'   => 'aie_bg_generate',
                    'job_id'   => $job_id,
                    'bg_token' => $bg_token,
                ],
            ]
        );

        // WP-Cron fallback – ha a loopback sikertelen
        if ( ! wp_next_scheduled( 'aie_bg_generate_cron', [ $job_id ] ) ) {
            wp_schedule_single_event( time() + 8, 'aie_bg_generate_cron', [ $job_id, $bg_token ] );
            spawn_cron();
        }

        return new WP_REST_Response( [
            'job_id'  => $job_id,
            'status'  => 'pending',
            'message' => __( 'Generálás elindítva.', 'ai-elementor-builder' ),
        ], 202 );
    }

    // ── Feladat státusz ───────────────────────────────────────────────────────

    public function handle_job_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = sanitize_key( $request->get_param( 'job_id' ) );
        $job    = get_transient( 'aie_job_' . $job_id );

        if ( false === $job ) {
            return new WP_Error( 'aie_job_not_found', __( 'Feladat nem található.', 'ai-elementor-builder' ), [ 'status' => 404 ] );
        }

        if ( (int) $job['user_id'] !== get_current_user_id() ) {
            return new WP_Error( 'aie_forbidden', __( 'Nem jogosult ehhez a feladathoz.', 'ai-elementor-builder' ), [ 'status' => 403 ] );
        }

        $age = time() - ( $job['created'] ?? 0 );

        // Ha 6 percnél több ideje pending – loopback és cron egyaránt sikertelen
        if ( 'pending' === $job['status'] && $age > 360 ) {
            delete_transient( 'aie_job_' . $job_id );
            return new WP_REST_Response( [
                'status'  => 'error',
                'message' => __( 'A generálás nem indult el. Ellenőrizd a szerver loopback beállításait, majd próbáld újra.', 'ai-elementor-builder' ),
            ], 200 );
        }

        // Ha 15 percnél több ideje processing – PHP process valószínűleg meghalt
        if ( 'processing' === $job['status'] && $age > 900 ) {
            delete_transient( 'aie_job_' . $job_id );
            return new WP_REST_Response( [
                'status'  => 'error',
                'message' => __( 'A generálás váratlanul leállt (PHP timeout vagy memória limit). Próbáld rövidebb prompttal vagy kevesebb szekció kéréssel.', 'ai-elementor-builder' ),
            ], 200 );
        }

        $response = [
            'status'  => $job['status'],
            'message' => $job['message'] ?? '',
        ];

        if ( 'done' === $job['status'] || 'error' === $job['status'] ) {
            delete_transient( 'aie_job_' . $job_id );
        }

        return new WP_REST_Response( $response, 200 );
    }

    // ── Háttérfeladat (wp_ajax) ───────────────────────────────────────────────

    public function process_bg_job(): void {
        $job_id   = sanitize_key( $_POST['job_id'] ?? '' );
        $bg_token = sanitize_text_field( $_POST['bg_token'] ?? '' );

        if ( empty( $job_id ) || empty( $bg_token ) ) {
            wp_die();
        }

        $job = get_transient( 'aie_job_' . $job_id );
        if ( false === $job || ! hash_equals( $job['bg_token'], wp_hash( $bg_token ) ) ) {
            wp_die();
        }

        // Ha már fut vagy kész, skip
        if ( in_array( $job['status'], [ 'processing', 'done', 'error' ], true ) ) {
            wp_die();
        }

        $this->execute_job( $job_id, $job );
        wp_die();
    }

    /**
     * WP-Cron fallback: csak akkor fut le, ha a loopback nem indult el.
     */
    public function cron_process_job( string $job_id, string $bg_token ): void {
        $job = get_transient( 'aie_job_' . $job_id );
        if ( false === $job ) {
            return;
        }
        if ( ! hash_equals( $job['bg_token'], wp_hash( $bg_token ) ) ) {
            return;
        }
        // Ha a loopback már elvégezte, skip
        if ( in_array( $job['status'], [ 'processing', 'done', 'error' ], true ) ) {
            return;
        }
        $this->execute_job( $job_id, $job );
    }

    /**
     * A tényleges generálási logika – loopback és cron is ezt hívja.
     */
    private function execute_job( string $job_id, array $job ): void {
        $job['status']  = 'processing';
        $job['message'] = __( 'AI tervezi az oldalt...', 'ai-elementor-builder' );
        set_transient( 'aie_job_' . $job_id, $job, 2 * HOUR_IN_SECONDS );

        @ignore_user_abort( true );
        @set_time_limit( 600 );

        $post_id          = (int) $job['post_id'];
        $prompt           = $job['prompt'];
        $mode             = $job['mode'];
        $reference_images = $job['reference_images'] ?? [];

        try {
            $data_manager  = new DataManager();
            $current_json  = $data_manager->get_elementor_data( $post_id );
            $global_styles = $data_manager->get_global_styles();

            if ( 'auto' === $mode ) {
                $mode = ( ! empty( $current_json ) && '[]' !== $current_json ) ? 'modify' : 'create';
            }

            $new_data = ( 'modify' === $mode )
                ? $this->run_modify( $prompt, $current_json, $global_styles )
                : $this->run_create( $prompt, $global_styles, $post_id, $reference_images, $job_id );

            if ( is_wp_error( $new_data ) ) {
                $job['status']  = 'error';
                $job['message'] = $new_data->get_error_message();
                set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );
                return;
            }

            // Képek letöltése média könyvtárba
            $this->update_job_message( $job_id, __( 'Képek mentése a médiába...', 'ai-elementor-builder' ) );
            $data_manager->import_page_images( $new_data, $post_id );

            $save_result = $data_manager->save_elementor_data( $post_id, $new_data );
            if ( is_wp_error( $save_result ) ) {
                $job['status']  = 'error';
                $job['message'] = $save_result->get_error_message();
                set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );
                return;
            }

            $data_manager->clear_elementor_cache( $post_id );

            $job['status']  = 'done';
            $job['message'] = __( 'Az oldal sikeresen generálva!', 'ai-elementor-builder' );
            set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );

        } catch ( \Throwable $e ) {
            $job['status']  = 'error';
            $job['message'] = 'Váratlan hiba: ' . $e->getMessage();
            set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );
        }
    }

    // ── Create ────────────────────────────────────────────────────────────────

    private function run_create(
        string $prompt,
        array  $global_styles,
        int    $post_id = 0,
        array  $reference_image_ids = [],
        string $job_id = ''
    ): array|WP_Error {
        $has_pro        = defined( 'ELEMENTOR_PRO_VERSION' );
        $prompt_builder = new PromptBuilder();
        $data_manager   = new DataManager();
        $settings       = (array) get_option( AIE_OPTION_KEY, [] );
        $provider       = $settings['ai_provider'] ?? 'claude';

        // 1. Szín-paletta generálás / Kit frissítés
        $this->update_job_message( $job_id, __( 'Szín-paletta generálása...', 'ai-elementor-builder' ) );
        $detected_colors = $this->extract_colors_from_prompt( $prompt );
        $kit_colors      = $global_styles['colors'] ?? [];

        // Ha nincs kit szín VAGY van saját szín a promptban → generáljunk palettát
        if ( empty( $kit_colors ) || ! empty( $detected_colors ) ) {
            $palette = $this->generate_color_palette( $prompt, $prompt_builder, $detected_colors );
            if ( ! empty( $palette ) ) {
                $data_manager->set_kit_colors( $palette );
                // Frissítjük a global_styles-t az új palettával
                $global_styles = $data_manager->get_global_styles();
            }
        }

        // 2. Referencia képek URL-ek lekérése (attachment ID → URL)
        $vision_image_urls = [];
        if ( ! empty( $reference_image_ids ) && 'claude' === $provider ) {
            foreach ( $reference_image_ids as $att_id ) {
                $url = $data_manager->get_attachment_url( (int) $att_id );
                if ( $url ) {
                    $vision_image_urls[] = $url;
                }
            }
        }

        // 3. Oldal-terv generálás
        $this->update_job_message( $job_id, __( 'AI tervezi az oldal struktúráját...', 'ai-elementor-builder' ) );
        $plan_messages = $prompt_builder->build_plan( $prompt, $vision_image_urls );
        $plan_raw      = $this->ai_call( $plan_messages, 512 );

        if ( is_wp_error( $plan_raw ) ) {
            $section_plan = $this->get_default_plan();
        } else {
            $section_plan = $this->parse_plan( $plan_raw );
            if ( empty( $section_plan ) ) {
                $section_plan = $this->get_default_plan();
            }
        }

        // 4. Szekciók generálása — template-first, AI fallback ismeretlen típusokhoz
        $total     = count( $section_plan );
        $sections  = [];
        $assembler = new TemplateAssembler();
        TemplateLibrary::reset_ids();

        foreach ( $section_plan as $idx => $section_meta ) {
            $type  = $section_meta['type'] ?? '';
            $label = $type ?: ( 'section_' . ( $idx + 1 ) );
            $this->update_job_message(
                $job_id,
                sprintf( __( 'Szekció: %d/%d — %s', 'ai-elementor-builder' ), $idx + 1, $total, $label )
            );

            // PHP sablon — ha van, azonnal kész (nincs AI hívás)
            $section = $assembler->try_template( $type, $section_meta );

            if ( null === $section ) {
                // Ismeretlen típus → AI generálja
                $messages = $prompt_builder->build_section( $section_meta, $prompt, $global_styles, $has_pro );
                $raw      = $this->ai_call( $messages, 0 );
                if ( is_wp_error( $raw ) ) {
                    continue;
                }
                $section = $this->parse_single_section( $raw );
            }

            if ( null !== $section ) {
                $sections[] = $section;
            }
        }

        if ( empty( $sections ) ) {
            return new WP_Error(
                'aie_generation_failed',
                __( 'Az AI nem tudott egyetlen szekciót sem generálni. Próbálj újra.', 'ai-elementor-builder' ),
                [ 'status' => 422 ]
            );
        }

        return $sections;
    }

    // ── Modify ────────────────────────────────────────────────────────────────

    private function run_modify( string $prompt, string $current_json, array $global_styles ): array|WP_Error {
        $prompt_builder = new PromptBuilder();
        $messages       = $prompt_builder->build_modify( $prompt, $current_json, $global_styles );
        $raw = $this->ai_call( $messages, 0 );
        if ( is_wp_error( $raw ) ) {
            return $raw;
        }
        return $this->parse_elementor_json( $raw );
    }

    // ── Szín-paletta generálás ────────────────────────────────────────────────

    private function extract_colors_from_prompt( string $prompt ): array {
        $colors = [];

        // Hex kódok kiszedése
        preg_match_all( '/#([0-9a-fA-F]{6}|[0-9a-fA-F]{3})\b/', $prompt, $hex_matches );
        foreach ( $hex_matches[0] as $hex ) {
            $colors[] = strtolower( $hex );
        }

        // Alapszín nevek
        $color_map = [
            'kék' => '#1d4ed8', 'blue' => '#1d4ed8', 'navy' => '#1e3a5f',
            'piros' => '#dc2626', 'red' => '#dc2626', 'crimson' => '#dc143c',
            'zöld' => '#16a34a', 'green' => '#16a34a',
            'lila' => '#7c3aed', 'purple' => '#7c3aed', 'violet' => '#8b5cf6',
            'narancs' => '#ea580c', 'orange' => '#ea580c',
            'sárga' => '#ca8a04', 'yellow' => '#ca8a04', 'gold' => '#b45309',
            'pink' => '#ec4899', 'rosa' => '#ec4899',
            'türkiz' => '#0d9488', 'teal' => '#0d9488', 'cyan' => '#0891b2',
            'barna' => '#92400e', 'brown' => '#92400e',
            'fekete' => '#111827', 'black' => '#111827',
            'fehér' => '#ffffff', 'white' => '#ffffff',
        ];

        $prompt_lower = mb_strtolower( $prompt );
        foreach ( $color_map as $name => $hex ) {
            if ( false !== mb_strpos( $prompt_lower, $name ) ) {
                $colors[] = $hex;
            }
        }

        return array_unique( $colors );
    }

    private function generate_color_palette( string $prompt, PromptBuilder $builder, array $detected_colors ): array {
        $messages = $builder->build_color_scheme( $prompt, $detected_colors );
        $raw      = $this->ai_call( $messages, 200 );

        if ( is_wp_error( $raw ) ) {
            return [];
        }

        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $start = strpos( $cleaned, '{' );
        $end   = strrpos( $cleaned, '}' );
        if ( false === $start || false === $end ) {
            return [];
        }

        $palette = json_decode( substr( $cleaned, $start, $end - $start + 1 ), true );
        return is_array( $palette ) ? $palette : [];
    }

    // ── AI hívás ─────────────────────────────────────────────────────────────

    private function ai_call( array $messages, int $max_tokens = 0 ): string|WP_Error {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $provider = $settings['ai_provider'] ?? 'claude';
        return ( 'groq' === $provider )
            ? ( new GroqClient() )->chat( $messages, $max_tokens )
            : ( new ClaudeClient() )->chat( $messages, $max_tokens );
    }

    private function update_job_message( string $job_id, string $message ): void {
        if ( empty( $job_id ) ) {
            return;
        }
        $job = get_transient( 'aie_job_' . $job_id );
        if ( false !== $job ) {
            $job['message'] = $message;
            set_transient( 'aie_job_' . $job_id, $job, 2 * HOUR_IN_SECONDS );
        }
    }

    // ── Plan parsing ──────────────────────────────────────────────────────────

    private function parse_plan( string $raw ): array {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $start = strpos( $cleaned, '[' );
        $end   = strrpos( $cleaned, ']' );
        if ( false === $start || false === $end || $end <= $start ) {
            return [];
        }

        $decoded = json_decode( substr( $cleaned, $start, $end - $start + 1 ), true );
        if ( ! is_array( $decoded ) ) {
            return [];
        }

        $valid = [];
        foreach ( $decoded as $item ) {
            if ( ! is_array( $item ) || empty( $item['type'] ) ) {
                continue;
            }
            $valid[] = [
                'type'     => sanitize_key( $item['type'] ),
                'purpose'  => sanitize_text_field( $item['purpose'] ?? '' ),
                'layout'   => sanitize_text_field( $item['layout'] ?? '' ),
                'content'  => sanitize_text_field( $item['content'] ?? '' ),
                'bg_theme' => in_array( $item['bg_theme'] ?? '', [ 'dark', 'light', 'accent' ], true )
                              ? $item['bg_theme']
                              : 'light',
            ];
        }

        if ( count( $valid ) < 3 ) {
            return [];
        }

        return array_slice( $valid, 0, 9 );
    }

    private function get_default_plan(): array {
        return [
            [ 'type' => 'hero',         'purpose' => 'Above-the-fold hero with headline and primary CTA',     'layout' => 'full-height split: text left 55%, image right 45%, dark gradient background',   'content' => 'h1 headline, subtitle, CTA button, 3 key stats' ],
            [ 'type' => 'services',     'purpose' => 'Showcase main services or features',                    'layout' => '3-column white cards with icons on light gray background',                      'content' => '3 service cards: icon, title, 2-sentence description' ],
            [ 'type' => 'about',        'purpose' => 'Build trust, explain why to choose this business',      'layout' => 'two-column: benefit list left, photo right, white background',                   'content' => 'section label, h2, 4 checkmark benefits, CTA button, photo' ],
            [ 'type' => 'stats',        'purpose' => 'Impress with numbers and social proof metrics',         'layout' => 'full-width accent gradient background, 4 counter widgets in a row',              'content' => '4 industry-specific statistics' ],
            [ 'type' => 'testimonials', 'purpose' => 'Social proof from real satisfied clients',              'layout' => '3-column testimonial cards on white background',                                 'content' => '3 quotes with client name, job title, star rating, avatar photo' ],
            [ 'type' => 'faq',          'purpose' => 'Reduce friction by answering common questions',         'layout' => 'two-column: intro text left, accordion right, light gray background',            'content' => '4-5 real Q&A pairs relevant to the business' ],
            [ 'type' => 'cta',          'purpose' => 'Final conversion push to get visitors to act',          'layout' => 'full-width centered, dark gradient background',                                  'content' => 'compelling headline, social proof subtitle, CTA button' ],
        ];
    }

    // ── JSON parsing ─────────────────────────────────────────────────────────

    private function parse_single_section( string $raw ): ?array {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = $this->fix_json_control_chars( trim( $cleaned ) );
        $cleaned = preg_replace( '/,\s*([\}\]])/', '$1', $cleaned );

        $decoded = json_decode( $cleaned, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $decoded = $this->extract_json_object( $cleaned );
        }

        if ( ! is_array( $decoded ) || empty( $decoded['elType'] ) ) {
            return null;
        }
        return $decoded;
    }

    private function parse_elementor_json( string $ai_raw ): array|WP_Error {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $ai_raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = $this->fix_json_control_chars( trim( $cleaned ) );
        $cleaned = preg_replace( '/,\s*([\}\]])/', '$1', $cleaned );

        $decoded = json_decode( $cleaned, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $decoded = $this->extract_json_object( $cleaned );
        }
        if ( null === $decoded ) {
            $decoded = $this->recover_truncated_sections( $cleaned );
        }
        if ( null === $decoded ) {
            return new WP_Error( 'aie_json_parse_error', __( 'Az AI nem adott vissza értelmezhető JSON-t.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
        }

        if ( isset( $decoded['elementor_data'] ) && is_array( $decoded['elementor_data'] ) ) {
            $decoded = $decoded['elementor_data'];
        }
        if ( ! is_array( $decoded ) || empty( $decoded ) ) {
            return new WP_Error( 'aie_json_empty', __( 'Az AI üres oldalt generált.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
        }

        $allowed = [ 'section', 'container' ];
        foreach ( $decoded as $element ) {
            if ( empty( $element['elType'] ) || ! in_array( $element['elType'], $allowed, true ) ) {
                return new WP_Error( 'aie_invalid_structure', __( 'Az AI érvénytelen Elementor struktúrát generált.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
            }
        }

        return $decoded;
    }

    // ── JSON javítók ─────────────────────────────────────────────────────────

    private function fix_json_control_chars( string $json ): string {
        $result = ''; $in_string = false; $escaped = false; $len = strlen( $json );
        for ( $i = 0; $i < $len; $i++ ) {
            $char = $json[ $i ]; $ord = ord( $char );
            if ( $escaped )                    { $result .= $char; $escaped = false; continue; }
            if ( '\\' === $char && $in_string ) { $result .= $char; $escaped = true; continue; }
            if ( '"' === $char )               { $in_string = ! $in_string; $result .= $char; continue; }
            if ( $in_string && $ord < 0x20 ) {
                switch ( $char ) {
                    case "\n": $result .= '\\n'; break;
                    case "\r": $result .= '\\r'; break;
                    case "\t": $result .= '\\t'; break;
                    default:   $result .= sprintf( '\\u%04x', $ord ); break;
                }
                continue;
            }
            $result .= $char;
        }
        return $result;
    }

    private function extract_json_object( string $text ): ?array {
        $start = strpos( $text, '{' );
        if ( false === $start ) return null;
        $depth = 0; $in_string = false; $escaped = false; $len = strlen( $text );
        for ( $i = $start; $i < $len; $i++ ) {
            $char = $text[ $i ];
            if ( $escaped )                    { $escaped = false; continue; }
            if ( '\\' === $char && $in_string ) { $escaped = true; continue; }
            if ( '"' === $char )               { $in_string = ! $in_string; continue; }
            if ( $in_string )                  { continue; }
            if ( '{' === $char )               { $depth++; }
            if ( '}' === $char ) {
                $depth--;
                if ( 0 === $depth ) {
                    $candidate = substr( $text, $start, $i - $start + 1 );
                    $result    = json_decode( $candidate, true );
                    return ( JSON_ERROR_NONE === json_last_error() ) ? $result : null;
                }
            }
        }
        return null;
    }

    private function recover_truncated_sections( string $text ): ?array {
        $array_start = false;
        $ed_pos = strpos( $text, '"elementor_data"' );
        if ( false !== $ed_pos ) { $array_start = strpos( $text, '[', $ed_pos ); }
        if ( false === $array_start ) { $array_start = strpos( $text, '[' ); }
        if ( false === $array_start ) return null;

        $sections = []; $i = $array_start + 1; $len = strlen( $text );
        while ( $i < $len ) {
            while ( $i < $len && '{' !== $text[ $i ] ) { $i++; }
            if ( $i >= $len ) break;
            $sec_start = $i; $depth = 0; $in_str = false; $esc = false;
            for ( ; $i < $len; $i++ ) {
                $c = $text[ $i ];
                if ( $esc )                   { $esc = false; continue; }
                if ( '\\' === $c && $in_str ) { $esc = true; continue; }
                if ( '"' === $c )             { $in_str = ! $in_str; continue; }
                if ( $in_str )               { continue; }
                if ( '{' === $c )            { $depth++; }
                if ( '}' === $c ) {
                    $depth--;
                    if ( 0 === $depth ) {
                        $chunk  = substr( $text, $sec_start, $i - $sec_start + 1 );
                        $parsed = json_decode( $chunk, true );
                        if ( JSON_ERROR_NONE === json_last_error() && ! empty( $parsed['elType'] ) ) {
                            $sections[] = $parsed;
                        }
                        $i++;
                        break;
                    }
                }
                if ( ']' === $c && 0 === $depth ) break 2;
            }
        }
        return empty( $sections ) ? null : $sections;
    }
}
