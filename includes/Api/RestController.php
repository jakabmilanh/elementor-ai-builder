<?php
/**
 * REST API végpont: /wp-json/ai-builder/v1/generate
 *
 * Egylépéses flow: prompt (+ referencia példák) → AI → teljes Elementor JSON → mentés.
 * Provider: Claude (ajánlott) vagy Groq (tartalék).
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
    private const ROUTE     = '/generate';

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

    private function get_endpoint_args(): array {
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

    // ── Fő kezelő ─────────────────────────────────────────────────────────────

    public function handle_generate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
        $post_id = (int) $request->get_param( 'post_id' );
        $prompt  = (string) $request->get_param( 'prompt' );
        $mode    = (string) $request->get_param( 'mode' );

        // ── 1. Elementor adatok ───────────────────────────────────────────────
        $data_manager  = new DataManager();
        $current_json  = $data_manager->get_elementor_data( $post_id );
        $global_styles = $data_manager->get_global_styles();

        // ── 2. Mód ───────────────────────────────────────────────────────────
        if ( 'auto' === $mode ) {
            $mode = ( ! empty( $current_json ) && '[]' !== $current_json ) ? 'modify' : 'create';
        }

        // ── 3. Prompt összeállítása ───────────────────────────────────────────
        $prompt_builder = new PromptBuilder();
        $messages       = $prompt_builder->build( $mode, $prompt, $current_json, $global_styles );

        // ── 4. AI hívás ───────────────────────────────────────────────────────
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $provider = $settings['ai_provider'] ?? 'claude';

        $ai_raw = ( 'groq' === $provider )
            ? ( new GroqClient() )->chat( $messages )
            : ( new ClaudeClient() )->chat( $messages );

        if ( is_wp_error( $ai_raw ) ) {
            return $ai_raw;
        }

        // ── 5. JSON validálás ─────────────────────────────────────────────────
        $new_data = $this->parse_elementor_json( $ai_raw );
        if ( is_wp_error( $new_data ) ) {
            return $new_data;
        }

        // ── 6. Mentés ─────────────────────────────────────────────────────────
        $save_result = $data_manager->save_elementor_data( $post_id, $new_data );
        if ( is_wp_error( $save_result ) ) {
            return $save_result;
        }

        $data_manager->clear_elementor_cache( $post_id );

        return new WP_REST_Response( [
            'success'        => true,
            'mode'           => $mode,
            'provider'       => $provider,
            'message'        => __( 'Az oldal sikeresen generálva/módosítva.', 'ai-elementor-builder' ),
            'elementor_data' => $new_data,
        ], 200 );
    }

    // ── JSON validálás ─────────────────────────────────────────────────────────

    private function parse_elementor_json( string $ai_raw ): array|WP_Error {
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $ai_raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $decoded = json_decode( $cleaned, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'aie_json_parse_error',
                sprintf( __( 'Az AI által visszaadott JSON nem érvényes: %s', 'ai-elementor-builder' ), json_last_error_msg() ),
                [ 'status' => 422, 'raw_response' => substr( $ai_raw, 0, 500 ) ]
            );
        }

        // {"elementor_data": [...]} wrapper kibontása
        if ( isset( $decoded['elementor_data'] ) && is_array( $decoded['elementor_data'] ) ) {
            $decoded = $decoded['elementor_data'];
        }

        if ( ! is_array( $decoded ) ) {
            return new WP_Error( 'aie_json_not_array', __( 'Az AI válasza nem Elementor JSON tömb.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
        }

        foreach ( $decoded as $element ) {
            if ( empty( $element['elType'] ) ) {
                return new WP_Error( 'aie_json_missing_eltype', __( 'Hiányzó elType mező.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
            }
            if ( ! in_array( $element['elType'], [ 'section', 'container' ], true ) ) {
                return new WP_Error(
                    'aie_invalid_root_eltype',
                    sprintf( __( 'Érvénytelen root elType: "%s"', 'ai-elementor-builder' ), $element['elType'] ),
                    [ 'status' => 422 ]
                );
            }
        }

        return $decoded;
    }
}
