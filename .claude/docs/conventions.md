# Соглашения

- Код бриджа — в `src/Infrastructure/Metrics/`; Application-слоя нет.
- Тесты: `tests/Unit/<Класс>Test.php` (`#[CoversClass]` + `#[UsesClass]` для реально исполняемых классов — иначе
  risky под coverage); фикстуры — `tests/Fixtures/`; `tests/Integration/KernelBridgeTest` поднимает настоящий
  `TestKernel` (Framework, Monolog, Metrics, Logic, бридж) с `inmemory://` хранилищем.
- Infection гоняет только unit-сьют: всё, что должно убивать мутантов, — в `tests/Unit`.
- Файлы инструментов байт-в-байт из `bundle-standard/templates`; менять их можно только в стандарте.
- Коммиты — одна строка на английском, ≤ 20 слов; теги `vX.Y.Z`.
