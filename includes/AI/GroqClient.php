<?php
/**
 * Groq API kliens – OpenAI-kompatibilis formátum, cURL alapú.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class GroqClient {

    private const API_ENDPOINT = 'https://api.groq.com/openai/v1/chat/completions';
    private const TIMEOUT_SEC  = 120;

    private string $api_key;
    private string $model;
    private int    $max_tokens;

    public function __construct() {
        $settings         = (array) get_option( AIE_OPTION_KEY, [] );
        $this->api_key    = sanitize_text_field( $settings['groq_api_key'] ?? '' );
        $this->model      = sanitize_text_field( $settings['groq_model']   ?? 'llama-3.3-70b-versatile' );
        $this->max_tokens = (int) ( $settings['max_tokens'] ?? 4096 );
    }

    /**
     * Chat Completions hívás.
     *
     * @param  array<int, array{role: string, content: string}> $messages
     * @param  int    $max_tokens  Ha 0, settings értéket használja.
     * @return string|WP_Error  Az AI nyers szöveges válasza vagy hiba.
     */
    public function chat( array $messages, int $max_tokens = 0 ): string|WP_Error {
        $tokens = $max_tokens > 0 ? $max_tokens : $this->max_tokens;
        if ( empty( $this->api_key ) ) {
            return new WP_Error(
                'aie_no_api_key',
                __( 'A Groq API kulcs nincs beállítva (AI Elementor Builder → Beállítások).', 'ai-elementor-builder' ),
                [ 'status' => 503 ]
            );
        }

        $body = wp_json_encode( [
            'model'           => $this->model,
            'messages'        => $messages,
            'max_tokens'      => $tokens,
            'temperature'     => 0.3,
            'response_format' => [ 'type' => 'json_object' ],
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
                sprintf( __( 'Groq kapcsolódási hiba: %s', 'ai-elementor-builder' ), $curl_err ),
                [ 'status' => 502 ]
            );
        }

        $decoded = json_decode( $response, true );

        if ( 200 !== $http_code ) {
            $error_msg = $decoded['error']['message'] ?? 'Ismeretlen Groq hiba.';
            return new WP_Error(
                'aie_groq_error',
                sprintf(
                    __( 'Groq API hiba (%d): %s', 'ai-elementor-builder' ),
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
                __( 'A Groq üres választ adott vissza.', 'ai-elementor-builder' ),
                [ 'status' => 502 ]
            );
        }

        $parsed = json_decode( $content, true );
        if ( isset( $parsed['elementor_data'] ) ) {
            return wp_json_encode( $parsed['elementor_data'] );
        }

        return $content;
    }
}
