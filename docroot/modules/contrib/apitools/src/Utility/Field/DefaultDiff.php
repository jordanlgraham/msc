<?php

namespace Drupal\apitools\Utility\Field;

use Drupal\Component\Utility\DiffArray;
use Drupal\Core\Entity\ContentEntityInterface;

class DefaultDiff {

  protected $entity;

  protected $fieldName;

  public function __construct(ContentEntityInterface $entity, $field_name) {
    $this->entity = $entity;
    $this->fieldName = $field_name;
  }

  public function hasChanges() {
    if (!$this->entity->hasField($this->fieldName)) {
      throw new \Exception('Field not found');
    }
    if (!$this->entity->original) {
      return !$this->entity->get($this->fieldName)->isEmpty();
    }
    $field_definition = $this->entity->get($this->fieldName)->getFieldDefinition();
    if ($field_definition->getType() === 'boolean') {
      $current = $this->entity->get($this->fieldName)->value;
      $previous = $this->entity->original->get($this->fieldName)->value;
      return $current != $previous;
    }
    return !$this->entity->get($this->fieldName)->equals($this->entity->original->get($this->fieldName));
  }

  public function added(): array {
    if ($this->entity->isNew() || !$this->entity->original) {
      return $this->entity->get($this->fieldName)->getValue();
    }

    $original_values = $this->entity->original->get($this->fieldName)->getValue();
    $new_values = $this->entity->get($this->fieldName)->getValue();

    return array_filter($new_values, function($v) use ($original_values) {
      return !$this->exists($v, $original_values);
    });
  }

  public function removed(): array {
    if ($this->entity->isNew() || !$this->entity->original) {
      return [];
    }

    $original_values = $this->entity->original->get($this->fieldName)->getValue();
    $new_values = $this->entity->get($this->fieldName)->getValue();

    return array_filter($original_values, function($v) use ($new_values) {
      return !$this->exists($v, $new_values);
    });
  }

  private function exists($needle, array $haystack) {
    foreach ($haystack as $hay) {
      $diff = DiffArray::diffAssocRecursive($hay, $needle);
      // Filter out empty settings or variables.
      $diff = array_filter($diff, function($v) {
        if (is_array($v) && empty($v)) {
          return FALSE;
        }
        return TRUE;
      });
      if (empty($diff)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  private function getDiff($removed_or_added) {
    $original_values = $this->entity->original->get($this->fieldName)->getValue();
    $new_values = $this->entity->get($this->fieldName)->getValue();

    return $removed_or_added === 'removed' ? DiffArray::diffAssocRecursive($original_values, $new_values) : DiffArray::diffAssocRecursive($new_values, $original_values);
  }
}
