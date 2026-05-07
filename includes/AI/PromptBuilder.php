<?php
/**
 * Prompt összeállítása – Claude-alapú, template-referenciákkal.
 * Az AI kap valódi Elementor JSON példákat inspirációként, de maga állítja össze az oldalt.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    private function has_pro(): bool {
        return defined( 'ELEMENTOR_PRO_VERSION' );
    }

    /**
     * Üzeneteket épít az AI számára (create és modify módhoz egyaránt).
     *
     * @param  string $mode           'create' | 'modify'
     * @param  string $user_prompt
     * @param  string $current_json
     * @param  array  $global_styles
     * @return array<int, array{role:string, content:string}>
     */
    public function build(
        string $mode,
        string $user_prompt,
        string $current_json,
        array  $global_styles
    ): array {
        return [
            [ 'role' => 'system', 'content' => $this->get_system_prompt( $mode, $global_styles ) ],
            [ 'role' => 'user',   'content' => $this->get_user_message( $mode, $user_prompt, $current_json ) ],
        ];
    }

    // ── System Prompt ─────────────────────────────────────────────────────────

    private function get_system_prompt( string $mode, array $global_styles ): string {
        $colors_str     = $this->format_colors( $global_styles['colors'] ?? [] );
        $typography_str = $this->format_typography( $global_styles['typography'] ?? [] );
        $pro_note       = $this->has_pro()
            ? 'Elementor Pro IS active — you may use: flip-box, animated-headline, price-table, countdown.'
            : 'Elementor Pro is NOT active — use only Free widgets (heading, text-editor, button, image, icon-box, icon-list, counter, testimonial, star-rating, accordion, tabs, progress, divider, spacer, social-icons).';

        $examples = $this->get_reference_examples();

        return <<<PROMPT
You are a senior Elementor developer and UX designer generating PREMIUM, AGENCY-QUALITY Elementor pages.
Your output must match the quality of top Envato Elements template kits (Lumira, Avena, Financi, etc.).
{$pro_note}

## OUTPUT FORMAT
Respond ONLY with valid JSON: {"elementor_data":[...root container elements...]}
No markdown fences, no explanation, no extra keys. Pure JSON only.

## ELEMENTOR JSON STRUCTURE RULES
- Root elements: "elType":"container", "isInner":false
- Nested containers (columns, rows): "elType":"container", "isInner":true
- Widgets: "elType":"widget", valid "widgetType", "elements":[]
- Every element MUST have "id": exactly 7 lowercase alphanumeric chars, ALL unique across the entire output
- flex_direction: "row" for horizontal layouts, "column" for vertical stacks
- content_width: "boxed" constrains content to max-width; "full" goes edge to edge

## DESIGN SYSTEM
Spacing scale (use only): 8 | 16 | 24 | 32 | 40 | 64 | 80 | 100 | 120 px
Section padding: top/bottom 100px standard, 120px for hero/CTA
Colors: dark #1a1a2e | accent #e94560 | muted #5a6a7a | light-bg #f5f6fa | card-bg #ffffff
Typography: h1 62px/800 | h2 44px/700 | h3 22px/700 | body 17px/1.7lh | label 12px/700/uppercase/2px-letter-spacing
White-on-dark: headings #ffffff, body rgba(255,255,255,0.72)
Section label (accent tag above heading): 12px, 700w, uppercase, letter-spacing 2px, color #e94560
Dark gradient: background_color #0a0e27 → background_color_b #1a2a5e, gradient_angle 135deg
Card style: white bg, border_radius 16px, box_shadow horizontal:0 vertical:8 blur:32 spread:0 rgba(0,0,0,0.08)

$colors_str
$typography_str

## SECTION STRUCTURE PATTERN
Every content section follows this pattern:
1. Root container (outer, isInner:false) — sets bg color/gradient and padding
2. Optional inner container for max-width (boxed, isInner:true)
3. Section intro block (centered label + h2 + subtitle) before every section
4. Content rows/grids (isInner:true, flex_direction:row)
5. Card containers (isInner:true) or widget elements

## IMAGE RULES — CRITICAL
- NEVER set background_image on any container — use solid color or gradient ONLY
- ALL images use the "image" widget with picsum.photos URL
- Format: https://picsum.photos/seed/{descriptive-keyword}/{width}/{height}
- Use DIFFERENT seeds for each image, descriptive and specific to the content
- Hero image: 750x620 | Section images: 700x560 | Avatars: 120x120

## MANDATORY PAGE STRUCTURE (for full page, in this order)
1. HERO — dark gradient bg, min-height 100vh, split layout (text left 52%, image right 44%)
2. FEATURES/SERVICES — light bg #f5f6fa, 3 icon-box cards in a row
3. STATS BAR — accent gradient, 4 counter widgets in a row
4. ABOUT/WHY — white bg, 2 columns (text+icon-list left, image right)
5. PROCESS/HOW IT WORKS — #f5f6fa, 3 numbered step cards
6. TESTIMONIALS — white, 3 testimonial cards with star ratings
7. FAQ — #f5f6fa, 2 columns (intro+button left, accordion right)
8. CTA — dark gradient, centered column, full-width

## HARD RULES
1. Write REAL copy in the SAME LANGUAGE as the user prompt — zero placeholders, zero Lorem ipsum
2. NEVER duplicate any element ID in the entire output
3. NEVER use background_image on containers
4. ALL images: image widget + picsum.photos
5. counter widget: ending_number must be an INTEGER (not a string)
6. icon-list: each item needs _id, text, selected_icon with value + library
7. Every section has a "section intro" block (label + h2 + subtitle) before content
8. Hero h1: max 8 words, punchy and specific to the industry
9. Minimum 7 sections for a full page
10. Last section is always the dark CTA

---

## REFERENCE EXAMPLES
Study these Elementor JSON sections for exact structure, settings keys, and premium patterns.
You are FREE to adapt, modify, recombine, and improve upon these — they are INSPIRATION, not templates to copy.

{$examples}

---
PROMPT;
    }

    // ── Reference examples from TemplateLibrary ───────────────────────────────

    private function get_reference_examples(): string {
        TemplateLibrary::reset_ids();

        $hero = TemplateLibrary::hero_split( [
            'label'          => '// PROFESSIONAL EXCELLENCE',
            'h1'             => 'Premium Services That Deliver Results',
            'subtitle'       => 'Over a decade of experience helping businesses grow with proven strategies.',
            'button_text'    => 'Get Started Today',
            'stat_1_number'  => 1500, 'stat_1_suffix' => '+', 'stat_1_label' => 'Happy Clients',
            'stat_2_number'  => 10,   'stat_2_suffix' => '+', 'stat_2_label' => 'Years Experience',
            'stat_3_number'  => 98,   'stat_3_suffix' => '%', 'stat_3_label' => 'Satisfaction',
            'image_seed'     => 'business-professional-team',
        ] );

        $features = TemplateLibrary::features_3( [
            'section_label'   => '// OUR SERVICES',
            'section_h2'      => 'Everything You Need to Succeed',
            'section_subtitle' => 'Comprehensive solutions designed to meet your unique goals and challenges.',
            'card_1_icon'     => 'fas fa-chart-line',
            'card_1_title'    => 'Strategic Planning',
            'card_1_desc'     => 'Data-driven strategies that align with your goals and accelerate growth.',
            'card_2_icon'     => 'fas fa-handshake',
            'card_2_title'    => 'Expert Consultation',
            'card_2_desc'     => 'Personalized guidance from seasoned professionals with real-world experience.',
            'card_3_icon'     => 'fas fa-award',
            'card_3_title'    => 'Proven Results',
            'card_3_desc'     => 'A track record of measurable outcomes and satisfied clients across industries.',
        ], $this->has_pro() );

        $cta = TemplateLibrary::cta_dark( [
            'label'       => '// START TODAY',
            'h2'          => 'Ready to Take the Next Step?',
            'subtitle'    => 'Join thousands of satisfied clients and transform your results starting today.',
            'button_text' => 'Book a Free Consultation',
        ] );

        $ex = [];
        $ex[] = '### EXAMPLE: HERO SPLIT SECTION' . "\n" . json_encode( $hero, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $ex[] = '### EXAMPLE: FEATURES SECTION' . "\n" . json_encode( $features, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
        $ex[] = '### EXAMPLE: CTA DARK SECTION' . "\n" . json_encode( $cta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

        return implode( "\n\n", $ex );
    }

    // ── User Message ──────────────────────────────────────────────────────────

    private function get_user_message( string $mode, string $user_prompt, string $current_json ): string {
        if ( 'create' === $mode ) {
            return $this->create_message( $user_prompt );
        }
        return $this->modify_message( $user_prompt, $current_json );
    }

    private function create_message( string $user_prompt ): string {
        $pro_note = $this->has_pro()
            ? 'Elementor Pro is active — consider using flip-box for feature cards and animated-headline in the hero.'
            : 'Elementor Pro is NOT active — use icon-box, heading, and other Free widgets only.';

        return <<<MSG
Generate a PREMIUM full-page Elementor layout for: "{$user_prompt}"

{$pro_note}

Requirements:
- Follow the mandatory page structure (hero → features → stats → about → process → testimonials → faq → cta)
- Adapt all content, icons, images, and copy to the specific industry and request
- Write real, professional copy in the SAME LANGUAGE as the request above
- Use the reference examples as inspiration for structure and settings — feel free to improve on them
- Generate minimum 7 sections, always ending with the dark CTA
- Every section has a centered section intro (accent label + h2 + subtitle) before the main content
- Hero: split layout (text left, image right), dark gradient, min-height 100vh
- All IDs: exactly 7 chars, unique, lowercase alphanumeric
- No background_image on containers, all images in image widgets with picsum.photos

Respond ONLY with the JSON object {"elementor_data":[...]}.
MSG;
    }

    private function modify_message( string $user_prompt, string $current_json ): string {
        $json_preview = $this->maybe_truncate_json( $current_json );

        return <<<MSG
Modify the following existing Elementor page JSON according to the instruction.

Current JSON:
{$json_preview}

Instruction: "{$user_prompt}"

Rules:
- Apply ONLY the requested changes — preserve all other elements exactly as-is
- Keep all existing element IDs; generate new 7-char unique IDs for any new elements
- New sections must follow the premium design patterns shown in the reference examples
- No background_image on containers (solid color or gradient only)
- All images in image widgets with picsum.photos URLs
- Return the COMPLETE modified page JSON

Respond ONLY with: {"elementor_data":[...complete modified page...]}
MSG;
    }

    // ── Formázók ─────────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return '### Site Colors: use defaults — #1a1a2e (dark), #e94560 (accent), #5a6a7a (muted), #f5f6fa (light)';
        }
        $lines = [ '### Site Global Colors (use these exactly)' ];
        foreach ( $colors as $c ) {
            $lines[] = '- ' . ( $c['label'] ?? 'Color' ) . ': ' . ( $c['value'] ?? '' );
        }
        return implode( "\n", $lines );
    }

    private function format_typography( array $typography ): string {
        if ( empty( $typography ) ) {
            return '### Typography: system sans-serif, headings 700–800w, body 17px 1.7lh';
        }
        $lines = [ '### Site Global Typography' ];
        foreach ( $typography as $t ) {
            $parts = [ '- ' . ( $t['label'] ?? 'Type' ) ];
            if ( ! empty( $t['family'] ) ) $parts[] = 'Font: ' . $t['family'];
            if ( ! empty( $t['size'] ) )   $parts[] = 'Size: ' . $t['size'] . 'px';
            if ( ! empty( $t['weight'] ) ) $parts[] = 'Weight: ' . $t['weight'];
            $lines[] = implode( ' | ', $parts );
        }
        return implode( "\n", $lines );
    }

    private function maybe_truncate_json( string $json, int $max_chars = 12000 ): string {
        if ( mb_strlen( $json ) <= $max_chars ) {
            return $json;
        }
        $half = (int) ( $max_chars / 2 );
        return mb_substr( $json, 0, $half ) . "\n... [TRUNCATED] ...\n" . mb_substr( $json, -$half );
    }
}
