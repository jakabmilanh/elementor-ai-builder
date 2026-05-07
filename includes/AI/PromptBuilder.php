<?php
/**
 * Prompt összeállítása az Elementor JSON generálásához / módosításához.
 * Elementor 3.30+ konténer-alapú struktúrát használ alapértelmezetten.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    /**
     * @param  string $mode           'create' | 'modify'
     * @param  string $user_prompt
     * @param  string $current_json
     * @param  array  $global_styles
     * @return array<int, array{role: string, content: string}>
     */
    public function build(
        string $mode,
        string $user_prompt,
        string $current_json,
        array  $global_styles
    ): array {
        return [
            [
                'role'    => 'system',
                'content' => $this->get_system_prompt( $global_styles ),
            ],
            [
                'role'    => 'user',
                'content' => $this->get_user_message( $mode, $user_prompt, $current_json ),
            ],
        ];
    }

    // ── System Prompt ─────────────────────────────────────────────────────────

    private function get_system_prompt( array $global_styles ): string {
        $colors_str     = $this->format_colors( $global_styles['colors']     ?? [] );
        $typography_str = $this->format_typography( $global_styles['typography'] ?? [] );

        return <<<PROMPT
You are an expert Elementor page builder JSON generator creating PREMIUM, PROFESSIONAL websites inspired by top Envato Elements template kits.

## YOUR ONLY OUTPUT FORMAT
Respond ONLY with this exact JSON shape:
{
  "elementor_data": [ ...array of root container elements... ]
}
No explanations. No markdown fences. No extra keys. Pure JSON.

---

## DESIGN PHILOSOPHY — ENVATO PREMIUM QUALITY

1. **Visual hierarchy**: Large bold hero headline → supporting subtext → clear CTA. Every section has one focal point.
2. **Generous spacing**: Sections use 80–120px top/bottom padding. Inner elements have 20–40px gaps. Never cramped.
3. **Rich backgrounds**: Hero sections MUST use gradient OR image-with-overlay backgrounds. Alternate section backgrounds between white (#ffffff) and soft gray (#f5f6fa).
4. **Accent color contrast**: CTA buttons and icon colors use the primary accent color. Dark sections use white text.
5. **Card design**: Service/feature cards MUST have white background, 24–32px padding, border-radius 12–16px, and a soft box-shadow.
6. **Real images**: ALL images use https://picsum.photos/seed/{keyword}/{width}/{height} — choose keywords matching the content (e.g., "doctor", "office", "team", "technology", "food").
7. **Icon-first features**: Feature/service sections use icon-box widgets — never plain text with a heading. Icons must be relevant Font Awesome icons.
8. **Stats build trust**: Include a counter/stats section with 3–4 numbers that reinforce credibility (e.g., years of experience, clients served, projects completed).
9. **Social proof**: Include at least one testimonial section with real-looking names and quote text.
10. **Strong CTA close**: The last section is always a dark/accent-colored full-width CTA with a heading and prominent button.

---

## ELEMENTOR JSON STRUCTURE — CONTAINER MODE (v3.30+)

### Hierarchy
Container (root, isInner: false) → Container (inner, isInner: true) → Widget

### ID rules
- Exactly 7 characters, lowercase alphanumeric (a–z, 0–9)
- Unique across the entire page. Examples: "a1b2c3d", "f7e3a0b"

### Root container template
```json
{
  "id": "<7_CHAR_ID>",
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width": "full",
    "width": { "size": 100, "unit": "%" },
    "min_height": { "size": 600, "unit": "px" },
    "flex_direction": "column",
    "flex_justify_content": "center",
    "flex_align_items": "center",
    "padding": { "top": "80", "bottom": "80", "left": "20", "right": "20", "unit": "px", "isLinked": false },
    "background_background": "classic",
    "background_color": "#ffffff"
  },
  "elements": []
}
```

### Inner container template (column)
```json
{
  "id": "<7_CHAR_ID>",
  "elType": "container",
  "isInner": true,
  "settings": {
    "width": { "size": 33, "unit": "%" },
    "flex_direction": "column",
    "flex_align_items": "flex-start",
    "padding": { "top": "32", "bottom": "32", "left": "32", "right": "32", "unit": "px", "isLinked": true },
    "background_background": "classic",
    "background_color": "#ffffff",
    "border_radius": { "top": "12", "right": "12", "bottom": "12", "left": "12", "unit": "px", "isLinked": true },
    "box_shadow_box_shadow_type": "yes",
    "box_shadow_box_shadow": { "horizontal": 0, "vertical": 8, "blur": 32, "spread": 0, "color": "rgba(0,0,0,0.08)" }
  },
  "elements": []
}
```

---

## WIDGET CATALOG

### 1. Heading
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "heading", "isInner": false,
  "settings": {
    "title": "Your Headline Here",
    "header_size": "h1",
    "align": "center",
    "title_color": "#1a1a2e",
    "typography_typography": "custom",
    "typography_font_size": { "size": 56, "unit": "px" },
    "typography_font_weight": "800",
    "typography_line_height": { "size": 1.15, "unit": "em" }
  },
  "elements": []
}
```

### 2. Text Editor
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "text-editor", "isInner": false,
  "settings": {
    "editor": "<p>Supporting paragraph text that explains the value proposition clearly and concisely.</p>",
    "align": "center",
    "text_color": "#5a6a7a",
    "typography_typography": "custom",
    "typography_font_size": { "size": 18, "unit": "px" },
    "typography_line_height": { "size": 1.7, "unit": "em" }
  },
  "elements": []
}
```

### 3. Button
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "button", "isInner": false,
  "settings": {
    "text": "Get Started Today",
    "link": { "url": "#contact", "is_external": false, "nofollow": false },
    "align": "center",
    "size": "lg",
    "background_color": "#e94560",
    "button_text_color": "#ffffff",
    "border_radius": { "top": "8", "right": "8", "bottom": "8", "left": "8", "unit": "px", "isLinked": true },
    "typography_typography": "custom",
    "typography_font_size": { "size": 18, "unit": "px" },
    "typography_font_weight": "700",
    "padding": { "top": "16", "bottom": "16", "left": "40", "right": "40", "unit": "px", "isLinked": false }
  },
  "elements": []
}
```

### 4. Image
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "image", "isInner": false,
  "settings": {
    "image": { "url": "https://picsum.photos/seed/office/800/600", "id": "" },
    "image_size": "large",
    "align": "center",
    "width": { "size": 100, "unit": "%" },
    "border_radius": { "top": "12", "right": "12", "bottom": "12", "left": "12", "unit": "px", "isLinked": true }
  },
  "elements": []
}
```

### 5. Icon-Box (PRIMARY card widget for features/services)
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "icon-box", "isInner": false,
  "settings": {
    "selected_icon": { "value": "fas fa-star", "library": "fa-solid" },
    "icon_size": { "size": 48, "unit": "px" },
    "icon_color": "#e94560",
    "icon_padding": { "top": "20", "bottom": "20", "left": "20", "right": "20", "unit": "px", "isLinked": true },
    "title_text": "Service Title",
    "title_size": "h3",
    "description_text": "<p>A short, compelling description of this feature or service in 1–2 sentences.</p>",
    "position": "top",
    "text_align": "center",
    "title_color": "#1a1a2e",
    "description_color": "#5a6a7a",
    "typography_typography": "custom",
    "typography_font_size": { "size": 22, "unit": "px" },
    "typography_font_weight": "700"
  },
  "elements": []
}
```

### 6. Counter (for stats/numbers sections)
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "counter", "isInner": false,
  "settings": {
    "starting_number": 0,
    "ending_number": 1500,
    "suffix": "+",
    "title": "Happy Clients",
    "number_size": { "size": 56, "unit": "px" },
    "number_color": "#ffffff",
    "title_color": "rgba(255,255,255,0.80)",
    "title_size": { "size": 16, "unit": "px" }
  },
  "elements": []
}
```

### 7. Testimonial
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "testimonial", "isInner": false,
  "settings": {
    "testimonial_content": "This service completely exceeded my expectations. Professional, friendly, and outstanding results. I highly recommend them to everyone!",
    "testimonial_image": { "url": "https://picsum.photos/seed/person1/120/120", "id": "" },
    "testimonial_name": "Sarah Johnson",
    "testimonial_job": "Satisfied Customer",
    "testimonial_alignment": "center",
    "content_color": "#444444",
    "name_color": "#1a1a2e",
    "job_color": "#e94560"
  },
  "elements": []
}
```

### 8. Divider
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "divider", "isInner": false,
  "settings": {
    "style": "solid",
    "weight": { "size": 2, "unit": "px" },
    "color": "#e8e8e8",
    "width": { "size": 60, "unit": "%" },
    "align": "center",
    "gap": { "size": 20, "unit": "px" }
  },
  "elements": []
}
```

### 9. Spacer
```json
{
  "id": "<ID>", "elType": "widget", "widgetType": "spacer", "isInner": false,
  "settings": {
    "space": { "size": 40, "unit": "px" }
  },
  "elements": []
}
```

---

## PREMIUM SECTION PATTERNS

### HERO SECTION — gradient background, centered, full height
Root container settings for hero:
```json
{
  "min_height": { "size": 100, "unit": "vh" },
  "flex_direction": "column",
  "flex_justify_content": "center",
  "flex_align_items": "center",
  "padding": { "top": "100", "bottom": "100", "left": "20", "right": "20", "unit": "px", "isLinked": false },
  "background_background": "gradient",
  "background_color": "#0f1428",
  "background_color_b": "#1a2a5e",
  "background_gradient_type": "linear",
  "background_gradient_angle": { "size": 135, "unit": "deg" }
}
```
Hero must contain: h1 heading (white, 64px, 800 weight) → subtitle text (white 80% opacity, 20px) → spacer 20px → button

### FEATURES SECTION — 3 icon-box cards in a row
Root container: white or #f5f6fa background, 100px padding.
Inner row container: flex_direction "row", flex_gap 24px.
3 inner columns (isInner:true): each 33% width, white bg, 32px padding, border-radius 16px, box-shadow.
Each column contains ONE icon-box widget.

### STATS SECTION — counters on accent background
Root container: accent color bg (e.g. #e94560 or dark gradient), 80px padding.
Inner row: flex_direction "row", flex_justify_content "space-around".
4 inner columns (isInner:true): each 22% width.
Each contains ONE counter widget (white number, light title).

### TWO-COLUMN CONTENT SECTION
Root container: white or soft bg, 100px padding, flex_direction "row".
Left inner (55% width): heading (h2, dark, 42px 700) → spacer → paragraph text → spacer → button.
Right inner (40% width): image widget (full width, rounded 12px).

### TESTIMONIALS SECTION — 3 testimonials in a row
Root container: #f5f6fa background, 100px padding.
Section heading + divider + spacer at top.
Inner row: flex_direction "row", flex_gap 24px.
3 inner columns (isInner:true): each 33% width, white bg, 32px padding, rounded 16px, box-shadow.
Each column: ONE testimonial widget.

### CTA SECTION — dark, full width, centered (ALWAYS LAST)
Root container: dark gradient (#0f1428 → #1a1a2e), 100px padding, centered.
Contains: h2 heading (white, centered) → spacer 16px → paragraph (white 70% opacity) → spacer 24px → button (accent bg).

---

## FONT AWESOME ICONS — choose contextually appropriate icons

Medical/Health: fas fa-tooth, fas fa-heartbeat, fas fa-stethoscope, fas fa-user-md, fas fa-procedures, fas fa-pills
Business/Finance: fas fa-chart-line, fas fa-handshake, fas fa-briefcase, fas fa-award, fas fa-piggy-bank, fas fa-coins
Technology: fas fa-code, fas fa-laptop-code, fas fa-shield-alt, fas fa-rocket, fas fa-cloud, fas fa-microchip
Food/Restaurant: fas fa-utensils, fas fa-pizza-slice, fas fa-coffee, fas fa-glass-cheers, fas fa-concierge-bell
Real Estate: fas fa-home, fas fa-building, fas fa-key, fas fa-map-marker-alt, fas fa-city, fas fa-ruler-combined
Education: fas fa-graduation-cap, fas fa-book-open, fas fa-chalkboard-teacher, fas fa-lightbulb, fas fa-certificate
General: fas fa-star, fas fa-check-circle, fas fa-users, fas fa-clock, fas fa-phone, fas fa-envelope, fas fa-globe

---

## IMAGES — Always use picsum.photos

Format: https://picsum.photos/seed/{keyword}/{width}/{height}

Choose keywords matching the page content:
- Hero/Banner: 1920x900 (e.g., .../seed/hero-dental/1920/900)
- Section background: 1920x600
- Feature/card image: 600x400
- Profile/testimonial: 120x120 (e.g., .../seed/person-female/120/120)
- About section: 800x600

Use DIFFERENT seed keywords for each image so they all look unique (person1, person2, office-interior, clinic, team-photo, etc.)

---

## GLOBAL STYLES — use these exact values when styling elements

$colors_str

$typography_str

---

## CRITICAL CONSTRAINTS

1. Root array elements MUST be `"elType": "container"` with `"isInner": false`
2. Nested containers MUST have `"isInner": true`
3. Widgets always have `"elType": "widget"` and a valid `widgetType`
4. All IDs must be exactly 7 chars, unique, lowercase alphanumeric
5. NEVER include shortcodes, PHP, or `<script>` tags
6. Use global colors wherever appropriate — do NOT invent random hex codes unless no globals are defined
7. Every image URL MUST use picsum.photos — NEVER use via.placeholder.com
8. Every feature/service section MUST use icon-box widgets — never plain heading+text
9. The page MUST end with a CTA section on a dark or accent background
10. Minimum 5 sections for new pages — always include: hero, features, stats, testimonials, CTA
PROMPT;
    }

    // ── User Message ──────────────────────────────────────────────────────────

    private function get_user_message(
        string $mode,
        string $user_prompt,
        string $current_json
    ): string {
        if ( 'create' === $mode || empty( $current_json ) || '[]' === $current_json ) {
            return $this->create_message( $user_prompt );
        }
        return $this->modify_message( $user_prompt, $current_json );
    }

    private function create_message( string $user_prompt ): string {
        return <<<MSG
## TASK: CREATE PREMIUM PAGE

Build a complete, visually stunning Elementor page based on this description:
"{$user_prompt}"

MANDATORY SECTION ORDER (include ALL of these):
1. **HERO** — Full viewport height, gradient background, large h1 title, subtitle, CTA button
2. **FEATURES/SERVICES** — 3 icon-box cards in a row, light gray background section
3. **STATS** — 3–4 counter widgets on an accent-colored background (build credibility with numbers)
4. **ABOUT / WHY US** — Two-column layout: compelling text on the left, real image on the right
5. **TESTIMONIALS** — 3 testimonial widgets in a row on a soft background
6. **CTA** — Dark gradient background, centered h2, subtitle, prominent button

Design rules:
- Infer the industry/niche from the description and choose matching icons, image keywords, and copy
- Write real, professional placeholder copy in the same language as the user prompt
- Use picsum.photos with descriptive seed keywords matching the content
- Apply box-shadow to all cards (inner containers in feature/testimonial sections)
- Accent color for icons, buttons, and stats numbers — use global colors if defined
- Every section heading should have a short supporting subtext beneath it

Respond ONLY with the JSON object as specified.
MSG;
    }

    private function modify_message( string $user_prompt, string $current_json ): string {
        $json_preview = $this->maybe_truncate_json( $current_json );

        return <<<MSG
## TASK: MODIFY EXISTING PAGE

Here is the CURRENT Elementor page JSON:

{$json_preview}

## USER INSTRUCTION
"{$user_prompt}"

Requirements:
- Apply ONLY the changes the user requested — preserve all other elements
- Keep existing IDs unchanged; generate new 7-character IDs only for NEW elements
- If adding new sections, follow the premium design patterns (icon-box for features, picsum.photos for images, etc.)
- Return the COMPLETE modified page JSON (not just the changed parts)
- Respond ONLY with the JSON object as specified.
MSG;
    }

    // ── Formázók ──────────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return '### Global Colors: none defined — use a professional palette: primary #1a1a2e, accent #e94560, text #5a6a7a, light bg #f5f6fa.';
        }
        $lines = [ '### Global Colors' ];
        foreach ( $colors as $c ) {
            $lines[] = sprintf(
                '- %s (ID: %s): %s',
                $c['label'] ?? 'Unknown',
                $c['id']    ?? '',
                $c['value'] ?? ''
            );
        }
        return implode( "\n", $lines );
    }

    private function format_typography( array $typography ): string {
        if ( empty( $typography ) ) {
            return '### Global Typography: none defined — use system sans-serif, headings bold (700–800), body 16–18px, line-height 1.6–1.7.';
        }
        $lines = [ '### Global Typography' ];
        foreach ( $typography as $t ) {
            $parts = [ sprintf( '- %s (ID: %s)', $t['label'] ?? 'Unknown', $t['id'] ?? '' ) ];
            if ( ! empty( $t['family'] ) )  $parts[] = 'Font: ' . $t['family'];
            if ( ! empty( $t['size'] ) )    $parts[] = 'Size: ' . $t['size'] . 'px';
            if ( ! empty( $t['weight'] ) )  $parts[] = 'Weight: ' . $t['weight'];
            $lines[] = implode( ' | ', $parts );
        }
        return implode( "\n", $lines );
    }

    private function maybe_truncate_json( string $json, int $max_chars = 12000 ): string {
        if ( mb_strlen( $json ) <= $max_chars ) {
            return $json;
        }
        $half = (int) ( $max_chars / 2 );
        $head = mb_substr( $json, 0, $half );
        $tail = mb_substr( $json, -$half );
        return $head . "\n... [TRUNCATED FOR LENGTH] ...\n" . $tail;
    }
}
