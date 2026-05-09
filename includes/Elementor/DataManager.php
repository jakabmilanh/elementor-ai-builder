<?php
/**
 * Elementor adatok olvasása és írása.
 * + Kit color scheme management
 * + Media library image import
 *
 * @package AIE\Elementor
 */

namespace AIE\Elementor;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class DataManager {

    private const META_KEY = '_elementor_data';

    // ── Olvasás ───────────────────────────────────────────────────────────────

    public function get_elementor_data( int $post_id ): string {
        $raw = get_post_meta( $post_id, self::META_KEY, true );
        return is_string( $raw ) ? $raw : '';
    }

    /**
     * Globális stílusok az aktív Elementor Kit-ből.
     */
    public function get_global_styles(): array {
        $kit_id = $this->get_active_kit_id();
        if ( ! $kit_id ) {
            return [ 'colors' => [], 'typography' => [] ];
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
            $kit_settings = [];
        }

        return [
            'colors'     => $this->extract_colors( $kit_settings ),
            'typography' => $this->extract_typography( $kit_settings ),
        ];
    }

    // ── Írás ──────────────────────────────────────────────────────────────────

    public function save_elementor_data( int $post_id, array $data ): true|WP_Error {
        $json   = wp_slash( wp_json_encode( $data ) );
        $result = update_post_meta( $post_id, self::META_KEY, $json );

        if ( false === $result ) {
            return new WP_Error(
                'aie_save_failed',
                __( 'Nem sikerült menteni az Elementor adatot.', 'ai-elementor-builder' ),
                [ 'status' => 500 ]
            );
        }

        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
        return true;
    }

    /**
     * Kit custom colors lecserélése az AI által generált palettával.
     *
     * @param array $palette ['primary'=>'#hex', 'accent'=>'#hex', 'dark'=>'#hex', 'light'=>'#hex', 'muted'=>'#hex', ...]
     */
    public function set_kit_colors( array $palette ): bool {
        $kit_id = $this->get_active_kit_id();
        if ( ! $kit_id ) {
            return false;
        }

        $kit_settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
        if ( ! is_array( $kit_settings ) ) {
            $kit_settings = [];
        }

        // Eredeti system colors megőrzése, csak a custom colors-t cseréljük
        $label_map = [
            'primary'            => 'Primary',
            'accent'             => 'Accent',
            'dark'               => 'Dark',
            'dark_gradient_end'  => 'Dark 2',
            'light'              => 'Light BG',
            'muted'              => 'Muted Text',
        ];

        $custom_colors = [];
        foreach ( $label_map as $key => $label ) {
            if ( ! empty( $palette[ $key ] ) ) {
                $custom_colors[] = [
                    '_id'   => 'aie_' . $key,
                    'title' => $label,
                    'color' => sanitize_hex_color( $palette[ $key ] ) ?? $palette[ $key ],
                ];
            }
        }

        if ( empty( $custom_colors ) ) {
            return false;
        }

        $kit_settings['custom_colors'] = $custom_colors;

        $result = update_post_meta( $kit_id, '_elementor_page_settings', $kit_settings );

        // Elementor Kit cache törlése
        if (
            class_exists( '\Elementor\Plugin' ) &&
            isset( \Elementor\Plugin::$instance->kits_manager )
        ) {
            try {
                $kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit_for_frontend();
                if ( $kit ) {
                    $kit->delete_cache();
                }
            } catch ( \Exception $e ) {
                // Silence – cache törlés sikertelen, nem kritikus
            }
        }

        return $result !== false;
    }

    // ── Média könyvtár kép import ─────────────────────────────────────────────

    /**
     * Kicseréli az összes loremflickr.com URL-t WordPress média könyvtárbeli képekre.
     * Az $sections tömböt referencia szerint módosítja.
     */
    public function import_page_images( array &$sections, int $post_id ): void {
        $json_str = wp_json_encode( $sections );
        if ( ! $json_str ) {
            return;
        }

        // Összes loremflickr URL megkeresése
        preg_match_all( '#https://loremflickr\.com/[^\s"\'\\\\]+#', $json_str, $matches );
        $urls = array_unique( $matches[0] );

        if ( empty( $urls ) ) {
            return;
        }

        // Max 5 képet töltünk le, hogy ne lógjon be a folyamat
        $urls    = array_slice( $urls, 0, 5 );
        $url_map = [];

        foreach ( $urls as $url ) {
            $clean_url = strtok( $url, '?' );
            if ( isset( $url_map[ $clean_url ] ) ) {
                $url_map[ $url ] = $url_map[ $clean_url ];
                continue;
            }

            $attachment_id = $this->sideload_image( $url, $post_id );
            if ( $attachment_id ) {
                $local_url       = wp_get_attachment_url( $attachment_id );
                $url_map[ $url ] = $local_url;
            }
        }

        if ( empty( $url_map ) ) {
            return;
        }

        // Csere a JSON-ban
        $updated = str_replace(
            array_map( 'addcslashes', array_keys( $url_map ), array_fill( 0, count( $url_map ), '/' ) ),
            array_values( $url_map ),
            $json_str
        );

        // Egyszerűbb csere: direkt str_replace
        $updated  = str_replace( array_keys( $url_map ), array_values( $url_map ), $json_str );
        $decoded  = json_decode( $updated, true );
        if ( is_array( $decoded ) ) {
            $sections = $decoded;
        }
    }

    /**
     * Egy külső URL-ről letölt egy képet és WP média könyvtárba menti.
     * Visszaadja az attachment ID-t vagy null-t.
     */
    public function sideload_image( string $url, int $post_id = 0 ): ?int {
        // Szükséges WP admin include-ok
        if ( ! function_exists( 'media_handle_sideload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        // Letöltés ideiglenes fájlba
        $tmp = download_url( $url, 12 );
        if ( is_wp_error( $tmp ) ) {
            return null;
        }

        // Fájlnév generálása a URL alapján
        $url_parts = parse_url( $url );
        $path      = $url_parts['path'] ?? '';
        $filename  = 'aie-' . substr( md5( $url ), 0, 8 ) . '.jpg';

        $file_array = [
            'name'     => $filename,
            'tmp_name' => $tmp,
            'error'    => 0,
            'size'     => filesize( $tmp ),
        ];

        $id = media_handle_sideload( $file_array, $post_id, '' );

        if ( is_wp_error( $id ) ) {
            @unlink( $tmp );
            return null;
        }

        return $id;
    }

    /**
     * Attachment URL lekérése ID-ból (referencia képekhez).
     */
    public function get_attachment_url( int $attachment_id ): string {
        return wp_get_attachment_url( $attachment_id ) ?: '';
    }

    // ── Cache törlés ──────────────────────────────────────────────────────────

    public function clear_elementor_cache( int $post_id ): void {
        if (
            defined( 'ELEMENTOR_VERSION' ) &&
            class_exists( '\Elementor\Plugin' ) &&
            isset( \Elementor\Plugin::$instance->files_manager )
        ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
        clean_post_cache( $post_id );
    }

    // ── Privát segédek ────────────────────────────────────────────────────────

    public function get_active_kit_id(): int {
        $kit_id = (int) get_option( 'elementor_active_kit' );
        return $kit_id > 0 ? $kit_id : 0;
    }

    private function extract_colors( array $settings ): array {
        $colors = [];

        $system_colors = $settings['system_colors'] ?? [];
        foreach ( $system_colors as $item ) {
            if ( isset( $item['_id'], $item['title'], $item['color'] ) ) {
                $colors[] = [
                    'id'    => $item['_id'],
                    'label' => $item['title'],
                    'value' => $item['color'],
                ];
            }
        }

        $custom_colors = $settings['custom_colors'] ?? [];
        foreach ( $custom_colors as $item ) {
            if ( isset( $item['_id'], $item['title'], $item['color'] ) ) {
                $colors[] = [
                    'id'    => $item['_id'],
                    'label' => $item['title'],
                    'value' => $item['color'],
                    'type'  => 'custom',
                ];
            }
        }

        return $colors;
    }

    private function extract_typography( array $settings ): array {
        $typography = [];

        $system_typo = $settings['system_typography'] ?? [];
        foreach ( $system_typo as $item ) {
            if ( isset( $item['_id'], $item['title'] ) ) {
                $typography[] = [
                    'id'     => $item['_id'],
                    'label'  => $item['title'],
                    'family' => $item['typography_typography']['font_family'] ?? '',
                    'size'   => $item['typography_typography']['font_size']['size'] ?? '',
                    'weight' => $item['typography_typography']['font_weight'] ?? '',
                ];
            }
        }

        $custom_typo = $settings['custom_typography'] ?? [];
        foreach ( $custom_typo as $item ) {
            if ( isset( $item['_id'], $item['title'] ) ) {
                $typography[] = [
                    'id'     => $item['_id'],
                    'label'  => $item['title'],
                    'family' => $item['typography_typography']['font_family'] ?? '',
                    'type'   => 'custom',
                ];
            }
        }

        return $typography;
    }
}
