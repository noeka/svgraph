<?php

declare(strict_types=1);

namespace Noeka\Svgraph\Tests\Charts;

use Noeka\Svgraph\Chart;
use Noeka\Svgraph\Data\Series;
use PHPUnit\Framework\TestCase;

/**
 * Coverage for the CSS-only toggle legend (issue #10).
 *
 * The legend is built from a hidden checkbox per series and a `<label>` per
 * series; CSS rules in the chart's <style> block hide `series-{N}` elements
 * when the matching checkbox is unchecked, with no JavaScript involved.
 */
final class LegendTest extends TestCase
{
    // ── Markup structure ──────────────────────────────────────────────────────

    public function test_line_legend_renders_one_input_and_label_per_series(): void
    {
        $svg = Chart::line(['Mon' => 10, 'Tue' => 20])
            ->addSeries(Series::of('Costs', ['Mon' => 5, 'Tue' => 8]))
            ->legend()
            ->render();

        // Two checkboxes — one per series — both checked by default.
        self::assertSame(2, substr_count($svg, '<input '));
        self::assertSame(2, substr_count($svg, 'class="svgraph-toggle"'));
        self::assertSame(2, substr_count($svg, ' checked'));
        // Two <label> entries.
        self::assertSame(2, substr_count($svg, '<label '));
        self::assertStringContainsString('class="svgraph-legend"', $svg);
    }

    public function test_line_legend_uses_series_name_when_present(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('Costs', [4, 5, 6]))
            ->legend()
            ->render();

        // First series has no name → fallback "Series 1"; second has "Costs".
        self::assertStringContainsString('Series 1', $svg);
        self::assertStringContainsString('Costs', $svg);
    }

    public function test_line_legend_label_for_matches_input_id(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('B', [3, 4]))
            ->legend()
            ->render();

        // Each label's `for` references its checkbox `id` — required by the
        // checkbox-trick toggle pattern.
        self::assertMatchesRegularExpression(
            '/<input[^>]*id="(svgraph-\d+-s0)"[^>]*>.*<label[^>]*for="\1"/s',
            $svg,
        );
        self::assertMatchesRegularExpression(
            '/<input[^>]*id="(svgraph-\d+-s1)"[^>]*>.*<label[^>]*for="\1"/s',
            $svg,
        );
    }

    public function test_legend_input_id_collisions_avoided_across_charts(): void
    {
        $a = Chart::line([1, 2])->addSeries(Series::of('B', [3, 4]))->legend()->render();
        $b = Chart::line([1, 2])->addSeries(Series::of('B', [3, 4]))->legend()->render();

        $extractId = static function (string $svg): string {
            if (preg_match('/id="(svgraph-\d+)-s0"/', $svg, $match) !== 1) {
                self::fail('No svgraph chart ID found in legend markup.');
            }
            return $match[1];
        };
        self::assertNotSame($extractId($a), $extractId($b));
    }

    public function test_legend_swatch_uses_resolved_series_color(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('Two', [3, 4], '#ff00ff'))
            ->legend()
            ->render();

        // Default palette colour for series 0 plus the explicit colour for series 1.
        self::assertStringContainsString('background:#3b82f6', $svg);
        self::assertStringContainsString('background:#ff00ff', $svg);
    }

    public function test_legend_aria_label_set_to_series_name(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('Visits', [3, 4]))
            ->legend()
            ->render();

        // Hidden checkbox needs an accessible name; we use the series name.
        self::assertStringContainsString('aria-label="Visits"', $svg);
    }

    // ── Toggle CSS rules ──────────────────────────────────────────────────────

    public function test_legend_emits_unchecked_hide_rule_per_series(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('B', [3, 4]))
            ->legend()
            ->render();

        // Each series gets a `:not(:checked) ~ .svgraph__chart .series-N { display:none }` rule.
        self::assertMatchesRegularExpression(
            '/#svgraph-\d+-s0:not\(:checked\)~\.svgraph__chart \.series-0\{display:none;\}/',
            $svg,
        );
        self::assertMatchesRegularExpression(
            '/#svgraph-\d+-s1:not\(:checked\)~\.svgraph__chart \.series-1\{display:none;\}/',
            $svg,
        );
    }

    public function test_legend_emits_unchecked_dim_rule_per_series(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('B', [3, 4]))
            ->legend()
            ->render();

        // Unchecked entries are dimmed via opacity.
        self::assertMatchesRegularExpression(
            '/#svgraph-\d+-s0:not\(:checked\)~\.svgraph-legend label\[for="svgraph-\d+-s0"\]\{opacity:0\.4;\}/',
            $svg,
        );
    }

    public function test_legend_emits_focus_visible_outline_for_keyboard_users(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('B', [3, 4]))
            ->legend()
            ->render();

        // Keyboard focus on the hidden input must surface visually on its label.
        self::assertMatchesRegularExpression(
            '/#svgraph-\d+-s0:focus-visible~\.svgraph-legend label\[for="svgraph-\d+-s0"\]\{outline:2px/',
            $svg,
        );
    }

    public function test_legend_visually_hides_checkbox(): void
    {
        $svg = Chart::line([1, 2])->legend()->render();

        // Visually-hidden pattern keeps the checkbox keyboard-accessible.
        self::assertStringContainsString('.svgraph-toggle{position:absolute;width:1px;height:1px;', $svg);
    }

    // ── Wrapper restructuring ────────────────────────────────────────────────

    public function test_legend_introduces_inner_chart_wrapper(): void
    {
        // Without legend the SVG sits directly inside .svgraph; with legend
        // it nests inside .svgraph__chart so the legend can flow below.
        $without = Chart::line([1, 2, 3])->render();
        $with = Chart::line([1, 2, 3])->legend()->render();

        self::assertStringNotContainsString('svgraph__chart', $without);
        self::assertStringContainsString('class="svgraph__chart"', $with);
        self::assertStringContainsString('padding-bottom:', $with);
    }

    public function test_no_legend_keeps_outer_padding_bottom(): void
    {
        // Backward compat: when legend is off, the .svgraph div itself
        // still carries padding-bottom for the responsive wrapper.
        $svg = Chart::line([1, 2, 3])->aspect(2.0)->render();
        self::assertMatchesRegularExpression(
            '/<div class="svgraph[^"]*" style="position:relative;width:100%;padding-bottom:50%/',
            $svg,
        );
    }

    public function test_legend_off_by_default(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [4, 5, 6]))
            ->render();

        self::assertStringNotContainsString('svgraph-legend', $svg);
        self::assertStringNotContainsString('svgraph-toggle', $svg);
        self::assertStringNotContainsString('<input ', $svg);
    }

    public function test_legend_can_be_disabled_explicitly(): void
    {
        $svg = Chart::line([1, 2])->legend(true)->legend(false)->render();
        self::assertStringNotContainsString('svgraph-legend', $svg);
    }

    // ── Single-series ─────────────────────────────────────────────────────────

    public function test_legend_works_with_single_series(): void
    {
        $svg = Chart::line(['Mon' => 10, 'Tue' => 20])->legend()->render();

        self::assertSame(1, substr_count($svg, '<input '));
        self::assertSame(1, substr_count($svg, '<label '));
    }

    // ── BarChart integration ──────────────────────────────────────────────────

    public function test_bar_legend_renders_per_series(): void
    {
        $svg = Chart::bar(['Q1' => 10, 'Q2' => 20])
            ->addSeries(Series::of('Costs', ['Q1' => 5, 'Q2' => 8]))
            ->legend()
            ->render();

        self::assertSame(2, substr_count($svg, 'class="svgraph-toggle"'));
        self::assertStringContainsString('Series 1', $svg);
        self::assertStringContainsString('Costs', $svg);
    }

    public function test_bar_legend_works_horizontal(): void
    {
        $svg = Chart::bar(['A' => 1, 'B' => 2])
            ->addSeries(Series::of('Two', ['A' => 3, 'B' => 4]))
            ->horizontal()
            ->legend()
            ->render();

        self::assertSame(2, substr_count($svg, 'class="svgraph-toggle"'));
        self::assertStringContainsString('class="svgraph-legend"', $svg);
    }

    public function test_bar_legend_stacked_targets_per_series_classes(): void
    {
        $svg = Chart::bar(['Q1' => 10])
            ->addSeries(Series::of('Costs', ['Q1' => 5]))
            ->stacked()
            ->legend()
            ->render();

        // Toggle rules must target the same `.series-N` classes the bars use.
        self::assertMatchesRegularExpression(
            '/:not\(:checked\)~\.svgraph__chart \.series-0\{display:none;\}/',
            $svg,
        );
        self::assertMatchesRegularExpression(
            '/:not\(:checked\)~\.svgraph__chart \.series-1\{display:none;\}/',
            $svg,
        );
    }

    // ── Output-safety ─────────────────────────────────────────────────────────

    public function test_legend_escapes_series_name(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('<script>x</script>', [3, 4]))
            ->legend()
            ->render();

        self::assertStringNotContainsString('<script>x</script>', $svg);
        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $svg);
    }

    public function test_legend_swatch_falls_back_when_color_invalid(): void
    {
        $svg = Chart::line([1, 2])
            ->addSeries(Series::of('Bad', [3, 4], 'red;background:url(x)'))
            ->legend()
            ->render();

        // Regression: the swatch interpolates the colour into a CSS `style`
        // attribute. Css::color must reject the injection so the swatch
        // falls back to a safe value rather than leaking `url(x)` into CSS.
        self::assertStringContainsString('<span class="svgraph-legend__swatch" style="background:currentColor;"', $svg);
        self::assertStringNotContainsString('background:red;background:url(x)', $svg);
    }

    // ── Co-existence with other features ──────────────────────────────────────

    public function test_legend_with_animation_renders_both(): void
    {
        $svg = Chart::line([1, 2, 3])
            ->addSeries(Series::of('B', [4, 5, 6]))
            ->animate()
            ->legend()
            ->render();

        self::assertStringContainsString('svgraph-line-path', $svg);
        self::assertStringContainsString('class="svgraph-legend"', $svg);
    }

    public function test_legend_with_axes_and_grid(): void
    {
        $svg = Chart::line(['Mon' => 1, 'Tue' => 2, 'Wed' => 3])
            ->addSeries(Series::of('B', ['Mon' => 4, 'Tue' => 5, 'Wed' => 6]))
            ->axes()
            ->grid()
            ->legend()
            ->render();

        // Axis labels still render inside the inner chart wrapper.
        self::assertStringContainsString('svgraph__chart', $svg);
        self::assertStringContainsString('svgraph__labels', $svg);
        self::assertStringContainsString('svgraph-legend', $svg);
    }
}
