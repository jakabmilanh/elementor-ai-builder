<?php
/**
 * Anthropic Claude API kliens.
 * Támogatja a szöveges és vision (kép+szöveg) üzeneteket.
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
     * Chat hívás – messages tartalmazhat sima stringet VAGY vision array-t.
     *
     * Vision format (user message content array):
     * [
     *   ['type'=>'image', 'source'=>['type'=>'base64','media_type'=>'image/jpeg','data'=>'...']],
     *   ['type'=>'text', 'text'=>'User message text'],
     * ]
     *
     * @param  array  $messages  [{role:system|user|assistant, content:string|array}, ...]
     * @param  int    $max_tokens  Ha 0, settings értéket használja.
     * @return string|WP_Error
     */
    public function chat( array $messages, int $max_tokens = 0 ): string|WP_Error {
        $settings   = (array) get_option( AIE_OPTION_KEY, [] );
        $api_key    = $settings['claude_api_key'] ?? '';
        $model      = $settings['claude_model']   ?? 'claude-haiku-4-5-20251001';

        $model_limits = [
            'claude-haiku-4-5-20251001' => 8192,
            'claude-sonnet-4-6'         => 16000,
            'claude-opus-4-7'           => 16000,
        ];
        $model_max  = $model_limits[ $model ] ?? 8192;
        $configured = (int) ( $settings['max_tokens'] ?? 8096 );
        $max_tokens = $max_tokens > 0 ? min( $max_tokens, $model_max ) : min( $configured, $model_max );

        if ( empty( $api_key ) ) {
            return new WP_Error(
                'aie_no_api_key',
                __( 'Claude API kulcs hiányzik. Add meg a beállításokban.', 'ai-elementor-builder' ),
                [ 'status' => 400 ]
            );
        }

        // System üzenet különválasztása
        $system            = '';
        $filtered_messages = [];
        foreach ( $messages as $msg ) {
            if ( 'system' === $msg['role'] ) {
                $system = is_string( $msg['content'] ) ? $msg['content'] : '';
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
            return new WP_Error( 'aie_claude_api_error', $msg, [ 'status' => $http_code ] );
        }

        return $data['content'][0]['text'] ?? '';
    }

    /**
     * Képek (design referenciák) base64 kódolása Claude vision API-hoz.
     * Átméretezi ha szükséges (Claude optimális: max 1568px a hosszabb oldalon).
     *
     * @param  string[] $image_urls  Kép URL-ek tömbje
     * @return array    Vision content blokkok tömbje
     */
    public function prepare_vision_images( array $image_urls ): array {
        $vision_blocks = [];

        foreach ( array_slice( $image_urls, 0, 3 ) as $url ) {
            $base64 = $this->fetch_image_as_base64( $url );
            if ( null === $base64 ) {
                continue;
            }
            $vision_blocks[] = [
                'type'   => 'image',
                'source' => [
                    'type'       => 'base64',
                    'media_type' => 'image/jpeg',
                    'data'       => $base64,
                ],
            ];
        }

        return $vision_blocks;
    }

    /**
     * Kép letöltése, átméretezése ha nagy, majd base64 kódolása.
     */
    private function fetch_image_as_base64( string $url ): ?string {
        // Letöltés
        $response = wp_remote_get( $url, [
            'timeout'   => 20,
            'sslverify' => true,
        ] );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $body = wp_remote_retrieve_body( $response );
        if ( empty( $body ) ) {
            return null;
        }

        // Ha GD elérhető, átméretezés
        if ( function_exists( 'imagecreatefromstring' ) ) {
            $img = @imagecreatefromstring( $body );
            if ( $img ) {
                $w = imagesx( $img );
                $h = imagesy( $img );
                $max = 1200;

                if ( $w > $max || $h > $max ) {
                    $ratio  = min( $max / $w, $max / $h );
                    $new_w  = (int) round( $w * $ratio );
                    $new_h  = (int) round( $h * $ratio );
                    $resized = imagecreatetruecolor( $new_w, $new_h );
                    imagecopyresampled( $resized, $img, 0, 0, 0, 0, $new_w, $new_h, $w, $h );
                    imagedestroy( $img );

                    ob_start();
                    imagejpeg( $resized, null, 82 );
                    $body = ob_get_clean();
                    imagedestroy( $resized );
                } else {
                    // Konvertálás JPEG-be (ha PNG stb.)
                    ob_start();
                    imagejpeg( $img, null, 85 );
                    $body = ob_get_clean();
                    imagedestroy( $img );
                }
            }
        }

        return base64_encode( $body );
    }
}
