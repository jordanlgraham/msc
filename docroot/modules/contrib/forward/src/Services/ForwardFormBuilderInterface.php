<?php

namespace Drupal\forward\Services;

use Drupal\Core\Entity\EntityInterface;

/**
 * Defines an interface for building a Forward form for an entity.
 */
interface ForwardFormBuilderInterface {

  /**
   * Builds a Forward form for a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for which the form is being built.
   *
   * @return array
   *   A render array for the form.
   */
  public function buildForm(EntityInterface $entity);

  /**
   * Builds an inline Forward form for a given entity.
   *
   * When rendered, the form will be inside a collapsible fieldset.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Entity for which the form is being built.
   *
   * @return array
   *   A render array for the form inside a details element.
   */
  public function buildInlineForm(EntityInterface $entity);

}
