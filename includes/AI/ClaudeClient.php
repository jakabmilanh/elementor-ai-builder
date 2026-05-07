<?php
/**
 * Anthropic Claude API kliens.
 * Kompatibilis a messages API v1-el (claude-haiku / sonnet / opus).
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class ClaudeClient {

    private const API_URL     = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';

    /**
     * Küld egy chat kérést a Claude API-nak.
     *
     * @param  array  $messages  [{role:system|user, content:string}, ...]
     * @param  int    $max_tokens  Ha 0, settings értéket használja.
     * @return string|WP_Error  Az AI nyers szöveges válasza.
     */
    public function chat( array $messages, int $max_tokens = 0 ): string|WP_Error {
        $settings   = (array) get_option( AIE_OPTION_KEY, [] );
        $api_key    = $settings['claude_api_key'] ?? '';
        $model      = $settings['claude_model']   ?? 'claude-haiku-4-5-20251001';
        $max_tokens = $max_tokens > 0 ? $max_tokens : (int) ( $settings['max_tokens'] ?? 8096 );

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'aie_no_api_key',
                __( 'Claude API kulcs hiányzik. Add meg a beállításokban.', 'ai-elementor-builder' ),
                [ 'status' => 400 ]
            );
        }

        // Claude: system üzenet külön param, messages csak user/assistant
        $system          = '';
        $filtered_messages = [];
        foreach ( $messages as $msg ) {
            if ( 'system' === $msg['role'] ) {
                $system = $msg['content'];
            } else {
                $filtered_messages[] = $msg;
            }
        }

        $body = [
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => $filtered_messages,
        ];
        if ( ! empty( $system ) ) {
            $body['system'] = $system;
        }

        $ch = curl_init( self::API_URL );
        curl_setopt_array( $ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $api_key,
                'anthropic-version: ' . self::API_VERSION,
            ],
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_SSL_VERIFYPEER => true,
        ] );

        $response   = curl_exec( $ch );
        $http_code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error = curl_error( $ch );
        curl_close( $ch );

        if ( $curl_error ) {
            return new WP_Error(
                'aie_curl_error',
                sprintf( __( 'cURL hiba: %s', 'ai-elementor-builder' ), $curl_error ),
                [ 'status' => 500 ]
            );
        }

        $data = json_decode( $response, true );

        if ( 200 !== $http_code ) {
            $msg = $data['error']['message'] ?? ( 'HTTP ' . $http_code );
            return new WP_Error(
                'aie_claude_api_error',
                $msg,
                [ 'status' => $http_code ]
            );
        }

        return $data['content'][0]['text'] ?? '';
    }
}
