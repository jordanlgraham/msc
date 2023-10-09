<?php

namespace Drupal\apitools\Utility;

use Drupal\apitools\Utility\Field\DefaultDiff;
use Drupal\apitools\Utility\Field\EntityReferenceDiff;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Utility class of helpful methods for working with Drupal fields.
 */
class FieldUtils {

  /**
   * Checks whether a field exists and has a value on an entity.
   *
   * If the field has a value, return the value of that field. If not, return
   * false.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to check.
   * @param string $field_name
   *   The field name to check for.
   *
   * @return \Drupal\Core\Field\FieldItemList|bool
   *   Whether we can use this field on this entity. Returns the field value.
   */
  public static function checkField(ContentEntityInterface $entity, $field_name) {
    if (!$entity->hasField($field_name)) {
      return FALSE;
    }

    $field = $entity->get($field_name);
    return !$field->isEmpty() ? $field : FALSE;
  }

  public static function diff(ContentEntityInterface $entity, $field_name) {
    if (!$entity->hasField($field_name)) {
      throw new \Exception('Field not found');
    }

    if (is_a($entity->get($field_name), EntityReferenceFieldItemListInterface::class)) {
      return new EntityReferenceDiff($entity, $field_name);
    }

    return new DefaultDiff($entity, $field_name);
  }
}
