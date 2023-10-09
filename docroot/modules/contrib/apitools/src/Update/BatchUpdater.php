<?php

namespace Drupal\apitools\Update;

/**
 * Batch process for hook_post_update.
 *
 * Example:
 * @code
 * function my_module_post_update_populate_custom_field(&$sandbox) {
 *   $user_storage = \Drupal::service('entity_type.manager')->getStorage('user');
 *
 *   $batch_update = Updater::batch($sandbox, 1, 20);
 *   if (!$batch_update->inProgress()) {
 *     // Run query for all data.
 *     $results = $user_storage->getQuery()
 *       ->condition('status', 1)
 *       ->condition('roles', ['custom role', 'another custom role'], 'IN')
 *       ->notExists('custom_field')
 *       ->execute();
 *
 *     if (empty($results)) {
 *       return t('No records found');
 *     }
 *
 *     $batch_update->init($results);
 *   }
 *
 *   $batch_update->process(function($user_ids) use ($user_storage) {
 *     $users = $user_storage->loadMultiple($user_ids);
 *     foreach ($users as $user) {
 *       $user->set('custom_field', 'test value');
 *       $user->save();
 *     }
 *   });
 *
 *   return $batch_update->summary();
 * }
 * @endcode
 */
class BatchUpdater {

  protected $sandbox;

  protected $size;

  protected $chunk;

  public function __construct(&$sandbox, $size = 10, $chunk = FALSE) {
    $this->sandbox = &$sandbox;
    $this->size = $size;
    $this->chunk = $chunk;
  }

  public function inProgress() {
    return isset($this->sandbox['progress']);
  }

  public function init($records) {
    // The count of nodes visited so far.
    $this->sandbox['progress'] = 0;
    if ($this->chunk !== FALSE) {
      $this->sandbox['records'] = array_chunk($records, $this->chunk, TRUE);
    }
    else {
      $this->sandbox['records'] = $records;
    }
    $this->sandbox['max'] = count($this->sandbox['records']);
    // Store the actual total if this is chunked for the summary.
    $this->sandbox['total'] = count($records);
  }

  public function process($callable) {
    $size = min($this->size, count($this->sandbox['records']));
    for ($x = 0; $x < $size; $x++) {
      $record = array_shift($this->sandbox['records']);
      $callable($record);
      $this->sandbox['current'] = [
        'record' => $record,
        'progress' => $this->sandbox['progress'],
      ];
      $this->sandbox['progress']++;
    }

    $this->sandbox['#finished'] = $this->sandbox['progress'] >= $this->sandbox['max'] ? TRUE : $this->sandbox['progress'] / $this->sandbox['max'];
  }

  public function summary() {
    $progress = $this->sandbox['progress'];
    // Process range or count of the total amount of records.
    if ($this->chunk !== FALSE) {
      // Current progress was incremented so rewind to display.
      $range_start = $this->sandbox['current']['progress'] * $this->chunk;
      $range_end = $range_start + count($this->sandbox['current']['record']);
      $progress = "{$range_start} - {$range_end}";
    }
    return t('Processed @count out of @total total records.', [
      '@count' => $progress,
      '@total' => $this->sandbox['total'],
    ]);
  }

}
