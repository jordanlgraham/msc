<?php

namespace Drupal\insert\Plugin\migrate\destination;

use Drupal\migrate\Plugin\migrate\destination\PerComponentEntityFormDisplay;
use Drupal\migrate\Row;

@trigger_error(
  "The Drupal\insert\Plugin\migrate\destination\PerComponentEntityFormDisplayInsert migrate destination plugin is deprecated in insert:8.x-2.0-beta4 and is removed in insert:8.x-3.0. Insert settings migrations are merged into Drupal core's 'd7_field_instance_widget_settings' migration. See https://drupal.org/node/123",
  E_USER_DEPRECATED
);

/**
 * This class imports Insert module field settings of an entity form display.
 *
 * @see \Drupal\migrate\Plugin\migrate\destination\PerComponentEntityFormDisplay
 *
 * @deprecated in insert:8.x-2.0-beta4 and is removed in insert:8.x-3.0. There
 *   is no replacement.
 *
 * @MigrateDestination(
 *   id = "component_entity_form_display_insert"
 * )
 */
class PerComponentEntityFormDisplayInsert extends PerComponentEntityFormDisplay {

  /**
   * @inheritdoc
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $values = [];
    foreach (array_keys($this->getIds()) as $id) {
      $values[$id] = $row->getDestinationProperty($id);
    }
    $entity = $this->getEntity($values['entity_type'], $values['bundle'], $values[static::MODE_NAME]);

    $insert_settings = $row->getDestinationProperty('options/third_party_settings/insert');
    // Add Insert module third party settings to field settings:
    if ($insert_settings && $field_component = $entity->getComponent($values['field_name'])) {
      $field_component['third_party_settings']['insert'] = $insert_settings;
      $entity->setComponent($values['field_name'], $field_component);
    }

    $entity->save();
    return array_values($values);
  }

}
