# CLAUDE.md

Guidance for Claude Code in this repository. Deep references live under `.claude/docs/`.

## What this is

Symfony bundle (`msstc4symfony/metrics-bridge-logic`, namespace `Msstc4Symfony\MetricsBridgeLogic`, extension alias
`msstc4symfony_metrics_bridge_logic`, no configuration tree) that connects `msstc4symfony/logic-bundle` to
`msstc4symfony/metrics-bundle`: every Action run becomes an observation of the
`logic_action_duration_histogram_seconds` histogram, labelled by Action name and outcome. PHP >= 8.2, Symfony 7.4 / 8.x.

## Common commands

Develop against the CI profile: `COMPOSER=composer-ci.json composer install` (needs ext-redis).

- `make check` — `php -l`, PHPStan level 10, PHP-CS-Fixer, `composer validate --strict`,
  `composer audit`, Rector dry-run, deptrac. Run as `COMPOSER=composer-ci.json make check`.
- `make test` — unit + integration suites; `make infection`, `make fix`.
- `php ../bundle-standard/bin/verify-standard.php .` — standard compliance (the same check CI runs).

## Architecture in 60 seconds

- `Infrastructure\Metrics\LogicMetric` — the single declaration of the metric (name, labels, buckets).
- `Infrastructure\Metrics\ActionDurationCollector` (extends metrics' `AbstractCollector`) — swallows and logs
  any failure, including the metric lookup metrics-bundle does not guard.
- `Infrastructure\Metrics\MetricsMiddleware` — logic-bundle `ActionMiddlewareInterface`, priority 400 (outside
  logging 300); name from `ActionNameResolver`, outcome from `ActionOutcome::of()`, rethrows the same exception;
  any recording failure (a throwing name builder included) goes to `ActionDurationCollector::reportFailure()`.
- `DependencyInjection\Compiler\RegisterLogicMetricsPass` appends the enum to `msstc4symfony_metrics.metric_enums`;
  without MetricsBundle it throws.

Details: `.claude/docs/architecture.md`. **Read `.claude/docs/known-issues.md` before chasing a
"weird" failure.**

## Pointers

- `.claude/docs/architecture.md` — layers, wiring, service ids.
- `.claude/docs/conventions.md` — layout, tests, commits.
- `.claude/docs/known-issues.md` — bridge and family-wide Symfony/CI traps.
