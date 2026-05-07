<?php
/**
 * Fő plugin osztály – singleton bootstrap.
 *
 * @package AIE
 */

namespace AIE;

defined( 'ABSPATH' ) || exit;

final class Plugin {

    private static ?self $instance = null;

    public static function get_instance(): self {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies(): void {
        require_once AIE_DIR . 'includes/Installer.php';
        require_once AIE_DIR . 'includes/Admin/SettingsPage.php';
        require_once AIE_DIR . 'includes/Api/RestController.php';
        require_once AIE_DIR . 'includes/Elementor/DataManager.php';
        require_once AIE_DIR . 'includes/AI/OpenAIClient.php';
        require_once AIE_DIR . 'includes/AI/PromptBuilder.php';
    }

    private function init_hooks(): void {
        // REST API
        add_action( 'rest_api_init', [ new Api\RestController(), 'register_routes' ] );

        // Admin settings oldal
        if ( is_admin() ) {
            add_action( 'admin_menu',    [ new Admin\SettingsPage(), 'register_menu' ] );
            add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
        }
    }

    /**
     * Admin JS/CSS – az Elementor szerkesztőbe kerülő panel számára is.
     */
    public function enqueue_admin_assets( string $hook ): void {
        // Elementor editor
        add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_panel' ] );
    }

    public function enqueue_editor_panel(): void {
        wp_enqueue_script(
            'aie-editor-panel',
            AIE_URL . 'assets/js/editor-panel.js',
            [ 'jquery' ],
            AIE_VERSION,
            true
        );

        wp_localize_script( 'aie-editor-panel', 'AIEData', [
            'restUrl' => rest_url( 'ai-builder/v1/generate' ),
            'nonce'   => wp_create_nonce( 'wp_rest' ),
            'postId'  => get_the_ID(),
        ] );
    }
}
