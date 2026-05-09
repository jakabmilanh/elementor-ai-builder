<?php
/**
 * Prompt összeállítása – szabad formátumú AI generálás.
 *
 * Nincs predefinált template – az AI teljesen szabadon tervezi és
 * generálja az Elementor szekciók JSON-ját, mint egy standalone weboldalt.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    // ── Oldal-terv (szabad, nem kötött template típusokhoz) ──────────────────

    public function build_plan( string $user_prompt ): array {
        $system = <<<'SYS'
You are a senior UI/UX designer and conversion specialist planning a premium landing page.

Your job: Design the perfect page structure for this specific business. Think like a top web agency designer creating a $10,000 website. Every section should serve a purpose in the visitor's journey from "who is this?" to "I need to contact them NOW."

For each section provide:
- "type": unique snake_case identifier (e.g. "hero", "services_cards", "team_intro", "booking_strip", "testimonials_grid", "trust_badges", "faq_accordion", "final_cta")
- "purpose": what this section achieves in the conversion funnel (1 sentence)
- "layout": specific layout description (e.g. "full-height split: 55% text left, 45% image right, dark gradient bg", "3-column white cards on light gray bg", "full-width accent color, 4 counters in a row", "centered text + large image, white bg")
- "content": key content elements (e.g. "h1 headline max 8 words, subtitle 2 sentences, 2 CTAs, 3 stats with icons")

Design rules:
- 6–8 sections total
- FIRST section must be a strong hero (type containing "hero")
- LAST section must be a conversion CTA (type containing "cta")
- Choose sections logical for THIS SPECIFIC business — no generic generic pages
- Think about the visitor journey: first impression → services → trust → proof → action
- Be creative: you can add unique sections like a "before/after", "video testimonial", "team", "location_map", "price_calculator", "process_timeline" etc.
- AVOID just listing generic section names — make them specific to the business

Return ONLY a valid JSON array, nothing else:
[{"type":"...","purpose":"...","layout":"...","content":"..."},...]
SYS;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user',   'content' => "Design the optimal landing page structure for this business: \"{$user_prompt}\"\n\nReturn ONLY the JSON array, nothing else." ],
        ];
    }

    // ── Szekció generálás (teljes design szabadság) ───────────────────────────

    public function build_section(
        array  $section_meta,
        string $user_prompt,
        array  $global_styles,
        bool   $has_pro
    ): array {
        $section_type    = $section_meta['type']    ?? 'section';
        $section_purpose = $section_meta['purpose'] ?? '';
        $section_layout  = $section_meta['layout']  ?? '';
        $section_content = $section_meta['content'] ?? '';

        $colors_str  = $this->format_colors( $global_styles['colors'] ?? [] );
        $pro_note    = $has_pro
            ? 'Elementor PRO is active — you can use ALL widgets: flip-box, animated-headline, price-table, nav-menu, posts, forms, etc.'
            : 'Elementor FREE only — available widgets: text-editor, heading, image, button, icon-box, icon-list, counter, testimonial, accordion, divider, spacer, star-rating, image-box, video, google_maps, social-icons, progress, tabs, toggle.';

        $elementor_ref = $this->get_elementor_reference();

        $system = <<<SYS
You are a senior Elementor developer building a REAL production website for a paying client. Your output goes directly onto a live website — it must be visually stunning, conversion-focused, and professional.

## OUTPUT FORMAT — CRITICAL
Return ONLY a single root JSON container object: {"id":"...","elType":"container","isInner":false,...}
- NO array wrapper, NO {"elementor_data":...} wrapper
- NO markdown fences, NO explanations
- Pure JSON starting with { and ending with }

## ELEMENTOR JSON STRUCTURE
{$elementor_ref}

## DESIGN FREEDOM
You have FULL creative freedom. Design this like a senior designer at a top agency:
- Choose any layout that fits the section's purpose (split, asymmetric, full-bleed, cards, grid, etc.)
- Use visual hierarchy: make the most important thing biggest and most prominent
- Use generous whitespace — it signals quality
- Create visual interest: varied backgrounds, accent colors, subtle shadows
- Every element should earn its place — no filler content

## DESIGN STANDARDS
- Section padding: 80–120px top/bottom (hero: can be 0 if full-height; CTA: 100–140px)
- Inner container max-width: use content_width "boxed" for readable content areas (max ~1200px)
- Full-bleed elements: use content_width "full" for background-only containers
- Cards: white background, border-radius 12–20px, box-shadow: {horizontal:0,vertical:8,blur:32,spread:0,color:"rgba(0,0,0,0.08)"}
- Column gaps: 24–48px between equal columns; 48–80px for split layouts
- Heading sizes: h1 52–72px weight 800, h2 36–52px weight 700, h3 20–26px weight 700
- Body text: 15–17px, line-height {"size":1.7,"unit":"em"}
- Buttons: padding top/bottom 14–18px, left/right 32–48px, border-radius 6–10px, font-weight 700

## COLOR SYSTEM
{$colors_str}

## ⚠️ COLOR CONTRAST — ABSOLUTE RULES — NEVER VIOLATE
Violating these creates broken, unusable designs. Check EVERY text color against its parent background.

RULE 1 — DARK BACKGROUND → MUST use LIGHT text:
  Dark backgrounds: #0a0e27, #1a1a2e, #1a2a5e, #0f3460, #12122a, #111111, #222222, #333333,
  or ANY color where the average of (R+G+B)/3 < 80
  → text_color, color, typography_color MUST be: #ffffff OR rgba(255,255,255,0.85) or higher
  → For secondary/muted text on dark bg: rgba(255,255,255,0.72) — still light!
  → FORBIDDEN dark text on dark bg: #1a1a2e, #5a6a7a, #333, #000, or similar dark colors

RULE 2 — LIGHT/WHITE BACKGROUND → MUST use DARK text:
  Light backgrounds: #ffffff, #f5f6fa, #f0f0f0, #fafafa, #eeeeee,
  or ANY color where the average of (R+G+B)/3 > 180
  → text_color MUST be: #1a1a2e (headings), #5a6a7a (body/muted), #333333
  → FORBIDDEN light text on light bg: #ffffff, rgba(255,255,255,...), #f5f6fa as text

RULE 3 — ACCENT BACKGROUND → white text:
  Accent: #e94560, #c0233e, any saturated/vivid color (high saturation)
  → Text MUST be: #ffffff

RULE 4 — GRADIENT BACKGROUNDS → use the DARKEST color to determine text:
  Dark gradient (#0a0e27 → #1a2a5e): white text required
  Accent gradient (#e94560 → #c0233e): white text required

RULE 5 — WHITE CARDS inside ANY section:
  Cards always have white (#ffffff) background → text must be dark (#1a1a2e, #5a6a7a)
  Even if the card is inside a dark section — the card itself is white → dark text inside

RULE 6 — ICONS must also contrast with their container background:
  Icon on dark bg → icon_color: #ffffff or accent #e94560
  Icon on white/light bg → icon_color: accent #e94560 or dark #1a1a2e

## IMAGE RULES
- NEVER set background_image on containers (use solid color or gradient backgrounds only)
- ALL images via image widget with URL: https://loremflickr.com/{width}/{height}/{kw1},{kw2},{kw3}
- Use industry-specific keywords from the business description (e.g., dental,dentist,teeth for a dental page)
- Portrait/avatar images: 120×120 with "portrait,professional,person"
- Section images: 700×560 or 800×600
- Hero images: 750×620 or 900×700
- ALWAYS set alt text to a meaningful description

## WIDGET IDs
- EXACTLY 7 characters, unique, lowercase alphanumeric
- Random-looking (not sequential): "a3f9k2m", "x7p2r4s", "b5n8q1j"
- Every container and widget needs a unique ID — no duplicates

## {$pro_note}
SYS;

        $user_msg = <<<MSG
Build this Elementor section for the website: "{$user_prompt}"

SECTION BRIEF:
- Section type: {$section_type}
- Purpose: {$section_purpose}
- Layout: {$section_layout}
- Content to include: {$section_content}

REQUIREMENTS:
1. Write ALL content in the SAME LANGUAGE as the business description ("{$user_prompt}")
2. Every piece of text must be specific to this exact business — NO generic placeholder text like "Lorem ipsum" or "Your Company Name"
3. Apply ALL color contrast rules — verify every text element against its background before finalizing
4. The layout hint is a suggestion — improve it if you see a better approach for this section type
5. Make it look like a world-class website

Return ONLY the single root container JSON object. Start with { and end with }.
MSG;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user',   'content' => $user_msg ],
        ];
    }

    // ── Módosítás ─────────────────────────────────────────────────────────────

    public function build_modify(
        string $user_prompt,
        string $current_json,
        array  $global_styles
    ): array {
        $colors_str   = $this->format_colors( $global_styles['colors'] ?? [] );
        $json_preview = $this->maybe_truncate_json( $current_json );

        $system = <<<SYS
You are a senior Elementor developer modifying an existing page.

OUTPUT: {"elementor_data":[...complete modified page...]}
No markdown fences. Pure JSON only.

RULES:
- Apply ONLY the requested changes, preserve everything else exactly
- Keep all existing element IDs; new elements get unique 7-char random-looking IDs
- NEVER use background_image on containers (solid color or gradient only)
- ALL images: image widget with https://loremflickr.com/{w}/{h}/{keywords} URL
- Apply color contrast: dark bg → white/light text; light/white bg → dark text
- Return the COMPLETE modified JSON, including unchanged sections

{$colors_str}
SYS;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user', 'content' => "Current Elementor JSON:\n{$json_preview}\n\nModification instruction: \"{$user_prompt}\"\n\nReturn ONLY the complete modified JSON." ],
        ];
    }

    // ── Elementor widget referencia ───────────────────────────────────────────

    private function get_elementor_reference(): string {
        return <<<'REF'
### Root section container (elType: "container", isInner: false)
{
  "id": "a3f9k2m",
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width": "full",           // "full" = 100% wide; "boxed" = max-width centered (~1200px)
    "flex_direction": "column",        // "row" or "column"
    "flex_align_items": "center",      // "flex-start" | "center" | "flex-end" | "stretch"
    "flex_justify_content": "center",  // "flex-start" | "center" | "flex-end" | "space-between" | "space-around"
    "gap": {"size": 0, "unit": "px"},
    "padding": {"top":"100","bottom":"100","left":"0","right":"0","unit":"px","isLinked":false},
    "background_color": "#f5f6fa",
    "min_height": {"size": 100, "unit": "vh"}
  },
  "elements": []
}

### Inner column/row container (elType: "container", isInner: true)
{
  "id": "b5n8q1j",
  "elType": "container",
  "isInner": true,
  "settings": {
    "content_width": "boxed",
    "flex_direction": "row",
    "width": {"size": 50, "unit": "%"},
    "gap": {"size": 32, "unit": "px"},
    "padding": {"top":"36","bottom":"36","left":"36","right":"36","unit":"px","isLinked":false},
    "background_color": "#ffffff",
    "border_radius": {"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":false},
    "box_shadow_box_shadow_type": "yes",
    "box_shadow_box_shadow": {"horizontal":0,"vertical":8,"blur":32,"spread":0,"color":"rgba(0,0,0,0.08)","position":""}
  },
  "elements": []
}

### GRADIENT BACKGROUND on any container:
"background_background": "gradient",
"background_color": "#0a0e27",
"background_color_b": "#1a2a5e",
"background_gradient_angle": {"size": 135, "unit": "deg"},
"background_gradient_type": "linear"

### AVAILABLE WIDGETS (elType: "widget", elements: [])

heading:
{"id":"x7p2r4s","elType":"widget","widgetType":"heading","settings":{"title":"Your Headline","header_size":"h2","align":"left","color":"#1a1a2e","typography_font_size":{"size":44,"unit":"px"},"typography_font_weight":"700","typography_line_height":{"size":1.15,"unit":"em"}},"elements":[]}

text-editor:
{"id":"c4m7p9x","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>Your paragraph text here.</p>","align":"left","color":"#5a6a7a","typography_font_size":{"size":17,"unit":"px"},"typography_line_height":{"size":1.7,"unit":"em"}},"elements":[]}

button:
{"id":"d2k5r8n","elType":"widget","widgetType":"button","settings":{"text":"Click Here","link":{"url":"#","is_external":false},"align":"left","background_color":"#e94560","button_text_color":"#ffffff","border_radius":{"top":"8","right":"8","bottom":"8","left":"8","unit":"px","isLinked":true},"typography_font_size":{"size":15,"unit":"px"},"typography_font_weight":"700","padding":{"top":"16","bottom":"16","left":"40","right":"40","unit":"px","isLinked":false}},"elements":[]}

image:
{"id":"e8j3f6m","elType":"widget","widgetType":"image","settings":{"image":{"url":"https://loremflickr.com/700/560/keyword1,keyword2","alt":"Description"},"width":{"size":100,"unit":"%"},"align":"center","border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true}},"elements":[]}

icon-box:
{"id":"f1n4q7k","elType":"widget","widgetType":"icon-box","settings":{"selected_icon":{"value":"fas fa-star","library":"fa-solid"},"title_text":"Feature Title","description_text":"Feature description text goes here.","icon_size":{"size":44,"unit":"px"},"icon_color":"#e94560","title_size":"h3","title_color":"#1a1a2e","description_color":"#5a6a7a","title_typography_font_size":{"size":20,"unit":"px"},"title_typography_font_weight":"700"},"elements":[]}

icon-list:
{"id":"g6p9s2w","elType":"widget","widgetType":"icon-list","settings":{"icon_list":[{"text":"Benefit one","selected_icon":{"value":"fas fa-check-circle","library":"fa-solid"}},{"text":"Benefit two","selected_icon":{"value":"fas fa-check-circle","library":"fa-solid"}}],"icon_color":"#e94560","text_color":"#5a6a7a","icon_size":{"size":18,"unit":"px"},"space_between":{"size":12,"unit":"px"},"typography_font_size":{"size":16,"unit":"px"}},"elements":[]}

counter:
{"id":"h3t6v9y","elType":"widget","widgetType":"counter","settings":{"starting_number":0,"ending_number":1500,"suffix":"+","title":"Happy Clients","number_color":"#ffffff","number_size":{"size":52,"unit":"px"},"number_typography_font_weight":"800","title_color":"rgba(255,255,255,0.72)","title_size":{"size":14,"unit":"px"}},"elements":[]}

testimonial:
{"id":"i5w8z1a","elType":"widget","widgetType":"testimonial","settings":{"content":"Client testimonial text here. Specific and genuine sounding.","name":"Client Name","job":"Job Title","image":{"url":"https://loremflickr.com/120/120/portrait,professional,person","alt":"Client photo"},"content_color":"#5a6a7a","name_color":"#1a1a2e","job_color":"#e94560","typography_font_size":{"size":15,"unit":"px"},"typography_line_height":{"size":1.65,"unit":"em"}},"elements":[]}

star-rating:
{"id":"j2x5b8e","elType":"widget","widgetType":"star-rating","settings":{"rating":5,"star_color":"#f5a623","unmarked_star_color":"rgba(245,166,35,0.25)","icon_size":{"size":18,"unit":"px"},"align":"left"},"elements":[]}

accordion:
{"id":"k7c1f4h","elType":"widget","widgetType":"accordion","settings":{"tabs":[{"tab_title":"Question one?","tab_content":"<p>Answer to question one. Be helpful and specific.</p>"},{"tab_title":"Question two?","tab_content":"<p>Answer to question two.</p>"}],"title_color":"#1a1a2e","icon_color":"#e94560","active_color":"#e94560","content_color":"#5a6a7a","item_spacing":{"size":4,"unit":"px"},"typography_font_size":{"size":16,"unit":"px"},"typography_font_weight":"600"},"elements":[]}

divider:
{"id":"l4g7j0m","elType":"widget","widgetType":"divider","settings":{"color":"rgba(0,0,0,0.08)","weight":{"size":1,"unit":"px"},"gap":{"size":0,"unit":"px"}},"elements":[]}

spacer:
{"id":"m9h2k5p","elType":"widget","widgetType":"spacer","settings":{"space":{"size":32,"unit":"px"}},"elements":[]}
REF;
    }

    // ── Segédmetódusok ────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return implode( "\n", [
                '## Site Color Palette',
                '- Dark/Background: #1a1a2e (headings, dark sections)',
                '- Accent/Primary: #e94560 (buttons, icons, highlights, labels)',
                '- Muted/Body text: #5a6a7a (paragraphs, descriptions on white bg)',
                '- Light section bg: #f5f6fa',
                '- White: #ffffff (cards, light sections)',
                '- Dark gradient: from #0a0e27 to #1a2a5e at 135°',
                '- Accent gradient: from #e94560 to #c0233e at 135°',
                '',
                'Text on dark bg (#0a0e27, #1a1a2e, etc.): use #ffffff or rgba(255,255,255,0.72+)',
                'Text on light bg (#f5f6fa, #ffffff): use #1a1a2e or #5a6a7a',
            ] );
        }
        $lines = [ '## Site Global Colors (use these — consistent with site brand)' ];
        foreach ( $colors as $c ) {
            $lines[] = '- ' . ( $c['label'] ?? 'Color' ) . ': ' . ( $c['value'] ?? '' );
        }
        $lines[] = '';
        $lines[] = 'Apply contrast rules: dark bg → light text; light bg → dark text.';
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
