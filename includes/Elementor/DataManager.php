<?php
/**
 * Elementor adatok olvasása és írása.
 *
 * @package AIE\Elementor
 */

namespace AIE\Elementor;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class DataManager {

    private const META_KEY = '_elementor_data';

    // ── Olvasás ───────────────────────────────────────────────────────────────

    /**
     * Az oldal Elementor JSON-ja (nyers string).
     */
    public function get_elementor_data( int $post_id ): string {
        $raw = get_post_meta( $post_id, self::META_KEY, true );
        return is_string( $raw ) ? $raw : '';
    }

    /**
     * Globális stílusok az aktív Elementor Kit-ből.
     * Visszatér egy tömbbel: ['colors' => [...], 'typography' => [...]]
     */
    public function get_global_styles(): array {
        $kit_id = $this->get_active_kit_id();
        if ( ! $kit_id ) {
            return [ 'colors' => [], 'typography' => [] ];
        }

        // Elementor Kit globális értékei a '__globals__' meta alatt vagy a kit settings-ben
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

    /**
     * Elmenti a generált Elementor adatot.
     *
     * @param int   $post_id
     * @param array $data     Dekódolt PHP tömb (nem JSON string!)
     * @return true|WP_Error
     */
    public function save_elementor_data( int $post_id, array $data ): true|WP_Error {
        // Elementor a wp_slash() + json_encode() kombinációt várja
        $json   = wp_slash( wp_json_encode( $data ) );
        $result = update_post_meta( $post_id, self::META_KEY, $json );

        if ( false === $result ) {
            return new WP_Error(
                'aie_save_failed',
                __( 'Nem sikerült menteni az Elementor adatot.', 'ai-elementor-builder' ),
                [ 'status' => 500 ]
            );
        }

        // Jelöljük, hogy ez Elementor-szerkesztett oldal
        update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

        return true;
    }

    /**
     * Elementor fájl-cache törlése az adott oldalhoz.
     */
    public function clear_elementor_cache( int $post_id ): void {
        if (
            defined( 'ELEMENTOR_VERSION' ) &&
            class_exists( '\Elementor\Plugin' ) &&
            isset( \Elementor\Plugin::$instance->files_manager )
        ) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        // WordPress saját cache
        clean_post_cache( $post_id );
    }

    // ── Privát segédek ────────────────────────────────────────────────────────

    private function get_active_kit_id(): int {
        // Az Elementor Kit ID-ját a site-options tárolja
        $kit_id = (int) get_option( 'elementor_active_kit' );
        return $kit_id > 0 ? $kit_id : 0;
    }

    /**
     * Kiszedi a színpalettát a Kit settings tömbből.
     */
    private function extract_colors( array $settings ): array {
        $colors = [];

        // Elementor 3.x+ system colors kulcsa: 'system_colors'
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

        // Egyedi (custom) színek
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

    /**
     * Kiszedi a tipográfiai beállításokat a Kit settings tömbből.
     */
    private function extract_typography( array $settings ): array {
        $typography = [];

        $system_typo = $settings['system_typography'] ?? [];
        foreach ( $system_typo as $item ) {
            if ( isset( $item['_id'], $item['title'] ) ) {
                $typography[] = [
                    'id'        => $item['_id'],
                    'label'     => $item['title'],
                    'family'    => $item['typography_typography']['font_family'] ?? '',
                    'size'      => $item['typography_typography']['font_size']['size'] ?? '',
                    'weight'    => $item['typography_typography']['font_weight'] ?? '',
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
