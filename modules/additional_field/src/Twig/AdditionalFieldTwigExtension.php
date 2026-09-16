<?php

namespace Drupal\additional_field\Twig;

use Drupal\additional_field\AdditionalFieldManager;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Exposes configured Additional Field values as Twig globals.
 */
final class AdditionalFieldTwigExtension extends AbstractExtension implements GlobalsInterface {

  public function __construct(
    private readonly AdditionalFieldManager $variableManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getGlobals(): array {
    return $this->variableManager->getTwigGlobals();
  }

}
