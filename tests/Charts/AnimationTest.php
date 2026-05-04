<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Theme;
use PHPUnit\Framework\TestCase;

final class AnimationTest extends TestCase
{
    // ── Builder API ───────────────────────────────────────────────────────────

    public function test_animate_returns_self_for_chaining(): void
    {
        $chart = Chart::line([1, 2, 3]);
        self::assertSame($chart, $chart->animate());
    }

    public function test_animate_off_by_default_no_animation_css(): void
    {
        $svg = Chart::line([1, 2, 3])->render();
        self::assertStringNotContainsString('svgraph-draw-line', $svg);
        self::assertStringNotContainsString('prefers-reduced-motion:no-preference', $svg);
    }

    public function test_animate_false_disables_animation(): void
    {
        $svg = Chart::line([1, 2, 3])->animate(false)->render();
        self::assertStringNotContainsString('svgraph-draw-line', $svg);
    }

    // ── Reduced-motion gate ───────────────────────────────────────────────────

    public function test_animation_wrapped_in_no_preference_media_query(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();
        self::assertStringContainsString('prefers-reduced-motion:no-preference', $svg);
    }

    public function test_animation_keyframes_only_inside_no_preference_block(): void
    {
        // Animation keyframes must live inside the no-preference block, not at top level.
        $svg = Chart::bar(['A' => 1, 'B' => 2])->animate()->render();
        // The keyframe name must appear inside the no-preference media query.
        self::assertMatchesRegularExpression(
            '/prefers-reduced-motion:no-preference\)\{[^}]*svgraph-grow-vbar/',
            $svg,
        );
    }

    // ── Theme animation tokens ────────────────────────────────────────────────

    public function test_animation_emits_anim_dur_custom_property(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();
        self::assertStringContainsString('--svgraph-anim-dur:0.6s', $svg);
    }

    public function test_animation_emits_anim_ease_custom_property(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();
        self::assertStringContainsString('--svgraph-anim-ease:ease-out', $svg);
    }

    public function test_with_animation_overrides_theme_tokens(): void
    {
        $svg = Chart::bar(['A' => 1])
            ->animate()
            ->theme(Theme::default()->withAnimation('0.4s', 'linear'))
            ->render();
        self::assertStringContainsString('--svgraph-anim-dur:0.4s', $svg);
        self::assertStringContainsString('--svgraph-anim-ease:linear', $svg);
    }

    public function test_with_animation_invalid_duration_falls_back(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->animate()
            ->theme(Theme::default()->withAnimation('fast', 'ease-out'))
            ->render();
        // Invalid duration falls back to the CSS default value.
        self::assertStringContainsString('--svgraph-anim-dur:0.6s', $svg);
    }

    public function test_with_animation_invalid_easing_falls_back(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->animate()
            ->theme(Theme::default()->withAnimation('0.5s', 'bounce;color:red'))
            ->render();
        self::assertStringContainsString('--svgraph-anim-ease:ease-out', $svg);
    }

    public function test_with_animation_preserves_other_theme_properties(): void
    {
        $base = Theme::default()->withPalette('#123456');
        $themed = $base->withAnimation('1s', 'linear');
        self::assertSame(['#123456'], $themed->palette);
        self::assertSame('1s', $themed->animationDuration);
        self::assertSame('linear', $themed->animationEasing);
    }

    // ── Line chart animation ──────────────────────────────────────────────────

    public function test_line_animation_emits_draw_line_keyframe(): void
    {
        $svg = Chart::line([1, 2, 3, 4])->animate()->render();
        self::assertStringContainsString('svgraph-draw-line', $svg);
        self::assertStringContainsString('stroke-dashoffset', $svg);
    }

    public function test_line_animation_adds_path_length_attribute(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();
        self::assertStringContainsString('pathLength="1"', $svg);
    }

    public function test_line_animation_adds_line_path_class(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();
        self::assertMatchesRegularExpression('/class="[^"]*svgraph-line-path[^"]*"/', $svg);
    }

    public function test_line_animation_targets_correct_variant_class(): void
    {
        $svg = Chart::line([1, 2, 3])->animate()->render();
        self::assertStringContainsString('.svgraph--line .svgraph-line-path', $svg);
    }

    public function test_sparkline_animation_targets_sparkline_variant(): void
    {
        $svg = Chart::sparkline([1, 2, 3])->animate()->render();
        self::assertStringContainsString('.svgraph--sparkline .svgraph-line-path', $svg);
        self::assertStringContainsString('pathLength="1"', $svg);
    }

    public function test_line_without_animate_has_no_path_length(): void
    {
        $svg = Chart::line([1, 2, 3])->render();
        self::assertStringNotContainsString('pathLength', $svg);
        self::assertStringNotContainsString('svgraph-line-path', $svg);
    }

    // ── Bar chart animation ───────────────────────────────────────────────────

    public function test_vertical_bar_animation_emits_grow_vbar_keyframe(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->animate()->render();
        self::assertStringContainsString('svgraph-grow-vbar', $svg);
        self::assertStringContainsString('scaleY(0)', $svg);
    }

    public function test_vertical_bar_animation_emits_transform_origin_property(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->animate()->render();
        self::assertStringContainsString('--svgraph-bar-tfo', $svg);
        self::assertStringContainsString('center bottom', $svg);
    }

    public function test_vertical_bar_negative_value_uses_center_top_origin(): void
    {
        $svg = Chart::bar(['A' => 5, 'B' => -3])->animate()->render();
        self::assertStringContainsString('center bottom', $svg);
        self::assertStringContainsString('center top', $svg);
    }

    public function test_vertical_bar_animation_emits_stagger_delay(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2, 'C' => 3])->animate()->render();
        self::assertStringContainsString('--svgraph-bar-delay:0s', $svg);
        self::assertStringContainsString('--svgraph-bar-delay:0.08s', $svg);
        self::assertStringContainsString('--svgraph-bar-delay:0.16s', $svg);
    }

    public function test_horizontal_bar_animation_emits_grow_hbar_keyframe(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->horizontal()->animate()->render();
        self::assertStringContainsString('svgraph-grow-hbar', $svg);
        self::assertStringContainsString('scaleX(0)', $svg);
    }

    public function test_horizontal_bar_animation_adds_bar_h_variant_class(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->horizontal()->animate()->render();
        self::assertStringContainsString('svgraph--bar-h', $svg);
    }

    public function test_horizontal_bar_animation_emits_left_center_origin(): void
    {
        $svg = Chart::bar(['A' => 5, 'B' => 8])->horizontal()->animate()->render();
        self::assertStringContainsString('left center', $svg);
    }

    public function test_horizontal_bar_negative_uses_right_center_origin(): void
    {
        $svg = Chart::bar(['A' => 5, 'B' => -3])->horizontal()->animate()->render();
        self::assertStringContainsString('left center', $svg);
        self::assertStringContainsString('right center', $svg);
    }

    public function test_non_animated_bar_has_no_bar_h_variant(): void
    {
        $svg = Chart::bar(['A' => 1])->horizontal()->render();
        self::assertStringNotContainsString('svgraph--bar-h', $svg);
    }

    public function test_bar_animation_scaleY_inside_no_preference_media(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])->animate()->render();
        // scaleY must appear only inside @media (prefers-reduced-motion:no-preference).
        self::assertMatchesRegularExpression(
            '/prefers-reduced-motion:no-preference\)[^<]*scaleY/',
            $svg,
        );
    }

    // ── Pie / donut chart animation ───────────────────────────────────────────

    public function test_pie_animation_emits_sweep_keyframe(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 30, 'C' => 20])->animate()->render();
        self::assertStringContainsString('svgraph-pie-sweep', $svg);
        self::assertStringContainsString('stroke-dasharray', $svg);
    }

    public function test_pie_animation_uses_stroke_circles_not_paths(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 30, 'C' => 20])->animate()->render();
        // Animated pie uses circles (stroke-based) instead of filled paths.
        self::assertSame(0, substr_count($svg, '<path '));
        self::assertGreaterThanOrEqual(3, substr_count($svg, '<circle '));
    }

    public function test_pie_animation_circles_have_stroke_dasharray(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 50])->animate()->render();
        self::assertMatchesRegularExpression('/stroke-dasharray="[0-9.]+ [0-9.]+"/', $svg);
    }

    public function test_pie_animation_circles_have_stroke_dashoffset(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 50])->animate()->render();
        self::assertStringContainsString('stroke-dashoffset=', $svg);
    }

    public function test_pie_animation_circles_have_pop_vectors(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 50])->animate()->render();
        self::assertMatchesRegularExpression('/style="--pop-x:[^"]+--pop-y:[^"]+"/', $svg);
    }

    public function test_pie_animation_circles_have_pie_custom_properties(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 50])->animate()->render();
        self::assertStringContainsString('--svgraph-pie-off:', $svg);
        self::assertStringContainsString('--svgraph-pie-len:', $svg);
        self::assertStringContainsString('--svgraph-pie-circ:', $svg);
    }

    public function test_pie_animation_emits_stagger_delay_on_slices(): void
    {
        $svg = Chart::pie(['A' => 1, 'B' => 1, 'C' => 1])->animate()->render();
        // Slice 0: 0s, slice 1: 0.08s, slice 2: 0.16s
        self::assertStringContainsString('animation-delay:0s', $svg);
        self::assertStringContainsString('animation-delay:0.08s', $svg);
        self::assertStringContainsString('animation-delay:0.16s', $svg);
    }

    public function test_pie_animation_single_slice_uses_stroke_circle(): void
    {
        $svg = Chart::pie(['Only' => 100])->animate()->render();
        // Single-slice animated pie also uses stroke-circle rendering.
        self::assertStringContainsString('stroke-dasharray=', $svg);
        self::assertStringContainsString('fill="none"', $svg);
        self::assertStringContainsString('stroke=', $svg);
    }

    public function test_donut_animation_uses_stroke_circles(): void
    {
        $svg = Chart::donut(['A' => 3, 'B' => 1])->animate()->render();
        self::assertStringContainsString('svgraph-pie-sweep', $svg);
        self::assertStringContainsString('--svgraph-pie-len:', $svg);
    }

    public function test_pie_animation_targets_correct_variant(): void
    {
        $svg = Chart::pie(['A' => 1, 'B' => 1])->animate()->render();
        self::assertStringContainsString('.svgraph--pie circle[class^="series-"]', $svg);
    }

    public function test_donut_animation_targets_donut_variant(): void
    {
        $svg = Chart::donut(['A' => 1, 'B' => 1])->animate()->render();
        self::assertStringContainsString('.svgraph--donut circle[class^="series-"]', $svg);
    }

    public function test_non_animated_pie_still_uses_paths(): void
    {
        $svg = Chart::pie(['A' => 50, 'B' => 30, 'C' => 20])->render();
        self::assertSame(3, substr_count($svg, '<path '));
        self::assertStringNotContainsString('stroke-dasharray', $svg);
    }

    public function test_pie_animation_with_gap_renders_reduced_arc(): void
    {
        $noGap = Chart::pie(['A' => 1, 'B' => 1])->animate()->render();
        $withGap = Chart::pie(['A' => 1, 'B' => 1])->gap(5)->animate()->render();
        // Gaps produce different dasharray values.
        self::assertNotSame($noGap, $withGap);
    }

    public function test_pie_animation_with_legend_still_renders(): void
    {
        $svg = Chart::pie(['Stripe' => 100, 'PayPal' => 50])->legend()->animate()->render();
        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('svgraph-pie-sweep', $svg);
    }

    // ── Hover pop still works on animated pie circles ─────────────────────────

    public function test_hover_pop_css_includes_circle_selectors_for_pie(): void
    {
        // Even non-animated pies get the circle selector in hover CSS (for
        // the single-slice case and future animated usage).
        $svg = Chart::pie(['A' => 1, 'B' => 1])->render();
        self::assertStringContainsString('.svgraph--pie circle[class^="series-"]:hover', $svg);
    }

    public function test_reduced_motion_suppresses_pop_on_pie_circles(): void
    {
        $svg = Chart::pie(['A' => 1, 'B' => 1])->render();
        self::assertMatchesRegularExpression(
            '/prefers-reduced-motion:reduce\).*circle\[class\^="series-"\].*transform:none/s',
            $svg,
        );
    }

    // ── No animation on empty charts ──────────────────────────────────────────

    public function test_empty_line_chart_with_animate_no_animation_css(): void
    {
        $svg = Chart::line([])->animate()->render();
        self::assertStringNotContainsString('svgraph-draw-line', $svg);
        self::assertStringNotContainsString('prefers-reduced-motion:no-preference', $svg);
    }

    public function test_empty_bar_chart_with_animate_no_animation_css(): void
    {
        $svg = Chart::bar([])->animate()->render();
        self::assertStringNotContainsString('svgraph-grow-vbar', $svg);
    }

    public function test_empty_pie_chart_with_animate_no_animation_css(): void
    {
        $svg = Chart::pie([])->animate()->render();
        self::assertStringNotContainsString('svgraph-pie-sweep', $svg);
    }
}
