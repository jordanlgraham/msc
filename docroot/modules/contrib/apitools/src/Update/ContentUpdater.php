<?php

namespace Drupal\apitools\Update;

use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class ContentUpdater {

  protected $data = [];

  /**
   * @var EntityTypeInterface
   */
  protected $entityTypeManager;

  /**
   * @var EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * ContentUpdater constructor.
   *
   * @param EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityRepositoryInterface $entity_repository) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
  }

  public function ensureMultiple($entity_type_id, array $entity_data) {
    $entities = [];
    foreach ($entity_data as $uuid => $data) {
      $entities[$uuid] = $this->ensure($entity_type_id, $uuid, $data);
    }
    return $entities;
  }

  public function ensure($entity_type_id, $uuid, array $data) {
    if ($entity = $this->get($entity_type_id, $uuid)) {
      return $entity;
    }
    if ($entity = $this->load($entity_type_id, $uuid)) {
      return $entity;
    }
    $data['uuid'] = $uuid;
    $entity = $this->entityTypeManager->getStorage($entity_type_id)->create($data);
    $entity->save();
    $this->data[$entity_type_id][$uuid] = $entity;
    return $entity;
  }

  public function load($entity_type_id, $uuid) {
    $entity = $this->entityRepository->loadEntityByUuid($entity_type_id, $uuid);
    if (!empty($entity)) {
      $this->data[$entity_type_id][$uuid] = $entity;
      return $entity;
    }
    return FALSE;
  }

  public function get($entity_type_id, $uuid) {
    return $this->data[$entity_type_id][$uuid] ?? NULL;
  }

  public function overwrite($entity_type_id, $uuid, array $data) {
    if (!$entity = $this->load($entity_type_id, $uuid)) {
      return;
    }
    foreach ($data as $key => $value) {
      if (!$entity->hasField($key)) {
        continue;
      }
      $entity->set($key, $value);
    }
    $entity->save();
  }

  public function getId($entity_type_id, $uuid) {
    if (!$entity = $this->get($entity_type_id, $uuid)) {
      return;
    }
    return $entity->id();
  }
}
