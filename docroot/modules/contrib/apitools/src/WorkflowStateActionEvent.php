<?php

namespace Drupal\apitools;

use Symfony\Contracts\EventDispatcher\Event;

class WorkflowStateActionEvent extends Event implements \IteratorAggregate {

  /**
   * The array of entities that have been updated.
   *
   * @var array
   */
  protected $entities = [];

  /**
   * An array of pre-update status IDs for the entities.
   *
   * @var array
   */
  protected $originalStatuses = [];

  /**
   * The status that was applied to the entities.
   *
   * @var string
   */
  protected $toState;

  /**
   *
   * Constructs a \Drupal\download_request\Event\DownloadRequestBulkActionEvent object.
   *
   * @param array $entities
   *   The entities that have been updated.
   */
  public function __construct(array $entities = []) {
    $this->setEntities($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Traversable
  {
    return new \ArrayIterator($this->getEntities());
  }

  /**
   * Get the updated entities.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   */
  public function getEntities() {
    return $this->entities;
  }

  /**
   * Set the updated entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $entities
   *   An array of the updated entity objects.
   *
   * @return $this
   */
  public function setEntities($entities) {
    $this->entities = $entities;
    return $this;
  }

  /**
   * Get the pre-update status IDs for the current entities.
   *
   * @return array
   */
  public function getOriginalStatuses() {
    return $this->originalStatuses;
  }

  /**
   * Set the pre-update status IDs for the current entities.
   *
   * @param array $original_statuses
   *   An array of the status IDs.
   *
   * @return $this
   */
  public function setOriginalStatuses(array $original_statuses) {
    $this->originalStatuses = $original_statuses;
    return $this;
  }

  /**
   * Get the workflow state ID being applied.
   *
   * @return mixed
   */
  public function getToState() {
    return $this->toState;
  }

  /**
   * Set the workflow state ID being applied.
   *
   * @param $state_id
   *   The workflow state ID.
   *
   * @return $this
   */
  public function setToState($state_id) {
    $this->toState = $state_id;
    return $this;
  }
}
