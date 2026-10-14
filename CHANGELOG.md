# Changelog

## 1.1.0 - 2026-10-14

- Changed: requires PHP 8.2 instead of 8.4 (Symfony 8 still needs PHP 8.4).
- Changed: class constants no longer declare a native type (PHP 8.2 has none); values are unchanged.
- Changed: requires logic-bundle ^1.2 and metrics-bundle ^1.4, their PHP 8.2 releases.

## 1.0.0 - 2026-10-12

- First release: `symfony_logic_action_duration_histogram_seconds{action, outcome}` recorded around every logic-bundle Action.
