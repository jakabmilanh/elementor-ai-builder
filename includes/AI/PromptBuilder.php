<?php
/**
 * Prompt összeállítása – szekciónkénti generálás.
 * 1. lépés: oldal terv (section type lista)
 * 2. lépés: minden szekciót külön kér az AI-tól
 *
 * @package AIE\AI
 */

namespace AIE\AI;

defined( 'ABSPATH' ) || exit;

class PromptBuilder {

    private const VALID_SECTION_TYPES = [
        'hero-split', 'features-3', 'stats-4', 'about-2col',
        'process-3steps', 'testimonials-3', 'pricing-3', 'faq', 'cta-dark',
    ];

    // ── Page plan ────────────────────────────────────────────────────────────

    public function build_plan( string $user_prompt ): array {
        $types_list = implode( ', ', self::VALID_SECTION_TYPES );
        $system = <<<SYS
You are a website architect selecting the optimal sections for a premium landing page.

Available section types: {$types_list}

Section descriptions:
- hero-split: Full-height hero with split layout (text left, image right), statistics
- features-3: Three main services/features as icon cards
- stats-4: Four key statistics/numbers as counters
- about-2col: About/Why Choose Us, two-column (text + image)
- process-3steps: How it works, 3 numbered steps
- testimonials-3: Three client testimonials with star ratings
- pricing-3: Three pricing plans (only if relevant to the business)
- faq: Frequently asked questions with accordion
- cta-dark: Dark CTA section — ALWAYS the last section

Rules:
- ALWAYS start with hero-split
- ALWAYS end with cta-dark
- Choose 7–9 sections total
- Choose sections most relevant to the specific industry and request
- Include pricing-3 only if the business logically has service plans
- Return ONLY a valid JSON array of section type strings

Example output: ["hero-split","features-3","stats-4","about-2col","process-3steps","testimonials-3","faq","cta-dark"]
SYS;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user',   'content' => "Select the best sections for this landing page: \"{$user_prompt}\"\n\nReturn ONLY a JSON array, nothing else." ],
        ];
    }

    // ── Single section ────────────────────────────────────────────────────────

    public function build_section(
        string $section_type,
        string $user_prompt,
        array  $global_styles,
        bool   $has_pro
    ): array {
        $colors_str = $this->format_colors( $global_styles['colors'] ?? [] );
        $example    = $this->get_section_example( $section_type, $has_pro );
        $rules      = $this->get_section_rules( $section_type, $has_pro );
        $user_msg   = $this->get_section_user_message( $section_type, $user_prompt, $has_pro );

        $system = <<<SYS
You are a senior Elementor developer generating ONE premium Elementor section.

## OUTPUT FORMAT — CRITICAL
Respond with ONLY a single root container JSON object: {"id":"...","elType":"container","isInner":false,...}
- Do NOT wrap in an array
- Do NOT add {"elementor_data": ...} wrapper
- No markdown fences, no explanation
- Pure JSON, starting with { and ending with }

## ELEMENTOR STRUCTURE
- Root section: elType=container, isInner=false
- Columns/rows inside: elType=container, isInner=true
- Widgets: elType=widget, valid widgetType, elements=[]
- IDs: EXACTLY 7 chars, unique, lowercase alphanumeric — generate random-looking IDs like "a3f9k2m"
- flex_direction: "row" for horizontal, "column" for vertical

## DESIGN SYSTEM
- Dark gradient: background_color #0a0e27 → background_color_b #1a2a5e, gradient_angle 135deg
- Light section: background_color #f5f6fa
- White section: background_color #ffffff
- Accent color: #e94560
- Dark text: #1a1a2e | Muted text: #5a6a7a
- Section label: 12px, weight 700, uppercase, letter-spacing 2px, color #e94560
- h2: 44px weight 700 | h3: 22px weight 700 | body: 17px line-height 1.7
- Card style: white bg, border_radius 16px, box_shadow {horizontal:0,vertical:8,blur:32,spread:0,color:"rgba(0,0,0,0.08)"}
- Section padding top/bottom: 100px standard | hero/CTA: 120px

## IMAGE RULES — CRITICAL
- NEVER set background_image on any container (solid or gradient only)
- ALL images MUST use the image widget with this URL format:
  https://loremflickr.com/{width}/{height}/{keyword1},{keyword2},{keyword3}
- Keywords MUST match the page industry and content (e.g., dental,dentist,clinic for a dental page)
- Portrait/avatar images: 120x120 with keywords like portrait,professional,person
- Section images: 700x560 | Hero image: 750x620

{$colors_str}

## SECTION-SPECIFIC RULES
{$rules}

## REFERENCE EXAMPLE
Study this for structure, settings keys, and premium patterns. You are FREE to improve and adapt:
{$example}
SYS;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user',   'content' => $user_msg ],
        ];
    }

    // ── Modify mode ───────────────────────────────────────────────────────────

    public function build_modify(
        string $user_prompt,
        string $current_json,
        array  $global_styles
    ): array {
        $colors_str     = $this->format_colors( $global_styles['colors'] ?? [] );
        $json_preview   = $this->maybe_truncate_json( $current_json );

        $system = <<<SYS
You are a senior Elementor developer modifying an existing page.

OUTPUT: {"elementor_data":[...complete modified page...]}
No markdown. Pure JSON only.

RULES:
- Apply ONLY the requested changes, preserve everything else
- Keep all existing IDs; new elements get new unique 7-char IDs
- NEVER use background_image on containers
- ALL images: image widget with loremflickr.com URL
- Return the COMPLETE modified JSON

{$colors_str}
SYS;

        return [
            [ 'role' => 'system', 'content' => $system ],
            [ 'role' => 'user', 'content' => "Current JSON:\n{$json_preview}\n\nInstruction: \"{$user_prompt}\"\n\nReturn ONLY the complete modified JSON." ],
        ];
    }

    // ── Section examples (TemplateLibrary alapján) ────────────────────────────

    private function get_section_example( string $section_type, bool $has_pro ): string {
        TemplateLibrary::reset_ids();

        $ex = match ( $section_type ) {
            'hero-split'     => TemplateLibrary::hero_split( [
                'label'         => '// PROFESSIONAL SERVICES',
                'h1'            => 'Premium Services That Deliver Real Results',
                'subtitle'      => 'Over a decade of experience helping clients achieve their goals.',
                'button_text'   => 'Get Started Today',
                'stat_1_number' => 1500, 'stat_1_suffix' => '+', 'stat_1_label' => 'Happy Clients',
                'stat_2_number' => 10,   'stat_2_suffix' => '+', 'stat_2_label' => 'Years Experience',
                'stat_3_number' => 98,   'stat_3_suffix' => '%', 'stat_3_label' => 'Satisfaction Rate',
                'image_seed'    => 'professional,business,team',
            ] ),
            'features-3'     => TemplateLibrary::features_3( [
                'section_label'    => '// OUR SERVICES',
                'section_h2'       => 'Everything You Need to Succeed',
                'section_subtitle' => 'Comprehensive solutions designed around your specific goals.',
                'card_1_icon'      => 'fas fa-chart-line',
                'card_1_title'     => 'Strategic Planning',
                'card_1_desc'      => 'Data-driven strategies that align with your goals and drive measurable growth.',
                'card_2_icon'      => 'fas fa-handshake',
                'card_2_title'     => 'Expert Consultation',
                'card_2_desc'      => 'Personalized guidance from seasoned professionals with years of experience.',
                'card_3_icon'      => 'fas fa-award',
                'card_3_title'     => 'Proven Results',
                'card_3_desc'      => 'A consistent track record of measurable outcomes and satisfied clients.',
            ], $has_pro ),
            'stats-4'        => TemplateLibrary::stats_4( [
                'stat_1_number' => 2500, 'stat_1_suffix' => '+', 'stat_1_label' => 'Satisfied Clients',
                'stat_2_number' => 15,   'stat_2_suffix' => '+', 'stat_2_label' => 'Years in Business',
                'stat_3_number' => 98,   'stat_3_suffix' => '%', 'stat_3_label' => 'Client Satisfaction',
                'stat_4_number' => 50,   'stat_4_suffix' => '+', 'stat_4_label' => 'Awards Won',
            ] ),
            'about-2col'     => TemplateLibrary::about_2col( [
                'section_label'    => '// WHY CHOOSE US',
                'section_h2'       => 'A Team You Can Trust',
                'section_subtitle' => 'We combine expertise with a genuine commitment to your success.',
                'benefit_1'        => 'Industry-leading expertise and knowledge',
                'benefit_2'        => 'Personalized approach for every client',
                'benefit_3'        => 'Transparent process and clear communication',
                'benefit_4'        => 'Proven track record with 2500+ clients',
                'button_text'      => 'Learn More About Us',
                'image_seed'       => 'professional,office,team',
            ] ),
            'process-3steps' => TemplateLibrary::process_3steps( [
                'section_label'    => '// HOW IT WORKS',
                'section_h2'       => 'Simple. Effective. Results-Driven.',
                'section_subtitle' => 'Our proven 3-step process makes getting started easy.',
                'step_1_title'     => 'Initial Consultation',
                'step_1_desc'      => 'We begin with a thorough assessment of your needs and goals.',
                'step_2_title'     => 'Custom Strategy',
                'step_2_desc'      => 'Our experts develop a tailored plan designed for your specific situation.',
                'step_3_title'     => 'Ongoing Results',
                'step_3_desc'      => 'We implement, monitor, and continuously optimize for the best outcomes.',
            ] ),
            'testimonials-3' => TemplateLibrary::testimonials_3( [
                'section_label'    => '// CLIENT REVIEWS',
                'section_h2'       => 'What Our Clients Say',
                'section_subtitle' => 'Real experiences from people who have trusted us with their goals.',
                'test_1_text'      => 'Absolutely exceeded every expectation. The team is professional, caring, and genuinely invested in your success. I cannot recommend them highly enough.',
                'test_1_name'      => 'Sarah Johnson',
                'test_1_job'       => 'Marketing Director',
                'test_1_seed'      => 'portrait,woman,professional',
                'test_2_text'      => 'The results speak for themselves. From day one I knew I was in good hands. Outstanding service and remarkable expertise throughout.',
                'test_2_name'      => 'Michael Thompson',
                'test_2_job'       => 'Business Owner',
                'test_2_seed'      => 'portrait,man,professional',
                'test_3_text'      => 'I\'ve worked with many firms before, but none compare to this level of dedication and quality. Truly exceptional experience.',
                'test_3_name'      => 'Emily Rodriguez',
                'test_3_job'       => 'Senior Manager',
                'test_3_seed'      => 'portrait,woman,executive',
            ] ),
            'faq'            => TemplateLibrary::faq_accordion( [
                'section_label'    => '// FREQUENTLY ASKED',
                'section_h2'       => 'Got Questions? We Have Answers.',
                'section_subtitle' => 'Everything you need to know before getting started.',
                'button_text'      => 'Ask Us Anything',
                'q1'               => 'How do I get started?',
                'a1'               => 'Simply contact us through the form above or give us a call. We\'ll schedule a free initial consultation to discuss your needs.',
                'q2'               => 'What does your service cost?',
                'a2'               => 'Pricing depends on your specific requirements. After our initial consultation, we provide a detailed, transparent quote with no hidden fees.',
                'q3'               => 'How long does the process take?',
                'a3'               => 'Timelines vary based on the scope of work. Most projects begin showing results within the first few weeks.',
                'q4'               => 'Do you offer a guarantee?',
                'a4'               => 'Yes. We stand behind our work 100%. If you\'re not satisfied, we\'ll work tirelessly to make it right.',
            ] ),
            'cta-dark'       => TemplateLibrary::cta_dark( [
                'label'       => '// TAKE THE NEXT STEP',
                'h2'          => 'Ready to Transform Your Results?',
                'subtitle'    => 'Join thousands of satisfied clients. Your success story starts with one conversation.',
                'button_text' => 'Book a Free Consultation',
            ] ),
            default          => '{}',
        };

        return json_encode( $ex, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    // ── Section design rules ──────────────────────────────────────────────────

    private function get_section_rules( string $section_type, bool $has_pro ): string {
        return match ( $section_type ) {
            'hero-split' => <<<R
- Root container: content_width="full", background=dark gradient (#0a0e27→#1a2a5e), min_height={size:100,unit:"vh"}
- Inner row container: content_width="boxed", flex_direction="row", gap=60px, padding top/bottom 100px
- Left column (52%): flex_direction=column, items flex-start
  → Section label text-editor (accent, 12px, uppercase)
  → h1 widget (white, 62px, 800 weight, line-height 1.1)
  → subtitle text-editor (rgba(255,255,255,0.72), 18px, 1.7lh)
  → button widget (accent bg #e94560, white text, padding 16px 40px, radius 8px)
  → Stats row: inner container flex-direction=row, 3 counter widgets (white number 40px, label 13px)
- Right column (44%): image widget with loremflickr.com, 750x620, industry keywords, border-radius 20px
R,
            'features-3' => $has_pro
                ? <<<R
- Root: content_width="boxed", background #f5f6fa, padding top/bottom 100px
- Section intro block: centered label + h2 (44px) + subtitle (17px)
- Card row: flex-direction=row, gap=28px, 3 flip-box PRO widgets (not icon-box!)
- flip-box cards: white front, accent back (#e94560), border-radius 16px
- Front: industry-relevant FA icon (48px, accent), title h3 (20px), short description
- Back: detailed description + "Book Now" / "Learn More" button
R
                : <<<R
- Root: content_width="boxed", background #f5f6fa, padding top/bottom 100px
- Section intro block: centered label + h2 (44px) + subtitle (17px)
- Card row inner container: flex-direction=row, gap=28px
- 3 card inner containers (each ~30% width):
  → white bg, border-radius 16px, padding 36px, box-shadow
  → icon-box widget: industry-specific FA icon (44px, accent #e94560), title (20px 700w), 2-sentence description
R,
            'stats-4' => <<<R
- Root: content_width="full", accent gradient bg (#e94560→#c0233e, 135deg), padding top/bottom 80px
- Inner row: content_width="boxed", flex-direction=row, justify=space-around
- 4 stat containers: flex-direction=column, flex-align=center
  → counter widget: starting_number=0, ending_number=INTEGER, suffix, title
  → number_color=#ffffff, number_size=52px weight 800
  → title_color=rgba(255,255,255,0.70), title_size=14px
R,
            'about-2col' => <<<R
- Root: content_width="boxed", white bg, padding top/bottom 100px
- Row inner: flex-direction=row, gap=64px
- Left column (50%): flex-direction=column, items flex-start
  → Section label (left-aligned, accent, 12px uppercase)
  → h2 (left-aligned, dark, 40px 700w)
  → subtitle text-editor (muted, 17px)
  → icon-list widget: 4 benefit items with fa-check-circle icons (accent color)
  → button (accent bg, padding 14px 36px)
- Right column (46%): image widget loremflickr.com 700x560, border-radius 20px, box-shadow
R,
            'process-3steps' => <<<R
- Root: content_width="boxed", bg #f5f6fa, padding top/bottom 100px
- Section intro: centered label + h2 + subtitle
- Card row: flex-direction=row, gap=28px
- 3 step cards (each ~30%):
  → white bg, border-radius 16px, padding 40px, box-shadow
  → Step number: text-editor "01" / "02" / "03" (accent color, 52px, 800w)
  → h3 heading (step title, dark, 22px)
  → text-editor description (muted, 15px, 1.65lh)
R,
            'testimonials-3' => <<<R
- Root: content_width="boxed", white bg, padding top/bottom 100px
- Section intro: centered label + h2 + subtitle
- Card row: flex-direction=row, gap=28px
- 3 testimonial cards (each ~30%):
  → white bg, border-radius 16px, padding 36px, box-shadow
  → star-rating widget (5 stars, gold #f5a623, 18px, align=left)
  → testimonial widget: real 2-3 sentence quote, avatar image (loremflickr 120x120 portrait keywords), name, job
  → content_color=#5a6a7a, name_color=#1a1a2e, job_color=#e94560
R,
            'pricing-3' => <<<R
- Root: content_width="boxed", bg #f5f6fa, padding top/bottom 100px
- Section intro: centered label + h2 + subtitle
- Card row: flex-direction=row, gap=28px
- 3 pricing cards (each ~30%), middle card featured (accent bg or different style)
- Each card: white bg, border-radius 16px, padding 40px, box-shadow
  → Plan name heading h3 (dark, 22px)
  → Price: large text-editor (accent color, 52px, 800w, "$ / month")
  → Features: icon-list with check-circle icons, 4-5 features
  → button (accent bg, full-width)
R,
            'faq' => <<<R
- Root: content_width="boxed", bg #f5f6fa, padding top/bottom 100px
- Row inner: flex-direction=row, gap=64px
- Left (38%): flex-direction=column, items flex-start
  → Section label + h2 (38px) + subtitle + button
- Right (57%): accordion widget
  → 4-5 real, industry-relevant Q&A items
  → title_color=#1a1a2e, icon_color=#e94560, active_color=#e94560, content_color=#5a6a7a
R,
            'cta-dark' => <<<R
- Root: content_width="boxed", dark gradient bg (#0a0e27→#1a1a2e, 135deg), padding top/bottom 120px
- flex_direction=column, flex_align=center, text_align=center
- Elements (centered):
  → Label text-editor (accent, 12px, uppercase, letter-spacing 2px)
  → h2 heading (white, 48px, 800w, centered)
  → subtitle text-editor (rgba(255,255,255,0.72), 18px, centered)
  → button (accent bg #e94560, white text, size xl, padding 18px 48px)
R,
            default => '- Follow standard Elementor container best practices.',
        };
    }

    // ── Section user messages ─────────────────────────────────────────────────

    private function get_section_user_message( string $section_type, string $user_prompt, bool $has_pro ): string {
        $pro_note = $has_pro
            ? 'Elementor Pro IS active.'
            : 'Elementor Pro is NOT active — use only Free widgets.';

        $content_guide = match ( $section_type ) {
            'hero-split'     => "Content to generate:\n- Section label: short uppercase tagline specific to this industry\n- H1 headline: max 7 powerful words, specific to the business\n- Subtitle: 1-2 sentences expanding on the headline\n- CTA button text: action-oriented\n- 3 relevant statistics for this industry (with realistic numbers)\n- Hero image: loremflickr.com 750x620 with 3 industry-specific keywords",
            'features-3'     => "Content to generate:\n- Section label + h2 + subtitle\n- 3 main services or features of this specific business\n- Each card: industry-specific Font Awesome icon, short title, 2-sentence description\n- Icons must match the specific industry (e.g., fas fa-tooth for dental, fas fa-chart-line for finance)",
            'stats-4'        => "Content to generate:\n- 4 impressive statistics relevant to this specific business/industry\n- Use realistic numbers (not too round, not too extreme)\n- Examples for dental: patients treated, years experience, satisfaction rate, procedures\n- ending_number MUST be an integer (no quotes)",
            'about-2col'     => "Content to generate:\n- 'Why Choose Us' or 'About Us' framing specific to this business\n- 4 real benefits/differentiators (not generic)\n- CTA button text\n- Image: loremflickr.com 700x560 with industry keywords (office/team/professional)",
            'process-3steps' => "Content to generate:\n- 'How it works' or booking/treatment process for this specific industry\n- 3 concrete, real steps a new client would go through\n- Each step: specific title and 2-sentence description of what happens",
            'testimonials-3' => "Content to generate:\n- 3 realistic testimonials specific to this industry and service\n- Each quote: 2-3 sentences, mentions a specific benefit or result\n- Real-sounding names (mix of genders, fits the local culture of the prompt language)\n- Portrait images: loremflickr.com 120x120 with 'portrait,professional' keywords",
            'pricing-3'      => "Content to generate:\n- 3 pricing tiers relevant to this specific business type\n- Plan names (e.g., Basic / Professional / Premium or similar)\n- Realistic prices for this industry\n- 4-5 features per plan that reflect actual service differences",
            'faq'            => "Content to generate:\n- 4-5 real questions actual customers of this business would ask\n- Helpful, informative answers (2-4 sentences each)\n- Questions about: pricing, process, what to expect, guarantees, first steps",
            'cta-dark'       => "Content to generate:\n- Compelling headline (max 8 words) creating urgency or excitement\n- Subtitle: 1-2 sentences with social proof or reassurance\n- CTA button: action-oriented, specific to this business",
            default          => '',
        };

        return <<<MSG
Generate a premium {$section_type} section for this business: "{$user_prompt}"

{$content_guide}

Important:
- Write ALL text in the SAME LANGUAGE as the business description above
- Content must be specific to this industry — no generic placeholder text
- {$pro_note}
- Image URLs: https://loremflickr.com/{{width}}/{{height}}/{{keyword1}},{{keyword2}},{{keyword3}} — use specific industry keywords

Respond with ONLY the single root container JSON object.
MSG;
    }

    // ── Formázók ─────────────────────────────────────────────────────────────

    private function format_colors( array $colors ): string {
        if ( empty( $colors ) ) {
            return '## Site Colors: use defaults — dark #1a1a2e, accent #e94560, muted #5a6a7a, light #f5f6fa';
        }
        $lines = [ '## Site Global Colors (use these exactly)' ];
        foreach ( $colors as $c ) {
            $lines[] = '- ' . ( $c['label'] ?? 'Color' ) . ': ' . ( $c['value'] ?? '' );
        }
        return implode( "\n", $lines );
    }

    private function format_typography( array $typography ): string {
        if ( empty( $typography ) ) {
            return '';
        }
        $lines = [ '## Site Typography' ];
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
