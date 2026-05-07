<?php
/**
 * Google Gemini API kliens – cURL alapú, Guzzle nélkül.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class GeminiClient {

    private const API_BASE    = 'https://generativelanguage.googleapis.com/v1beta/models/';
    private const TIMEOUT_SEC = 120;

    private string $api_key;
    private string $model;
    private int    $max_tokens;

    public function __construct() {
        $settings         = (array) get_option( AIE_OPTION_KEY, [] );
        $this->api_key    = sanitize_text_field( $settings['gemini_api_key'] ?? '' );
        $this->model      = sanitize_text_field( $settings['gemini_model']   ?? 'gemini-2.0-flash' );
        $this->max_tokens = (int) ( $settings['max_tokens'] ?? 8192 );
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
                __( 'A Gemini API kulcs nincs beállítva (AI Elementor Builder → Beállítások).', 'ai-elementor-builder' ),
                [ 'status' => 503 ]
            );
        }

        // Rendszerüzenetet és felhasználói üzeneteket szétválasztjuk
        $system_instruction = null;
        $contents           = [];

        foreach ( $messages as $message ) {
            if ( 'system' === $message['role'] ) {
                $system_instruction = [ 'parts' => [ [ 'text' => $message['content'] ] ] ];
            } else {
                $contents[] = [
                    'role'  => 'user' === $message['role'] ? 'user' : 'model',
                    'parts' => [ [ 'text' => $message['content'] ] ],
                ];
            }
        }

        $request_body = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'      => 0.3,
                'maxOutputTokens'  => $this->max_tokens,
                'responseMimeType' => 'application/json',
            ],
        ];

        if ( $system_instruction ) {
            $request_body['systemInstruction'] = $system_instruction;
        }

        $endpoint = self::API_BASE . rawurlencode( $this->model ) . ':generateContent?key=' . rawurlencode( $this->api_key );
        $body     = wp_json_encode( $request_body );

        // ── cURL hívás ────────────────────────────────────────────────────────
        $ch = curl_init( $endpoint );

        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => self::TIMEOUT_SEC,
            CURLOPT_HTTPHEADER     => [ 'Content-Type: application/json' ],
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
                sprintf( __( 'Gemini kapcsolódási hiba: %s', 'ai-elementor-builder' ), $curl_err ),
                [ 'status' => 502 ]
            );
        }

        $decoded = json_decode( $response, true );

        if ( 200 !== $http_code ) {
            $error_msg = $decoded['error']['message'] ?? 'Ismeretlen Gemini hiba.';
            return new WP_Error(
                'aie_gemini_error',
                sprintf(
                    __( 'Gemini API hiba (%d): %s', 'ai-elementor-builder' ),
                    $http_code,
                    $error_msg
                ),
                [ 'status' => 502 ]
            );
        }

        $content = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ( null === $content ) {
            return new WP_Error(
                'aie_empty_response',
                __( 'A Gemini üres választ adott vissza.', 'ai-elementor-builder' ),
                [ 'status' => 502 ]
            );
        }

        // Ha a válasz "elementor_data" kulcsot tartalmaz, csak a belső tömböt adjuk vissza
        $parsed = json_decode( $content, true );
        if ( isset( $parsed['elementor_data'] ) ) {
            return wp_json_encode( $parsed['elementor_data'] );
        }

        return $content;
    }
}
