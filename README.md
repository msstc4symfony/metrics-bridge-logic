# msstc4symfony/metrics-bridge-logic

![Build Status](https://github.com/msstc4symfony/metrics-bridge-logic/actions/workflows/checks.yml/badge.svg?branch=main)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

Records every [logic-bundle](https://github.com/msstc4symfony/logic-bundle) Action run as an
observation of a Prometheus histogram of [metrics-bundle](https://github.com/msstc4symfony/metrics-bundle):
how long it took and how it ended.

## Compatibility

| Bundle | PHP  | Symfony  | logic-bundle | metrics-bundle |
|--------|------|----------|--------------|----------------|
| 1.1+   | 8.2+ | 7.4, 8.x | ^1.2         | ^1.4           |
| 1.0    | 8.4+ | 7.4, 8.x | ^1.1         | ^1.3           |

Symfony 8.x needs PHP 8.4; on PHP 8.2 and 8.3 Composer installs Symfony 7.4.

## Installation

The packages are not on Packagist yet, so register their GitHub repositories first:

```sh
composer config repositories.msstc4symfony-logic vcs https://github.com/msstc4symfony/logic-bundle
composer config repositories.msstc4symfony-metrics vcs https://github.com/msstc4symfony/metrics-bundle
composer config repositories.msstc4symfony-metrics-bridge-logic vcs https://github.com/msstc4symfony/metrics-bridge-logic
composer require msstc4symfony/metrics-bridge-logic
```

Register the bridge in `config/bundles.php` alongside the two bundles it connects (order does not matter):

```php
Msstc4Symfony\MetricsBundle\MetricsBundle::class => ['all' => true],
Msstc4Symfony\LogicBundle\LogicBundle::class => ['all' => true],
Msstc4Symfony\MetricsBridgeLogic\MetricsBridgeLogicBundle::class => ['all' => true],
```

Nothing to configure. Without MetricsBundle the container fails to compile with a message naming it.

## The metric

| Name                                               | Type      | Labels                                           |
|----------------------------------------------------|-----------|--------------------------------------------------|
| `symfony_logic_action_duration_histogram_seconds`  | histogram | `application`, `component`, `action`, `outcome`  |

- Buckets (seconds): `0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10, 30`.
- `application` / `component` come from the metrics-bundle configuration.
- `outcome` is `success`, `expected_failure` (an `ExpectedFailureInterface` exception or Symfony's
  `ValidationFailedException`) or `error` (any other exception), as logic-bundle's `ActionOutcome` classifies it.
- `action` is the name logic-bundle uses in its logs (`ActionNameResolver`): the Action class, unless an
  `ActionNameBuilderInterface` service supports it. Register one to get short, stable names such as `order.create`.

The bridge is an Action middleware at priority 400, outside logging (300), validation (200) and lock (100):
the duration covers the whole pipeline the caller waits for, lock wait included. A failure to record the metric
is logged as an error and never fails the Action or replaces its exception.

## PromQL

Success rate per Action:

```promql
sum by (action) (rate(symfony_logic_action_duration_histogram_seconds_count{outcome="success"}[5m]))
  / sum by (action) (rate(symfony_logic_action_duration_histogram_seconds_count[5m]))
```

p95 latency per Action:

```promql
histogram_quantile(0.95, sum by (le, action) (rate(symfony_logic_action_duration_histogram_seconds_bucket[5m])))
```

With [metrics-bridge-profiling](https://github.com/msstc4symfony/metrics-bridge-profiling) and
[profiling-bridge-logic](https://github.com/msstc4symfony/profiling-bridge-logic) the same runs also appear as
spans in `symfony_profiling_span_duration_histogram_seconds`.

## Local development

`composer-ci.json` installs logic-bundle and metrics-bundle from their GitHub repositories; metrics-bundle needs ext-redis.

```sh
make install-ci                        # composer-ci.json profile with every CI tool
COMPOSER=composer-ci.json make check   # lint, PHPStan, CS, composer validate/audit, Rector, deptrac
make test                              # unit + integration
```

## License

MIT — see [LICENSE](LICENSE).
