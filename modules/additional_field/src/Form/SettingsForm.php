<?php

namespace Drupal\additional_field\Form;

use Drupal\additional_field\AdditionalFieldManager;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configures site-wide Additional Field variables.
 */
final class SettingsForm extends ConfigFormBase {

  private const COLLECTIONS = [
    'social_links' => 'social',
    'custom_variables' => 'custom',
  ];

  /**
   * The site-wide variables manager.
   */
  protected AdditionalFieldManager $variableManager;

  public function __construct(
    ConfigFactoryInterface $config_factory,
    TypedConfigManagerInterface $typed_config_manager,
    AdditionalFieldManager $variable_manager,
  ) {
    parent::__construct($config_factory, $typed_config_manager);
    $this->variableManager = $variable_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed'),
      $container->get('additional_field.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'additional_field_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['additional_field.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('additional_field.settings');

    $form['settings_tabs'] = [
      '#type' => 'vertical_tabs',
      '#title' => $this->t('Разделы настроек'),
    ];

    $form['main'] = [
      '#type' => 'details',
      '#title' => $this->t('Основная информация'),
      '#group' => 'settings_tabs',
      '#open' => TRUE,
    ];

    $form['main']['usage'] = [
      '#type' => 'item',
      '#title' => $this->t('Использование переменных'),
      '#markup' => $this->t('PHP: <code>print_field_info("variable_name");</code> Twig: <code>{{ variable_name }}</code>'),
    ];

    $form['main']['phone_number'] = $this->buildTextField(
      $this->t('Номер телефона'),
      'phone_number',
      (string) $config->get('phone_number'),
      'tel',
    );
    $form['main']['email'] = $this->buildTextField(
      $this->t('Адрес электронной почты'),
      'email',
      (string) $config->get('email'),
      'email',
    );
    $form['main']['working_hours'] = $this->buildTextField(
      $this->t('Режим работы'),
      'working_hours',
      (string) $config->get('working_hours'),
    );
    $form['main']['company_address'] = $this->buildTextField(
      $this->t('Адрес компании'),
      'company_address',
      (string) $config->get('company_address'),
    );

    $form['main']['yandex_map_code'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Код Яндекс карты'),
      '#default_value' => (string) $config->get('yandex_map_code'),
      '#rows' => 5,
      '#description' => $this->t('Переменная: <code>yandex_map_code</code>. Разрешён один iframe или внешний script с HTTPS-адресом Яндекса; inline JavaScript запрещён.'),
    ];

    $form['main']['additional_info'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Дополнительная информация'),
      '#default_value' => (string) $config->get('additional_info'),
      '#rows' => 5,
      '#description' => $this->t('Переменная: <code>additional_info</code>.'),
    ];

    $form['social'] = [
      '#type' => 'details',
      '#title' => $this->t('Социальные сети'),
      '#group' => 'settings_tabs',
    ];
    $form['social']['description'] = [
      '#markup' => $this->t('Создайте переменную для каждой нужной социальной сети, например <code>telegram_url</code>.'),
    ];
    $form['social']['social_links_wrapper'] = $this->buildVariablesWrapper('social_links', $form_state);

    $form['custom'] = [
      '#type' => 'details',
      '#title' => $this->t('Произвольные переменные'),
      '#group' => 'settings_tabs',
    ];
    $form['custom']['description'] = [
      '#markup' => $this->t('Создайте дополнительные переменные, которые должны быть доступны в PHP и Twig.'),
    ];
    $form['custom']['custom_variables_wrapper'] = $this->buildVariablesWrapper('custom_variables', $form_state);

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);

    $map_code = trim((string) $form_state->getValue('yandex_map_code'));
    if ($map_code !== '' && $this->variableManager->sanitizeYandexMapCode($map_code) === NULL) {
      $form_state->setErrorByName(
        'yandex_map_code',
        $this->t('Укажите один iframe или внешний script с HTTPS-адресом в домене Яндекса. Inline JavaScript и обработчики событий запрещены.'),
      );
    }

    $used_names = array_fill_keys($this->variableManager->getUnavailableVariableNames(), TRUE);
    foreach (array_keys(self::COLLECTIONS) as $collection) {
      $rows = $form_state->getValue($collection, []);
      if (!is_array($rows)) {
        continue;
      }

      foreach ($rows as $delta => $row) {
        if (!is_array($row)) {
          continue;
        }

        $name = trim((string) ($row['variable_name'] ?? ''));
        $value = trim((string) ($row['variable_value'] ?? ''));
        if ($name === '' && $value === '') {
          continue;
        }
        if ($name === '') {
          $form_state->setErrorByName(
            $collection . '][' . $delta . '][variable_name',
            $this->t('Укажите название переменной.'),
          );
          continue;
        }
        if (!$this->variableManager->isValidVariableName($name)) {
          $form_state->setErrorByName(
            $collection . '][' . $delta . '][variable_name',
            $this->t('Название должно начинаться с латинской строчной буквы и содержать только a-z, 0-9 и знак подчёркивания. Зарезервированные имена использовать нельзя.'),
          );
          continue;
        }
        if (isset($used_names[$name])) {
          $form_state->setErrorByName(
            $collection . '][' . $delta . '][variable_name',
            $this->t('Переменная %name уже существует или зарезервирована.', ['%name' => $name]),
          );
          continue;
        }

        $used_names[$name] = TRUE;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->config('additional_field.settings');

    foreach (AdditionalFieldManager::FIXED_VARIABLES as $name) {
      $config->set($name, trim((string) $form_state->getValue($name)));
    }

    foreach (array_keys(self::COLLECTIONS) as $collection) {
      $config->set($collection, $this->normalizeRows($form_state->getValue($collection, [])));
    }

    $config->save();
    $this->variableManager->reset();
    parent::submitForm($form, $form_state);
  }

  /**
   * Adds an empty variables row and rebuilds only its AJAX wrapper.
   */
  public function addRow(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $collection = (string) ($trigger['#variables_collection'] ?? '');
    if (!isset(self::COLLECTIONS[$collection])) {
      return;
    }

    $rows = $this->getSubmittedRows($collection, $form_state);
    $rows[] = ['variable_name' => '', 'variable_value' => ''];
    $this->storeRebuiltRows($collection, $rows, $form_state);
  }

  /**
   * Removes one variables row and rebuilds only its AJAX wrapper.
   */
  public function removeRow(array &$form, FormStateInterface $form_state): void {
    $trigger = $form_state->getTriggeringElement();
    $collection = (string) ($trigger['#variables_collection'] ?? '');
    $delta = (int) ($trigger['#row_delta'] ?? -1);
    if (!isset(self::COLLECTIONS[$collection]) || $delta < 0) {
      return;
    }

    $rows = $this->getSubmittedRows($collection, $form_state);
    unset($rows[$delta]);
    $this->storeRebuiltRows($collection, array_values($rows), $form_state);
  }

  /**
   * Returns the requested variables wrapper after an AJAX rebuild.
   */
  public function ajaxRows(array &$form, FormStateInterface $form_state): array {
    $trigger = $form_state->getTriggeringElement();
    $collection = (string) ($trigger['#variables_collection'] ?? '');
    $section = self::COLLECTIONS[$collection] ?? NULL;

    if ($section !== NULL && isset($form[$section][$collection . '_wrapper'])) {
      return $form[$section][$collection . '_wrapper'];
    }

    return $form;
  }

  /**
   * Builds a fixed-value form element with its public variable name.
   */
  private function buildTextField($title, string $name, string $value, string $type = 'textfield'): array {
    return [
      '#type' => $type,
      '#title' => $title,
      '#default_value' => $value,
      '#description' => $this->t('Переменная: <code>@name</code>.', ['@name' => $name]),
    ];
  }

  /**
   * Builds one dynamic table and its AJAX controls.
   */
  private function buildVariablesWrapper(string $collection, FormStateInterface $form_state): array {
    $config_rows = $this->config('additional_field.settings')->get($collection);
    $config_rows = is_array($config_rows) ? array_values($config_rows) : [];

    $state_key = $collection . '_row_count';
    $row_count = $form_state->get($state_key);
    if ($row_count === NULL) {
      $row_count = count($config_rows);
      $form_state->set($state_key, $row_count);
    }

    $submitted_rows = $form_state->getValue($collection);
    $rows = is_array($submitted_rows) ? array_values($submitted_rows) : $config_rows;
    $wrapper_id = 'additional-field-' . str_replace('_', '-', $collection) . '-wrapper';

    $wrapper = [
      '#type' => 'container',
      '#attributes' => ['id' => $wrapper_id],
    ];

    if ($row_count > 0) {
      $wrapper[$collection] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Название переменной'),
          $this->t('Значение переменной'),
          $this->t('Операции'),
        ],
        '#tree' => TRUE,
      ];

      for ($delta = 0; $delta < $row_count; $delta++) {
        $row = $rows[$delta] ?? [];
        $wrapper[$collection][$delta]['variable_name'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Название переменной'),
          '#title_display' => 'invisible',
          '#default_value' => (string) ($row['variable_name'] ?? ''),
          '#placeholder' => 'variable_name',
          '#maxlength' => 128,
        ];
        $wrapper[$collection][$delta]['variable_value'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Значение переменной'),
          '#title_display' => 'invisible',
          '#default_value' => (string) ($row['variable_value'] ?? ''),
          '#maxlength' => 2048,
        ];
        $wrapper[$collection][$delta]['remove'] = [
          '#type' => 'submit',
          '#value' => $this->t('Удалить'),
          '#name' => $collection . '_remove_' . $delta,
          '#submit' => ['::removeRow'],
          '#limit_validation_errors' => [],
          '#variables_collection' => $collection,
          '#row_delta' => $delta,
          '#ajax' => [
            'callback' => '::ajaxRows',
            'wrapper' => $wrapper_id,
          ],
        ];
      }
    }

    $wrapper['create'] = [
      '#type' => 'submit',
      '#value' => $this->t('Создать'),
      '#name' => $collection . '_create',
      '#submit' => ['::addRow'],
      '#limit_validation_errors' => [],
      '#variables_collection' => $collection,
      '#ajax' => [
        'callback' => '::ajaxRows',
        'wrapper' => $wrapper_id,
      ],
    ];

    return $wrapper;
  }

  /**
   * Preserves unsaved row values across a Form API AJAX rebuild.
   *
   * @param array<int, array<string, mixed>> $rows
   *   Current table rows.
   */
  private function storeRebuiltRows(string $collection, array $rows, FormStateInterface $form_state): void {
    $form_state->setValue($collection, $rows);
    $user_input = $form_state->getUserInput();
    $user_input[$collection] = $rows;
    $form_state->setUserInput($user_input);
    $form_state->set($collection . '_row_count', count($rows));
    $form_state->setRebuild();
  }

  /**
   * Returns rows from raw input before limited validation removes values.
   *
   * Add/remove buttons intentionally use #limit_validation_errors = []. Core
   * therefore clears regular values before their submit callbacks run, while
   * the original browser input remains available in FormState user input.
   *
   * @return array<int, array<string, mixed>>
   *   Submitted rows in their current order.
   */
  private function getSubmittedRows(string $collection, FormStateInterface $form_state): array {
    $user_input = $form_state->getUserInput();
    $rows = $user_input[$collection] ?? $form_state->getValue($collection, []);
    if (!is_array($rows)) {
      return [];
    }

    return array_values(array_filter($rows, 'is_array'));
  }

  /**
   * Removes empty rows and button values before saving configuration.
   *
   * @return array<int, array{variable_name: string, variable_value: string}>
   *   Normalized configuration rows.
   */
  private function normalizeRows(mixed $rows): array {
    if (!is_array($rows)) {
      return [];
    }

    $normalized = [];
    foreach ($rows as $row) {
      if (!is_array($row)) {
        continue;
      }

      $name = trim((string) ($row['variable_name'] ?? ''));
      $value = trim((string) ($row['variable_value'] ?? ''));
      if ($name === '' && $value === '') {
        continue;
      }

      $normalized[] = [
        'variable_name' => $name,
        'variable_value' => $value,
      ];
    }

    return $normalized;
  }

}
