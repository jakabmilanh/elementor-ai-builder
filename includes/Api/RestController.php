<?php
/**
 * REST API végpontok + aszinkron háttérfeladat-feldolgozó.
 *
 * Async flow:
 *   1. POST /generate-async → job_id (azonnali válasz, háttér indul)
 *   2. GET  /job-status/{id} → {status, message} (polling)
 *   3. wp_ajax aie_bg_generate → tényleges generálás
 *
 * Sync flow (kompatibilitás):
 *   POST /generate → blokkoló válasz (használható ha nincs 504 probléma)
 *
 * @package AIE\Api
 */

namespace AIE\Api;

defined( 'ABSPATH' ) || exit;

use AIE\AI\ClaudeClient;
use AIE\AI\GroqClient;
use AIE\AI\PromptBuilder;
use AIE\Elementor\DataManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class RestController {

    private const NAMESPACE = 'ai-builder/v1';

    public function register_routes(): void {
        // Szinkron (backward compat)
        register_rest_route( self::NAMESPACE, '/generate', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_generate' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => $this->get_generate_args(),
            ],
        ] );

        // Aszinkron indítás – azonnal válaszol job_id-vel
        register_rest_route( self::NAMESPACE, '/generate-async', [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_start_async' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => $this->get_generate_args(),
            ],
        ] );

        // Feladat státusz lekérdezése (polling)
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

    // ── Végpont argumentumok ──────────────────────────────────────────────────

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
        ];
    }

    // ── Szinkron kezelő (backward compat) ─────────────────────────────────────

    public function handle_generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param( 'post_id' );
        $prompt  = (string) $request->get_param( 'prompt' );
        $mode    = (string) $request->get_param( 'mode' );

        $data_manager  = new DataManager();
        $current_json  = $data_manager->get_elementor_data( $post_id );
        $global_styles = $data_manager->get_global_styles();

        if ( 'auto' === $mode ) {
            $mode = ( ! empty( $current_json ) && '[]' !== $current_json ) ? 'modify' : 'create';
        }

        $new_data = ( 'modify' === $mode )
            ? $this->run_modify( $prompt, $current_json, $global_styles )
            : $this->run_create( $prompt, $global_styles );

        if ( is_wp_error( $new_data ) ) {
            return $new_data;
        }

        $save_result = $data_manager->save_elementor_data( $post_id, $new_data );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }
        $data_manager->clear_elementor_cache( $post_id );

        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $provider = $settings['ai_provider'] ?? 'claude';

        return new WP_REST_Response( [
            'success'        => true,
            'mode'           => $mode,
            'provider'       => $provider,
            'message'        => __( 'Az oldal sikeresen generálva/módosítva.', 'ai-elementor-builder' ),
            'elementor_data' => $new_data,
        ], 200 );
    }

    // ── Aszinkron indítás ─────────────────────────────────────────────────────

    public function handle_start_async( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param( 'post_id' );
        $prompt  = (string) $request->get_param( 'prompt' );
        $mode    = (string) $request->get_param( 'mode' );

        $job_id   = substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 24 );
        $bg_token = wp_generate_password( 32, false, false );

        set_transient( 'aie_job_' . $job_id, [
            'status'   => 'pending',
            'user_id'  => get_current_user_id(),
            'bg_token' => wp_hash( $bg_token ),
            'post_id'  => $post_id,
            'prompt'   => $prompt,
            'mode'     => $mode,
            'created'  => time(),
            'message'  => __( 'Feladat elindítva...', 'ai-elementor-builder' ),
        ], 2 * HOUR_IN_SECONDS );

        // Nem-blokkoló loopback kérés – a szerver feldolgozza a háttérben
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

        return new WP_REST_Response( [
            'job_id'  => $job_id,
            'status'  => 'pending',
            'message' => __( 'Generálás elindítva.', 'ai-elementor-builder' ),
        ], 202 );
    }

    // ── Feladat státusz (polling endpoint) ────────────────────────────────────

    public function handle_job_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $job_id = sanitize_key( $request->get_param( 'job_id' ) );
        $job    = get_transient( 'aie_job_' . $job_id );

        if ( false === $job ) {
            return new WP_Error( 'aie_job_not_found', __( 'Feladat nem található.', 'ai-elementor-builder' ), [ 'status' => 404 ] );
        }

        if ( (int) $job['user_id'] !== get_current_user_id() ) {
            return new WP_Error( 'aie_forbidden', __( 'Nem jogosult ehhez a feladathoz.', 'ai-elementor-builder' ), [ 'status' => 403 ] );
        }

        // Ha 5 percnél régebben pending – a loopback valószínűleg nem indult el
        $age = time() - ( $job['created'] ?? 0 );
        if ( 'pending' === $job['status'] && $age > 300 ) {
            delete_transient( 'aie_job_' . $job_id );
            return new WP_REST_Response( [
                'status'  => 'error',
                'message' => __( 'A generálás nem indult el (loopback probléma). Próbáld újra.', 'ai-elementor-builder' ),
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

    // ── Háttérfeladat-feldolgozó (wp_ajax) ───────────────────────────────────

    public function process_bg_job(): void {
        $job_id   = sanitize_key( $_POST['job_id'] ?? '' );
        $bg_token = sanitize_text_field( $_POST['bg_token'] ?? '' );

        if ( empty( $job_id ) || empty( $bg_token ) ) {
            wp_die();
        }

        $job = get_transient( 'aie_job_' . $job_id );
        if ( false === $job ) {
            wp_die();
        }

        if ( ! hash_equals( $job['bg_token'], wp_hash( $bg_token ) ) ) {
            wp_die();
        }

        // Feldolgozás állapot
        $job['status']  = 'processing';
        $job['message'] = __( 'AI tervezi az oldalt...', 'ai-elementor-builder' );
        set_transient( 'aie_job_' . $job_id, $job, 2 * HOUR_IN_SECONDS );

        @ignore_user_abort( true );
        @set_time_limit( 300 );

        $post_id = (int) $job['post_id'];
        $prompt  = $job['prompt'];
        $mode    = $job['mode'];

        $data_manager  = new DataManager();
        $current_json  = $data_manager->get_elementor_data( $post_id );
        $global_styles = $data_manager->get_global_styles();

        if ( 'auto' === $mode ) {
            $mode = ( ! empty( $current_json ) && '[]' !== $current_json ) ? 'modify' : 'create';
        }

        $new_data = ( 'modify' === $mode )
            ? $this->run_modify( $prompt, $current_json, $global_styles )
            : $this->run_create( $prompt, $global_styles, $job_id );

        if ( is_wp_error( $new_data ) ) {
            $job['status']  = 'error';
            $job['message'] = $new_data->get_error_message();
            set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );
            wp_die();
        }

        $save_result = $data_manager->save_elementor_data( $post_id, $new_data );
        if ( is_wp_error( $save_result ) ) {
            $job['status']  = 'error';
            $job['message'] = $save_result->get_error_message();
            set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );
            wp_die();
        }

        $data_manager->clear_elementor_cache( $post_id );

        $job['status']  = 'done';
        $job['message'] = __( 'Az oldal sikeresen generálva!', 'ai-elementor-builder' );
        set_transient( 'aie_job_' . $job_id, $job, HOUR_IN_SECONDS );

        wp_die();
    }

    // ── Create: terv + szekciónkénti generálás ───────────────────────────────

    private function run_create( string $prompt, array $global_styles, string $job_id = '' ): array|WP_Error {
        $has_pro        = defined( 'ELEMENTOR_PRO_VERSION' );
        $prompt_builder = new PromptBuilder();

        // 1. lépés: szabad formátumú oldal-terv
        $this->update_job_message( $job_id, __( 'AI tervezi az oldal struktúráját...', 'ai-elementor-builder' ) );
        $plan_messages = $prompt_builder->build_plan( $prompt );
        $plan_raw      = $this->ai_call( $plan_messages, 512 );

        if ( is_wp_error( $plan_raw ) ) {
            $section_plan = $this->get_default_plan();
        } else {
            $section_plan = $this->parse_plan( $plan_raw );
            if ( empty( $section_plan ) ) {
                $section_plan = $this->get_default_plan();
            }
        }

        $total    = count( $section_plan );
        $sections = [];

        // 2. lépés: szekciók szabad generálása
        foreach ( $section_plan as $idx => $section_meta ) {
            $section_label = $section_meta['type'] ?? ( 'section_' . ( $idx + 1 ) );
            $this->update_job_message(
                $job_id,
                sprintf(
                    __( 'Szekció generálása: %d/%d — %s', 'ai-elementor-builder' ),
                    $idx + 1,
                    $total,
                    $section_label
                )
            );

            $messages = $prompt_builder->build_section( $section_meta, $prompt, $global_styles, $has_pro );
            $raw      = $this->ai_call( $messages, 0 );
            if ( is_wp_error( $raw ) ) {
                continue;
            }
            $section = $this->parse_single_section( $raw );
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

    // ── AI hívás ─────────────────────────────────────────────────────────────

    private function ai_call( array $messages, int $max_tokens = 0 ): string|WP_Error {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $provider = $settings['ai_provider'] ?? 'claude';
        return ( 'groq' === $provider )
            ? ( new GroqClient() )->chat( $messages, $max_tokens )
            : ( new ClaudeClient() )->chat( $messages, $max_tokens );
    }

    // ── Háttérfeladat progress frissítés ─────────────────────────────────────

    private function update_job_message( string $job_id, string $message ): void {
        if ( empty( $job_id ) ) return;
        $job = get_transient( 'aie_job_' . $job_id );
        if ( false !== $job ) {
            $job['message'] = $message;
            set_transient( 'aie_job_' . $job_id, $job, 2 * HOUR_IN_SECONDS );
        }
    }

    // ── Plan parsing ─────────────────────────────────────────────────────────

    private function parse_plan( string $raw ): array {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $start = strpos( $cleaned, '[' );
        $end   = strrpos( $cleaned, ']' );
        if ( false === $start || false === $end || $end <= $start ) {
            return [];
        }

        $json    = substr( $cleaned, $start, $end - $start + 1 );
        $decoded = json_decode( $json, true );

        if ( ! is_array( $decoded ) ) {
            return [];
        }

        $valid = [];
        foreach ( $decoded as $item ) {
            if ( ! is_array( $item ) || empty( $item['type'] ) ) {
                continue;
            }
            $valid[] = [
                'type'    => sanitize_key( $item['type'] ),
                'purpose' => sanitize_text_field( $item['purpose'] ?? '' ),
                'layout'  => sanitize_text_field( $item['layout'] ?? '' ),
                'content' => sanitize_text_field( $item['content'] ?? '' ),
            ];
        }

        if ( count( $valid ) < 3 ) {
            return [];
        }
        if ( count( $valid ) > 9 ) {
            $valid = array_slice( $valid, 0, 9 );
        }

        return $valid;
    }

    private function get_default_plan(): array {
        return [
            [ 'type' => 'hero',         'purpose' => 'Above-the-fold hero with headline and primary CTA',   'layout' => 'full-height split: text left 55%, image right 45%, dark gradient background',  'content' => 'h1 headline, subtitle, CTA button, 3 key stats' ],
            [ 'type' => 'services',     'purpose' => 'Showcase main services or features',                  'layout' => '3-column white cards with icons on light gray background',                     'content' => '3 service cards: icon, title, 2-sentence description' ],
            [ 'type' => 'about',        'purpose' => 'Build trust and explain why to choose this business', 'layout' => 'two-column: benefit list left, photo right, white background',                  'content' => 'section label, h2, 4 checkmark benefits, CTA button, photo' ],
            [ 'type' => 'stats',        'purpose' => 'Impress with numbers and social proof metrics',       'layout' => 'full-width accent gradient background, 4 counter widgets in a row',             'content' => '4 industry-specific statistics with numbers and labels' ],
            [ 'type' => 'testimonials', 'purpose' => 'Social proof from real satisfied clients',            'layout' => '3-column testimonial cards on white background',                                'content' => '3 quotes with client name, job title, star rating, avatar photo' ],
            [ 'type' => 'faq',          'purpose' => 'Reduce friction by answering common questions',       'layout' => 'two-column: intro text left, accordion right, light gray background',           'content' => '4-5 real Q&A pairs relevant to the business' ],
            [ 'type' => 'cta',          'purpose' => 'Final conversion push to get visitors to act',        'layout' => 'full-width centered, dark gradient background, large text',                     'content' => 'compelling headline, social proof subtitle, prominent CTA button' ],
        ];
    }

    // ── Single section parsing ────────────────────────────────────────────────

    private function parse_single_section( string $raw ): ?array {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $cleaned = $this->fix_json_control_chars( $cleaned );
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

    // ── Full-page JSON parsing (modify mode) ─────────────────────────────────

    private function parse_elementor_json( string $ai_raw ): array|WP_Error {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $ai_raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $cleaned = $this->fix_json_control_chars( $cleaned );
        $cleaned = preg_replace( '/,\s*([\}\]])/', '$1', $cleaned );

        $decoded = json_decode( $cleaned, true );
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $decoded = $this->extract_json_object( $cleaned );
        }

        if ( null === $decoded ) {
            $sections = $this->recover_truncated_sections( $cleaned );
            if ( ! empty( $sections ) ) {
                $decoded = $sections;
            }
        }

        if ( null === $decoded ) {
            return new WP_Error(
                'aie_json_parse_error',
                __( 'Az AI nem adott vissza értelmezhető JSON-t. Próbálj újra vagy használj rövidebb promptot.', 'ai-elementor-builder' ),
                [ 'status' => 422 ]
            );
        }

        if ( isset( $decoded['elementor_data'] ) && is_array( $decoded['elementor_data'] ) ) {
            $decoded = $decoded['elementor_data'];
        }

        if ( ! is_array( $decoded ) || empty( $decoded ) ) {
            return new WP_Error( 'aie_json_empty', __( 'Az AI üres oldalt generált. Próbálj újra.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
        }

        $allowed = [ 'section', 'container' ];
        foreach ( $decoded as $element ) {
            if ( empty( $element['elType'] ) || ! in_array( $element['elType'], $allowed, true ) ) {
                return new WP_Error(
                    'aie_invalid_structure',
                    __( 'Az AI érvénytelen Elementor struktúrát generált. Próbálj újra.', 'ai-elementor-builder' ),
                    [ 'status' => 422 ]
                );
            }
        }

        return $decoded;
    }

    // ── JSON javítók ─────────────────────────────────────────────────────────

    private function fix_json_control_chars( string $json ): string {
        $result    = '';
        $in_string = false;
        $escaped   = false;
        $len       = strlen( $json );

        for ( $i = 0; $i < $len; $i++ ) {
            $char = $json[ $i ];
            $ord  = ord( $char );

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

        $depth = 0; $in_string = false; $escaped = false;
        $len = strlen( $text );

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
        if ( false !== $ed_pos ) {
            $array_start = strpos( $text, '[', $ed_pos );
        }
        if ( false === $array_start ) {
            $array_start = strpos( $text, '[' );
        }
        if ( false === $array_start ) return null;

        $sections = [];
        $i        = $array_start + 1;
        $len      = strlen( $text );

        while ( $i < $len ) {
            while ( $i < $len && '{' !== $text[ $i ] ) { $i++; }
            if ( $i >= $len ) break;

            $sec_start = $i;
            $depth = 0; $in_str = false; $esc = false;

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
