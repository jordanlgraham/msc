<?php

namespace Drupal\apitools;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;

  /**
   * Helper methods for syncing API data.
   */
abstract class SyncManager {

  use StringTranslationTrait;

  /**
   * Drupal\Core\Entity\EntityTypeManager definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * Drupal\apitools\ClientManagerInterface definition.
   *
   * @var \Drupal\apitools\ClientManagerInterface
   */
  protected $client;

  /**
   * The queue factory service.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The logger channel if set in services.yml.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   Entity type manager.
   * @param \Drupal\apitools\ClientManagerInterface $client_manager
   *   ApiTools client plugin manager.
   * @param QueueFactory $queue_factory
   *   Queue factory services.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ClientManagerInterface $client_manager, QueueFactory $queue_factory) {
    $this->entityTypeManager = $entity_type_manager;
    $this->client = $client_manager->load($this->getClientPluginId());
    $this->queueFactory = $queue_factory;
  }

  public function setLogger(LoggerChannelInterface $logger) {
    $this->logger = $logger;
  }

  public function getLogger() {
    return $this->logger;
  }

  /**
   * ApiTools Client plugin ID.
   *
   * @return string
   */
  abstract protected function getClientPluginId();

  /**
   * Helper function to create or load a taxonomy term by field and vocab.
   *
   * @param $field_name
   *   The machine id of the field to query on.
   * @param $field_value
   *   The value of the field to query on.
   * @param $term_name
   *   The title of the term name to save.
   * @param $vocab
   *   The vocabulary to specify in the query.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function ensureTermByFieldValue($field_name, $field_value, $term_name, $vocab) {
    if (empty($field_name) || !isset($field_value)) {
      return NULL;
    }
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => $vocab,
      $field_name => $field_value,
    ]);

    if (empty($terms)) {
      // Save now in order to avoid duplication issues in the same script.
      $taxonomy_term = $this->entityTypeManager->getStorage('taxonomy_term')->create([
        'name' => $term_name,
        'vid' => $vocab,
        $field_name => $field_value,
      ]);
      $this->entityTypeManager->getStorage('taxonomy_term')->save($taxonomy_term);
      return $taxonomy_term;
    }

    return reset($terms);
  }
}
