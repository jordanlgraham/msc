<?php

namespace Drupal\insert\Utility;

use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

/**
 * Helper functions for migrating D7 insert widget settings to D9.
 */
class MigrateInsertWidgetSettings {

  /**
   * Determines whether the standalone insert settings migration can be skipped.
   *
   * We migrate Insert third party settings as part of Drupal core's
   * "d7_field_instance_widget_settings" migration, with a
   * migration_plugins_alter and with insert_migrate_prepare_row hook
   * implementation. But before we remove the preexisting, standalone insert
   * settings migration, we have to check if the map table of
   * 'd7_field_instance_widget_insert_settings' is empty.
   *
   * @return bool
   *   Whether the standalone insert settings migration can be skipped.
   */
  public static function standaloneMigrationIsOmittable(): bool {
    $plugin_manager = \Drupal::service('plugin.manager.migration');
    assert($plugin_manager instanceof MigrationPluginManagerInterface);
    $standalone_migration = $plugin_manager->createStubMigration([
      'id' => 'd7_field_instance_widget_insert_settings',
      'source' => ['plugin' => 'd7_field_instance_per_form_display'],
      'destination' => ['plugin' => 'null'],
    ]);

    // The map table of 'd7_field_instance_widget_insert_settings' is not empty.
    if ($standalone_migration->getIdMap()->processedCount() > 0) {
      @trigger_error(
        "The standalone Insert settings migration 'd7_field_instance_widget_insert_settings' is deprecated in insert:8.x-2.0-beta4 and is removed in insert:8.x-3.0. Insert settings migrations are merged into Drupal core's 'd7_field_instance_widget_settings' migration. See https://drupal.org/node/123",
        E_USER_DEPRECATED
      );
      return FALSE;
    }

    // The map table of 'd7_field_instance_widget_insert_settings' is empty,
    // which means:
    // - It wasn't ever executed.
    // - It was rolled back.
    // - There are no Drupal 7 fields in the source database.
    // All of the above means that the standalone insert settings migration can
    // be ignored.
    return TRUE;
  }

  /**
   * Converts D7 field insert widget settings to Drupal 9 third party settings.
   *
   * @param array $widget_settings
   *   Drupal 7 widget settings of the field.
   *
   * @return array|null
   *   Drupal 9 Insert third party settings of the actual field component.
   */
  public static function getInsertWidgetSettings(array $widget_settings): ?array {
    if (empty($widget_settings['insert'])) {
      return NULL;
    }

    $insert_settings = [];
    foreach ($widget_settings['insert_styles'] as $style_key => $style_value) {
      $style_key = static::getTargetStyleFromSource($style_key);
      $insert_settings['styles'][$style_key] = $style_value ? $style_key : 0;
    }
    $insert_settings['default'] = static::getTargetStyleFromSource($widget_settings['insert_default']);
    $insert_settings['class'] = $widget_settings['insert_class'];
    $insert_settings['width'] = $widget_settings['insert_width'];

    return $insert_settings;
  }

  /**
   * Maps a Drupal 7 insert style to its Drupal 9 equivalent.
   *
   * @param string|null $source_style
   *   The Drupal 7 insert style to process.
   *
   * @return string
   *   The Drupal 9 equivalent of the given Drupal 7 insert style ID.
   */
  protected static function getTargetStyleFromSource(string $source_style = NULL): string {
    // Map 'auto' to 'insert__auto'.
    if ($source_style === 'auto') {
      return 'insert__auto';
    }
    // Map D7 image styles to D9 image style config name.
    if (preg_match('/^image_(.*)$/', $source_style, $matches)) {
      return $matches[1];
    }

    return $source_style ?? INSERT_DEFAULT_SETTINGS['default'];
  }

}
