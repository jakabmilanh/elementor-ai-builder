<?php
/**
 * Prompt összeállítása az Elementor JSON generálásához / módosításához.
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    /**
     * Összeállítja az OpenAI messages tömböt.
     *
     * @param  string $mode           'create' | 'modify'
     * @param  string $user_prompt    Felhasználó utasítása
     * @param  string $current_json   Meglévő Elementor JSON (üres ha nincs)
     * @param  array  $global_styles  Globális színek és tipográfia
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
You are an expert Elementor page builder JSON generator.

## YOUR ONLY OUTPUT FORMAT
You MUST respond ONLY with a valid JSON object in this exact shape:
{
  "elementor_data": [ ...array of section elements... ]
}

No explanations. No markdown fences. No extra keys. Pure JSON.

## ELEMENTOR JSON STRUCTURE RULES

### Hierarchy
Every page consists of: Section → Column → Widget

### Element template
```json
{
  "id": "<7_CHAR_ID>",
  "elType": "section|column|widget",
  "isInner": false,
  "settings": {},
  "elements": [],
  "widgetType": "<only for widgets>"
}
```

### ID generation
- Each element MUST have a unique 7-character alphanumeric ID (lowercase letters and digits only).
- Example valid IDs: "a1b2c3d", "f7e3a0b", "9c1d4e2"
- Never reuse IDs within the same page.

### elType values
- `"section"` — top-level row; contains columns
- `"column"`  — inside a section; contains widgets
- `"widget"`  — leaf node; has a `widgetType` field

### Common widgetType values
| widgetType       | Description         |
|------------------|---------------------|
| heading          | H1–H6 title         |
| text-editor      | Rich text / body    |
| image            | Image block         |
| button           | CTA button          |
| icon-box         | Icon + title + text |
| divider          | Horizontal rule     |
| spacer           | Vertical space      |
| video            | Embedded video      |

### Section settings (important keys)
```json
{
  "layout":           "boxed",
  "content_width":    { "size": 1140, "unit": "px" },
  "padding":          { "top": "80", "bottom": "80", "left": "20", "right": "20", "unit": "px", "isLinked": false },
  "background_color": "#ffffff"
}
```

### Column settings
```json
{
  "_column_size": 50,
  "padding": { "top": "20", "bottom": "20", "left": "20", "right": "20", "unit": "px", "isLinked": false }
}
```

### Heading widget example
```json
{
  "id": "a1b2c3d",
  "elType": "widget",
  "isInner": false,
  "settings": {
    "title": "Your headline here",
    "header_size": "h2",
    "align": "center",
    "typography_typography": "custom",
    "typography_font_size": { "size": 42, "unit": "px" },
    "typography_font_weight": "700",
    "title_color": "#1a1a2e"
  },
  "elements": [],
  "widgetType": "heading"
}
```

### Text editor widget example
```json
{
  "id": "b2c3d4e",
  "elType": "widget",
  "isInner": false,
  "settings": {
    "editor": "<p>Your paragraph text here.</p>",
    "align": "left",
    "text_color": "#444444",
    "typography_font_size": { "size": 18, "unit": "px" }
  },
  "elements": [],
  "widgetType": "text-editor"
}
```

### Button widget example
```json
{
  "id": "c3d4e5f",
  "elType": "widget",
  "isInner": false,
  "settings": {
    "text": "Get Started",
    "link": { "url": "#", "is_external": false, "nofollow": false },
    "button_type": "info",
    "align": "center",
    "size": "lg",
    "background_color": "#e94560",
    "button_text_color": "#ffffff",
    "border_radius": { "size": 6, "unit": "px" }
  },
  "elements": [],
  "widgetType": "button"
}
```

## GLOBAL STYLES (use these exact values when styling)
$colors_str

$typography_str

## IMPORTANT CONSTRAINTS
1. The root array MUST contain only `"elType": "section"` elements.
2. Each section MUST have at least one column.
3. Each column MUST have at least one widget.
4. All IDs must be exactly 7 characters, unique, lowercase alphanumeric.
5. Use the global color values listed above for backgrounds and text whenever appropriate.
6. Do NOT include WordPress shortcodes or PHP code.
7. Keep settings minimal — only include keys that have non-default values.
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

Build a complete Elementor page layout based on the following description:

"{$user_prompt}"

Requirements:
- Create a visually appealing, multi-section page
- Include a hero section, at least 2–3 content sections, and a call-to-action section
- Use the global color palette defined in the system prompt
- Ensure the layout is responsive-friendly (use appropriate column widths)
- Every element must have a unique 7-character ID

Respond ONLY with the JSON object as specified.
MSG;
    }

    private function modify_message( string $user_prompt, string $current_json ): string {
        // JSON rövidítése, ha nagyon hosszú (token limit)
        $json_preview = $this->maybe_truncate_json( $current_json );

        return <<<MSG
## TASK: MODIFY EXISTING PAGE

Here is the CURRENT Elementor page JSON:

{$json_preview}

## USER INSTRUCTION
"{$user_prompt}"

Requirements:
- Apply ONLY the changes the user requested — preserve all other sections/widgets
- Keep existing IDs unchanged; generate new 7-character IDs only for NEW elements
- Return the COMPLETE modified page JSON (not just the changed parts)
- Respond ONLY with the JSON object as specified.
MSG;
    }

    // ── Formázók ──────────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return 'No global colors defined.';
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
            return 'No global typography defined.';
        }

        $lines = [ '### Global Typography' ];
        foreach ( $typography as $t ) {
            $parts = [ sprintf( '- %s (ID: %s)', $t['label'] ?? 'Unknown', $t['id'] ?? '' ) ];
            if ( ! empty( $t['family'] ) ) {
                $parts[] = 'Font: ' . $t['family'];
            }
            if ( ! empty( $t['size'] ) ) {
                $parts[] = 'Size: ' . $t['size'] . 'px';
            }
            if ( ! empty( $t['weight'] ) ) {
                $parts[] = 'Weight: ' . $t['weight'];
            }
            $lines[] = implode( ' | ', $parts );
        }

        return implode( "\n", $lines );
    }

    /**
     * Ha a JSON túl hosszú, levágjuk a közepét, hogy beleferjen a token ablakba.
     */
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
