# Архитектура

- `MetricsBridgeLogicBundle` — `AbstractBundle` без дерева конфигурации: `build()` добавляет
  `RegisterLogicMetricsPass`, `loadExtension()` импортирует `src/Resources/config/services.php`.
- `Infrastructure/Metrics/`:
  - `LogicMetric` — enum метрики (`MetricLabelEnumInterface`): имя, тип histogram, лейблы `action`, `outcome`, бакеты.
  - `ActionDurationCollector` — `AbstractCollector`, аргументы `$applicationName`/`$componentName` из параметров
    `msstc4symfony_metrics.*`.
  - `MetricsMiddleware` — `#[AsActionMiddleware(priority: 400)]`, автоконфигурируется в пайплайн logic-bundle.
- `DependencyInjection/Compiler/RegisterLogicMetricsPass` — дописывает `LogicMetric::class` в
  `msstc4symfony_metrics.metric_enums` (идемпотентно); без параметра — `LogicException` с понятным текстом.
- Сервисы приватные, id = FQCN.
- deptrac: DependencyInjection видит Application и Infrastructure (pass ссылается на enum метрики).
- Имя и исход Action берутся из logic-bundle (`ActionNameResolver`, `ActionOutcome`), чтобы логи, метрики и спаны совпадали.
