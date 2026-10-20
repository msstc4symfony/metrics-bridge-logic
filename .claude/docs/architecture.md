# Архитектура

- `MetricsBridgeLogicBundle` — `AbstractBundle` без дерева конфигурации: `build()` добавляет
  `RegisterLogicMetricsPass`, `loadExtension()` импортирует `src/Resources/config/services.php`.
- `Infrastructure/Metrics/`:
  - `LogicMetric` — enum метрики (`Application\Metric\MetricDefinitionInterface` metrics 2.0): имя, тип histogram,
    лейблы `action`, `outcome`, бакеты. Публичный API моста вместе с бандлом.
  - `ActionDurationCollector` — `MetricRecorderInterface` + `?LoggerInterface` (`#[WithMonologChannel('metrics_bundle')]`);
    метки `application`/`component` добавляет адаптер metrics, мост передаёт `[action, outcome]`.
  - `MetricsMiddleware` — `#[AsActionMiddleware(priority: 400)]`, автоконфигурируется в пайплайн logic-bundle.
- `DependencyInjection/CompilerPass/RegisterLogicMetricsPass` — дописывает `LogicMetric::class` в
  `msstc4symfony_metrics.metric_enums` (идемпотентно); без параметра — `LogicException` с понятным текстом.
- Сервисы приватные, id = FQCN.
- deptrac (шаблон v3): Infrastructure ни от чего; DependencyInjection → Infrastructure (pass ссылается на enum); BundleRoot → DependencyInjection.
- `@internal`: коллектор, middleware, pass. Тесты unit — на `tests/Fixtures/RecordingMetricRecorder` (порт), kernel — настоящий registry.
- Имя и исход Action берутся из logic-bundle (`ActionNameResolver`, `ActionOutcome`), чтобы логи, метрики и спаны совпадали.
