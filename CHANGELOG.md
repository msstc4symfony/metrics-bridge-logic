# Changelog

## 2.0.0-rc.1 - 2026-10-20

- Changed: requires metrics-bundle ^2.0 (`^2.0@rc` until metrics 2.0.0 is final): `LogicMetric` implements
  `Application\Metric\MetricDefinitionInterface`; `ActionDurationCollector` records through `MetricRecorderInterface`
  instead of extending the removed `AbstractCollector`; logic-bundle ^1.3.
- Changed: a metric the recorder cannot write is logged to metrics-bundle's `metrics_bundle` channel instead of the
  application channel, so `symfony_error` no longer counts it. Metric name, labels and buckets are unchanged.
- Changed: `RegisterLogicMetricsPass` moved to `DependencyInjection\CompilerPass`; it, the collector and the middleware
  are `@internal` (README "Public API": the bundle class and `LogicMetric`).
- Changed: PHPStan enforces `#[\Override]`; adopts bundle-standard v3; declares `monolog/monolog` ^3.5 and `psr/log`,
  which it uses directly.
- Upgrading from 1.x: upgrade metrics-bundle to 2.0; the bridge has no configuration.

## 1.1.0 - 2026-10-14

- Changed: requires PHP 8.2 instead of 8.4 (Symfony 8 still needs PHP 8.4).
- Changed: class constants no longer declare a native type (PHP 8.2 has none); values are unchanged.
- Changed: requires logic-bundle ^1.2 and metrics-bundle ^1.4, their PHP 8.2 releases.

## 1.0.0 - 2026-10-12

- First release: `symfony_logic_action_duration_histogram_seconds{action, outcome}` recorded around every logic-bundle Action.
