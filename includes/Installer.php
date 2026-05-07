<?php
/**
 * Telepítő – aktiválás/deaktiválás kezelése.
 *
 * @package AIE
 */

namespace AIE;

defined( 'ABSPATH' ) || exit;

class Installer {

    public static function activate(): void {
        // Alapértelmezett beállítások mentése, ha még nincsenek
        if ( ! get_option( AIE_OPTION_KEY ) ) {
            update_option( AIE_OPTION_KEY, [
                'openai_api_key' => '',
                'openai_model'   => 'gpt-4o',
                'max_tokens'     => 4096,
            ] );
        }
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }
}
