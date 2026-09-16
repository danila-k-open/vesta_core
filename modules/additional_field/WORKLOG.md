# Журнал additional_field

Журнал append-only.

Не записывай секреты, cookies, tokens, пароли, private keys, полные production payloads или персональные данные.

## 2026-09-16T05:03:58+00:00 — Инициализация

- Запрос: создано рабочее пространство проекта.
- Изменено: подготовлены project context и checkpoint-файлы.
- Проверено: шаблоны созданы без перезаписи существующих файлов.
- Следующий шаг: получить задачу пользователя или заполнить `PROJECT.md` из его сообщения.

## 2026-09-16T10:29:44+05:00 — Глобальные переменные и форма настроек

- Запрос: исправить PHP/Twig-вывод и выполнить требования `PROJECT.md` для Drupal 10/11.
- Изменено: добавлены manager service и Twig globals; PHP helper теперь печатает безопасный результат; форма разделена на три секции; социальные и произвольные строки управляются через Form API AJAX; добавлены default config, schema и update hook; удалён неиспользуемый публичный AJAX route/controller.
- Безопасность: variable names валидируются; обычный вывод экранируется; Яндекс embed ограничен разрешёнными HTTPS hosts/routes и атрибутами.
- Совместимость: `core_version_requirement` обновлён на `^10 || ^11`, dependency `vesta_core:vesta_core` сохранена; DI формы совместим с Drupal `DependencySerializationTrait`.
- Проверено: `php -l` — 5 файлов без ошибок; YAML parser — 7 файлов; isolated manager/update/form assertions пройдены; негативные map/XSS cases отклонены; security-pattern scan совпадений не нашёл; route и menu parent сохранены; повторный независимый review завершён без security/logic ошибок.
- Ограничение: Drupal runtime в проекте отсутствует, поэтому HTTP/AJAX, Twig и Display Suite требуют smoke test после установки на сайт.
- Следующий шаг: выполнить `drush updb -y && drush cr`, затем проверить settings form, `{{ variable_name }}`, `print_field_info()` и Display Suite Twig field.

## 2026-09-16 — Исправления по независимому review

- Изменено: DI-сервис `SettingsForm` оставлен в сериализуемом `protected` свойстве; добавлен `ConfigSaveSubscriber`, который инвалидирует render-cache tag `rendered` при сохранении `additional_field.settings`; update hook нормализует и уникализирует legacy machine names без удаления значений; permission `additional_info` помечен `restrict access: true`.
- Проверено: Drupal 10.0–10.6 и 11 используют tag `rendered` для render-cache items; isolated subscriber/update assertions — 18 проверок успешно.
- Ограничение: runtime HTTP/AJAX/Twig/Display Suite smoke test остаётся заблокирован отсутствием Drupal runtime.
- Следующий шаг: выполнить `drush updb -y && drush cr` на целевом сайте и провести runtime smoke test.

## 2026-09-16 — Итоговая локальная проверка после review

- Проверено: `php -l` — 6 PHP-файлов без ошибок; YAML parser — 7 файлов; security-pattern scan — 0 совпадений; subscriber/update harness — 18 assertions успешно; временный test harness удалён.
- Статус: все три блокирующих замечания закрыты локально.
- Осталось: runtime smoke test на Drupal 10/11 с `vesta_core` и Display Suite.

## 2026-09-16T10:57:33+05:00 — Сохранение строк при AJAX add/remove

- Симптом: повторное нажатие `Создать` удаляло введённые строки и строки, загруженные после миграции.
- Root cause: кнопки используют `#limit_validation_errors: []`, поэтому Drupal очищает `$form_state->getValues()` перед submit callback; `addRow()` и `removeRow()` ошибочно читали строки из уже очищенных processed values и перезаписывали raw input пустым массивом.
- Изменено: AJAX callbacks читают строки из `$form_state->getUserInput()` с fallback на processed values, затем синхронно обновляют raw input, values и row count.
- Проверено: red harness воспроизвёл потерю строки до исправления; после исправления `AJAX_ROWS_PRESERVED_OK assertions=9` проверил повторное добавление, четыре мигрированные строки, добавление новой строки и удаление средней строки; `php -l src/Form/SettingsForm.php` — без ошибок.
- Осталось: проверить UI на целевом Drupal-сайте после `drush cr`.

## 2026-09-16T10:57:33+05:00 — Финальная regression-проверка AJAX

- Проверено: `AJAX_ROWS_PRESERVED_OK assertions=10`; дополнительно подтверждено сохранение несвязанного несохранённого поля основной формы; все 6 PHP-файлов проходят `php -l`; 7 YAML-файлов успешно разобраны.
- Cleanup: временный `/tmp/hermes-verify-additional-field-ajax-*` harness удалён.
