<?php

namespace Drupal\additional_field;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;

/**
 * Provides configured site-wide variables to PHP and Twig.
 */
final class AdditionalFieldManager {

  public const EMPTY_MESSAGE = 'Переменная не заполнена!';

  public const MISSING_MESSAGE = 'Переменная не найдена!';

  public const INVALID_MAP_MESSAGE = 'Некорректный код Яндекс карты!';

  /**
   * Fixed variables that are always available in Twig.
   */
  public const FIXED_VARIABLES = [
    'phone_number',
    'email',
    'working_hours',
    'company_address',
    'yandex_map_code',
    'additional_info',
  ];

  /**
   * Names reserved by Twig or commonly present in Drupal templates.
   */
  private const RESERVED_VARIABLES = [
    '_charset',
    '_context',
    'app',
    'attributes',
    'block',
    'content',
    'false',
    'loop',
    'node',
    'none',
    'null',
    'page',
    'parent',
    'this',
    'true',
    'user',
  ];

  /**
   * Variables collected during the current request.
   *
   * @var array<string, string>|null
   */
  private ?array $variables = NULL;

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Returns all fixed and configured dynamic variables.
   *
   * @return array<string, string>
   *   Variables keyed by their Twig/PHP machine names.
   */
  public function getVariables(): array {
    if ($this->variables !== NULL) {
      return $this->variables;
    }

    $config = $this->configFactory->get('additional_field.settings');
    $variables = [];

    foreach (self::FIXED_VARIABLES as $name) {
      $value = $config->get($name);
      $variables[$name] = is_scalar($value) ? (string) $value : '';
    }

    foreach (['social_links', 'custom_variables'] as $collection) {
      $rows = $config->get($collection);
      if (!is_array($rows)) {
        continue;
      }

      foreach ($rows as $row) {
        if (!is_array($row)) {
          continue;
        }

        $name = trim((string) ($row['variable_name'] ?? ''));
        if (!$this->isValidVariableName($name) || array_key_exists($name, $variables)) {
          continue;
        }

        $value = $row['variable_value'] ?? '';
        $variables[$name] = is_scalar($value) ? (string) $value : '';
      }
    }

    $this->variables = $variables;
    return $this->variables;
  }

  /**
   * Returns a display value with an explicit empty/missing-state message.
   */
  public function getDisplayValue(string $name): string {
    $variables = $this->getVariables();
    if (!array_key_exists($name, $variables)) {
      return self::MISSING_MESSAGE;
    }

    return trim($variables[$name]) === '' ? self::EMPTY_MESSAGE : $variables[$name];
  }

  /**
   * Builds globally available Twig render variables with cache metadata.
   *
   * @return array<string, array<string, mixed>>
   *   Render arrays keyed by Twig global variable name.
   */
  public function getTwigGlobals(): array {
    $config = $this->configFactory->get('additional_field.settings');
    $cache_tags = $config->getCacheTags();
    $globals = [];

    foreach ($this->getVariables() as $name => $value) {
      if (trim($value) === '') {
        $globals[$name] = [
          '#plain_text' => self::EMPTY_MESSAGE,
          '#cache' => ['tags' => $cache_tags],
        ];
        continue;
      }

      if ($name === 'yandex_map_code') {
        $map_markup = $this->sanitizeYandexMapCode($value);
        $globals[$name] = $map_markup === NULL
          ? [
            '#plain_text' => self::INVALID_MAP_MESSAGE,
            '#cache' => ['tags' => $cache_tags],
          ]
          : [
            '#markup' => Markup::create($map_markup),
            '#cache' => ['tags' => $cache_tags],
          ];
        continue;
      }

      $globals[$name] = [
        '#plain_text' => $value,
        '#cache' => ['tags' => $cache_tags],
      ];
    }

    return $globals;
  }

  /**
   * Returns safe HTML output for the procedural PHP compatibility helper.
   */
  public function getPhpOutput(string $name): string {
    $value = $this->getDisplayValue($name);

    if ($name === 'yandex_map_code' && !in_array($value, [self::EMPTY_MESSAGE, self::MISSING_MESSAGE], TRUE)) {
      $map_markup = $this->sanitizeYandexMapCode($value);
      return $map_markup ?? Html::escape(self::INVALID_MAP_MESSAGE);
    }

    return Html::escape($value);
  }

  /**
   * Determines whether a name can safely be exposed as a Twig global.
   */
  public function isValidVariableName(string $name): bool {
    return preg_match('/^[a-z][a-z0-9_]*$/', $name) === 1
      && !in_array($name, self::RESERVED_VARIABLES, TRUE);
  }

  /**
   * Returns fixed and reserved names unavailable to dynamic rows.
   *
   * @return string[]
   *   Reserved variable names.
   */
  public function getUnavailableVariableNames(): array {
    return array_values(array_unique(array_merge(self::FIXED_VARIABLES, self::RESERVED_VARIABLES)));
  }

  /**
   * Sanitizes a single external Yandex iframe or script embed.
   *
   * Inline scripts, event handlers, non-HTTPS sources and non-Yandex hosts are
   * deliberately rejected because this configuration is rendered site-wide.
   */
  public function sanitizeYandexMapCode(string $code): ?string {
    $code = trim($code);
    if ($code === '') {
      return NULL;
    }

    if (preg_match('/^<(iframe|script)\b([^>]*)>(.*?)<\/\1>$/is', $code, $matches) !== 1) {
      return NULL;
    }

    $tag = strtolower($matches[1]);
    $attributes = $this->parseHtmlAttributes($matches[2]);
    if ($attributes === NULL || trim($matches[3]) !== '') {
      return NULL;
    }

    $source = trim((string) ($attributes['src'] ?? ''));
    if (!$this->isAllowedYandexUrl($source, $tag)) {
      return NULL;
    }

    if ($tag === 'script') {
      $allowed_names = ['src', 'type', 'charset', 'async', 'defer'];
      if (array_diff(array_keys($attributes), $allowed_names)) {
        return NULL;
      }

      $safe_attributes = ['src' => $source];
      $type = strtolower(trim((string) ($attributes['type'] ?? '')));
      if ($type !== '') {
        if (!in_array($type, ['text/javascript', 'application/javascript', 'module'], TRUE)) {
          return NULL;
        }
        $safe_attributes['type'] = $type;
      }

      $charset = trim((string) ($attributes['charset'] ?? ''));
      if ($charset !== '') {
        if (preg_match('/^[a-z0-9_-]+$/i', $charset) !== 1) {
          return NULL;
        }
        $safe_attributes['charset'] = $charset;
      }
      foreach (['async', 'defer'] as $boolean_attribute) {
        if (array_key_exists($boolean_attribute, $attributes)) {
          $safe_attributes[$boolean_attribute] = NULL;
        }
      }

      return '<script' . $this->buildHtmlAttributes($safe_attributes) . '></script>';
    }

    $allowed_names = [
      'src',
      'title',
      'width',
      'height',
      'frameborder',
      'allowfullscreen',
      'allow',
      'loading',
      'referrerpolicy',
      'style',
    ];
    if (array_diff(array_keys($attributes), $allowed_names)) {
      return NULL;
    }

    $safe_attributes = [
      'src' => $source,
      'title' => trim((string) ($attributes['title'] ?? '')) ?: 'Яндекс Карта',
    ];

    foreach (['width', 'height'] as $dimension) {
      $value = trim((string) ($attributes[$dimension] ?? ''));
      if ($value !== '') {
        if (preg_match('/^[0-9]{1,4}(?:px|%)?$/', $value) !== 1) {
          return NULL;
        }
        $safe_attributes[$dimension] = $value;
      }
    }

    $frameborder = trim((string) ($attributes['frameborder'] ?? ''));
    if ($frameborder !== '') {
      if (!in_array($frameborder, ['0', '1'], TRUE)) {
        return NULL;
      }
      $safe_attributes['frameborder'] = $frameborder;
    }

    $allow = strtolower(trim((string) ($attributes['allow'] ?? '')));
    if ($allow !== '' && $allow !== 'fullscreen') {
      return NULL;
    }
    if (array_key_exists('allowfullscreen', $attributes) || $allow === 'fullscreen') {
      $safe_attributes['allowfullscreen'] = NULL;
    }

    $loading = strtolower(trim((string) ($attributes['loading'] ?? '')));
    if ($loading !== '') {
      if (!in_array($loading, ['eager', 'lazy'], TRUE)) {
        return NULL;
      }
      $safe_attributes['loading'] = $loading;
    }

    $referrer_policy = strtolower(trim((string) ($attributes['referrerpolicy'] ?? '')));
    if ($referrer_policy !== '') {
      $allowed_policies = ['no-referrer', 'origin', 'strict-origin', 'strict-origin-when-cross-origin'];
      if (!in_array($referrer_policy, $allowed_policies, TRUE)) {
        return NULL;
      }
      $safe_attributes['referrerpolicy'] = $referrer_policy;
    }

    $style = strtolower(preg_replace('/\s+/', '', (string) ($attributes['style'] ?? '')) ?? '');
    if ($style !== '') {
      if (!in_array($style, ['position:relative', 'position:relative;'], TRUE)) {
        return NULL;
      }
      $safe_attributes['style'] = 'position: relative;';
    }

    return '<iframe' . $this->buildHtmlAttributes($safe_attributes) . '></iframe>';
  }

  /**
   * Clears the per-request variables after configuration is saved.
   */
  public function reset(): void {
    $this->variables = NULL;
  }

  /**
   * Parses an HTML attribute list without accepting unparsed fragments.
   *
   * @return array<string, string|null>|null
   *   Parsed attributes, or NULL when the input is malformed or duplicated.
   */
  private function parseHtmlAttributes(string $input): ?array {
    $attributes = [];
    while (trim($input) !== '') {
      $matched = preg_match(
        "/^\\s*([a-zA-Z_:][a-zA-Z0-9_.:-]*)(?:\\s*=\\s*(?:\"([^\"]*)\"|'([^']*)'|([^\\s\"'=<>`]+)))?/s",
        $input,
        $matches,
        PREG_UNMATCHED_AS_NULL,
      );
      if ($matched !== 1 || $matches[0] === '') {
        return NULL;
      }

      $name = strtolower($matches[1]);
      if (array_key_exists($name, $attributes) || str_starts_with($name, 'on')) {
        return NULL;
      }

      $value = $matches[2] ?? $matches[3] ?? $matches[4] ?? NULL;
      $attributes[$name] = $value === NULL
        ? NULL
        : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $input = substr($input, strlen($matches[0]));
    }

    return $attributes;
  }

  private function isAllowedYandexUrl(string $url, string $tag): bool {
    $parts = parse_url($url);
    if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
      return FALSE;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '/');

    if ($tag === 'script') {
      return preg_match('/^api-maps\.yandex\.(?:ru|com)$/', $host) === 1
        && str_starts_with($path, '/services/constructor/');
    }

    return preg_match('/(^|\.)yandex\.(?:ru|com|net|by|kz|uz|com\.tr)$/', $host) === 1
      && str_starts_with($path, '/map-widget/');
  }

  /**
   * Builds escaped HTML attributes from a strict allowlist.
   *
   * @param array<string, string|null> $attributes
   *   Attribute names and values. NULL represents a boolean attribute.
   */
  private function buildHtmlAttributes(array $attributes): string {
    $output = '';
    foreach ($attributes as $name => $value) {
      $output .= ' ' . $name;
      if ($value !== NULL) {
        $output .= '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8') . '"';
      }
    }
    return $output;
  }

}
