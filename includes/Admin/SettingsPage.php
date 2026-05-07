<?php
/**
 * Admin beállítások oldal – AI provider, API kulcsok, modell konfigurálása.
 *
 * @package AIE\Admin
 */

namespace AIE\Admin;

defined( 'ABSPATH' ) || exit;

class SettingsPage {

    private const PAGE_SLUG    = 'ai-elementor-builder';
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

        add_settings_section( 'aie_provider_section', __( 'AI Szolgáltató', 'ai-elementor-builder' ), null, self::PAGE_SLUG );
        add_settings_section( 'aie_claude_section',   __( 'Claude (Anthropic) Beállítások', 'ai-elementor-builder' ), null, self::PAGE_SLUG );
        add_settings_section( 'aie_groq_section',     __( 'Groq Beállítások (tartalék)', 'ai-elementor-builder' ), null, self::PAGE_SLUG );
        add_settings_section( 'aie_general_section',  __( 'Általános', 'ai-elementor-builder' ), null, self::PAGE_SLUG );

        add_settings_field( 'ai_provider',     __( 'AI Motor', 'ai-elementor-builder' ),      [ $this, 'render_provider_field' ],    self::PAGE_SLUG, 'aie_provider_section' );
        add_settings_field( 'claude_api_key',  __( 'Claude API Kulcs', 'ai-elementor-builder' ), [ $this, 'render_claude_key_field' ], self::PAGE_SLUG, 'aie_claude_section' );
        add_settings_field( 'claude_model',    __( 'Claude Model', 'ai-elementor-builder' ),   [ $this, 'render_claude_model_field' ], self::PAGE_SLUG, 'aie_claude_section' );
        add_settings_field( 'groq_api_key',    __( 'Groq API Kulcs', 'ai-elementor-builder' ), [ $this, 'render_groq_key_field' ],   self::PAGE_SLUG, 'aie_groq_section' );
        add_settings_field( 'groq_model',      __( 'Groq Model', 'ai-elementor-builder' ),     [ $this, 'render_groq_model_field' ], self::PAGE_SLUG, 'aie_groq_section' );
        add_settings_field( 'max_tokens',      __( 'Max Tokenek', 'ai-elementor-builder' ),    [ $this, 'render_max_tokens_field' ], self::PAGE_SLUG, 'aie_general_section' );
    }

    public function sanitize_settings( array $input ): array {
        return [
            'ai_provider'    => in_array( $input['ai_provider'] ?? '', [ 'claude', 'groq' ], true )
                                    ? $input['ai_provider']
                                    : 'claude',
            'claude_api_key' => sanitize_text_field( $input['claude_api_key'] ?? '' ),
            'claude_model'   => sanitize_text_field( $input['claude_model']   ?? 'claude-haiku-4-5-20251001' ),
            'groq_api_key'   => sanitize_text_field( $input['groq_api_key']   ?? '' ),
            'groq_model'     => sanitize_text_field( $input['groq_model']     ?? 'llama-3.3-70b-versatile' ),
            'max_tokens'     => min( 16000, max( 512, (int) ( $input['max_tokens'] ?? 8096 ) ) ),
        ];
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $provider = $settings['ai_provider'] ?? 'claude';
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'AI Elementor Builder – Beállítások', 'ai-elementor-builder' ); ?></h1>

            <?php if ( 'claude' === $provider && empty( $settings['claude_api_key'] ) ) : ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e( 'Add meg a Claude API kulcsot a plugin működéséhez.', 'ai-elementor-builder' ); ?>
                    <a href="https://console.anthropic.com/settings/keys" target="_blank">
                        <?php esc_html_e( 'Szerezd meg itt →', 'ai-elementor-builder' ); ?>
                    </a>
                </p>
            </div>
            <?php elseif ( 'groq' === $provider && empty( $settings['groq_api_key'] ) ) : ?>
            <div class="notice notice-warning">
                <p>
                    <?php esc_html_e( 'Add meg a Groq API kulcsot a plugin működéséhez.', 'ai-elementor-builder' ); ?>
                    <a href="https://console.groq.com/keys" target="_blank">
                        <?php esc_html_e( 'Szerezd meg itt →', 'ai-elementor-builder' ); ?>
                    </a>
                </p>
            </div>
            <?php endif; ?>

            <?php if ( 'claude' === $provider ) : ?>
            <div class="notice notice-info" style="border-left-color:#9b59b6;">
                <p>
                    <strong>💡 Ajánlott: Claude (Anthropic)</strong> —
                    <?php esc_html_e( 'Lényegesen jobb minőségű oldalgenerálás + template-alapú prémium design. Ár: ~$0.01–0.03/generálás (Haiku), ~$0.05–0.15/generálás (Sonnet).', 'ai-elementor-builder' ); ?>
                </p>
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
            <h2><?php esc_html_e( 'REST API Végpont', 'ai-elementor-builder' ); ?></h2>
            <code><?php echo esc_url( rest_url( 'ai-builder/v1/generate' ) ); ?></code>
            <p><?php esc_html_e( 'POST paraméterek: post_id (int), prompt (string), mode (auto|create|modify)', 'ai-elementor-builder' ); ?></p>
        </div>
        <?php
    }

    // ── Mezők ────────────────────────────────────────────────────────────────

    public function render_provider_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $current  = $settings['ai_provider'] ?? 'claude';
        ?>
        <fieldset>
            <label style="margin-right:24px;">
                <input type="radio" name="<?php echo esc_attr( AIE_OPTION_KEY ); ?>[ai_provider]"
                    value="claude" <?php checked( $current, 'claude' ); ?>>
                <strong>Claude (Anthropic)</strong> —
                <?php esc_html_e( 'Ajánlott. Prémium minőség, template-alapú oldalgenerálás.', 'ai-elementor-builder' ); ?>
            </label>
            <br><br>
            <label>
                <input type="radio" name="<?php echo esc_attr( AIE_OPTION_KEY ); ?>[ai_provider]"
                    value="groq" <?php checked( $current, 'groq' ); ?>>
                <strong>Groq (Llama)</strong> —
                <?php esc_html_e( 'Ingyenes, de alacsonyabb minőség. Korlátozott TPM limit.', 'ai-elementor-builder' ); ?>
            </label>
        </fieldset>
        <?php
    }

    public function render_claude_key_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $value    = $settings['claude_api_key'] ?? '';
        printf(
            '<input type="password" name="%s[claude_api_key]" value="%s" class="regular-text" autocomplete="new-password">
             <p class="description">%s <a href="https://console.anthropic.com/settings/keys" target="_blank">console.anthropic.com/settings/keys</a></p>',
            esc_attr( AIE_OPTION_KEY ),
            esc_attr( $value ),
            esc_html__( 'Anthropic Console API kulcs:', 'ai-elementor-builder' )
        );
    }

    public function render_claude_model_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $current  = $settings['claude_model'] ?? 'claude-haiku-4-5-20251001';
        $models   = [
            'claude-haiku-4-5-20251001' => 'claude-haiku-4-5 — Gyors, olcsó (~$0.01–0.03/generálás) ✅ Ajánlott kezdőknek',
            'claude-sonnet-4-6'         => 'claude-sonnet-4-6 — Legjobb minőség (~$0.05–0.15/generálás) 🏆 Prémium',
        ];
        echo '<select name="' . esc_attr( AIE_OPTION_KEY ) . '[claude_model]">';
        foreach ( $models as $model => $label ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $model ),
                selected( $current, $model, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Haiku: gyors és olcsó. Sonnet: legjobb minőség, magasabb ár.', 'ai-elementor-builder' ) . '</p>';
    }

    public function render_groq_key_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $value    = $settings['groq_api_key'] ?? '';
        printf(
            '<input type="password" name="%s[groq_api_key]" value="%s" class="regular-text" autocomplete="new-password">
             <p class="description">%s <a href="https://console.groq.com/keys" target="_blank">console.groq.com/keys</a></p>',
            esc_attr( AIE_OPTION_KEY ),
            esc_attr( $value ),
            esc_html__( 'Groq Console API kulcs (ingyenes):', 'ai-elementor-builder' )
        );
    }

    public function render_groq_model_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $current  = $settings['groq_model'] ?? 'llama-3.3-70b-versatile';
        $models   = [
            'llama-3.3-70b-versatile',
            'llama-3.1-8b-instant',
            'mixtral-8x7b-32768',
        ];
        echo '<select name="' . esc_attr( AIE_OPTION_KEY ) . '[groq_model]">';
        foreach ( $models as $model ) {
            printf(
                '<option value="%s" %s>%s</option>',
                esc_attr( $model ),
                selected( $current, $model, false ),
                esc_html( $model )
            );
        }
        echo '</select>';
        echo '<p class="description">' . esc_html__( 'Figyelem: a Groq free tier tokenkorlátja alacsony (12 000 TPM).', 'ai-elementor-builder' ) . '</p>';
    }

    public function render_max_tokens_field(): void {
        $settings = (array) get_option( AIE_OPTION_KEY, [] );
        $value    = (int) ( $settings['max_tokens'] ?? 8096 );
        printf(
            '<input type="number" name="%s[max_tokens]" value="%d" min="512" max="16000" step="256" class="small-text">
             <p class="description">%s</p>',
            esc_attr( AIE_OPTION_KEY ),
            $value,
            esc_html__( 'Claude: 8096 ajánlott. Groq: max 8192.', 'ai-elementor-builder' )
        );
    }
}
