<?php
/**
 * REST API végpont: /wp-json/ai-builder/v1/generate
 *
 * @package AIE\Api
 */

namespace AIE\Api;

defined( 'ABSPATH' ) || exit;

use AIE\AI\OpenAIClient;
use AIE\AI\PromptBuilder;
use AIE\Elementor\DataManager;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

class RestController {

    private const NAMESPACE = 'ai-builder/v1';
    private const ROUTE     = '/generate';

    // ── Regisztrálás ──────────────────────────────────────────────────────────

    public function register_routes(): void {
        register_rest_route( self::NAMESPACE, self::ROUTE, [
            [
                'methods'             => 'POST',
                'callback'            => [ $this, 'handle_generate' ],
                'permission_callback' => [ $this, 'check_permissions' ],
                'args'                => $this->get_endpoint_args(),
            ],
        ] );
    }

    // ── Jogosultság-ellenőrzés ────────────────────────────────────────────────

    public function check_permissions( WP_REST_Request $request ): bool|WP_Error {
        // 1. Bejelentkezett felhasználó
        if ( ! is_user_logged_in() ) {
            return new WP_Error(
                'aie_unauthorized',
                __( 'Ehhez be kell jelentkezned.', 'ai-elementor-builder' ),
                [ 'status' => 401 ]
            );
        }

        // 2. Képesség-ellenőrzés (Elementor szerkesztési jog)
        if ( ! current_user_can( 'edit_posts' ) ) {
            return new WP_Error(
                'aie_forbidden',
                __( 'Nincs jogosultságod ehhez a művelethez.', 'ai-elementor-builder' ),
                [ 'status' => 403 ]
            );
        }

        // 3. Az adott oldal szerkesztési joga
        $post_id = (int) $request->get_param( 'post_id' );
        if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
            return new WP_Error(
                'aie_forbidden_post',
                __( 'Nincs jogod szerkeszteni ezt az oldalt.', 'ai-elementor-builder' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    // ── Végpont argumentumok (validálás + sanitálás) ──────────────────────────

    private function get_endpoint_args(): array {
        return [
            'post_id' => [
                'required'          => true,
                'type'              => 'integer',
                'minimum'           => 1,
                'sanitize_callback' => 'absint',
                'description'       => 'A szerkesztendő oldal/bejegyzés ID-ja.',
            ],
            'prompt' => [
                'required'          => true,
                'type'              => 'string',
                'minLength'         => 5,
                'maxLength'         => 2000,
                'sanitize_callback' => 'sanitize_textarea_field',
                'description'       => 'A felhasználó természetes nyelvű utasítása.',
            ],
            'mode' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => 'auto',
                'enum'              => [ 'auto', 'create', 'modify' ],
                'sanitize_callback' => 'sanitize_key',
                'description'       => 'auto=automatikus, create=új generálás, modify=iteratív módosítás.',
            ],
        ];
    }

    // ── Fő kezelő ─────────────────────────────────────────────────────────────

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param( 'post_id' );
        $prompt  = (string) $request->get_param( 'prompt' );
        $mode    = (string) $request->get_param( 'mode' );

        // ── 1. Aktuális Elementor JSON lekérése ───────────────────────────────
        $data_manager    = new DataManager();
        $current_json    = $data_manager->get_elementor_data( $post_id );   // '' ha üres
        $global_styles   = $data_manager->get_global_styles();

        // ── 2. Mód meghatározása ──────────────────────────────────────────────
        $effective_mode = $mode;
        if ( 'auto' === $mode ) {
            $effective_mode = ( ! empty( $current_json ) && '[]' !== $current_json )
                ? 'modify'
                : 'create';
        }

        // ── 3. Prompt összeállítása ───────────────────────────────────────────
        $prompt_builder = new PromptBuilder();
        $messages       = $prompt_builder->build(
            mode:           $effective_mode,
            user_prompt:    $prompt,
            current_json:   $current_json,
            global_styles:  $global_styles,
        );

        // ── 4. OpenAI hívás ───────────────────────────────────────────────────
        $openai  = new OpenAIClient();
        $ai_result = $openai->chat( $messages );

        if ( is_wp_error( $ai_result ) ) {
            return $ai_result;
        }

        // ── 5. JSON validálás ─────────────────────────────────────────────────
        $new_data = $this->parse_and_validate_elementor_json( $ai_result );
        if ( is_wp_error( $new_data ) ) {
            return $new_data;
        }

        // ── 6. Mentés ─────────────────────────────────────────────────────────
        $save_result = $data_manager->save_elementor_data( $post_id, $new_data );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }

        // ── 7. Elementor cache törlése ────────────────────────────────────────
        $data_manager->clear_elementor_cache( $post_id );

        return new WP_REST_Response( [
            'success'        => true,
            'mode'           => $effective_mode,
            'message'        => __( 'Az oldal sikeresen generálva/módosítva.', 'ai-elementor-builder' ),
            'elementor_data' => $new_data,   // opcionálisan visszaküldjük az editornak
        ], 200 );
    }

    // ── Segédmetódusok ────────────────────────────────────────────────────────

    /**
     * Kinyeri és validálja az AI válaszából az Elementor JSON-t.
     *
     * @param string $ai_raw Az LLM nyers szövege.
     * @return array|WP_Error
     */
    private function parse_and_validate_elementor_json( string $ai_raw ): array|WP_Error {
        // Markdown kódblokkot eltávolítjuk, ha az AI mégis becsomagolta
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $ai_raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $decoded = json_decode( $cleaned, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'aie_json_parse_error',
                sprintf(
                    __( 'Az AI által visszaadott JSON nem érvényes: %s', 'ai-elementor-builder' ),
                    json_last_error_msg()
                ),
                [ 'status' => 422, 'raw_response' => substr( $ai_raw, 0, 500 ) ]
            );
        }

        if ( ! is_array( $decoded ) ) {
            return new WP_Error(
                'aie_json_not_array',
                __( 'Az AI válasza nem Elementor JSON tömb.', 'ai-elementor-builder' ),
                [ 'status' => 422 ]
            );
        }

        // Minimális struktúra-ellenőrzés: minden elemnek section típusúnak kell lennie
        foreach ( $decoded as $section ) {
            if ( empty( $section['elType'] ) ) {
                return new WP_Error(
                    'aie_json_missing_eltype',
                    __( 'A JSON egyik eleme hiányos (nincs elType mező).', 'ai-elementor-builder' ),
                    [ 'status' => 422 ]
                );
            }
        }

        return $decoded;
    }
}
