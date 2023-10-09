<?php

namespace Drupal\apitools\Update;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityDefinitionUpdateManager;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class Updater implements ContainerAwareInterface {

  use ContainerAwareTrait;

  use DependencySerializationTrait;

  protected $entityTypeManager;

  protected $entityDefinitionUpdateManager;

  protected $entityFieldManager;

  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityDefinitionUpdateManagerInterface $entity_definition_update_manager, EntityFieldManagerInterface $entity_field_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
    $this->entityFieldManager = $entity_field_manager;
  }

  public function batch(&$sandbox, $size = 10, $chunk = FALSE) {
    return new BatchUpdater($sandbox, $size, $chunk);
  }

  public function content() {
    return $this->container->get('apitools.updater.content');
  }

  public function field() {
    return $this->container->get('apitools.updater.field');
  }

  public function reinstallEntityType($entity_type_id) {
    if (!$entity_type = $this->entityTypeManager->getDefinition($entity_type_id)) {
      throw new \Exception("Entity type $entity_type_id does not exist or is not installed");
    }
    $this->entityDefinitionUpdateManager->uninstallEntityType($entity_type);
    $this->entityDefinitionUpdateManager->installEntityType($entity_type);
  }

  public function uninstallEntityType($entity_type_id) {
    $entity_type_manager = \Drupal::service('entity_type.manager');
    if (!$entity_type = $entity_type_manager->getDefinition($entity_type_id)) {
      throw new \Exception("Entity type $entity_type_id does not exist or is not installed");
    }
    $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
    $definition_update_manager->uninstallEntityType($entity_type);
  }

  public function installEntityType($entity_type_id) {
    /** @var EntityDefinitionUpdateManager $definition_update_manager */
    $definition_update_manager = \Drupal::entityDefinitionUpdateManager();
    if ($definition_update_manager->getEntityType($entity_type_id)) {
      return;
    }
    $entity_type_manager = \Drupal::service('entity_type.manager');
    if (!$entity_type = $entity_type_manager->getDefinition($entity_type_id)) {
      throw new \Exception("Entity type $entity_type_id does not exist");
    }
    $definition_update_manager->installEntityType($entity_type);
  }
}
