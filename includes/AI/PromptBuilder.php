<?php
/**
 * Prompt összeállítása – szabad formátumú AI generálás.
 * + Szín-paletta generálás
 * + Design referencia kép támogatás
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    // ── Szín-paletta generálás ────────────────────────────────────────────────

    /**
     * Prompt az AI számára hogy generáljon teljes szín-palettát.
     *
     * @param string $user_prompt  Felhasználói prompt (iparág, stílus)
     * @param array  $detected_colors  Promptból kiszedett hex színek (pl. ['#2563eb', '#1e3a5f'])
     */
    public function build_color_scheme( string $user_prompt, array $detected_colors = [] ): array {
        $base_hint = empty( $detected_colors )
            ? "No specific colors were mentioned — choose colors that best fit the industry and feel of the business."
            : "Base colors detected in the user's prompt: " . implode( ', ', $detected_colors ) . ". Build the palette around these.";

        $system = <<<'SYS'
You are a professional brand designer creating a website color palette.

Return ONLY valid JSON with exactly these keys:
{
  "primary": "#hex",           // Main CTA/action color (buttons, key elements)
  "accent": "#hex",            // Secondary accent (slightly darker than primary)
  "dark": "#hex",              // Dark text color AND dark section backgrounds (must be very dark: RGB avg < 40)
  "dark_gradient_end": "#hex", // Slightly different dark for gradients (can be same as dark or close variant)
  "light": "#hex",             // Light section backgrounds (very light: RGB avg > 230)
  "muted": "#hex",             // Muted/secondary text on light backgrounds (medium gray, RGB avg 90-130)
  "name": "Palette Name"       // Short name describing the palette vibe
}

Color rules:
- primary and accent should look great as button colors — they MUST have enough contrast with white text (#ffffff)
- dark MUST be very dark (like #0a0e27, #1a1a2e, #111827, #0f172a) — used for dark section backgrounds
- light MUST be very light (like #f5f6fa, #f8fafc, #f0f4f8) — used for section backgrounds
- muted MUST be mid-gray (like #5a6a7a, #64748b, #6b7280) — readable on white but clearly not black
- The palette should feel cohesive and professional
- dark and light must have maximum contrast between them
SYS;

        $user_msg = <<<MSG
Create a website color palette for: "{$user_prompt}"

{$base_hint}

Return ONLY the JSON object, nothing else.
MSG;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user',   'content' => $user_msg ],
        ];
    }

    // ── Oldal-terv ────────────────────────────────────────────────────────────

    /**
     * @param string   $user_prompt
     * @param string[] $vision_image_urls  URL-ek amik design referenciák (opcionális)
     */
    public function build_plan( string $user_prompt, array $vision_image_urls = [] ): array {
        $system = <<<'SYS'
You are a senior UI/UX designer and conversion specialist planning a premium landing page.

Design the perfect page structure for this specific business. Think like a top web agency designer creating a $10,000 website. Every section should serve a purpose in the visitor's journey.

For each section provide:
- "type": unique snake_case identifier (e.g. "hero", "services_cards", "team_intro", "booking_strip", "testimonials_grid", "faq_accordion", "final_cta")
- "purpose": what this section achieves in the conversion funnel (1 sentence)
- "layout": specific layout description (e.g. "full-height split: 55% text left, 45% image right, dark gradient bg", "3-column white cards on light gray bg", "full-width accent color, 4 counters in a row")
- "content": key content elements to include (e.g. "h1 max 8 words, subtitle 2 sentences, 2 CTAs, 3 stats with icons")

Design rules:
- 6–8 sections total
- FIRST section: hero/above-fold (type containing "hero")
- LAST section: conversion CTA (type containing "cta")
- Sections must be logical for THIS SPECIFIC business — not generic
- Think about the visitor journey: first impression → services → trust → proof → action
- Be creative: unique sections for the industry (e.g. "before_after" for clinics, "fleet_showcase" for car rentals, "menu_preview" for restaurants, "portfolio_grid" for agencies)

Return ONLY a valid JSON array:
[{"type":"...","purpose":"...","layout":"...","content":"..."},...]
SYS;

        // Ha vannak design referencia képek, ezeket vision inputként küldjük
        if ( ! empty( $vision_image_urls ) ) {
            $content_blocks = [];
            foreach ( array_slice( $vision_image_urls, 0, 3 ) as $img_url ) {
                $content_blocks[] = [
                    'type'   => 'image',
                    'source' => [
                        'type' => 'url',
                        'url'  => $img_url,
                    ],
                ];
            }
            $content_blocks[] = [
                'type' => 'text',
                'text' => "These are design reference screenshots showing the visual style and layout quality I want to achieve. Use them as INSPIRATION for the page structure and design approach — the sections you design should reflect this level of quality and layout sophistication.\n\nNow design the page structure for: \"{$user_prompt}\"\n\nReturn ONLY the JSON array.",
            ];

            return [
                [ 'role' => 'system', 'content' => $system ],
                [ 'role' => 'user',   'content' => $content_blocks ],
            ];
        }

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user',   'content' => "Design the optimal landing page structure for: \"{$user_prompt}\"\n\nReturn ONLY the JSON array, nothing else." ],
        ];
    }

    // ── Szekció generálás ─────────────────────────────────────────────────────

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

        $colors_str = $this->format_colors( $global_styles['colors'] ?? [] );
        $pro_note   = $has_pro
            ? 'Elementor PRO is active — you can use ALL widgets: flip-box, animated-headline, price-table, etc.'
            : 'Elementor FREE only — use only: text-editor, heading, image, button, icon-box, icon-list, counter, testimonial, accordion, divider, spacer, star-rating, image-box, video, social-icons, tabs, toggle.';

        $elementor_ref = $this->get_elementor_reference();

        $system = <<<SYS
You are a senior Elementor developer building a REAL production website. Your output goes directly onto a live website — it must be visually stunning, conversion-focused, and professional.

## OUTPUT FORMAT — CRITICAL
Return ONLY a single root JSON container object: {"id":"...","elType":"container","isInner":false,...}
- NO array wrapper, NO {"elementor_data":...} wrapper
- NO markdown fences, NO explanations
- Pure JSON starting with { and ending with }

## ELEMENTOR JSON STRUCTURE
{$elementor_ref}

## DESIGN FREEDOM
You have FULL creative freedom. Design it like a senior designer at a top agency:
- Choose any layout that fits the section's purpose (split, asymmetric, full-bleed, cards, grid, etc.)
- Use visual hierarchy: most important = biggest and most prominent
- Generous whitespace — it signals quality
- Every element should earn its place — no filler

## DESIGN STANDARDS
- Section padding: 80–120px top/bottom (hero: can be 0 if full-height; CTA: 100–140px)
- Boxed content: use content_width "boxed" for readable content (~1200px max)
- Full-bleed sections: content_width "full" for background-only outer containers
- Cards: white bg, border-radius 12–20px, box-shadow: {horizontal:0,vertical:8,blur:32,spread:0,color:"rgba(0,0,0,0.08)"}
- Column gaps: 24–48px between equal; 48–80px for split layouts
- h1: 52–72px weight 800 | h2: 36–52px weight 700 | h3: 20–26px weight 700
- Body text: 15–17px, line-height {"size":1.7,"unit":"em"}
- Buttons: padding top/bottom 14–18px, left/right 32–48px, radius 6–10px, weight 700

## COLOR SYSTEM
{$colors_str}

## ⚠️ COLOR CONTRAST — ABSOLUTE RULES — NEVER VIOLATE
RULE 1 — DARK BACKGROUND → MUST use LIGHT text:
  Dark = #0a0e27, #1a1a2e, #1a2a5e, #0f3460, #12122a, #111, #222, #333 (RGB avg < 80)
  → text color MUST be: #ffffff OR rgba(255,255,255,0.85+) for primary
  → secondary/muted text on dark bg: rgba(255,255,255,0.72) — still light!
  → FORBIDDEN: dark text on dark bg (#1a1a2e, #5a6a7a, #333 etc. as text color)

RULE 2 — LIGHT/WHITE BACKGROUND → MUST use DARK text:
  Light = #ffffff, #f5f6fa, #f0f0f0, #fafafa (RGB avg > 180)
  → text MUST be: #1a1a2e (headings) or #5a6a7a (body) or similar dark
  → FORBIDDEN: white/light text on white/light bg

RULE 3 — ACCENT BACKGROUND → white text (#ffffff always)

RULE 4 — GRADIENT → use the DARKEST color to determine text rules

RULE 5 — WHITE CARDS in dark sections: card bg is white → text inside must be DARK

RULE 6 — ICONS: must contrast with their container background

## IMAGE RULES
- NEVER set background_image on containers
- Images: image widget, URL: https://loremflickr.com/{width}/{height}/{kw1},{kw2},{kw3}
- Use industry-specific keywords from the business description
- Portrait/avatars: 120×120, "portrait,professional,person"
- Section images: 700×560 | Hero images: 750×620

## IDs: exactly 7 chars, unique, lowercase alphanumeric, random-looking: "a3f9k2m", "x7p2r4s"

## {$pro_note}
SYS;

        $user_msg = <<<MSG
Build this Elementor section for: "{$user_prompt}"

SECTION BRIEF:
- Type: {$section_type}
- Purpose: {$section_purpose}
- Layout: {$section_layout}
- Content: {$section_content}

REQUIREMENTS:
1. ALL text in the SAME LANGUAGE as the business description
2. Every text must be SPECIFIC to this exact business — no generic placeholders
3. Apply ALL color contrast rules — verify every text element vs its background
4. The layout hint is a suggestion — improve it if you see a better approach
5. Make it look like a world-class website

Return ONLY the single root container JSON. Start with { end with }.
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
No markdown. Pure JSON only.

RULES:
- Apply ONLY the requested changes, preserve everything else
- Keep all existing IDs; new elements get unique 7-char IDs
- NEVER use background_image on containers
- ALL images: image widget with loremflickr.com URL
- Color contrast: dark bg → white/light text; light bg → dark text
- Return the COMPLETE modified JSON

{$colors_str}
SYS;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user', 'content' => "Current JSON:\n{$json_preview}\n\nInstruction: \"{$user_prompt}\"\n\nReturn ONLY the complete modified JSON." ],
        ];
    }

    // ── Elementor widget referencia ───────────────────────────────────────────

    private function get_elementor_reference(): string {
        return <<<'REF'
### Root section (elType:"container", isInner:false)
{"id":"a3f9k2m","elType":"container","isInner":false,"settings":{"content_width":"full","flex_direction":"column","flex_align_items":"center","flex_justify_content":"center","gap":{"size":0,"unit":"px"},"padding":{"top":"100","bottom":"100","left":"0","right":"0","unit":"px","isLinked":false},"background_color":"#f5f6fa","min_height":{"size":100,"unit":"vh"}},"elements":[]}

### Inner column/row (elType:"container", isInner:true)
{"id":"b5n8q1j","elType":"container","isInner":true,"settings":{"content_width":"boxed","flex_direction":"row","width":{"size":50,"unit":"%"},"gap":{"size":32,"unit":"px"},"padding":{"top":"36","bottom":"36","left":"36","right":"36","unit":"px","isLinked":false},"background_color":"#ffffff","border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":false},"box_shadow_box_shadow_type":"yes","box_shadow_box_shadow":{"horizontal":0,"vertical":8,"blur":32,"spread":0,"color":"rgba(0,0,0,0.08)","position":""}},"elements":[]}

### GRADIENT: "background_background":"gradient","background_color":"#0a0e27","background_color_b":"#1a2a5e","background_gradient_angle":{"size":135,"unit":"deg"},"background_gradient_type":"linear"

### Widgets (elType:"widget", elements:[])
heading: {"id":"x7p2r4s","elType":"widget","widgetType":"heading","settings":{"title":"Headline","header_size":"h2","align":"left","color":"#1a1a2e","typography_font_size":{"size":44,"unit":"px"},"typography_font_weight":"700","typography_line_height":{"size":1.15,"unit":"em"}},"elements":[]}

text-editor: {"id":"c4m7p9x","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>Text here.</p>","align":"left","color":"#5a6a7a","typography_font_size":{"size":17,"unit":"px"},"typography_line_height":{"size":1.7,"unit":"em"}},"elements":[]}

button: {"id":"d2k5r8n","elType":"widget","widgetType":"button","settings":{"text":"Click Here","link":{"url":"#","is_external":false},"align":"left","background_color":"#e94560","button_text_color":"#ffffff","border_radius":{"top":"8","right":"8","bottom":"8","left":"8","unit":"px","isLinked":true},"typography_font_size":{"size":15,"unit":"px"},"typography_font_weight":"700","padding":{"top":"16","bottom":"16","left":"40","right":"40","unit":"px","isLinked":false}},"elements":[]}

image: {"id":"e8j3f6m","elType":"widget","widgetType":"image","settings":{"image":{"url":"https://loremflickr.com/700/560/keyword1,keyword2","alt":"Description"},"width":{"size":100,"unit":"%"},"align":"center","border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true}},"elements":[]}

icon-box: {"id":"f1n4q7k","elType":"widget","widgetType":"icon-box","settings":{"selected_icon":{"value":"fas fa-star","library":"fa-solid"},"title_text":"Title","description_text":"Description.","icon_size":{"size":44,"unit":"px"},"icon_color":"#e94560","title_size":"h3","title_color":"#1a1a2e","description_color":"#5a6a7a","title_typography_font_size":{"size":20,"unit":"px"},"title_typography_font_weight":"700"},"elements":[]}

icon-list: {"id":"g6p9s2w","elType":"widget","widgetType":"icon-list","settings":{"icon_list":[{"text":"Item","selected_icon":{"value":"fas fa-check-circle","library":"fa-solid"}}],"icon_color":"#e94560","text_color":"#5a6a7a","icon_size":{"size":18,"unit":"px"},"space_between":{"size":12,"unit":"px"}},"elements":[]}

counter: {"id":"h3t6v9y","elType":"widget","widgetType":"counter","settings":{"starting_number":0,"ending_number":1500,"suffix":"+","title":"Label","number_color":"#ffffff","number_size":{"size":52,"unit":"px"},"number_typography_font_weight":"800","title_color":"rgba(255,255,255,0.72)","title_size":{"size":14,"unit":"px"}},"elements":[]}

testimonial: {"id":"i5w8z1a","elType":"widget","widgetType":"testimonial","settings":{"content":"Quote text.","name":"Client Name","job":"Job Title","image":{"url":"https://loremflickr.com/120/120/portrait,professional,person","alt":"Client"},"content_color":"#5a6a7a","name_color":"#1a1a2e","job_color":"#e94560"},"elements":[]}

star-rating: {"id":"j2x5b8e","elType":"widget","widgetType":"star-rating","settings":{"rating":5,"star_color":"#f5a623","unmarked_star_color":"rgba(245,166,35,0.25)","icon_size":{"size":18,"unit":"px"},"align":"left"},"elements":[]}

accordion: {"id":"k7c1f4h","elType":"widget","widgetType":"accordion","settings":{"tabs":[{"tab_title":"Question?","tab_content":"<p>Answer.</p>"}],"title_color":"#1a1a2e","icon_color":"#e94560","active_color":"#e94560","content_color":"#5a6a7a","item_spacing":{"size":4,"unit":"px"}},"elements":[]}

divider: {"id":"l4g7j0m","elType":"widget","widgetType":"divider","settings":{"color":"rgba(0,0,0,0.08)","weight":{"size":1,"unit":"px"}},"elements":[]}

spacer: {"id":"m9h2k5p","elType":"widget","widgetType":"spacer","settings":{"space":{"size":32,"unit":"px"}},"elements":[]}
REF;
    }

    // ── Segédmetódusok ────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return implode( "\n", [
                '## Site Color Palette',
                '- Dark/Background: #1a1a2e',
                '- Accent/Primary: #e94560',
                '- Muted/Body text: #5a6a7a',
                '- Light section bg: #f5f6fa',
                '- White: #ffffff',
                '- Dark gradient: #0a0e27 → #1a2a5e at 135°',
                '',
                'Text on dark bg: use #ffffff or rgba(255,255,255,0.72+)',
                'Text on light bg: use #1a1a2e or #5a6a7a',
            ] );
        }
        $lines = [ '## Site Global Colors (use these — set in Elementor Kit)' ];
        foreach ( $colors as $c ) {
            $lines[] = '- ' . ( $c['label'] ?? 'Color' ) . ': ' . ( $c['value'] ?? '' );
        }
        $lines[] = '';
        $lines[] = 'Apply contrast: dark bg → light text; light bg → dark text.';
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
