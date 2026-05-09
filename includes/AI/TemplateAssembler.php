<?php
/**
 * AI page plan → kész Elementor JSON összerakása a TemplateLibrary szekciókból.
 *
 * Az AI egy kompakt "page plan" JSON-t ad vissza (csak tartalom, nincs Elementor struktúra).
 * Ez az osztály azt parseolja és a megfelelő template metódusokat hívja össze.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

use WP_Error;

class TemplateAssembler {

    private bool $has_pro;

    public function __construct() {
        $this->has_pro = defined( 'ELEMENTOR_PRO_VERSION' );
    }

    /**
     * Az AI nyers szövegéből Elementor JSON tömböt épít.
     *
     * @param  string $ai_raw  Az AI által visszaadott JSON string.
     * @return array|WP_Error
     */
    public function assemble( string $ai_raw ): array|WP_Error {
        $plan = $this->parse_plan( $ai_raw );
        if ( is_wp_error( $plan ) ) {
            return $plan;
        }

        TemplateLibrary::reset_ids();

        $sections = [];
        foreach ( $plan['sections'] as $s ) {
            $type    = $s['type'] ?? '';
            $content = $s;
            $section = $this->build_section( $type, $content );
            if ( null !== $section ) {
                $sections[] = $section;
            }
        }

        if ( empty( $sections ) ) {
            return new WP_Error(
                'aie_no_sections',
                __( 'Az AI nem adott vissza felismerhető szekciókat.', 'ai-elementor-builder' ),
                [ 'status' => 422 ]
            );
        }

        return $sections;
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function parse_plan( string $ai_raw ): array|WP_Error {
        // Markdown kódblokkot eltávolítjuk
        $cleaned = preg_replace( '/^```(?:json)?\s*/m', '', $ai_raw );
        $cleaned = preg_replace( '/\s*```$/m', '', $cleaned );
        $cleaned = trim( $cleaned );

        $decoded = json_decode( $cleaned, true );

        if ( JSON_ERROR_NONE !== json_last_error() ) {
            return new WP_Error(
                'aie_plan_parse_error',
                sprintf(
                    __( 'Page plan JSON hiba: %s', 'ai-elementor-builder' ),
                    json_last_error_msg()
                ),
                [ 'status' => 422 ]
            );
        }

        if ( empty( $decoded['sections'] ) || ! is_array( $decoded['sections'] ) ) {
            return new WP_Error(
                'aie_plan_missing_sections',
                __( 'A page plan nem tartalmaz "sections" tömböt.', 'ai-elementor-builder' ),
                [ 'status' => 422 ]
            );
        }

        return $decoded;
    }

    /**
     * Megpróbál PHP-sablonból szekciót építeni.
     * Ha az adott típushoz nincs sablon, null-t ad vissza — a hívó AI-t hívhat.
     */
    public function try_template( string $type, array $c ): ?array {
        return $this->build_section( $type, $c );
    }

    private function build_section( string $type, array $c ): ?array {
        return match ( $type ) {
            'hero-split'     => TemplateLibrary::hero_split( $c ),
            'features-3'     => TemplateLibrary::features_3( $c, $this->has_pro ),
            'stats-4'        => TemplateLibrary::stats_4( $c ),
            'about-2col'     => TemplateLibrary::about_2col( $c ),
            'process-3steps' => TemplateLibrary::process_3steps( $c ),
            'testimonials-3' => TemplateLibrary::testimonials_3( $c ),
            'faq'            => TemplateLibrary::faq_accordion( $c ),
            'cta-dark'       => TemplateLibrary::cta_dark( $c ),
            default          => null,
        };
    }
}
