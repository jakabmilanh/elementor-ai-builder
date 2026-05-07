<?php
/**
 * OpenAI API kliens – cURL alapú, Guzzle nélkül.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class OpenAIClient {

    private const API_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
    private const TIMEOUT_SEC  = 120;

    private string $api_key;
    private string $model;
    private int    $max_tokens;

    public function __construct() {
        $settings         = (array) get_option( AIE_OPTION_KEY, [] );
        $this->api_key    = sanitize_text_field( $settings['openai_api_key'] ?? '' );
        $this->model      = sanitize_text_field( $settings['openai_model']   ?? 'gpt-4o' );
        $this->max_tokens = (int) ( $settings['max_tokens'] ?? 4096 );
    }

    /**
     * Chat Completions hívás.
     *
     * @param  array<int, array{role: string, content: string}> $messages
     * @return string|WP_Error  Az AI nyers szöveges válasza vagy hiba.
     */
    public function chat( array $messages ): string|WP_Error {
        if ( empty( $this->api_key ) ) {
            return new WP_Error(
                'aie_no_api_key',
                __( 'Az OpenAI API kulcs nincs beállítva (AI Elementor Builder → Beállítások).', 'ai-elementor-builder' ),
                [ 'status' => 503 ]
            );
        }

        $body = wp_json_encode( [
            'model'       => $this->model,
            'messages'    => $messages,
            'max_tokens'  => $this->max_tokens,
            'temperature' => 0.3,   // Alacsony hőmérséklet = determinisztikusabb JSON kimenet
            'response_format' => [ 'type' => 'json_object' ],  // GPT-4o JSON mód
        ] );

        // ── cURL hívás ────────────────────────────────────────────────────────
        $ch = curl_init( self::API_ENDPOINT );

        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->api_key,
            ],
            // SSL ellenőrzés – éles környezetben mindig true!
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ] );

        $response  = curl_exec( $ch );
        $http_code = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_err  = curl_error( $ch );
        curl_close( $ch );

        // ── Hibakezelés ───────────────────────────────────────────────────────
        if ( false === $response || $curl_err ) {
            return new WP_Error(
                'aie_curl_error',
                sprintf( __( 'OpenAI kapcsolódási hiba: %s', 'ai-elementor-builder' ), $curl_err ),
                [ 'status' => 502 ]
            );
        }

        $decoded = json_decode( $response, true );

        if ( $http_code !== 200 ) {
            $error_msg = $decoded['error']['message'] ?? 'Ismeretlen OpenAI hiba.';
            return new WP_Error(
                'aie_openai_error',
                sprintf(
                    __( 'OpenAI API hiba (%d): %s', 'ai-elementor-builder' ),
                    $http_code,
                    $error_msg
                ),
                [ 'status' => 502 ]
            );
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if ( null === $content ) {
            return new WP_Error(
                'aie_empty_response',
                __( 'Az OpenAI üres választ adott vissza.', 'ai-elementor-builder' ),
                [ 'status' => 502 ]
            );
        }

        // Ha a response_format json_object módban jött, a content maga egy JSON string,
        // amelynek "elementor_data" kulcsa tartalmazza a tömböt.
        $parsed = json_decode( $content, true );
        if ( isset( $parsed['elementor_data'] ) ) {
            // Visszaadjuk a belső tömb JSON string-ként, hogy a Controller feldolgozhassa
            return wp_json_encode( $parsed['elementor_data'] );
        }

        return $content;
    }
}
