<?php

namespace Drupal\insert\Utility;

use Drupal\migrate\Plugin\MigrationPluginManagerInterface;

/**
 * Helper functions for migrating D7 insert widget settings to D9.
 */
class MigrateInsertWidgetSettings {

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
