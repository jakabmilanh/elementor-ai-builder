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
        require_once AIE_DIR . 'includes/AI/ClaudeClient.php';
        require_once AIE_DIR . 'includes/AI/GroqClient.php';
        require_once AIE_DIR . 'includes/AI/PromptBuilder.php';
        require_once AIE_DIR . 'includes/AI/TemplateLibrary.php';
        require_once AIE_DIR . 'includes/AI/TemplateAssembler.php';
    }

    private function init_hooks(): void {
        // REST API
        add_action( 'rest_api_init', [ new Api\RestController(), 'register_routes' ] );

        // Admin settings oldal
        if ( is_admin() ) {
            add_action( 'admin_menu', [ new Admin\SettingsPage(), 'register_menu' ] );
        }

        // ── Elementor editor hook-ok – KÖZVETLENÜL regisztrálva ──────────────
        // Ezek kellenek, mert az Elementor editor nem standard admin oldal:
        add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_panel' ] );
        add_action( 'elementor/editor/footer',                [ $this, 'inject_panel_container' ] );
    }

    /**
     * Az Elementor editor JS-ének betöltése.
     */
    public function enqueue_editor_panel(): void {
        wp_enqueue_script(
            'aie-editor-panel',
            AIE_URL . 'assets/js/editor-panel.js',
            [ 'jquery', 'elementor-editor' ],
            AIE_VERSION,
            true
        );

        wp_localize_script( 'aie-editor-panel', 'AIEData', [
            'restUrl'  => rest_url( 'ai-builder/v1/generate' ),
            'nonce'    => wp_create_nonce( 'wp_rest' ),
            'postId'   => isset( $_GET['post'] ) ? (int) $_GET['post'] : 0,
            'hasElPro' => defined( 'ELEMENTOR_PRO_VERSION' ),
        ] );
    }

    /**
     * Üres div konténer az editor footer-be – a JS ide injektál.
     */
    public function inject_panel_container(): void {
        echo '<div id="aie-panel-mount"></div>';
    }
}
