<?php

namespace Drupal\apitools\Utility\Field;

class EntityReferenceDiff extends DefaultDiff {

  public function added(): array {
    if ($this->entity->isNew() || !$this->entity->original) {
      return $this->entity->get($this->fieldName)->referencedEntities();
    }

    return $this->getEntityDiff('added');
  }

  public function removed(): array {
    if ($this->entity->isNew() || !$this->entity->original) {
      return [];
    }
    return $this->getEntityDiff('removed');
  }

  private function getEntityDiff($removed_or_added) {
    $original_values = $this->entity->original->get($this->fieldName)->getValue();
    $original_values = array_column($original_values, 'target_id');
    $new_values = $this->entity->get($this->fieldName)->getValue();
    $new_values = array_column($new_values, 'target_id');

    $changed_ids = $removed_or_added === 'removed' ? array_diff($original_values, $new_values) : array_diff($new_values, $original_values);

    $entities = $this->collectEntitiesById();
    return array_filter($entities, function($entity) use ($changed_ids) {
      return in_array($entity->id(), $changed_ids);
    });
  }

  private function collectEntitiesById() {
    $entities = [];
    foreach ($this->entity->get($this->fieldName)->referencedEntities() as $referenced_entity) {
      $entities[$referenced_entity->id()] = $referenced_entity;
    }
    if ($this->entity->original && !$this->entity->original->get($this->fieldName)->isEmpty()) {
      foreach ($this->entity->original->get($this->fieldName)->referencedEntities() as $referenced_entity) {
        $entities[$referenced_entity->id()] = $referenced_entity;
      }
    }
    return $entities;
  }
}
