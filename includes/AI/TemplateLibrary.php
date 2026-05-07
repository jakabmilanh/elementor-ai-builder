<?php
/**
 * Prémium Elementor szekció sablonok.
 * Minden metódus egy kész Elementor JSON tömböt ad vissza, AI-provided tartalommal feltöltve.
 * Az ID-k generálása a TemplateAssembler feladata.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class TemplateLibrary {

    // Belső ID counter – az Assembler hívja reset_ids() után
    private static int $id_seq = 0;

    public static function reset_ids(): void {
        self::$id_seq = 0;
    }

    private static function id(): string {
        self::$id_seq++;
        return 'aie' . str_pad( (string) self::$id_seq, 4, '0', STR_PAD_LEFT );
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. HERO SPLIT
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   label, h1, subtitle, button_text,
     *   stat_1_number, stat_1_suffix, stat_1_label,
     *   stat_2_number, stat_2_suffix, stat_2_label,
     *   stat_3_number, stat_3_suffix, stat_3_label,
     *   image_seed
     * }
     */
    public static function hero_split( array $c ): array {
        $keywords = $c['image_seed'] ?? 'professional,business,team';
        $img = 'https://loremflickr.com/750/620/' . rawurlencode( $keywords );

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'            => 'full',
                'flex_direction'           => 'column',
                'flex_align_items'         => 'center',
                'flex_justify_content'     => 'center',
                'min_height'               => [ 'size' => 100, 'unit' => 'vh' ],
                'padding'                  => [ 'top' => '0', 'bottom' => '0', 'left' => '0', 'right' => '0', 'unit' => 'px', 'isLinked' => false ],
                'background_background'    => 'gradient',
                'background_color'         => '#0a0e27',
                'background_color_b'       => '#1a2a5e',
                'background_gradient_type' => 'linear',
                'background_gradient_angle' => [ 'size' => 135, 'unit' => 'deg' ],
            ],
            'elements' => [
                // ── Row wrapper ──────────────────────────────────────────────
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'content_width'        => 'boxed',
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'center',
                        'flex_justify_content' => 'space-between',
                        'gap'                  => [ 'size' => 60, 'unit' => 'px' ],
                        'padding'              => [ 'top' => '100', 'bottom' => '100', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                    ],
                    'elements' => [
                        // ── Left column ──────────────────────────────────────
                        [
                            'id'      => self::id(),
                            'elType'  => 'container',
                            'isInner' => true,
                            'settings' => [
                                'width'                => [ 'size' => 52, 'unit' => '%' ],
                                'flex_direction'       => 'column',
                                'flex_align_items'     => 'flex-start',
                                'flex_justify_content' => 'center',
                            ],
                            'elements' => [
                                // Label
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'text-editor',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'editor'                   => '<p>' . esc_html( $c['label'] ?? '// TAGLINE' ) . '</p>',
                                        'align'                    => 'left',
                                        'text_color'               => '#e94560',
                                        'typography_typography'    => 'custom',
                                        'typography_font_size'     => [ 'size' => 12, 'unit' => 'px' ],
                                        'typography_font_weight'   => '700',
                                        'typography_letter_spacing' => [ 'size' => 2, 'unit' => 'px' ],
                                        'typography_text_transform' => 'uppercase',
                                    ],
                                    'elements' => [],
                                ],
                                // Spacer 16
                                self::spacer( 16 ),
                                // H1
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'heading',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'title'                  => wp_kses_post( $c['h1'] ?? 'Premium Headline' ),
                                        'header_size'            => 'h1',
                                        'align'                  => 'left',
                                        'title_color'            => '#ffffff',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 62, 'unit' => 'px' ],
                                        'typography_font_weight' => '800',
                                        'typography_line_height' => [ 'size' => 1.1, 'unit' => 'em' ],
                                    ],
                                    'elements' => [],
                                ],
                                // Spacer 20
                                self::spacer( 20 ),
                                // Subtitle
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'text-editor',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'editor'                 => '<p>' . esc_html( $c['subtitle'] ?? '' ) . '</p>',
                                        'align'                  => 'left',
                                        'text_color'             => 'rgba(255,255,255,0.72)',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 18, 'unit' => 'px' ],
                                        'typography_line_height' => [ 'size' => 1.7, 'unit' => 'em' ],
                                    ],
                                    'elements' => [],
                                ],
                                // Spacer 32
                                self::spacer( 32 ),
                                // Button
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'button',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'text'                   => esc_html( $c['button_text'] ?? 'Get Started' ),
                                        'link'                   => [ 'url' => '#', 'is_external' => false, 'nofollow' => false ],
                                        'align'                  => 'left',
                                        'size'                   => 'lg',
                                        'background_color'       => '#e94560',
                                        'button_text_color'      => '#ffffff',
                                        'border_radius'          => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true ],
                                        'padding'                => [ 'top' => '16', 'bottom' => '16', 'left' => '40', 'right' => '40', 'unit' => 'px', 'isLinked' => false ],
                                        'typography_typography'  => 'custom',
                                        'typography_font_weight' => '700',
                                        'typography_font_size'   => [ 'size' => 16, 'unit' => 'px' ],
                                    ],
                                    'elements' => [],
                                ],
                                // Spacer 48
                                self::spacer( 48 ),
                                // Stats row
                                [
                                    'id'      => self::id(),
                                    'elType'  => 'container',
                                    'isInner' => true,
                                    'settings' => [
                                        'flex_direction'       => 'row',
                                        'flex_align_items'     => 'center',
                                        'flex_justify_content' => 'flex-start',
                                        'gap'                  => [ 'size' => 40, 'unit' => 'px' ],
                                    ],
                                    'elements' => [
                                        self::hero_stat( $c['stat_1_number'] ?? 1500, $c['stat_1_suffix'] ?? '+', $c['stat_1_label'] ?? 'Clients' ),
                                        self::hero_stat_divider(),
                                        self::hero_stat( $c['stat_2_number'] ?? 10, $c['stat_2_suffix'] ?? '+', $c['stat_2_label'] ?? 'Years' ),
                                        self::hero_stat_divider(),
                                        self::hero_stat( $c['stat_3_number'] ?? 50, $c['stat_3_suffix'] ?? '+', $c['stat_3_label'] ?? 'Awards' ),
                                    ],
                                ],
                            ],
                        ],
                        // ── Right column ─────────────────────────────────────
                        [
                            'id'      => self::id(),
                            'elType'  => 'container',
                            'isInner' => true,
                            'settings' => [
                                'width'                => [ 'size' => 44, 'unit' => '%' ],
                                'flex_direction'       => 'column',
                                'flex_align_items'     => 'center',
                                'flex_justify_content' => 'center',
                            ],
                            'elements' => [
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'image',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'image'         => [ 'url' => $img, 'id' => '' ],
                                        'image_size'    => 'large',
                                        'align'         => 'center',
                                        'width'         => [ 'size' => 100, 'unit' => '%' ],
                                        'border_radius' => [ 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true ],
                                        'box_shadow_box_shadow_type' => 'yes',
                                        'box_shadow_box_shadow'      => [ 'horizontal' => 0, 'vertical' => 24, 'blur' => 60, 'spread' => 0, 'color' => 'rgba(0,0,0,0.35)' ],
                                    ],
                                    'elements' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. FEATURES – 3 icon-box cards (free) or flip-box cards (pro)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   section_label, section_h2, section_subtitle,
     *   card_1_icon, card_1_title, card_1_desc,
     *   card_2_icon, card_2_title, card_2_desc,
     *   card_3_icon, card_3_title, card_3_desc
     * }
     */
    public static function features_3( array $c, bool $use_pro = false ): array {
        $cards = [
            [ 'icon' => $c['card_1_icon'] ?? 'fas fa-star',   'title' => $c['card_1_title'] ?? 'Feature 1', 'desc' => $c['card_1_desc'] ?? '' ],
            [ 'icon' => $c['card_2_icon'] ?? 'fas fa-check',  'title' => $c['card_2_title'] ?? 'Feature 2', 'desc' => $c['card_2_desc'] ?? '' ],
            [ 'icon' => $c['card_3_icon'] ?? 'fas fa-rocket', 'title' => $c['card_3_title'] ?? 'Feature 3', 'desc' => $c['card_3_desc'] ?? '' ],
        ];

        $card_elements = [];
        foreach ( $cards as $card ) {
            $card_elements[] = $use_pro
                ? self::flip_box_card( $card['icon'], $card['title'], $card['desc'] )
                : self::icon_box_card( $card['icon'], $card['title'], $card['desc'] );
        }

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'flex_align_items'      => 'center',
                'padding'               => [ 'top' => '100', 'bottom' => '100', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background' => 'classic',
                'background_color'      => '#f5f6fa',
            ],
            'elements' => [
                self::section_intro( $c['section_label'] ?? '', $c['section_h2'] ?? '', $c['section_subtitle'] ?? '' ),
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'stretch',
                        'flex_justify_content' => 'space-between',
                        'gap'                  => [ 'size' => 28, 'unit' => 'px' ],
                        'width'                => [ 'size' => 100, 'unit' => '%' ],
                    ],
                    'elements' => $card_elements,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. STATS BAR – 4 counters
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   stat_1_number, stat_1_suffix, stat_1_label,
     *   stat_2_number, stat_2_suffix, stat_2_label,
     *   stat_3_number, stat_3_suffix, stat_3_label,
     *   stat_4_number, stat_4_suffix, stat_4_label
     * }
     */
    public static function stats_4( array $c ): array {
        $stats = [
            [ 'num' => $c['stat_1_number'] ?? 1500, 'sfx' => $c['stat_1_suffix'] ?? '+', 'lbl' => $c['stat_1_label'] ?? 'Clients' ],
            [ 'num' => $c['stat_2_number'] ?? 20,   'sfx' => $c['stat_2_suffix'] ?? '+', 'lbl' => $c['stat_2_label'] ?? 'Years' ],
            [ 'num' => $c['stat_3_number'] ?? 98,   'sfx' => $c['stat_3_suffix'] ?? '%', 'lbl' => $c['stat_3_label'] ?? 'Satisfaction' ],
            [ 'num' => $c['stat_4_number'] ?? 50,   'sfx' => $c['stat_4_suffix'] ?? '+', 'lbl' => $c['stat_4_label'] ?? 'Awards' ],
        ];

        $stat_cols = [];
        foreach ( $stats as $s ) {
            $stat_cols[] = [
                'id'      => self::id(),
                'elType'  => 'container',
                'isInner' => true,
                'settings' => [
                    'flex_direction'       => 'column',
                    'flex_align_items'     => 'center',
                    'flex_justify_content' => 'center',
                    'width'                => [ 'size' => 22, 'unit' => '%' ],
                ],
                'elements' => [
                    [
                        'id'         => self::id(),
                        'elType'     => 'widget',
                        'widgetType' => 'counter',
                        'isInner'    => false,
                        'settings'   => [
                            'starting_number'        => 0,
                            'ending_number'          => (int) $s['num'],
                            'suffix'                 => esc_html( $s['sfx'] ),
                            'title'                  => esc_html( $s['lbl'] ),
                            'number_size'            => [ 'size' => 52, 'unit' => 'px' ],
                            'number_color'           => '#ffffff',
                            'title_color'            => 'rgba(255,255,255,0.70)',
                            'title_size'             => [ 'size' => 14, 'unit' => 'px' ],
                            'typography_typography'  => 'custom',
                            'typography_font_weight' => '800',
                        ],
                        'elements' => [],
                    ],
                ],
            ];
        }

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'            => 'full',
                'flex_direction'           => 'column',
                'flex_align_items'         => 'center',
                'padding'                  => [ 'top' => '80', 'bottom' => '80', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background'    => 'gradient',
                'background_color'         => '#e94560',
                'background_color_b'       => '#c0233e',
                'background_gradient_type' => 'linear',
                'background_gradient_angle' => [ 'size' => 135, 'unit' => 'deg' ],
            ],
            'elements' => [
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'content_width'        => 'boxed',
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'center',
                        'flex_justify_content' => 'space-around',
                        'width'                => [ 'size' => 100, 'unit' => '%' ],
                    ],
                    'elements' => $stat_cols,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. ABOUT – 2-column (text left, image right)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   section_label, section_h2, section_subtitle,
     *   benefit_1, benefit_2, benefit_3, benefit_4,
     *   button_text, image_seed
     * }
     */
    public static function about_2col( array $c ): array {
        $keywords = $c['image_seed'] ?? 'professional,office,team';
        $img = 'https://loremflickr.com/700/560/' . rawurlencode( $keywords );

        $benefits = [
            [ 'text' => $c['benefit_1'] ?? 'Benefit 1', 'icon' => [ 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ] ],
            [ 'text' => $c['benefit_2'] ?? 'Benefit 2', 'icon' => [ 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ] ],
            [ 'text' => $c['benefit_3'] ?? 'Benefit 3', 'icon' => [ 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ] ],
            [ 'text' => $c['benefit_4'] ?? 'Benefit 4', 'icon' => [ 'value' => 'fas fa-check-circle', 'library' => 'fa-solid' ] ],
        ];

        $icon_list_items = [];
        foreach ( $benefits as $i => $b ) {
            $icon_list_items[] = [
                '_id'           => 'bn' . $i,
                'text'          => esc_html( $b['text'] ),
                'selected_icon' => $b['icon'],
            ];
        }

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'flex_align_items'      => 'center',
                'padding'               => [ 'top' => '100', 'bottom' => '100', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background' => 'classic',
                'background_color'      => '#ffffff',
            ],
            'elements' => [
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'center',
                        'flex_justify_content' => 'space-between',
                        'gap'                  => [ 'size' => 64, 'unit' => 'px' ],
                        'width'                => [ 'size' => 100, 'unit' => '%' ],
                    ],
                    'elements' => [
                        // Left
                        [
                            'id'      => self::id(),
                            'elType'  => 'container',
                            'isInner' => true,
                            'settings' => [
                                'width'                => [ 'size' => 50, 'unit' => '%' ],
                                'flex_direction'       => 'column',
                                'flex_align_items'     => 'flex-start',
                                'flex_justify_content' => 'center',
                            ],
                            'elements' => [
                                // Label
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'text-editor',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'editor'                    => '<p>' . esc_html( $c['section_label'] ?? '' ) . '</p>',
                                        'align'                     => 'left',
                                        'text_color'                => '#e94560',
                                        'typography_typography'     => 'custom',
                                        'typography_font_size'      => [ 'size' => 12, 'unit' => 'px' ],
                                        'typography_font_weight'    => '700',
                                        'typography_letter_spacing' => [ 'size' => 2, 'unit' => 'px' ],
                                        'typography_text_transform' => 'uppercase',
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 12 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'heading',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'title'                  => wp_kses_post( $c['section_h2'] ?? '' ),
                                        'header_size'            => 'h2',
                                        'align'                  => 'left',
                                        'title_color'            => '#1a1a2e',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 40, 'unit' => 'px' ],
                                        'typography_font_weight' => '700',
                                        'typography_line_height' => [ 'size' => 1.2, 'unit' => 'em' ],
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 16 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'text-editor',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'editor'                 => '<p>' . esc_html( $c['section_subtitle'] ?? '' ) . '</p>',
                                        'align'                  => 'left',
                                        'text_color'             => '#5a6a7a',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 17, 'unit' => 'px' ],
                                        'typography_line_height' => [ 'size' => 1.7, 'unit' => 'em' ],
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 28 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'icon-list',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'icon_list'              => $icon_list_items,
                                        'space_between'          => [ 'size' => 16, 'unit' => 'px' ],
                                        'icon_color'             => '#e94560',
                                        'text_color'             => '#1a1a2e',
                                        'icon_size'              => [ 'size' => 18, 'unit' => 'px' ],
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 16, 'unit' => 'px' ],
                                        'typography_font_weight' => '500',
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 32 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'button',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'text'                   => esc_html( $c['button_text'] ?? 'Learn More' ),
                                        'link'                   => [ 'url' => '#', 'is_external' => false, 'nofollow' => false ],
                                        'align'                  => 'left',
                                        'size'                   => 'lg',
                                        'background_color'       => '#e94560',
                                        'button_text_color'      => '#ffffff',
                                        'border_radius'          => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true ],
                                        'padding'                => [ 'top' => '14', 'bottom' => '14', 'left' => '36', 'right' => '36', 'unit' => 'px', 'isLinked' => false ],
                                        'typography_typography'  => 'custom',
                                        'typography_font_weight' => '700',
                                        'typography_font_size'   => [ 'size' => 16, 'unit' => 'px' ],
                                    ],
                                    'elements' => [],
                                ],
                            ],
                        ],
                        // Right – image
                        [
                            'id'      => self::id(),
                            'elType'  => 'container',
                            'isInner' => true,
                            'settings' => [
                                'width'                => [ 'size' => 46, 'unit' => '%' ],
                                'flex_direction'       => 'column',
                                'flex_align_items'     => 'center',
                                'flex_justify_content' => 'center',
                            ],
                            'elements' => [
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'image',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'image'         => [ 'url' => $img, 'id' => '' ],
                                        'image_size'    => 'large',
                                        'align'         => 'center',
                                        'width'         => [ 'size' => 100, 'unit' => '%' ],
                                        'border_radius' => [ 'top' => '20', 'right' => '20', 'bottom' => '20', 'left' => '20', 'unit' => 'px', 'isLinked' => true ],
                                        'box_shadow_box_shadow_type' => 'yes',
                                        'box_shadow_box_shadow'      => [ 'horizontal' => 0, 'vertical' => 16, 'blur' => 48, 'spread' => 0, 'color' => 'rgba(0,0,0,0.12)' ],
                                    ],
                                    'elements' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. PROCESS – 3 numbered steps
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   section_label, section_h2, section_subtitle,
     *   step_1_title, step_1_desc,
     *   step_2_title, step_2_desc,
     *   step_3_title, step_3_desc
     * }
     */
    public static function process_3steps( array $c ): array {
        $steps = [
            [ 'num' => '01', 'title' => $c['step_1_title'] ?? 'Step 1', 'desc' => $c['step_1_desc'] ?? '' ],
            [ 'num' => '02', 'title' => $c['step_2_title'] ?? 'Step 2', 'desc' => $c['step_2_desc'] ?? '' ],
            [ 'num' => '03', 'title' => $c['step_3_title'] ?? 'Step 3', 'desc' => $c['step_3_desc'] ?? '' ],
        ];

        $step_cards = [];
        foreach ( $steps as $s ) {
            $step_cards[] = [
                'id'      => self::id(),
                'elType'  => 'container',
                'isInner' => true,
                'settings' => [
                    'width'                          => [ 'size' => 30, 'unit' => '%' ],
                    'flex_direction'                 => 'column',
                    'flex_align_items'               => 'flex-start',
                    'padding'                        => [ 'top' => '40', 'bottom' => '40', 'left' => '36', 'right' => '36', 'unit' => 'px', 'isLinked' => false ],
                    'background_background'          => 'classic',
                    'background_color'               => '#ffffff',
                    'border_radius'                  => [ 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ],
                    'box_shadow_box_shadow_type'     => 'yes',
                    'box_shadow_box_shadow'          => [ 'horizontal' => 0, 'vertical' => 8, 'blur' => 32, 'spread' => 0, 'color' => 'rgba(0,0,0,0.08)' ],
                ],
                'elements' => [
                    [
                        'id'         => self::id(),
                        'elType'     => 'widget',
                        'widgetType' => 'text-editor',
                        'isInner'    => false,
                        'settings'   => [
                            'editor'                 => '<p>' . $s['num'] . '</p>',
                            'align'                  => 'left',
                            'text_color'             => '#e94560',
                            'typography_typography'  => 'custom',
                            'typography_font_size'   => [ 'size' => 52, 'unit' => 'px' ],
                            'typography_font_weight' => '800',
                            'typography_line_height' => [ 'size' => 1, 'unit' => 'em' ],
                        ],
                        'elements' => [],
                    ],
                    self::spacer( 16 ),
                    [
                        'id'         => self::id(),
                        'elType'     => 'widget',
                        'widgetType' => 'heading',
                        'isInner'    => false,
                        'settings'   => [
                            'title'                  => esc_html( $s['title'] ),
                            'header_size'            => 'h3',
                            'align'                  => 'left',
                            'title_color'            => '#1a1a2e',
                            'typography_typography'  => 'custom',
                            'typography_font_size'   => [ 'size' => 22, 'unit' => 'px' ],
                            'typography_font_weight' => '700',
                        ],
                        'elements' => [],
                    ],
                    self::spacer( 12 ),
                    [
                        'id'         => self::id(),
                        'elType'     => 'widget',
                        'widgetType' => 'text-editor',
                        'isInner'    => false,
                        'settings'   => [
                            'editor'                 => '<p>' . esc_html( $s['desc'] ) . '</p>',
                            'align'                  => 'left',
                            'text_color'             => '#5a6a7a',
                            'typography_typography'  => 'custom',
                            'typography_font_size'   => [ 'size' => 15, 'unit' => 'px' ],
                            'typography_line_height' => [ 'size' => 1.65, 'unit' => 'em' ],
                        ],
                        'elements' => [],
                    ],
                ],
            ];
        }

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'flex_align_items'      => 'center',
                'padding'               => [ 'top' => '100', 'bottom' => '100', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background' => 'classic',
                'background_color'      => '#f5f6fa',
            ],
            'elements' => [
                self::section_intro( $c['section_label'] ?? '', $c['section_h2'] ?? '', $c['section_subtitle'] ?? '' ),
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'stretch',
                        'flex_justify_content' => 'space-between',
                        'gap'                  => [ 'size' => 28, 'unit' => 'px' ],
                        'width'                => [ 'size' => 100, 'unit' => '%' ],
                    ],
                    'elements' => $step_cards,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. TESTIMONIALS – 3 cards
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   section_label, section_h2, section_subtitle,
     *   test_1_text, test_1_name, test_1_job, test_1_seed,
     *   test_2_text, test_2_name, test_2_job, test_2_seed,
     *   test_3_text, test_3_name, test_3_job, test_3_seed
     * }
     */
    public static function testimonials_3( array $c ): array {
        $tests = [
            [ 'text' => $c['test_1_text'] ?? '', 'name' => $c['test_1_name'] ?? 'Client Name', 'job' => $c['test_1_job'] ?? 'Client', 'seed' => $c['test_1_seed'] ?? 'person1' ],
            [ 'text' => $c['test_2_text'] ?? '', 'name' => $c['test_2_name'] ?? 'Client Name', 'job' => $c['test_2_job'] ?? 'Client', 'seed' => $c['test_2_seed'] ?? 'person2' ],
            [ 'text' => $c['test_3_text'] ?? '', 'name' => $c['test_3_name'] ?? 'Client Name', 'job' => $c['test_3_job'] ?? 'Client', 'seed' => $c['test_3_seed'] ?? 'person3' ],
        ];

        $cards = [];
        foreach ( $tests as $t ) {
            $avatar = 'https://loremflickr.com/120/120/' . rawurlencode( $t['seed'] );
            $cards[] = [
                'id'      => self::id(),
                'elType'  => 'container',
                'isInner' => true,
                'settings' => [
                    'width'                      => [ 'size' => 30, 'unit' => '%' ],
                    'flex_direction'             => 'column',
                    'flex_align_items'           => 'flex-start',
                    'padding'                    => [ 'top' => '36', 'bottom' => '36', 'left' => '32', 'right' => '32', 'unit' => 'px', 'isLinked' => false ],
                    'background_background'      => 'classic',
                    'background_color'           => '#ffffff',
                    'border_radius'              => [ 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ],
                    'box_shadow_box_shadow_type' => 'yes',
                    'box_shadow_box_shadow'      => [ 'horizontal' => 0, 'vertical' => 8, 'blur' => 32, 'spread' => 0, 'color' => 'rgba(0,0,0,0.08)' ],
                ],
                'elements' => [
                    [
                        'id'         => self::id(),
                        'elType'     => 'widget',
                        'widgetType' => 'star-rating',
                        'isInner'    => false,
                        'settings'   => [
                            'rating_scale' => 5,
                            'rating'       => 5,
                            'star_color'   => '#f5a623',
                            'star_size'    => [ 'size' => 18, 'unit' => 'px' ],
                            'align'        => 'left',
                        ],
                        'elements' => [],
                    ],
                    self::spacer( 16 ),
                    [
                        'id'         => self::id(),
                        'elType'     => 'widget',
                        'widgetType' => 'testimonial',
                        'isInner'    => false,
                        'settings'   => [
                            'testimonial_content'   => esc_html( $t['text'] ),
                            'testimonial_image'     => [ 'url' => $avatar, 'id' => '' ],
                            'testimonial_name'      => esc_html( $t['name'] ),
                            'testimonial_job'       => esc_html( $t['job'] ),
                            'testimonial_alignment' => 'left',
                            'content_color'         => '#5a6a7a',
                            'name_color'            => '#1a1a2e',
                            'job_color'             => '#e94560',
                            'typography_typography'  => 'custom',
                            'typography_font_size'   => [ 'size' => 15, 'unit' => 'px' ],
                            'typography_line_height' => [ 'size' => 1.7, 'unit' => 'em' ],
                        ],
                        'elements' => [],
                    ],
                ],
            ];
        }

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'flex_align_items'      => 'center',
                'padding'               => [ 'top' => '100', 'bottom' => '100', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background' => 'classic',
                'background_color'      => '#ffffff',
            ],
            'elements' => [
                self::section_intro( $c['section_label'] ?? '', $c['section_h2'] ?? '', $c['section_subtitle'] ?? '' ),
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'stretch',
                        'flex_justify_content' => 'space-between',
                        'gap'                  => [ 'size' => 28, 'unit' => 'px' ],
                        'width'                => [ 'size' => 100, 'unit' => '%' ],
                    ],
                    'elements' => $cards,
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. FAQ – accordion
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c {
     *   section_label, section_h2, section_subtitle, button_text,
     *   q1, a1, q2, a2, q3, a3, q4, a4
     * }
     */
    public static function faq_accordion( array $c ): array {
        $tabs = [];
        for ( $i = 1; $i <= 4; $i++ ) {
            $q = $c[ 'q' . $i ] ?? '';
            $a = $c[ 'a' . $i ] ?? '';
            if ( ! empty( $q ) ) {
                $tabs[] = [ '_id' => 'fq' . $i, 'tab_title' => esc_html( $q ), 'tab_content' => esc_html( $a ) ];
            }
        }
        if ( empty( $tabs ) ) {
            $tabs = [
                [ '_id' => 'fq1', 'tab_title' => 'Question 1?', 'tab_content' => 'Answer 1.' ],
                [ '_id' => 'fq2', 'tab_title' => 'Question 2?', 'tab_content' => 'Answer 2.' ],
            ];
        }

        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'         => 'boxed',
                'flex_direction'        => 'column',
                'flex_align_items'      => 'center',
                'padding'               => [ 'top' => '100', 'bottom' => '100', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background' => 'classic',
                'background_color'      => '#f5f6fa',
            ],
            'elements' => [
                [
                    'id'      => self::id(),
                    'elType'  => 'container',
                    'isInner' => true,
                    'settings' => [
                        'flex_direction'       => 'row',
                        'flex_align_items'     => 'flex-start',
                        'flex_justify_content' => 'space-between',
                        'gap'                  => [ 'size' => 64, 'unit' => 'px' ],
                        'width'                => [ 'size' => 100, 'unit' => '%' ],
                    ],
                    'elements' => [
                        // Left – intro + button
                        [
                            'id'      => self::id(),
                            'elType'  => 'container',
                            'isInner' => true,
                            'settings' => [
                                'width'                => [ 'size' => 38, 'unit' => '%' ],
                                'flex_direction'       => 'column',
                                'flex_align_items'     => 'flex-start',
                                'flex_justify_content' => 'flex-start',
                            ],
                            'elements' => [
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'text-editor',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'editor'                    => '<p>' . esc_html( $c['section_label'] ?? '' ) . '</p>',
                                        'align'                     => 'left',
                                        'text_color'                => '#e94560',
                                        'typography_typography'     => 'custom',
                                        'typography_font_size'      => [ 'size' => 12, 'unit' => 'px' ],
                                        'typography_font_weight'    => '700',
                                        'typography_letter_spacing' => [ 'size' => 2, 'unit' => 'px' ],
                                        'typography_text_transform' => 'uppercase',
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 12 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'heading',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'title'                  => wp_kses_post( $c['section_h2'] ?? '' ),
                                        'header_size'            => 'h2',
                                        'align'                  => 'left',
                                        'title_color'            => '#1a1a2e',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 38, 'unit' => 'px' ],
                                        'typography_font_weight' => '700',
                                        'typography_line_height' => [ 'size' => 1.2, 'unit' => 'em' ],
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 16 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'text-editor',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'editor'                 => '<p>' . esc_html( $c['section_subtitle'] ?? '' ) . '</p>',
                                        'align'                  => 'left',
                                        'text_color'             => '#5a6a7a',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 17, 'unit' => 'px' ],
                                        'typography_line_height' => [ 'size' => 1.7, 'unit' => 'em' ],
                                    ],
                                    'elements' => [],
                                ],
                                self::spacer( 28 ),
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'button',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'text'                   => esc_html( $c['button_text'] ?? 'Get in Touch' ),
                                        'link'                   => [ 'url' => '#', 'is_external' => false, 'nofollow' => false ],
                                        'align'                  => 'left',
                                        'size'                   => 'md',
                                        'background_color'       => '#e94560',
                                        'button_text_color'      => '#ffffff',
                                        'border_radius'          => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true ],
                                        'typography_typography'  => 'custom',
                                        'typography_font_weight' => '700',
                                        'typography_font_size'   => [ 'size' => 15, 'unit' => 'px' ],
                                    ],
                                    'elements' => [],
                                ],
                            ],
                        ],
                        // Right – accordion
                        [
                            'id'      => self::id(),
                            'elType'  => 'container',
                            'isInner' => true,
                            'settings' => [
                                'width'                => [ 'size' => 57, 'unit' => '%' ],
                                'flex_direction'       => 'column',
                                'flex_align_items'     => 'flex-start',
                                'flex_justify_content' => 'flex-start',
                            ],
                            'elements' => [
                                [
                                    'id'         => self::id(),
                                    'elType'     => 'widget',
                                    'widgetType' => 'accordion',
                                    'isInner'    => false,
                                    'settings'   => [
                                        'tabs'                   => $tabs,
                                        'border_color'           => '#e0e0e8',
                                        'title_color'            => '#1a1a2e',
                                        'icon_color'             => '#e94560',
                                        'active_color'           => '#e94560',
                                        'content_color'          => '#5a6a7a',
                                        'typography_typography'  => 'custom',
                                        'typography_font_size'   => [ 'size' => 16, 'unit' => 'px' ],
                                        'typography_font_weight' => '600',
                                    ],
                                    'elements' => [],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. CTA DARK – always last
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $c { label, h2, subtitle, button_text }
     */
    public static function cta_dark( array $c ): array {
        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => false,
            'settings' => [
                'content_width'            => 'boxed',
                'flex_direction'           => 'column',
                'flex_align_items'         => 'center',
                'flex_justify_content'     => 'center',
                'padding'                  => [ 'top' => '120', 'bottom' => '120', 'left' => '20', 'right' => '20', 'unit' => 'px', 'isLinked' => false ],
                'background_background'    => 'gradient',
                'background_color'         => '#0a0e27',
                'background_color_b'       => '#1a1a2e',
                'background_gradient_type' => 'linear',
                'background_gradient_angle' => [ 'size' => 135, 'unit' => 'deg' ],
                'text_align'               => 'center',
            ],
            'elements' => [
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'text-editor',
                    'isInner'    => false,
                    'settings'   => [
                        'editor'                    => '<p>' . esc_html( $c['label'] ?? '' ) . '</p>',
                        'align'                     => 'center',
                        'text_color'                => '#e94560',
                        'typography_typography'     => 'custom',
                        'typography_font_size'      => [ 'size' => 12, 'unit' => 'px' ],
                        'typography_font_weight'    => '700',
                        'typography_letter_spacing' => [ 'size' => 2, 'unit' => 'px' ],
                        'typography_text_transform' => 'uppercase',
                    ],
                    'elements' => [],
                ],
                self::spacer( 16 ),
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'heading',
                    'isInner'    => false,
                    'settings'   => [
                        'title'                  => wp_kses_post( $c['h2'] ?? 'Ready to Get Started?' ),
                        'header_size'            => 'h2',
                        'align'                  => 'center',
                        'title_color'            => '#ffffff',
                        'typography_typography'  => 'custom',
                        'typography_font_size'   => [ 'size' => 48, 'unit' => 'px' ],
                        'typography_font_weight' => '800',
                        'typography_line_height' => [ 'size' => 1.2, 'unit' => 'em' ],
                    ],
                    'elements' => [],
                ],
                self::spacer( 16 ),
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'text-editor',
                    'isInner'    => false,
                    'settings'   => [
                        'editor'                 => '<p>' . esc_html( $c['subtitle'] ?? '' ) . '</p>',
                        'align'                  => 'center',
                        'text_color'             => 'rgba(255,255,255,0.72)',
                        'typography_typography'  => 'custom',
                        'typography_font_size'   => [ 'size' => 18, 'unit' => 'px' ],
                        'typography_line_height' => [ 'size' => 1.7, 'unit' => 'em' ],
                    ],
                    'elements' => [],
                ],
                self::spacer( 40 ),
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'button',
                    'isInner'    => false,
                    'settings'   => [
                        'text'                   => esc_html( $c['button_text'] ?? 'Get Started Today' ),
                        'link'                   => [ 'url' => '#', 'is_external' => false, 'nofollow' => false ],
                        'align'                  => 'center',
                        'size'                   => 'xl',
                        'background_color'       => '#e94560',
                        'button_text_color'      => '#ffffff',
                        'border_radius'          => [ 'top' => '8', 'right' => '8', 'bottom' => '8', 'left' => '8', 'unit' => 'px', 'isLinked' => true ],
                        'padding'                => [ 'top' => '18', 'bottom' => '18', 'left' => '48', 'right' => '48', 'unit' => 'px', 'isLinked' => false ],
                        'typography_typography'  => 'custom',
                        'typography_font_weight' => '700',
                        'typography_font_size'   => [ 'size' => 17, 'unit' => 'px' ],
                    ],
                    'elements' => [],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Közös építőelemek
    // ─────────────────────────────────────────────────────────────────────────

    public static function section_intro( string $label, string $h2, string $subtitle ): array {
        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => true,
            'settings' => [
                'flex_direction'       => 'column',
                'flex_align_items'     => 'center',
                'flex_justify_content' => 'center',
                'width'                => [ 'size' => 700, 'unit' => 'px' ],
                'margin'               => [ 'top' => '0', 'bottom' => '60', 'left' => 'auto', 'right' => 'auto', 'unit' => 'px', 'isLinked' => false ],
            ],
            'elements' => [
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'text-editor',
                    'isInner'    => false,
                    'settings'   => [
                        'editor'                    => '<p>' . esc_html( $label ) . '</p>',
                        'align'                     => 'center',
                        'text_color'                => '#e94560',
                        'typography_typography'     => 'custom',
                        'typography_font_size'      => [ 'size' => 12, 'unit' => 'px' ],
                        'typography_font_weight'    => '700',
                        'typography_letter_spacing' => [ 'size' => 2, 'unit' => 'px' ],
                        'typography_text_transform' => 'uppercase',
                    ],
                    'elements' => [],
                ],
                self::spacer( 12 ),
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'heading',
                    'isInner'    => false,
                    'settings'   => [
                        'title'                  => wp_kses_post( $h2 ),
                        'header_size'            => 'h2',
                        'align'                  => 'center',
                        'title_color'            => '#1a1a2e',
                        'typography_typography'  => 'custom',
                        'typography_font_size'   => [ 'size' => 44, 'unit' => 'px' ],
                        'typography_font_weight' => '700',
                        'typography_line_height' => [ 'size' => 1.2, 'unit' => 'em' ],
                    ],
                    'elements' => [],
                ],
                self::spacer( 16 ),
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'text-editor',
                    'isInner'    => false,
                    'settings'   => [
                        'editor'                 => '<p>' . esc_html( $subtitle ) . '</p>',
                        'align'                  => 'center',
                        'text_color'             => '#5a6a7a',
                        'typography_typography'  => 'custom',
                        'typography_font_size'   => [ 'size' => 17, 'unit' => 'px' ],
                        'typography_line_height' => [ 'size' => 1.7, 'unit' => 'em' ],
                    ],
                    'elements' => [],
                ],
            ],
        ];
    }

    private static function spacer( int $px ): array {
        return [
            'id'         => self::id(),
            'elType'     => 'widget',
            'widgetType' => 'spacer',
            'isInner'    => false,
            'settings'   => [ 'space' => [ 'size' => $px, 'unit' => 'px' ] ],
            'elements'   => [],
        ];
    }

    private static function hero_stat( int $number, string $suffix, string $label ): array {
        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => true,
            'settings' => [
                'flex_direction'       => 'column',
                'flex_align_items'     => 'flex-start',
                'flex_justify_content' => 'center',
            ],
            'elements' => [
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'counter',
                    'isInner'    => false,
                    'settings'   => [
                        'starting_number'        => 0,
                        'ending_number'          => $number,
                        'suffix'                 => esc_html( $suffix ),
                        'title'                  => esc_html( $label ),
                        'number_size'            => [ 'size' => 40, 'unit' => 'px' ],
                        'number_color'           => '#ffffff',
                        'title_color'            => 'rgba(255,255,255,0.65)',
                        'title_size'             => [ 'size' => 13, 'unit' => 'px' ],
                        'typography_typography'  => 'custom',
                        'typography_font_weight' => '800',
                    ],
                    'elements' => [],
                ],
            ],
        ];
    }

    private static function hero_stat_divider(): array {
        return [
            'id'         => self::id(),
            'elType'     => 'widget',
            'widgetType' => 'divider',
            'isInner'    => false,
            'settings'   => [
                'style'     => 'solid',
                'weight'    => [ 'size' => 1, 'unit' => 'px' ],
                'color'     => 'rgba(255,255,255,0.20)',
                'direction' => 'vertical',
                'height'    => [ 'size' => 48, 'unit' => 'px' ],
                'gap'       => [ 'size' => 0, 'unit' => 'px' ],
            ],
            'elements' => [],
        ];
    }

    private static function icon_box_card( string $icon, string $title, string $desc ): array {
        return [
            'id'      => self::id(),
            'elType'  => 'container',
            'isInner' => true,
            'settings' => [
                'flex_direction'             => 'column',
                'flex_align_items'           => 'flex-start',
                'padding'                    => [ 'top' => '36', 'bottom' => '36', 'left' => '32', 'right' => '32', 'unit' => 'px', 'isLinked' => false ],
                'background_background'      => 'classic',
                'background_color'           => '#ffffff',
                'border_radius'              => [ 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ],
                'box_shadow_box_shadow_type' => 'yes',
                'box_shadow_box_shadow'      => [ 'horizontal' => 0, 'vertical' => 8, 'blur' => 32, 'spread' => 0, 'color' => 'rgba(0,0,0,0.08)' ],
            ],
            'elements' => [
                [
                    'id'         => self::id(),
                    'elType'     => 'widget',
                    'widgetType' => 'icon-box',
                    'isInner'    => false,
                    'settings'   => [
                        'selected_icon'          => [ 'value' => $icon, 'library' => 'fa-solid' ],
                        'icon_size'              => [ 'size' => 44, 'unit' => 'px' ],
                        'icon_color'             => '#e94560',
                        'title_text'             => esc_html( $title ),
                        'title_size'             => 'h3',
                        'description_text'       => '<p>' . esc_html( $desc ) . '</p>',
                        'position'               => 'top',
                        'text_align'             => 'left',
                        'title_color'            => '#1a1a2e',
                        'description_color'      => '#5a6a7a',
                        'typography_typography'  => 'custom',
                        'typography_font_size'   => [ 'size' => 20, 'unit' => 'px' ],
                        'typography_font_weight' => '700',
                        'icon_padding'           => [ 'top' => '0', 'bottom' => '20', 'left' => '0', 'right' => '0', 'unit' => 'px', 'isLinked' => false ],
                    ],
                    'elements' => [],
                ],
            ],
        ];
    }

    private static function flip_box_card( string $icon, string $title, string $desc ): array {
        return [
            'id'         => self::id(),
            'elType'     => 'widget',
            'widgetType' => 'flip-box',
            'isInner'    => false,
            'settings'   => [
                'flip_effect'            => 'flip',
                'flip_direction'         => 'left',
                'front_title_text'       => esc_html( $title ),
                'front_description_text' => esc_html( $desc ),
                'front_selected_icon'    => [ 'value' => $icon, 'library' => 'fa-solid' ],
                'front_icon_size'        => [ 'size' => 48, 'unit' => 'px' ],
                'front_icon_color'       => '#e94560',
                'front_background_color' => '#ffffff',
                'front_title_color'      => '#1a1a2e',
                'front_description_color' => '#5a6a7a',
                'front_border_radius'    => [ 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ],
                'back_title_text'        => esc_html( $title ),
                'back_description_text'  => esc_html( $desc ),
                'back_button_text'       => 'Learn More',
                'back_button_url'        => [ 'url' => '#', 'is_external' => false ],
                'back_background_color'  => '#e94560',
                'back_title_color'       => '#ffffff',
                'back_description_color' => 'rgba(255,255,255,0.85)',
                'back_border_radius'     => [ 'top' => '16', 'right' => '16', 'bottom' => '16', 'left' => '16', 'unit' => 'px', 'isLinked' => true ],
            ],
            'elements' => [],
        ];
    }
}
