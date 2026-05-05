# Contributing to svgraph

Thanks for your interest. This document covers the developer workflow:
how to set up, what tools the package uses, and how each fits into
day-to-day development.

## Project values

Three rules govern almost every decision:

1. **Zero runtime dependencies.** `composer require noeka/svgraph` must
   only pull in PHP itself. Anything you'd add to `require` needs a strong
   case; default to inlining or rejecting. `require-dev` is fair game.
2. **PHP 8.3+ only.** New code uses modern idioms (readonly classes,
   constructor property promotion, enums, first-class callable syntax).
3. **No JavaScript.** Charts render as static SVG markup. Hover, focus,
   and animation are CSS-only. Nothing produced by this package may
   require a JS runtime.

## Requirements

- PHP 8.3 or newer
- [Composer](https://getcomposer.org/) 2.x
- A PHP coverage driver (`pcov` or `xdebug`) — only required for
  `composer mutate` and the coverage step locally; CI installs `pcov`.

## Setup

```bash
git clone https://github.com/noeka/svgraph.git
cd svgraph
composer install
composer check
```

`composer check` runs the same gating checks CI runs (PHPStan, PHP-CS-Fixer,
Rector, PHPUnit). It should pass on a clean checkout.

## Developer tools

Every tool below is in `require-dev` — none ship to consumers of the
package.

| Tool | Purpose | Command |
|---|---|---|
| **PHPUnit** | Unit & integration tests for chart rendering | `composer test` |
| **PHPStan** | Static analysis at level 10 (strictest) | `composer lint` |
| **PHP-CS-Fixer** | Code style (PER-CS 2.0 + strict types) | `composer cs` / `composer cs:fix` |
| **Rector** | Automated PHP 8.3 modernization & code-quality fixes | `composer rector` / `composer rector:fix` |
| **Infection** | Mutation testing — surfaces weak assertions | `composer mutate` |
| **spatie/phpunit-snapshot-assertions** | Snapshot/approval testing for SVG output | used inside test files |
| **roave/security-advisories** | Blocks `composer install` if any dep has a known CVE | passive — runs on every install |

### PHPUnit (`composer test`)

Tests live under `tests/`, mirroring the `src/` namespace layout. Most
tests assert on rendered SVG strings — either via direct
`assertStringContainsString` checks, regex matches on attributes, or
snapshot assertions for full markup.

To run a single test class:

```bash
vendor/bin/phpunit tests/Charts/ChartRenderingTest.php
```

To generate a coverage report locally (requires pcov/xdebug):

```bash
vendor/bin/phpunit --coverage-html build/coverage
```

CI gates on **90% line coverage** of `src/`. Don't lower this threshold.

### PHPStan (`composer lint`)

Configured at level 10 in `phpstan.neon`. Both `src/` and `tests/` are
analyzed. New code must pass with no errors and no baseline entries —
fix the type issue rather than silencing it.

### PHP-CS-Fixer (`composer cs`, `composer cs:fix`)

`composer cs` checks; `composer cs:fix` applies. Configuration is in
`.php-cs-fixer.php`. The repo follows PER-CS 2.0 with `declare_strict_types`
and strict param/return rules. Run `cs:fix` before committing if you've
been moving code around.

### Rector (`composer rector`, `composer rector:fix`)

`composer rector` runs in dry-run mode and fails if any rule would
change a file — this gates CI. `composer rector:fix` applies the fixes.
Rules are configured in `rector.php` (PHP 8.3 set + code-quality, dead-code,
type-declaration, early-return).

If you add code that Rector flags, the usual flow is:

```bash
composer rector:fix    # apply suggestions
composer cs:fix        # re-format if needed
composer check         # confirm everything passes
```

### Infection (`composer mutate`)

Mutation testing on `src/`. Configured in `infection.json5`; results are
written to `build/infection/`. Infection requires a coverage driver —
install `pcov` (faster) or `xdebug` first.

```bash
composer mutate
```

This is **not run on every push** — it's slow and the project hasn't
established a baseline MSI yet. Use it locally when changing math/geometry
code (`src/Geometry/`, `src/Data/Series.php`) to find assertions that
don't actually constrain behavior. A mutator that survives is a hint
that the test suite would not catch a real regression at that line.

If you don't have a coverage driver installed locally, there's an
opt-in `Infection (manual)` workflow at
`.github/workflows/infection.yml`. Trigger it from the Actions tab via
"Run workflow" — the score lands in the job summary and the full reports
attach as a build artifact.

### Snapshot assertions

`spatie/phpunit-snapshot-assertions` is available for any test that wants
to assert on full SVG output rather than fragments:

```php
use Spatie\Snapshots\MatchesSnapshots;

final class MyChartTest extends \PHPUnit\Framework\TestCase
{
    use MatchesSnapshots;

    public function test_renders_expected_svg(): void
    {
        $svg = (string) Chart::line(['Mon' => 12, 'Tue' => 27]);
        $this->assertMatchesSnapshot($svg);
    }
}
```

First run writes the expected fixture under `tests/__snapshots__/`;
subsequent runs diff against it. **Review snapshot diffs in code review
the same as any other change** — they're the contract for downstream
output. Delete a `.txt` snapshot to regenerate it.

Reach for snapshots when you're testing the shape of the whole SVG.
Keep `assertStringContainsString` for narrow per-attribute checks where
the rest of the markup is incidental.

### roave/security-advisories

This is a virtual `require-dev` package. Every `composer install` /
`composer update` is checked against the [FriendsOfPHP advisories
database](https://github.com/FriendsOfPHP/security-advisories); install
fails if any installed dep version has a known CVE. There's nothing to
run — it just protects the dev surface.

## CI

`.github/workflows/ci.yml` runs on push and PR against `main`. The matrix
covers PHP 8.3, 8.4, 8.5. Each job runs:

1. `composer install`
2. `composer lint` — PHPStan
3. `composer cs` — PHP-CS-Fixer
4. `composer rector` — Rector dry-run
5. `vendor/bin/phpunit` with coverage
6. 90% line-coverage gate

Mutation testing and image regeneration are **not** in CI.

## Regenerating example images

The SVGs in `docs/images/` are generated from runnable PHP scripts in
`examples/`. After any change that affects rendered output, regenerate:

```bash
composer docs:images
```

Commit the regenerated SVGs alongside the code change so docs stay in sync.

## Pull requests

- Branch from `main`.
- Run `composer check` before pushing — CI will run the same checks.
- Keep changes focused. Bug fixes shouldn't include surrounding
  refactors; new features shouldn't include unrelated cleanup.
- If you change rendered output, regenerate `docs/images/` (see above).
- New runtime dependencies will be rejected unless there's a clear
  justification. Dev dependencies are fine when they pull weight.
