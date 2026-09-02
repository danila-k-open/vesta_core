<?php

namespace Drupal\nocache_redirects\PageCache;

use Drupal\Core\PageCache\ResponsePolicyInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class NoCacheRedirectResponsePolicy implements ResponsePolicyInterface {

  public function check(Response $response, Request $request): ?string {
    if ($response->isRedirection()) {
      return self::DENY;
    }
    return NULL;
  }

}
