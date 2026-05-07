<?php
/**
 * Admin beállítások oldal – API kulcs és modell konfigurálása.
 *
 * @package AIE\Admin
 */

namespace AIE\Admin;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

    private const PAGE_SLUG   = 'ai-elementor-builder';
    private const OPTION_GROUP = 'aie_settings_group';

    public function register_menu(): void {
        add_options_page(
            __( 'AI Elementor Builder', 'ai-elementor-builder' ),
            __( 'AI Elementor Builder', 'ai-elementor-builder' ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );

        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings(): void {
        register_setting(
            self::OPTION_GROUP,
            AIE_OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
            ]
        );

        add_settings_section(
            'aie_main_section',
            __( 'OpenAI API Beállítások', 'ai-elementor-builder' ),
            null,
            self::PAGE_SLUG
        );

        add_settings_field(
            'openai_api_key',
            __( 'API Kulcs', 'ai-elementor-builder' ),
            [ $this, 'render_api_key_field' ],
            self::PAGE_SLUG,
            'aie_main_section'
        );

        add_settings_field(
            'openai_model',
            __( 'Model', 'ai-elementor-builder' ),
            [ $this, 'render_model_field' ],
            self::PAGE_SLUG,
            'aie_main_section'
        );

        add_settings_field(
            'max_tokens',
            __( 'Max Tokenek', 'ai-elementor-builder' ),
            [ $this, 'render_max_tokens_field' ],
            self::PAGE_SLUG,
            'aie_main_section'
        );
    }

    public function sanitize_settings( array $input ): array {
        return [
            'openai_api_key' => sanitize_text_field( $input['openai_api_key'] ?? '' ),
            'openai_model'   => sanitize_text_field( $input['openai_model']   ?? 'gpt-4o' ),
            'max_tokens'     => min( 8192, max( 512, (int) ( $input['max_tokens'] ?? 4096 ) ) ),
        ];
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AI Elementor Builder – Beállítások', 'ai-elementor-builder' ); ?></h1>

            <?php if ( empty( $settings['openai_api_key'] ) ) : ?>
            <div class="notice notice-warning">
                <p><?php esc_html_e( 'Kérjük, add meg az OpenAI API kulcsot a plugin működéséhez.', 'ai-elementor-builder' ); ?></p>
            </div>
            <?php endif; ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( self::OPTION_GROUP );
                do_settings_sections( self::PAGE_SLUG );
                submit_button( __( 'Mentés', 'ai-elementor-builder' ) );
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e( 'REST API Teszt', 'ai-elementor-builder' ); ?></h2>
            <p><?php esc_html_e( 'Az alábbi végpont érhető el:', 'ai-elementor-builder' ); ?></p>
            <code><?php echo esc_url( rest_url( 'ai-builder/v1/generate' ) ); ?></code>
            <p><?php esc_html_e( 'POST paraméterek: post_id (int), prompt (string), mode (auto|create|modify)', 'ai-elementor-builder' ); ?></p>
        </div>
        <?php
    }

    public function render_api_key_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $value    = $settings['openai_api_key'] ?? '';
        printf(
            '<input type="password" name="%s[openai_api_key]" value="%s" class="regular-text" autocomplete="new-password">
             <p class="description">%s</p>',
            esc_attr( AIE_OPTION_KEY ),
            esc_attr( $value ),
            esc_html__( 'OpenAI platform.openai.com/api-keys oldalon generálható.', 'ai-elementor-builder' )
        );
    }

    public function render_model_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $current  = $settings['openai_model'] ?? 'gpt-4o';
        $models   = [ 'gpt-4o', 'gpt-4o-mini', 'gpt-4-turbo' ];
        echo '<select name="' . esc_attr( AIE_OPTION_KEY ) . '[openai_model]">';
        foreach ( $models as $model ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $model ),
                selected( $current, $model, false ),
                esc_html( $model )
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Ajánlott: gpt-4o (legjobb minőség + JSON mód).', 'ai-elementor-builder' ) . '</p>';
    }

    public function render_max_tokens_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $value    = (int) ( $settings['max_tokens'] ?? 4096 );
        printf(
            '<input type="number" name="%s[max_tokens]" value="%d" min="512" max="8192" step="256" class="small-text">
             <p class="description">%s</p>',
            esc_attr( AIE_OPTION_KEY ),
            $value,
            esc_html__( 'Komplex oldalakhoz ajánlott: 4096–8192. Magasabb érték = több API költség.', 'ai-elementor-builder' )
        );
    }
}
