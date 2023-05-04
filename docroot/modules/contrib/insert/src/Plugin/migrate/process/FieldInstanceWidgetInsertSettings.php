<?php

namespace Drupal\insert\Plugin\migrate\process;

use Drupal\insert\Utility\MigrateInsertWidgetSettings;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

@trigger_error(
  "The Drupal\insert\Plugin\migrate\process\FieldInstanceWidgetInsertSettings migrate process plugin is deprecated in insert:8.x-2.0-beta4 and is removed in insert:8.x-3.0. Insert settings migrations are merged into Drupal core's 'd7_field_instance_widget_settings' migration. See https://drupal.org/node/123",
  E_USER_DEPRECATED
);

/**
 * Gets the field instance widget's Insert module specific settings.
 *
 * @deprecated in insert:8.x-2.0-beta4 and is removed in insert:8.x-3.0. There
 *   is no replacement.
 *
 * @MigrateProcessPlugin(
 *   id = "field_instance_widget_insert_settings",
 *   handle_multiples = TRUE
 * )
 */
class FieldInstanceWidgetInsertSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return $this->getInsertSettings($value);
  }

  /**
   * Merges the default D8 and specified D7 Insert module settings for a widget
   * type.
   *
   * @param array $widget_settings
   *   The widget settings from D7 for this widget.
   *
   * @return array[]
   */
  public function getInsertSettings(array $widget_settings) {
    $insert = MigrateInsertWidgetSettings::getInsertWidgetSettings($widget_settings);
    return ['insert' => $insert];
  }

}
