<?php

namespace Drupal\nocache_redirects\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Drupal\Core\Site\Settings;
use Symfony\Component\HttpFoundation\Request;

/**
 * Deny caching for requests with query string (except allowlisted paths).
 */
final class DenyQueryStringRequestPolicy implements RequestPolicyInterface {

  public function check(Request $request): ?string {
    // Если query нет — всё норм.
    if ($request->query->count() === 0) {
      return NULL;
    }

    // Только GET имеет смысл кэшировать.
    if ($request->getMethod() !== 'GET') {
      return self::DENY;
    }

    $path = $request->getPathInfo();

    // Админку/служебные не трогаем.
    if (str_starts_with($path, '/admin') || str_starts_with($path, '/user') || str_starts_with($path, '/system')) {
      return self::DENY;
    }

    // Разрешённые пути (где query реально нужен).
    $allowed = (array) Settings::get('nocache_allowed_query_paths', [
      // пример:
      // '/search',
      // '/catalog',
    ]);

    foreach ($allowed as $prefix) {
      $prefix = rtrim((string) $prefix, '/');
      if ($prefix !== '' && ($path === $prefix || str_starts_with($path, $prefix . '/'))) {
        return NULL;
      }
    }

    // Везде остальное — запрещаем кэш.
    return self::DENY;
  }

}
