# Архитектура additional_field

## Текущее состояние

- Последнее обновление: 2026-09-16
- Drupal version: Drupal 10/11
- Environment: standalone custom module source; Drupal runtime отсутствует
- Project root: `/home/kalugin-d/workspace/projects/Drupal_Projects/additional_field`
- Docroot: не входит в проект

## Структура проекта

- Custom module: `additional_field`
- Required dependency: `vesta_core:vesta_core`
- Config object: `additional_field.settings`
- Default config: `config/install/additional_field.settings.yml`
- Config schema: `config/schema/additional_field.schema.yml`
- Composer layout: отсутствует; каталог содержит только модуль
- Drush path: отсутствует

## Data model

Фиксированные переменные:

- `phone_number`
- `email`
- `working_hours`
- `company_address`
- `yandex_map_code`
- `additional_info`

Динамические collections:

- `social_links`
- `custom_variables`

Каждая строка содержит `variable_name` и `variable_value`. Machine name проверяется по формату, уникальности и списку зарезервированных Twig/Drupal имён.

## Rendering и UI

- Administrative settings route: `/admin/config/system/additional-field`
- Menu parent: `system.admin_config_system`
- `AdditionalFieldManager` — единый источник config-переменных и сообщений для пустых/неизвестных значений.
- `AdditionalFieldTwigExtension` реализует `GlobalsInterface` и предоставляет globals для обычных и inline Twig templates.
- Twig получает render arrays с cache tag `config:additional_field.settings`.
- PHP compatibility helper `print_field_info()` печатает HTML-экранированное значение.
- Форма настроек содержит vertical tabs: основные данные, социальные сети, произвольные переменные.
- Добавление и удаление динамических строк выполняется Form API AJAX без промежуточной записи config.
- AJAX callbacks восстанавливают строки из `FormState::getUserInput()`, потому что `#limit_validation_errors: []` намеренно очищает обычные processed values перед submit callback; это сохраняет несохранённые и мигрированные строки при повторном нажатии `Создать`.

## Access и безопасность

- Administrative permission: `additional_info`
- Administrative route использует permission check.
- Старый публичный custom AJAX route и контроллер удалены; используется core Form API AJAX.
- Динамические имена проходят format/reserved/duplicate validation.
- Обычные значения выводятся через `#plain_text`/`Html::escape()`.
- `yandex_map_code` принимает только один внешний HTTPS iframe или script: iframe ограничен Yandex `map-widget`, script — `api-maps.yandex.* /services/constructor/`; inline JavaScript, event handlers и сторонние hosts отклоняются.
- Конфигурация не предназначена для credentials.

## Cache и performance

- Источник данных: Config API.
- Значения загружаются один раз на request сервисом `additional_field.manager`.
- Twig render arrays несут cache tag config-объекта, поэтому изменение настроек инвалидирует зависящий render cache.
- `ConfigSaveSubscriber` при сохранении `additional_field.settings` инвалидирует общий render-cache tag `rendered`. Это покрывает шаблоны, где ещё не существующая динамическая переменная ранее отрендерилась пустой и поэтому не могла передать собственный config cache tag.
- Отдельный постоянный cache не используется.

## Update path

- `additional_field_update_10001()` переносит `mail`, адресные части, legacy social keys и старый `table` в новую структуру.
- Legacy machine names нормализуются и делаются уникальными между обеими collections; для некорректных и повторяющихся имён создаются детерминированные `legacy_*` имена без потери значений.
- Устаревшие ключи удаляются после переноса.

## Deployment и verification

- После обновления установленного модуля: `drush updb -y && drush cr`.
- PHP syntax: `php -l` для `.module`, `.install` и всех классов.
- YAML: parse всех семи `.yml` файлов.
- Isolated assertions: manager values/escaping/cache metadata, update migration, AJAX row state/validation/save.
- Runtime smoke test: проверить форму, Twig globals, PHP helper и Display Suite на целевом Drupal-сайте.
- Rollback: восстановить предыдущий код и резервную копию active config/БД.

## Известные ограничения

- Twig local context с тем же именем имеет приоритет над global; поэтому динамические имена с типичными Drupal/Twig collisions запрещены, но произвольный проектный local name всё ещё может перекрыть global по правилам Twig.
- Полная runtime-проверка Drupal, Twig и AJAX невозможна без сайта с Drupal 10/11, `vesta_core` и Display Suite.
