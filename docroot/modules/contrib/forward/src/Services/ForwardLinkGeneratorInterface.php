<?php

namespace Drupal\forward\Services;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for generating a Forward link on an entity.
 */
interface ForwardLinkGeneratorInterface {

  /**
   * Generate a Forward link for a given entity.
   *
   * See ForwardLinkFormatter.php for example usage.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for which the link is being generated.
   * @param array $settings
   *   Array with these keys:
   *     title - the link title, with tokens allowed
   *     style - 0, 1, or 2 (text only, icon only, text and icon)
   *     icon - optional path to custom icon, or a blank string
   *     nofollow - true if a nofollow tag should be included.
   *
   * @return array
   *   A render array containing the generated link.
   */
  public function generate(EntityInterface $entity, array $settings);

}
