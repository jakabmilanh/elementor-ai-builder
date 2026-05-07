<?php
/**
 * Prompt összeállítása.
 * Create mód: kétlépéses template-alapú megközelítés (page plan → TemplateAssembler).
 * Modify mód: közvetlen Elementor JSON módosítás.
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
     * Page plan prompt – CREATE módhoz.
     * Az AI csak tartalmat generál (kompakt JSON), nem Elementor struktúrát.
     *
     * @return array<int, array{role:string, content:string}>
     */
    public function build_plan(
        string $user_prompt,
        array  $global_styles
    ): array {
        return [
            [ 'role' => 'system', 'content' => $this->get_plan_system_prompt( $global_styles ) ],
            [ 'role' => 'user',   'content' => $this->get_plan_user_message( $user_prompt ) ],
        ];
    }

    /**
     * Modify prompt – MODIFY módhoz (közvetlen JSON módosítás).
     *
     * @return array<int, array{role:string, content:string}>
     */
    public function build_modify(
        string $user_prompt,
        string $current_json,
        array  $global_styles
    ): array {
        return [
            [ 'role' => 'system', 'content' => $this->get_modify_system_prompt( $global_styles ) ],
            [ 'role' => 'user',   'content' => $this->get_modify_user_message( $user_prompt, $current_json ) ],
        ];
    }

    // ── Page Plan System Prompt ───────────────────────────────────────────────

    private function get_plan_system_prompt( array $global_styles ): string {
        $colors_str = $this->format_colors( $global_styles['colors'] ?? [] );
        $pro_note   = $this->has_pro()
            ? 'Elementor Pro IS active.'
            : 'Elementor Pro is NOT active.';

        return <<<PROMPT
You are an expert website copywriter and page architect. Your job is to create a PREMIUM page plan as compact JSON.

You do NOT generate Elementor JSON. You generate a page plan that specifies:
1. Which section types to use (from the available list below)
2. The exact content for each section (headlines, copy, labels, etc.)

The page plan will be converted to premium Elementor JSON automatically by our system.
{$pro_note}

## AVAILABLE SECTION TYPES AND THEIR REQUIRED FIELDS

### hero-split
{"type":"hero-split","label":"// UPPERCASE TAGLINE","h1":"Main headline (max 8 words, punchy)","subtitle":"Supporting sentence (max 20 words)","button_text":"CTA text","stat_1_number":1500,"stat_1_suffix":"+","stat_1_label":"Happy Clients","stat_2_number":10,"stat_2_suffix":"+","stat_2_label":"Years Experience","stat_3_number":98,"stat_3_suffix":"%","stat_3_label":"Satisfaction Rate","image_seed":"relevant-keyword-for-picsum"}

### features-3
{"type":"features-3","section_label":"// UPPERCASE LABEL","section_h2":"Section Heading","section_subtitle":"One sentence supporting text","card_1_icon":"fas fa-icon-name","card_1_title":"Feature Title","card_1_desc":"2-sentence description","card_2_icon":"fas fa-icon-name","card_2_title":"Feature Title","card_2_desc":"2-sentence description","card_3_icon":"fas fa-icon-name","card_3_title":"Feature Title","card_3_desc":"2-sentence description"}

### stats-4
{"type":"stats-4","stat_1_number":1500,"stat_1_suffix":"+","stat_1_label":"Label","stat_2_number":20,"stat_2_suffix":"+","stat_2_label":"Label","stat_3_number":98,"stat_3_suffix":"%","stat_3_label":"Label","stat_4_number":50,"stat_4_suffix":"+","stat_4_label":"Label"}

### about-2col
{"type":"about-2col","section_label":"// UPPERCASE LABEL","section_h2":"Heading","section_subtitle":"Supporting sentence","benefit_1":"Benefit text","benefit_2":"Benefit text","benefit_3":"Benefit text","benefit_4":"Benefit text","button_text":"CTA","image_seed":"relevant-keyword"}

### process-3steps
{"type":"process-3steps","section_label":"// UPPERCASE LABEL","section_h2":"Heading","section_subtitle":"Supporting sentence","step_1_title":"Step title","step_1_desc":"Step description","step_2_title":"Step title","step_2_desc":"Step description","step_3_title":"Step title","step_3_desc":"Step description"}

### testimonials-3
{"type":"testimonials-3","section_label":"// UPPERCASE LABEL","section_h2":"Heading","section_subtitle":"Supporting sentence","test_1_text":"Realistic testimonial quote (2-3 sentences)","test_1_name":"Full Name","test_1_job":"Job Title","test_1_seed":"person-seed","test_2_text":"...","test_2_name":"...","test_2_job":"...","test_2_seed":"person2-seed","test_3_text":"...","test_3_name":"...","test_3_job":"...","test_3_seed":"person3-seed"}

### faq
{"type":"faq","section_label":"// UPPERCASE LABEL","section_h2":"Heading","section_subtitle":"Supporting sentence","button_text":"CTA","q1":"Question?","a1":"Answer.","q2":"Question?","a2":"Answer.","q3":"Question?","a3":"Answer.","q4":"Question?","a4":"Answer."}

### cta-dark
{"type":"cta-dark","label":"// UPPERCASE LABEL","h2":"Compelling CTA headline","subtitle":"Supporting sentence","button_text":"CTA button text"}

## RULES
1. Always include hero-split as first section and cta-dark as last section.
2. Full page: include 6–8 sections in logical order.
3. Write ALL copy in the SAME LANGUAGE as the user's prompt.
4. Write REAL, PROFESSIONAL copy — no placeholders, no Lorem ipsum.
5. Match icons to industry context (Font Awesome class names: fas fa-tooth for dental, fas fa-chart-line for business, etc.)
6. stat_*_number must be an INTEGER (no quotes around numbers).
7. image_seed: use descriptive English keywords that describe the visual content.

$colors_str

## OUTPUT FORMAT
Respond ONLY with this JSON structure:
{"sections":[...array of section objects in order...]}
No explanation, no markdown. Pure JSON only.
PROMPT;
    }

    private function get_plan_user_message( string $user_prompt ): string {
        return 'Create a premium full-page plan for: "' . $user_prompt . '"' . "\n\nInclude hero-split first, cta-dark last, and 4–6 relevant sections in between. Write all copy in the same language as the request above.";
    }

    // ── Modify System Prompt ──────────────────────────────────────────────────

    private function get_modify_system_prompt( array $global_styles ): string {
        $colors_str     = $this->format_colors( $global_styles['colors'] ?? [] );
        $typography_str = $this->format_typography( $global_styles['typography'] ?? [] );

        return <<<PROMPT
You are a senior Elementor developer modifying an existing premium page JSON.

## OUTPUT FORMAT
Respond ONLY with: {"elementor_data":[...complete modified page...]}
No markdown. Pure JSON only.

## ELEMENTOR JSON RULES
- Root elements: elType=container, isInner=false
- Nested containers: elType=container, isInner=true
- Widgets: elType=widget + valid widgetType + elements=[]
- IDs: exactly 7 chars, unique, lowercase alphanumeric
- New elements get new 7-char IDs; existing IDs are preserved

## DESIGN SYSTEM
Colors: dark #1a1a2e | accent #e94560 | muted #5a6a7a | light-bg #f5f6fa
Typography: h1 62px 800w | h2 44px 700w | h3 22px 700w | body 17px 1.7lh
Spacing: 8/16/24/40/64/100px
Images: image widget only, picsum.photos/seed/{keyword}/{w}/{h}

$colors_str
$typography_str

## HARD RULES
1. Apply ONLY the requested changes — preserve all other elements exactly
2. NEVER use background_image on containers (solid or gradient only)
3. ALL images must be image widgets with picsum.photos URLs
4. Return the COMPLETE modified page JSON
PROMPT;
    }

    private function get_modify_user_message( string $user_prompt, string $current_json ): string {
        $json_preview = $this->maybe_truncate_json( $current_json );
        return "Current Elementor JSON:\n{$json_preview}\n\nInstruction: \"{$user_prompt}\"\n\nRespond ONLY with the complete modified JSON object.";
    }

    // ── Formázók ─────────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return '### Colors: use #1a1a2e (dark), #e94560 (accent), #5a6a7a (muted), #f5f6fa (light bg)';
        }
        $lines = [ '### Global Colors' ];
        foreach ( $colors as $c ) {
            $lines[] = '- ' . ( $c['label'] ?? 'Color' ) . ': ' . ( $c['value'] ?? '' );
        }
        return implode( "\n", $lines );
    }

    private function format_typography( array $typography ): string {
        if ( empty( $typography ) ) {
            return '### Typography: system sans-serif, headings 700–800w, body 17px 1.7lh';
        }
        $lines = [ '### Global Typography' ];
        foreach ( $typography as $t ) {
            $parts = [ '- ' . ( $t['label'] ?? 'Type' ) ];
            if ( ! empty( $t['family'] ) ) $parts[] = 'Font: ' . $t['family'];
            if ( ! empty( $t['size'] ) )   $parts[] = 'Size: ' . $t['size'] . 'px';
            if ( ! empty( $t['weight'] ) ) $parts[] = 'Weight: ' . $t['weight'];
            $lines[] = implode( ' | ', $parts );
        }
        return implode( "\n", $lines );
    }

    private function maybe_truncate_json( string $json, int $max_chars = 10000 ): string {
        if ( mb_strlen( $json ) <= $max_chars ) {
            return $json;
        }
        $half = (int) ( $max_chars / 2 );
        return mb_substr( $json, 0, $half ) . "\n... [TRUNCATED] ...\n" . mb_substr( $json, -$half );
    }
}
