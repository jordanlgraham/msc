<?php

namespace Drupal\apitools\Plugin\migrate\process;

use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Transforms a numeric array into a simple associative array with one key.
 *
 * @see https://drupal.stackexchange.com/questions/255625/how-to-sub-process-a-non-associative-array-in-migration-yml/255726#255726
 *
 * The inner array will be filled with one key. E.g. ['alpha', 'beta'] becomes
 * [[value => 'alpha'], [value => 'beta']]
 *
 * Use this plugin to preprocess a numeric/non-associative array for other
 * plugins that requires an associative array as input, such as the sub_process
 * plugin.
 *
 * Available configuration keys:
 * - source: Source property.
 * - keyname: name of the key to be used for the associative sub-arrays,
 *   defaults to 'value'.
 *
 * Example:
 *
 * @code
 * source:
 *   my_flat_array:
 *     - category1
 *     - category2
 * process:
 *   my_associative_array:
 *     plugin: deepen
 *     source: my_flat_array
 *   field_taxonomy_term:
 *     plugin: sub_process
 *     source: '@my_associative_array'
 *     process:
 *       target_id:
 *         plugin: migration_lookup
 *         migration: my_taxonomy_migration
 *         source: value
 * @endcode
 *
 * @see \Drupal\migrate\Plugin\MigrateProcessInterface
 *
 * @MigrateProcessPlugin(
 *   id = "deepen",
 *   handle_multiples = TRUE
 * )
 */
class Deepen extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $keyname = (is_string($this->configuration['keyname']) && $this->configuration['keyname'] != '') ? $this->configuration['keyname'] : 'value';

    if (is_array($value) || $value instanceof \Traversable) {
      return array_map(function ($value) use ($keyname) {
        return [$keyname => $value];
      }, $value);
    }
    else {
      throw new MigrateException(sprintf('%s is not traversable', var_export($value, TRUE)));
    }
  }
}
