<?php

use Drupal\views\Views;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\query\QueryPluginBase;

/**
 * @filesource
 */

/**
 * Implements hook_views_query_alter().
 *
 * @param \Drupal\views\ViewExecutable $view
 *   The view object about to be processed.
 * @param QueryPluginBase $query
 *   The query plugin object for the query.
 */
function msca_misc_views_query_alter(ViewExecutable $view, QueryPluginBase $query) {
  if ($view->id() == 'preferred_vendors'
    && array_key_exists('field_primary_services_target_id', $view->exposed_raw_input)) {
    // Traverse through the 'where' part of the query.
    foreach ($query->where as $key => &$condition_group) {
      if (empty($condition_group['conditions'])) {
        unset($query->where[$key]);
        continue;
      }
      foreach ($condition_group['conditions'] as $index => &$condition) {
        // If this is the part of the query filtering on field_primary_services,
        // add condition in the OR group filtering on field_additional_services.
        switch ($condition['field']) {
          case 'node__field_primary_services.field_primary_services_target_id = :node__field_primary_services_field_primary_services_target_id':
            $tid = $condition['value'][':node__field_primary_services_field_primary_services_target_id'];

            $view->setExposedInput([
              'field_primary_services_target_id' => $tid,
              'field_additional_services_target_id' => $tid,
            ]);

            $definition = [
              'type' => 'INNER',
              'table' => 'node__field_additional_services',
              'field' => 'entity_id',
              'left_table' => 'node_field_data',
              'left_field' => 'nid',
              'operator' => '=',
            ];
            $join = Views::pluginManager('join')->createInstance('standard', $definition);
            $query->addRelationship('node__field_additional_services', $join, 'node_field_data');

            $condition_group['type'] = 'OR';
            $condition_group['conditions'][] = [
              'field' => "node__field_additional_services.field_additional_services_target_id = :node__field_additional_services_field_additional_services_target_id",
              'value' =>  [
                ':node__field_additional_services_field_additional_services_target_id' => $tid
              ],
              'operator' => 'formula',
            ];
            break;

          case 'node__field_additional_services.field_additional_services_target_id':
            unset ($condition_group['conditions'][$index]);
            break;
        }
      }
    }
  }
}
