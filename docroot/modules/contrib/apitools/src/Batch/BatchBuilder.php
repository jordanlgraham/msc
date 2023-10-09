<?php

namespace Drupal\apitools\Batch;

use BatchProcessorInterface;
use Drupal\Core\Batch\BatchBuilder as BatchBuilderCore;

class BatchBuilder extends BatchBuilderCore {


  public $size = 25;

  /**
   * The class string definition that will handle processing.
   *
   * @var string
   */
  protected $processorClass;

  /**
   * Parameters to pass to processor methods.
   *
   * @var array
   */
  protected $params = [];

  /**
   * The data that will be batch processed.
   *
   * @var array
   */
  protected $data;

  /**
   * Sets the size of each batch.
   *
   * @param $size
   *
   * @return $this
   */
  public function size($size) {
    $this->size = $size;
    return $this;
  }

  /**
   * Set the parameters to send to the processor class methods.
   *
   * @param array $params
   *
   * @return $this
   */
  public function params(array $params) {
    $this->params = $params;
    return $this;
  }

  /**
   * Set the data that will be processed.
   *
   * @param array $data
   *
   * @return $this
   */
  public function data(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Main method to run the current batch.
   *
   * @throws \Exception
   */
  public function run() {
    if (empty($this->data)) {
      throw new \Exception(t('No data set for this batch.'));
    }
    $this->addOperation([$this, 'process'], [$this->params, $this->data]);

    batch_set($this->toArray());
  }

  /**
   * Set the processor class to act on each record and/or record set.
   *
   * @param $processor_class
   *
   * @return $this
   */
  public function setProcessorClass($processor_class) {
    $this->processorClass = $processor_class;
    return $this;
  }

  /**
   * The process method passed to batch_set.
   *
   * @param array $params
   * @param array $data
   * @param $context
   */
  public function process(array $params, array $data, &$context) {
    $this->initContext($params, $data, $context);

    $sandbox = &$context['sandbox'];

    while ($record_set = array_shift($sandbox['records'])) {
      $this->processRecordSet($record_set, $context);
      $sandbox['progress']++;
    }

    // Set the "finished" status, to tell batch engine whether this function
    // needs to run again. If you set a float, this will indicate the progress
    // of the batch so the progress bar will update.
    $context['finished'] = $sandbox['progress'] >= $sandbox['max'] ? TRUE : $sandbox['progress'] / $sandbox['max'];
  }

  /**
   * Initialize any variables needed in the context.
   *
   * @param array $params
   * @param array $data
   * @param $context
   */
  protected function initContext(array $params, array $data, &$context) {
    // Add params to send to processor functions.
    if (!isset($context['params'])) {
      $context['params'] = $params;
    }

    $sandbox = &$context['sandbox'];
    // Add progress tracking and total rows for the sandbox in the batch.
    if (!isset($sandbox['progress'])) {

      // The count of nodes visited so far.
      $sandbox['progress'] = 0;

      $iterator = new ArrayIterator($data);

      $sandbox['records'] = $iterator->chunk($this->size);;
      $sandbox['max'] = count($sandbox['records']);

      // A place to store messages during the run.
      $sandbox['messages'] = [];
    }
  }

  /**
   * Process one record set created from array_chunk.
   *
   * @param $record_set
   * @param $context
   */
  protected function processRecordSet($record_set, &$context) {
    $this->callProcessorMethod('batchProcessRecordSet', $record_set, $context);
    while ($record = $record_set->current()) {
      $this->processRecord($record, $context);
      $record_set->next();
    }
  }

  /**
   * Process a single record.
   *
   * @param $record
   * @param $context
   */
  protected function processRecord($record, &$context) {
    $this->callProcessorMethod('batchProcessRecord', $record, $context);
  }

  /**
   * Gets the current processor and calls the method if they exist.
   *
   * TODO: create an interface that declares batchProcessRecord and batchProcessRecordSet.
   */
  protected function callProcessorMethod() {
    $args = func_get_args();
    $method_name = array_shift($args);
    if ($this->processorClass) {
      $instance = \Drupal::service('class_resolver')->getInstanceFromDefinition($this->processorClass);
      if (method_exists($instance, $method_name)) {
        call_user_func_array([$instance, $method_name], $args);
      }
    }
  }

}

