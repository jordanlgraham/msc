<?php

namespace Drupal\apitools\Batch;

interface BatchProcessorInterface {

  /**
   * Custom processor function for BatchBuilder.
   *
   * @param $item
   *   Current batch set.
   * @param $context
   *   Batch run storage.
   * @return mixed
   */
  public function batchProcessRecordSet($item, &$context);

  /**
   * Custom processor function for BatchBuilder.
   *
   * @param $item
   *   Current batch item.
   * @param $context
   *   Batch run storage.
   * @return mixed
   */
  public function batchProcessRecord($item, &$context);
}
