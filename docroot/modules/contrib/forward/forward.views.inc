<?php

/**
 * @file
 * Provide views data.
 */

/**
 * Implements hook_views_data().
 */
function forward_views_data() {
  $data = array();

  // Forward statistics

  // Define the base group of this table. Fields that don't
  // have a group defined will go into this field by default.
  $data['forward_statistics']['table'] = array(
    'group' => t('Forward statistics'),
    'wizard_id' => 'forward_statistics',
  );

  // For other entity base tables, explain how we join.
  $entity_types = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_types as $type => $info) {
    if (is_a($info, 'Drupal\Core\Entity\ContentEntityType')) {
      if ($info->getBaseTable()) {
        $data['forward_statistics']['table']['join'][$info->getBaseTable() . '_field_data'] = array(
          // 'left_field' is the primary key in the referenced table.
          // 'field' is the foreign key in this table.
          'left_field' => $info->getKey('id'),
          'field' => 'id',
          'extra' => array(
            array('field' => 'type', 'value' => $type),
          ),
        );
      }
    }
  }

  // Forward statistics fields.
  $data['forward_statistics']['forward_count'] = array(
    'title' => t('Forward count'),
    'help' => t('The number of times an entity was forwarded.'),
    'field' => array(
      'id' => 'numeric',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'id' => 'numeric',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );
  $data['forward_statistics']['last_forward_timestamp'] = array(
    'title' => t('Most recent forward'),
    'help' => t('The last time an entity was forwarded.'),
    'field' => array(
      'id' => 'date',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'id' => 'date',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );

  // Forward log.
  $data['forward_log']['table'] = array(
    'group' => t('Forward log'),
    'wizard_id' => 'forward_log',
  );

  // Forward log fields.
  $data['forward_log']['path'] = array(
    'title' => t('Path'),
    'help' => t('The path that was forwarded.'),
    'field' => array(
      'id' => 'standard',
      'click sortable' => TRUE,
    ),
    'argument' => array(
      'id' => 'string',
    ),
    'filter' => array(
      'id' => 'standard',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );
  $data['forward_log']['timestamp'] = array(
    'title' => t('Timestamp'),
    'help' => t('The date and time of the forward.'),
    'field' => array(
      'id' => 'date',
      'click sortable' => TRUE,
    ),
    'filter' => array(
      'id' => 'date',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );
  $data['forward_log']['uid'] = array(
    'title' => t('UID'),
    'help' => t('The user ID of the user who forwarded.'),
    'field' => array(
      'id' => 'numeric',
    ),
    'argument' => array(
      'id' => 'numeric',
    ),
    'filter' => array(
      'id' => 'numeric',
    ),
    'search' => array(
      'id' => 'standard',
    ),
    'relationship' => array(
      'title' => t('User'),
      'label' => t('User referenced by Forward log'),
      'help' => t('The user that forwarded.'),
      'base' => 'users',
      'base field' => 'uid',
      'id' => 'standard',
    ),
  );
  $data['forward_log']['hostname'] = array(
    'title' => t('Hostname'),
    'help' => t('Hostname of the user who forwarded.'),
    'field' => array(
      'id' => 'standard',
    ),
    'argument' => array(
      'id' => 'string',
    ),
    'filter' => array(
      'id' => 'standard',
    ),
    'sort' => array(
      'id' => 'standard',
    ),
  );

  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function forward_views_data_alter(&$data) {
  $entity_types = \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_types as $type => $info) {
    if (is_a($info, 'Drupal\Core\Entity\ContentEntityType')) {
      if ($info->getBaseTable()) {
        $label = (string)$info->getLabel();
        // Add a Forward link field.
        $data[$info->getBaseTable()]['forward_link'] = array(
          'field' => array(
            'title' => t('Forward link'),
            'group' => t($label),
            'help' => t('Provide a simple link to forward the @entity-type to a friend.', array('@entity-type' => strtolower(t($label)))),
            'id' => 'forward_link',
          ),
        );
        // Add a relationship to the logs.
        $data[$info->getBaseTable() . '_field_data']['forward_log'] = array(
          'title' => t('Entity with forward logs'),
          'help' => t('Relate all forward logs for the entity. Creates a row for each instance the entity was forwarded.'),
          'relationship' => array(
            'group' => t('Forward log'),
            'label' => t('Forward log referenced by entity'),
            'base' => 'forward_log',
            'base field' => 'id',
            'relationship field' => $info->getKey('id'),
            'id' => 'standard',
            'extra' => array(
              array(
                'field' => 'type',
                'value' => $type,
              ),
            ),
          ),
        );
      }
    }
  }
}
