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

    // ── JSON sanitálás ────────────────────────────────────────────────────────

    /**
     * Literal vezérlőkaraktereket escape-el JSON string literálokon belül.
     * Claude néha nyers newline/tab karaktereket ír string értékekbe \n helyett.
     */
    private function fix_json_control_chars( string $json ): string {
        $result    = '';
        $in_string = false;
        $escaped   = false;
        $len       = strlen( $json );

        for ( $i = 0; $i < $len; $i++ ) {
            $char = $json[ $i ];
            $ord  = ord( $char );

            if ( $escaped ) {
                $result .= $char;
                $escaped = false;
                continue;
            }

            if ( '\\' === $char && $in_string ) {
                $result .= $char;
                $escaped = true;
                continue;
            }

            if ( '"' === $char ) {
                $in_string = ! $in_string;
                $result   .= $char;
                continue;
            }

            // Vezérlőkarakter string belsejében → escape-elés
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

    // ── JSON validálás + javítás ──────────────────────────────────────────────

    private function parse_elementor_json( string $ai_raw ): array|WP_Error {
        // 1. Markdown kódblokk eltávolítása
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $ai_raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        // 2. Vezérlőkarakter-javítás string literálokon belül
        $cleaned = $this->fix_json_control_chars( $cleaned );

        // 3. Trailing comma javítás (},  } vagy ],  ] előtt)
        $cleaned = preg_replace( '/,\s*([\}\]])/', '$1', $cleaned );

        // 4. Direkt parse megkísérlete
        $decoded = json_decode( $cleaned, true );

        // 5. Ha sikertelen → megpróbálunk kinyerni a JSON objektumot
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            $decoded = $this->extract_json_object( $cleaned );
        }

        // 6. Ha még mindig sikertelen → truncation recovery (csonkított válasz)
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

        // 7. {"elementor_data": [...]} wrapper kibontása
        if ( isset( $decoded['elementor_data'] ) && is_array( $decoded['elementor_data'] ) ) {
            $decoded = $decoded['elementor_data'];
        }

        if ( ! is_array( $decoded ) || empty( $decoded ) ) {
            return new WP_Error( 'aie_json_empty', __( 'Az AI üres oldalt generált. Próbálj újra.', 'ai-elementor-builder' ), [ 'status' => 422 ] );
        }

        // 8. Root elemek ellenőrzése
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

    /**
     * Megkeresi az első teljes JSON objektumot a szövegben.
     */
    private function extract_json_object( string $text ): ?array {
        $start = strpos( $text, '{' );
        if ( false === $start ) return null;

        $depth     = 0;
        $in_string = false;
        $escaped   = false;
        $len       = strlen( $text );

        for ( $i = $start; $i < $len; $i++ ) {
            $char = $text[ $i ];
            if ( $escaped )               { $escaped = false; continue; }
            if ( '\\' === $char && $in_string ) { $escaped = true; continue; }
            if ( '"' === $char )          { $in_string = ! $in_string; continue; }
            if ( $in_string )             { continue; }
            if ( '{' === $char )          { $depth++; }
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

    /**
     * Csonkított válaszból kinyeri a befejezett root container elemeket.
     * Ha a JSON félbeszakadt (max_tokens limit), az eddig kész szekciók menthetők.
     */
    private function recover_truncated_sections( string $text ): ?array {
        // Megkeressük az elementor_data tömb kezdetét
        $array_start = false;

        $ed_pos = strpos( $text, '"elementor_data"' );
        if ( false !== $ed_pos ) {
            $array_start = strpos( $text, '[', $ed_pos );
        }
        if ( false === $array_start ) {
            $array_start = strpos( $text, '[' );
        }
        if ( false === $array_start ) return null;

        $sections  = [];
        $i         = $array_start + 1;
        $len       = strlen( $text );

        while ( $i < $len ) {
            // Következő '{' keresése (root section kezdete)
            while ( $i < $len && '{' !== $text[ $i ] ) { $i++; }
            if ( $i >= $len ) break;

            $sec_start = $i;
            $depth     = 0;
            $in_str    = false;
            $esc       = false;

            for ( ; $i < $len; $i++ ) {
                $c = $text[ $i ];
                if ( $esc )                { $esc = false; continue; }
                if ( '\\' === $c && $in_str ) { $esc = true; continue; }
                if ( '"' === $c )          { $in_str = ! $in_str; continue; }
                if ( $in_str )             { continue; }
                if ( '{' === $c )          { $depth++; }
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
