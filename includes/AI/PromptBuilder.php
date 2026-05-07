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
You are an expert Elementor page builder JSON generator (Elementor 3.30+).

## YOUR ONLY OUTPUT FORMAT
Respond ONLY with this exact JSON shape:
{
  "elementor_data": [ ...array of root container elements... ]
}

No explanations. No markdown fences. No extra keys. Pure JSON.

## ELEMENTOR JSON STRUCTURE — CONTAINER MODE (PREFERRED, v3.30+)

### Hierarchy
Container (root) → Container (inner, optional) → Widget

### Container template
```json
{
  "id": "<7_CHAR_ID>",
  "elType": "container",
  "isInner": false,
  "settings": {
    "content_width":      "boxed",
    "width":              { "size": 100, "unit": "%" },
    "min_height":         { "size": 0, "unit": "px" },
    "flex_direction":     "row",
    "flex_gap":           { "size": 20, "unit": "px", "column": "20", "row": "20" },
    "flex_justify_content": "center",
    "flex_align_items":   "center",
    "padding":            { "top": "60", "bottom": "60", "left": "20", "right": "20", "unit": "px", "isLinked": false },
    "background_background": "classic",
    "background_color":   "#ffffff"
  },
  "elements": [ ...child containers or widgets... ]
}
```

### Widget template
```json
{
  "id": "<7_CHAR_ID>",
  "elType": "widget",
  "isInner": false,
  "settings": { ... },
  "elements": [],
  "widgetType": "<heading|text-editor|button|image|...>"
}
```

### ID generation rules
- Each element MUST have a unique 7-character alphanumeric ID (lowercase letters + digits).
- Examples of valid IDs: "a1b2c3d", "f7e3a0b", "9c1d4e2"
- Never reuse IDs within the same page.

### Common widget examples

**Heading:**
```json
{
  "id": "a1b2c3d",
  "elType": "widget",
  "settings": {
    "title": "Your headline",
    "header_size": "h1",
    "align": "center",
    "title_color": "#1a1a2e",
    "typography_typography": "custom",
    "typography_font_size": { "size": 48, "unit": "px" },
    "typography_font_weight": "700"
  },
  "elements": [],
  "widgetType": "heading"
}
```

**Text editor:**
```json
{
  "id": "b2c3d4e",
  "elType": "widget",
  "settings": {
    "editor": "<p>Body paragraph here.</p>",
    "align": "left",
    "text_color": "#444444"
  },
  "elements": [],
  "widgetType": "text-editor"
}
```

**Button:**
```json
{
  "id": "c3d4e5f",
  "elType": "widget",
  "settings": {
    "text": "Get Started",
    "link": { "url": "#", "is_external": false, "nofollow": false },
    "align": "center",
    "size": "lg",
    "background_color": "#e94560",
    "button_text_color": "#ffffff",
    "border_radius": { "size": 6, "unit": "px", "top": "6", "right": "6", "bottom": "6", "left": "6", "isLinked": true }
  },
  "elements": [],
  "widgetType": "button"
}
```

**Image:**
```json
{
  "id": "d4e5f6a",
  "elType": "widget",
  "settings": {
    "image": { "url": "https://via.placeholder.com/600x400", "id": "" },
    "image_size": "large",
    "align": "center"
  },
  "elements": [],
  "widgetType": "image"
}
```

### LAYOUT PATTERNS

**Two-column layout (text left, image right):**
Use a root container with `flex_direction: "row"`, then 2 inner containers (`isInner: true`) each with `width: { size: 50, unit: "%" }`.

**Vertical stack (hero section):**
Use a root container with `flex_direction: "column"` and `flex_align_items: "center"`.

**Grid of cards (3 columns):**
Use a root container with `flex_direction: "row"` and `flex_wrap: "wrap"`, then 3+ inner containers each with `width: { size: 33.33, unit: "%" }`.

## GLOBAL STYLES (use these exact hex values when styling)
$colors_str

$typography_str

## CRITICAL CONSTRAINTS
1. Root array MUST contain only `"elType": "container"` elements with `"isInner": false`.
2. Inner containers (nested) must have `"isInner": true`.
3. Widgets always have `"elType": "widget"` and a valid `widgetType`.
4. All IDs must be exactly 7 characters, unique, lowercase alphanumeric.
5. Use the global colors above whenever appropriate.
6. NEVER include WordPress shortcodes, PHP code, or `<script>` tags.
7. Keep settings minimal — omit defaults.
8. For images, use `https://via.placeholder.com/...` URLs as placeholders.
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
## TASK: CREATE NEW PAGE

Build a complete Elementor page layout based on this description:

"{$user_prompt}"

Requirements:
- Create a visually appealing, multi-section page using CONTAINER mode
- Include 3–5 root containers (e.g., hero, features, testimonial, CTA, footer-like)
- Use the global color palette
- Make it responsive-friendly (use percentage widths for inner containers)
- Every element must have a unique 7-character ID

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
- Return the COMPLETE modified page JSON (not just the changed parts)
- Respond ONLY with the JSON object as specified.
MSG;
    }

    // ── Formázók ──────────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return '### Global Colors: none defined — use a tasteful neutral palette.';
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
            return '### Global Typography: none defined — use system defaults (sans-serif).';
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
