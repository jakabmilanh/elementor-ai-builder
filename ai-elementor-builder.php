<?php
/**
 * Plugin Name: AI Elementor Builder
 * Plugin URI:  https://example.com/ai-elementor-builder
 * Description: Elementor oldalak építése és módosítása AI segítségével (OpenAI GPT-4).
 * Version:     1.0.0
 * Author:      Your Name
 * License:     GPL-2.0+
 * Text Domain: ai-elementor-builder
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

defined( 'ABSPATH' ) || exit;

// ── Konstansok ────────────────────────────────────────────────────────────────
define( 'AIE_VERSION',     '1.0.0' );
define( 'AIE_FILE',        __FILE__ );
define( 'AIE_DIR',         plugin_dir_path( __FILE__ ) );
define( 'AIE_URL',         plugin_dir_url( __FILE__ ) );
define( 'AIE_OPTION_KEY',  'aie_settings' );

// ── Autoloader ────────────────────────────────────────────────────────────────
spl_autoload_register( function ( string $class ): void {
    $prefix = 'AIE\\';
    if ( ! str_starts_with( $class, $prefix ) ) {
        return;
    }
    $relative = str_replace( '\\', DIRECTORY_SEPARATOR, substr( $class, strlen( $prefix ) ) );
    $file     = AIE_DIR . 'includes' . DIRECTORY_SEPARATOR . $relative . '.php';
    if ( is_readable( $file ) ) {
        require_once $file;
    }
} );

// ── Bootstrap ─────────────────────────────────────────────────────────────────
add_action( 'plugins_loaded', [ 'AIE\\Plugin', 'get_instance' ] );

// ── Aktiválás / Deaktiválás ───────────────────────────────────────────────────
register_activation_hook(   AIE_FILE, [ 'AIE\\Installer', 'activate'   ] );
register_deactivation_hook( AIE_FILE, [ 'AIE\\Installer', 'deactivate' ] );
