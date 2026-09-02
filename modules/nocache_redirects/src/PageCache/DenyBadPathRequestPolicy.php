<?php

namespace Drupal\nocache_redirects\PageCache;

use Drupal\Core\PageCache\RequestPolicyInterface;
use Symfony\Component\HttpFoundation\Request;

final class DenyBadPathRequestPolicy implements RequestPolicyInterface {

  public function check(Request $request): ?string {
    if ($request->getMethod() !== 'GET') {
      return NULL;
    }

    $path = $request->getPathInfo();

    // Мусорные хвосты, которые создают дубли и раздувают кэш.
    // Добавь сюда то, что видишь в логах.
    if (preg_match('/[:;,.]+$/u', $path)) {
      return self::DENY;
    }

    return NULL;
  }

}
