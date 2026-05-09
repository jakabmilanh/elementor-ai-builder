<?php
/**
 * Prompt összeállítása – szabad formátumú AI generálás.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    // ── Szín-paletta generálás ────────────────────────────────────────────────

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
  "dark_gradient_end": "#hex", // Slightly different dark for gradients (close variant of dark)
  "light": "#hex",             // Light section backgrounds (very light: RGB avg > 230)
  "muted": "#hex",             // Muted/secondary text on light backgrounds (medium gray, RGB avg 90-130)
  "name": "Palette Name"       // Short name describing the palette vibe
}

Color rules:
- primary and accent MUST have enough contrast with white text (#ffffff) — WCAG AA minimum
- dark MUST be very dark (like #0a0e27, #1a1a2e, #111827, #0f172a) — used for dark section backgrounds
- light MUST be very light (like #f5f6fa, #f8fafc, #f0f4f8) — used for section backgrounds
- muted MUST be mid-gray (like #5a6a7a, #64748b, #6b7280) — readable on white but clearly not black
- The palette should feel cohesive and professional
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

    public function build_plan( string $user_prompt, array $vision_image_urls = [] ): array {
        $system = <<<'SYS'
You are a senior UI/UX designer and conversion specialist planning a premium landing page for a specific business.

Think like a top web agency designer creating a $15,000+ website. Design the optimal page structure for THIS specific business — every section must serve the visitor journey for this exact industry and audience.

For each section provide:
- "type": unique snake_case identifier (e.g. "hero", "client_logos", "services_grid", "ai_process_steps", "case_studies", "team_intro", "pricing_table", "testimonials_carousel", "faq_accordion", "final_cta")
- "purpose": what conversion goal this section serves (1 sentence)
- "layout": specific layout description with bg color suggestion (e.g. "full-height split 55/45 — dark gradient bg left text, right 3D illustration", "3-column white cards on light gray bg", "full-width accent color, 4 stat counters centered")
- "content": key elements to include (e.g. "animated headline, 2-line subtitle, primary CTA button, secondary 'Watch Demo' link, 3 trust badges below fold")
- "bg_theme": "dark" | "light" | "accent" — so sections alternate visually

Design rules:
- 7–10 sections total (scale with business complexity)
- FIRST section: hero/above-fold with strong visual impact
- LAST section: conversion CTA with urgency
- Sections must be UNIQUE to THIS business type — think about their specific content (e.g. for an AI agency: hero→what_we_do→ai_services_grid→how_it_works_steps→case_studies→team→pricing→testimonials→faq→cta)
- Alternate bg_theme for visual rhythm: dark → light → dark → light OR dark → light → accent → dark → light
- Include at least 1 unique section type specific to this industry that generic websites don't have
- Think: what would make a visitor immediately trust this business and take action?

Return ONLY a valid JSON array:
[{"type":"...","purpose":"...","layout":"...","content":"...","bg_theme":"dark|light|accent"},...]
SYS;

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
                'text' => "These are design reference screenshots showing the visual style and quality level I want to achieve. Use them as INSPIRATION for the layout sophistication, visual density, and section variety.\n\nDesign the page structure for: \"{$user_prompt}\"\n\nReturn ONLY the JSON array.",
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
        $bg_theme        = $section_meta['bg_theme'] ?? 'light';

        $colors_str    = $this->format_colors( $global_styles['colors'] ?? [] );
        $widget_ref    = $this->get_widget_reference( $has_pro );
        $layout_ref    = $this->get_layout_patterns();
        $contrast_ref  = $this->get_contrast_rules();

        $pro_widgets = $has_pro
            ? 'PRO ACTIVE — use: animated-headline, flip-box, price-table, countdown, slides, nav-menu, form, posts, portfolio'
            : 'FREE ONLY — available: heading, text-editor, button, image, icon-box, icon-list, counter, testimonial, accordion, divider, spacer, star-rating, image-box, video, social-icons, tabs, toggle, progress';

        $system = <<<SYS
You are a senior Elementor developer building a REAL production website. Your output is placed directly on a live website — it must be visually stunning, conversion-focused, and professional.

## OUTPUT FORMAT — CRITICAL
Return ONLY a single root JSON container object.
- Start with: {"id":"
- End with the final closing brace: }
- NO array wrapper, NO {"elementor_data":...} wrapper
- NO markdown code fences, NO backticks, NO explanations
- PURE JSON only

## ELEMENTOR STRUCTURE
{$layout_ref}

## WIDGET LIBRARY
{$widget_ref}

## AVAILABLE WIDGETS
{$pro_widgets}

## COLOR SYSTEM
{$colors_str}

{$contrast_ref}

## IMAGE RULES
- NEVER use background_image on containers (Elementor doesn't render it well in this context)
- Images via image widget only: https://loremflickr.com/{width}/{height}/{keyword1},{keyword2},{keyword3}
- Use SPECIFIC keywords for this business type
- Portraits/avatars: 120×120px keywords: "portrait,professional,person"
- Hero/feature images: 760×600px
- Card thumbnails: 560×420px
- Team photos: 400×480px

## IDs
Every element needs a unique 7-character lowercase alphanumeric ID (random-looking): "a3f9k2m", "x7p2r4s", "b8n2k4q"

## DESIGN STANDARDS
- Section padding: 80–120px top/bottom for content sections; CTA sections: 100–140px
- Use content_width:"boxed" for readable containers (max ~1200px); content_width:"full" for outer wrapper when bg color spans full width
- Cards: white background, border_radius 12–20px, box_shadow {horizontal:0,vertical:8,blur:32,spread:0,color:"rgba(0,0,0,0.08)"}
- Column/card gaps: 24–32px for tight grids; 48–64px for split layouts
- Typography: h1 56–72px/800w, h2 40–52px/700w, h3 20–28px/700w, body 16–17px/1.7em line-height
- Buttons: padding 15–18px top/bottom, 36–48px left/right, border_radius 8px, font_weight 700
- Every section must feel complete and polished — no empty containers
SYS;

        $bg_hint = match( $bg_theme ) {
            'dark'   => 'Use a DARK background (the site dark color or dark gradient). All text MUST be white/light.',
            'accent' => 'Use the ACCENT/PRIMARY color as background. All text MUST be white (#ffffff).',
            default  => 'Use a LIGHT background (white or light gray). All text MUST be dark.',
        };

        $user_msg = <<<MSG
Build this Elementor section for the website: "{$user_prompt}"

SECTION BRIEF:
- Type: {$section_type}
- Purpose: {$section_purpose}
- Suggested layout: {$section_layout}
- Content to include: {$section_content}
- Background theme: {$bg_hint}

REQUIREMENTS:
1. ALL text in the SAME LANGUAGE as the business description
2. Every text must be SPECIFIC and REAL for this exact business — zero generic placeholders like "Lorem ipsum" or "Service Name"
3. VERIFY every widget's text color against its container background before writing — dark text on dark bg is a critical error
4. The layout hint is a guide — improve it if you see a better design approach
5. Use appropriate widgets from the library — flip-box for interactive cards, animated-headline for heroes, price-table for pricing
6. Make this look like it belongs on an award-winning website

Return ONLY the root container JSON object. First character: { Last character: }
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

    // ── Kontraszt szabályok ───────────────────────────────────────────────────

    private function get_contrast_rules(): string {
        return <<<'CONTRAST'
## ⚠️ COLOR CONTRAST — CRITICAL RULES — VIOLATIONS BREAK THE WEBSITE

### The fundamental rule: TEXT COLOR must always be OPPOSITE to BACKGROUND COLOR in brightness.

**DARK BACKGROUNDS** (background_color RGB avg < 80 — e.g. #0a0e27, #1a1a2e, #111827, #0f3460, #12122a):
  ✅ CORRECT text colors: #ffffff, rgba(255,255,255,0.9), rgba(255,255,255,0.75)
  ❌ FORBIDDEN text colors: #1a1a2e, #333333, #5a6a7a, #111, any dark hex on dark bg
  ❌ Example of violation: {"background_color":"#1a1a2e"} with {"color":"#1a1a2e"} — INVISIBLE TEXT

**LIGHT BACKGROUNDS** (background_color RGB avg > 180 — e.g. #ffffff, #f5f6fa, #f8fafc, #f0f4f8):
  ✅ CORRECT text colors: #1a1a2e, #111827, #0f172a, #5a6a7a (for body text)
  ❌ FORBIDDEN text colors: #ffffff, rgba(255,255,255,x), any light color on light bg
  ❌ Example of violation: {"background_color":"#ffffff"} with {"color":"#ffffff"} — INVISIBLE TEXT

**ACCENT BACKGROUNDS** (primary/accent color as section background):
  ✅ CORRECT: #ffffff always
  ❌ FORBIDDEN: accent color as text on accent background

**GRADIENT BACKGROUNDS** (background_background:"gradient"):
  → Determine text color from the DARKEST gradient stop
  → If dark gradient: use #ffffff for all text
  → If light gradient: use #1a1a2e for all text

**WHITE CARDS INSIDE DARK SECTIONS**:
  → The card itself has white/light background
  → Text INSIDE the card must be DARK (#1a1a2e, #5a6a7a)
  → Do NOT use the outer section's text color inside cards

**ICON COLORS**:
  → On dark bg: icon_color should be the accent/primary color OR #ffffff
  → On light bg: icon_color should be the accent/primary color OR #1a1a2e
  → Never use dark color on dark bg or light color on light bg

**BEFORE WRITING**: For every widget, trace up to its nearest container background and pick the contrasting text color.
CONTRAST;
    }

    // ── Layout sablonok ───────────────────────────────────────────────────────

    private function get_layout_patterns(): string {
        return <<<'LAYOUT'
## CONTAINER SYSTEM

### ROOT SECTION (isInner:false) — wrapper for full-width backgrounds
{"id":"SEC0001","elType":"container","isInner":false,"settings":{"content_width":"full","flex_direction":"column","flex_align_items":"center","flex_justify_content":"center","gap":{"size":0,"unit":"px"},"padding":{"top":"0","bottom":"0","left":"0","right":"0","unit":"px","isLinked":false},"background_color":"#0a0e27"},"elements":[INNER_CONTAINERS]}

### BOXED ROW (isInner:true) — centers and limits content width
{"id":"ROW0001","elType":"container","isInner":true,"settings":{"content_width":"boxed","flex_direction":"row","flex_align_items":"center","flex_justify_content":"center","gap":{"size":48,"unit":"px"},"padding":{"top":"100","bottom":"100","left":"0","right":"0","unit":"px","isLinked":false},"width":{"size":100,"unit":"%"}},"elements":[CHILDREN]}

### COLUMN (isInner:true, inside a row) — for split layouts
{"id":"COL0001","elType":"container","isInner":true,"settings":{"content_width":"full","flex_direction":"column","flex_justify_content":"center","flex_align_items":"flex-start","gap":{"size":16,"unit":"px"},"width":{"size":55,"unit":"%"}},"elements":[WIDGETS]}

### CARD (isInner:true) — white card with shadow
{"id":"CARD001","elType":"container","isInner":true,"settings":{"content_width":"full","flex_direction":"column","flex_align_items":"flex-start","gap":{"size":12,"unit":"px"},"padding":{"top":"40","bottom":"40","left":"36","right":"36","unit":"px","isLinked":false},"background_color":"#ffffff","border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true},"box_shadow_box_shadow_type":"yes","box_shadow_box_shadow":{"horizontal":0,"vertical":8,"blur":32,"spread":0,"color":"rgba(0,0,0,0.08)","position":""},"width":{"size":33.333,"unit":"%"}},"elements":[WIDGETS]}

### GRADIENT BACKGROUND: add to section settings:
"background_background":"gradient","background_color":"#0a0e27","background_color_b":"#1a2a5e","background_gradient_angle":{"size":135,"unit":"deg"},"background_gradient_type":"linear"

### FULL-HEIGHT: add to section settings: "min_height":{"size":100,"unit":"vh"}

### FLEX WRAP for card grids: "flex_wrap":"wrap" — allows 3-per-row cards to wrap on mobile
LAYOUT;
    }

    // ── Widget referencia ─────────────────────────────────────────────────────

    private function get_widget_reference( bool $has_pro ): string {
        $free = <<<'FREE'
## FREE WIDGETS

heading: {"id":"hdg0001","elType":"widget","widgetType":"heading","settings":{"title":"Your Headline Here","header_size":"h2","align":"left","color":"#ffffff","typography_font_size":{"size":48,"unit":"px"},"typography_font_weight":"700","typography_line_height":{"size":1.15,"unit":"em"},"typography_letter_spacing":{"size":-0.5,"unit":"px"}},"elements":[]}

text-editor: {"id":"txt0001","elType":"widget","widgetType":"text-editor","settings":{"editor":"<p>Your body text here.</p>","align":"left","color":"rgba(255,255,255,0.75)","typography_font_size":{"size":17,"unit":"px"},"typography_line_height":{"size":1.7,"unit":"em"}},"elements":[]}

button: {"id":"btn0001","elType":"widget","widgetType":"button","settings":{"text":"Get Started","link":{"url":"#","is_external":false},"align":"left","background_color":"#e94560","button_text_color":"#ffffff","border_radius":{"top":"8","right":"8","bottom":"8","left":"8","unit":"px","isLinked":true},"typography_font_size":{"size":15,"unit":"px"},"typography_font_weight":"700","padding":{"top":"16","bottom":"16","left":"40","right":"40","unit":"px","isLinked":false},"hover_background_color":"#c73652"},"elements":[]}

button (outline/secondary): {"id":"btn0002","elType":"widget","widgetType":"button","settings":{"text":"Learn More","link":{"url":"#"},"align":"left","background_color":"transparent","button_text_color":"#ffffff","border_border":"solid","border_width":{"top":"2","right":"2","bottom":"2","left":"2","unit":"px","isLinked":true},"border_color":"rgba(255,255,255,0.5)","border_radius":{"top":"8","right":"8","bottom":"8","left":"8","unit":"px","isLinked":true},"typography_font_weight":"600","padding":{"top":"14","bottom":"14","left":"36","right":"36","unit":"px","isLinked":false}},"elements":[]}

image: {"id":"img0001","elType":"widget","widgetType":"image","settings":{"image":{"url":"https://loremflickr.com/760/600/keyword1,keyword2","alt":"Description"},"width":{"size":100,"unit":"%"},"align":"center","border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true}},"elements":[]}

image-box: {"id":"imb0001","elType":"widget","widgetType":"image-box","settings":{"image":{"url":"https://loremflickr.com/560/420/keyword","alt":"Alt"},"title_text":"Feature Title","description_text":"Description of this feature goes here.","title_size":"h3","title_color":"#1a1a2e","description_color":"#5a6a7a","title_typography_font_size":{"size":20,"unit":"px"},"title_typography_font_weight":"700","description_typography_font_size":{"size":15,"unit":"px"},"image_border_radius":{"top":"12","right":"12","bottom":"12","left":"12","unit":"px","isLinked":true},"position":"top"},"elements":[]}

icon-box: {"id":"icb0001","elType":"widget","widgetType":"icon-box","settings":{"selected_icon":{"value":"fas fa-rocket","library":"fa-solid"},"title_text":"Service Title","description_text":"Short description of what this service offers and the value it provides.","icon_size":{"size":48,"unit":"px"},"icon_color":"#e94560","icon_padding":{"top":"16","bottom":"16","left":"16","right":"16","unit":"px","isLinked":true},"title_size":"h3","title_color":"#1a1a2e","description_color":"#5a6a7a","title_typography_font_size":{"size":20,"unit":"px"},"title_typography_font_weight":"700","position":"top","view":"stacked","shape":"square","background_color_icon":"rgba(233,69,96,0.1)","border_radius_icon":{"top":"12","right":"12","bottom":"12","left":"12","unit":"px","isLinked":true}},"elements":[]}

icon-box (on dark bg — WHITE text): {"id":"icb0002","elType":"widget","widgetType":"icon-box","settings":{"selected_icon":{"value":"fas fa-bolt","library":"fa-solid"},"title_text":"Service Title","description_text":"Description here.","icon_size":{"size":44,"unit":"px"},"icon_color":"#e94560","title_size":"h3","title_color":"#ffffff","description_color":"rgba(255,255,255,0.72)","title_typography_font_size":{"size":20,"unit":"px"},"title_typography_font_weight":"700","position":"top"},"elements":[]}

icon-list: {"id":"icl0001","elType":"widget","widgetType":"icon-list","settings":{"icon_list":[{"text":"Feature one","selected_icon":{"value":"fas fa-check-circle","library":"fa-solid"}},{"text":"Feature two","selected_icon":{"value":"fas fa-check-circle","library":"fa-solid"}},{"text":"Feature three","selected_icon":{"value":"fas fa-check-circle","library":"fa-solid"}}],"icon_color":"#e94560","text_color":"#5a6a7a","icon_size":{"size":18,"unit":"px"},"space_between":{"size":14,"unit":"px"},"item_icon_indent":{"size":8,"unit":"px"}},"elements":[]}

counter (on dark bg): {"id":"cnt0001","elType":"widget","widgetType":"counter","settings":{"starting_number":0,"ending_number":2500,"suffix":"+","title":"Satisfied Clients","number_color":"#ffffff","number_size":{"size":56,"unit":"px"},"number_typography_font_weight":"800","title_color":"rgba(255,255,255,0.65)","title_size":{"size":14,"unit":"px"},"title_typography_font_weight":"500"},"elements":[]}

counter (on accent bg): {"id":"cnt0002","elType":"widget","widgetType":"counter","settings":{"starting_number":0,"ending_number":500,"suffix":"+","title":"Projects Done","number_color":"#ffffff","number_size":{"size":56,"unit":"px"},"number_typography_font_weight":"800","title_color":"rgba(255,255,255,0.8)","title_size":{"size":14,"unit":"px"}},"elements":[]}

testimonial: {"id":"tst0001","elType":"widget","widgetType":"testimonial","settings":{"content":"This service completely transformed our business. We saw a 300% increase in leads within the first month. Highly recommended!","name":"Kovács Péter","job":"CEO, TechStart Kft.","image":{"url":"https://loremflickr.com/120/120/portrait,professional,man","alt":"Kovács Péter"},"content_color":"#5a6a7a","name_color":"#1a1a2e","job_color":"#e94560","content_typography_font_size":{"size":16,"unit":"px"},"content_typography_font_style":"italic","name_typography_font_weight":"700"},"elements":[]}

star-rating: {"id":"str0001","elType":"widget","widgetType":"star-rating","settings":{"rating":5,"star_color":"#f59e0b","unmarked_star_color":"rgba(245,158,11,0.2)","icon_size":{"size":18,"unit":"px"},"align":"left"},"elements":[]}

accordion: {"id":"acc0001","elType":"widget","widgetType":"accordion","settings":{"tabs":[{"tab_title":"Frequently asked question?","tab_content":"<p>Clear and helpful answer that addresses the visitor's concern directly.</p>"},{"tab_title":"Another common question?","tab_content":"<p>Detailed answer here.</p>"}],"title_color":"#1a1a2e","icon_color":"#e94560","active_color":"#e94560","content_color":"#5a6a7a","tab_active_background":"#ffffff","border_color":"rgba(0,0,0,0.08)","item_spacing":{"size":4,"unit":"px"},"title_typography_font_weight":"600","title_typography_font_size":{"size":16,"unit":"px"}},"elements":[]}

tabs: {"id":"tab0001","elType":"widget","widgetType":"tabs","settings":{"tabs":[{"tab_title":"Tab One","tab_content":"<p>Tab one content goes here with relevant information.</p>"},{"tab_title":"Tab Two","tab_content":"<p>Tab two content here.</p>"},{"tab_title":"Tab Three","tab_content":"<p>Tab three content.</p>"}],"tab_active_color":"#e94560","tab_color":"#5a6a7a","tab_hover_color":"#1a1a2e","tab_background_color":"#f5f6fa","tab_active_background_color":"#ffffff","content_color":"#5a6a7a","border_color":"rgba(0,0,0,0.08)","tab_typography_font_weight":"600"},"elements":[]}

video: {"id":"vid0001","elType":"widget","widgetType":"video","settings":{"video_type":"youtube","youtube_url":"https://www.youtube.com/watch?v=XHOmBV4js_E","show_image_overlay":"yes","image_overlay":{"url":"https://loremflickr.com/960/540/business,presentation,office","alt":"Video preview"},"show_play_icon":"yes","lightbox":"yes","border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true}},"elements":[]}

progress: {"id":"prg0001","elType":"widget","widgetType":"progress","settings":{"title":"SEO Optimization","percent":{"size":90,"unit":"%"},"progress_color":"#e94560","bgcolor":"rgba(233,69,96,0.1)","title_color":"#1a1a2e","percent_value_color":"#e94560","bar_height":{"size":8,"unit":"px"},"inner_text_heading_color":"#ffffff","typography_font_weight":"600","typography_font_size":{"size":14,"unit":"px"}},"elements":[]}

social-icons: {"id":"soc0001","elType":"widget","widgetType":"social-icons","settings":{"social_icon_list":[{"social_icon":{"value":"fab fa-facebook","library":"fa-brands"},"social_icon_color":"custom","icon_color":"#ffffff","icon_hover_color":"#e94560","link":{"url":"#"}},{"social_icon":{"value":"fab fa-instagram","library":"fa-brands"},"social_icon_color":"custom","icon_color":"#ffffff","icon_hover_color":"#e94560","link":{"url":"#"}}],"icon_size":{"size":18,"unit":"px"},"icon_spacing":{"size":12,"unit":"px"},"shape":"rounded","background_color":"rgba(255,255,255,0.1)"},"elements":[]}

divider: {"id":"div0001","elType":"widget","widgetType":"divider","settings":{"color":"rgba(0,0,0,0.08)","weight":{"size":1,"unit":"px"}},"elements":[]}

spacer: {"id":"spc0001","elType":"widget","widgetType":"spacer","settings":{"space":{"size":40,"unit":"px"}},"elements":[]}
FREE;

        if ( ! $has_pro ) {
            return $free;
        }

        $pro = <<<'PRO'

## PRO WIDGETS (Elementor PRO active — use these for higher quality)

animated-headline: {"id":"anh0001","elType":"widget","widgetType":"animated-headline","settings":{"headline_style":"highlighted","before_text":"We Build","highlighted_text":"Stunning","after_text":"Websites","animation_type":"typing","loop":"yes","main_style_color":"#ffffff","highlighted_background_color":"#e94560","main_style_font_size":{"size":64,"unit":"px"},"main_style_font_weight":"800","main_style_line_height":{"size":1.1,"unit":"em"}},"elements":[]}

animated-headline (on light bg): {"id":"anh0002","elType":"widget","widgetType":"animated-headline","settings":{"headline_style":"highlighted","before_text":"Your","highlighted_text":"Growth","after_text":"Starts Here","animation_type":"flip","loop":"yes","main_style_color":"#1a1a2e","highlighted_background_color":"#e94560","highlighted_text_color":"#ffffff","main_style_font_size":{"size":56,"unit":"px"},"main_style_font_weight":"800"},"elements":[]}

flip-box: {"id":"flb0001","elType":"widget","widgetType":"flip-box","settings":{"flip_effect":"slide","flip_direction":"right","side_a_title_text":"Service Name","side_a_description_text":"Brief description of what this service includes.","side_a_title_color":"#ffffff","side_a_description_color":"rgba(255,255,255,0.8)","side_a_title_size":{"size":22,"unit":"px"},"side_a_background_color":"#1a1a2e","side_b_title_text":"Learn More →","side_b_description_text":"Extended details about this service and its benefits.","side_b_background_color":"#e94560","side_b_title_color":"#ffffff","side_b_description_color":"rgba(255,255,255,0.9)","side_b_title_size":{"size":22,"unit":"px"},"border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true},"height":{"size":260,"unit":"px"},"selected_icon":{"value":"fas fa-star","library":"fa-solid"},"icon_size":{"size":48,"unit":"px"},"icon_color":"#e94560","side_a_icon_color":"#e94560"},"elements":[]}

price-table: {"id":"prt0001","elType":"widget","widgetType":"price-table","settings":{"title":"Professional","price":"149","currency_symbol":"€","sub_title":"Per month","features_list":[{"item_text":"5 Active Projects","_id":"f1"},{"item_text":"Priority Support","_id":"f2"},{"item_text":"Advanced Analytics","_id":"f3"},{"item_text":"Custom Integrations","_id":"f4"},{"item_text":"Monthly Report","_id":"f5"}],"cta_text":"Get Started","cta_link":{"url":"#"},"header_bgcolor":"#e94560","header_title_color":"#ffffff","header_price_color":"#ffffff","header_sub_title_color":"rgba(255,255,255,0.8)","features_bg_color":"#ffffff","features_text_color":"#5a6a7a","features_item_icon_color":"#e94560","cta_button_background_color":"#e94560","cta_button_text_color":"#ffffff","cta_button_border_radius":{"top":"8","right":"8","bottom":"8","left":"8","unit":"px","isLinked":true},"border_radius":{"top":"16","right":"16","bottom":"16","left":"16","unit":"px","isLinked":true},"box_shadow_box_shadow_type":"yes","box_shadow_box_shadow":{"horizontal":0,"vertical":16,"blur":48,"spread":0,"color":"rgba(0,0,0,0.12)","position":""},"ribbon_title":"Most Popular","ribbon_bg_color":"#f59e0b","ribbon_text_color":"#ffffff"},"elements":[]}

countdown (Pro): {"id":"cdt0001","elType":"widget","widgetType":"countdown","settings":{"due_date":"2026-01-01 00:00","label_days":"nap","label_hours":"óra","label_minutes":"perc","label_seconds":"mp","digits_color":"#ffffff","label_color":"rgba(255,255,255,0.65)","digits_typography_font_size":{"size":60,"unit":"px"},"digits_typography_font_weight":"800","label_typography_font_size":{"size":13,"unit":"px"}},"elements":[]}
PRO;

        return $free . $pro;
    }

    // ── Segédmetódusok ────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return implode( "\n", [
                '## Site Color Palette (defaults)',
                '- Dark background: #1a1a2e',
                '- Dark gradient end: #0a0e27',
                '- Primary/Accent: #e94560',
                '- Secondary accent: #c73652',
                '- Light section bg: #f5f6fa',
                '- Muted body text (on light): #5a6a7a',
                '- White: #ffffff',
                '',
                '→ Text on dark bg (#1a1a2e, #0a0e27): MUST be #ffffff or rgba(255,255,255,0.75+)',
                '→ Text on light bg (#f5f6fa, #ffffff): MUST be #1a1a2e or #5a6a7a',
                '→ Text on accent bg (#e94560): MUST be #ffffff',
            ] );
        }

        $lines   = [ '## Site Global Colors (from Elementor Kit — use these exact values)' ];
        $darks   = [];
        $lights  = [];
        $accents = [];

        foreach ( $colors as $c ) {
            $label = $c['label'] ?? 'Color';
            $value = $c['value'] ?? '';
            $lines[] = "- {$label}: {$value}";

            // Classify for contrast guidance
            $hex = ltrim( $value, '#' );
            if ( strlen( $hex ) === 6 ) {
                $r = hexdec( substr( $hex, 0, 2 ) );
                $g = hexdec( substr( $hex, 2, 2 ) );
                $b = hexdec( substr( $hex, 4, 2 ) );
                $avg = ( $r + $g + $b ) / 3;
                if ( $avg < 80 ) {
                    $darks[] = $value;
                } elseif ( $avg > 180 ) {
                    $lights[] = $value;
                } else {
                    $accents[] = $value;
                }
            }
        }

        $lines[] = '';
        if ( ! empty( $darks ) ) {
            $lines[] = '→ DARK colors (' . implode( ', ', $darks ) . '): use #ffffff text on these';
        }
        if ( ! empty( $lights ) ) {
            $lines[] = '→ LIGHT colors (' . implode( ', ', $lights ) . '): use #1a1a2e or #5a6a7a text on these';
        }
        if ( ! empty( $accents ) ) {
            $lines[] = '→ ACCENT colors (' . implode( ', ', $accents ) . '): use #ffffff text on these';
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
