<?php

namespace Drupal\apitools\Update;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityLastInstalledSchemaRepository;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Sql\DefaultTableMapping;
use Drupal\Core\Entity\Sql\TableMappingInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Site\Settings;

class FieldUpdater {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface;
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityDefinitionUpdateManagerInterface;
   */
  protected $entityDefinitionUpdateManager;

  /**
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface;
   */
  protected $entityFieldManager;

  /**
   * @var \Drupal\Core\Database\Connection;
   */
  protected $database;

  /**
   * @var \Drupal\Core\Entity\EntityLastInstalledSchemaRepository;
   */
  protected $entityLastInstalledSchemaRepository;

  /**
   * @var \Drupal\Core\Config\StorageInterface;
   */
  protected $configStorage;

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface;
   */
  protected $configFactory;

  /**
   * FieldUpdater constructor.
   * @param EntityTypeManagerInterface $entity_type_manager
   * @param EntityDefinitionUpdateManagerInterface $entity_definition_update_manager
   * @param EntityFieldManagerInterface $entity_field_manager
   * @param Connection $database
   * @param EntityLastInstalledSchemaRepository $entity_last_installed_schema_repository
   * @param StorageInterface $config_storage
   * @param ConfigFactoryInterface $config_factory
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityDefinitionUpdateManagerInterface $entity_definition_update_manager,
    EntityFieldManagerInterface $entity_field_manager,
    Connection $database,
    EntityLastInstalledSchemaRepository $entity_last_installed_schema_repository,
    StorageInterface $config_storage,
    ConfigFactoryInterface $config_factory
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityDefinitionUpdateManager = $entity_definition_update_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->database = $database;
    $this->entityLastInstalledSchemaRepository = $entity_last_installed_schema_repository;
    $this->configStorage = $config_storage;
    $this->configFactory = $config_factory;
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   *
   * @return array
   */
  public function fetchFieldDedicatedStorageRevisionData($entity_type_id, $field_name) {
    return $this->doFetchFieldStorageData($entity_type_id, $field_name, TRUE, FALSE);
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return mixed
   */
  public function fetchFieldDedicatedStorageData($entity_type_id, $field_name) {
    return $this->doFetchFieldStorageData($entity_type_id, $field_name, FALSE, FALSE);
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fetchFieldSharedStorageRevisionData($entity_type_id, $field_name) {
    return $this->doFetchFieldStorageData($entity_type_id, $field_name, TRUE, TRUE);
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return mixed
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function fetchFieldSharedStorageData($entity_type_id, $field_name) {
    return $this->doFetchFieldStorageData($entity_type_id, $field_name, FALSE, TRUE);
  }

  /**
   * @param array $data
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @throws \Exception
   */
  public function populateFieldDedicatedStorageRevisionData(array $data, $entity_type_id, $field_name) {
    $this->doPopulateFieldStorageData($data, $entity_type_id, $field_name, TRUE, FALSE);
  }

  /**
   * TODO: This is not finished.
   *
   * @param array $data
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @throws \Exception
   */
  public function populateFieldDedicatedStorageData(array $data, $entity_type_id, $field_name, $mapping_callback = NULL) {
    $this->doPopulateFieldStorageData($data, $entity_type_id, $field_name, FALSE, FALSE, $mapping_callback);
  }

  /**
   * TODO: This is not finished.
   *
   * @param array $data
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function populateFieldSharedStorageRevisionData(array $data, $entity_type_id, $field_name) {
    $id_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('revision_id');
    $this->doPopulateFieldStorageData($data, $entity_type_id, $field_name, TRUE, TRUE);
  }

  /**
   * @param array $data
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function populateFieldSharedStorageData(array $data, $entity_type_id, $field_name) {
    $id_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('id');
    $this->doPopulateFieldStorageData($data, $entity_type_id, $field_name, FALSE, TRUE);
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return \Drupal\Core\Database\StatementInterface|int|string|null
   */
  public function clearFieldDedicatedStorageRevisionData($entity_type_id, $field_name) {
    return $this->doClearFieldStorageData($entity_type_id, $field_name, TRUE, FALSE);
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return \Drupal\Core\Database\StatementInterface|int|string|null
   */
  public function clearFieldDedicatedStorageData($entity_type_id, $field_name) {
    return $this->doClearFieldStorageData($entity_type_id, $field_name, FALSE, FALSE);
  }

  /**
   * TODO: Not finished.
   *
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return \Drupal\Core\Database\StatementInterface|int|string|null
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function clearFieldSharedStorageRevisionData($entity_type_id, $field_name) {
    $id_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('revision_id');
    return $this->doClearFieldStorageData($entity_type_id, $field_name, TRUE, TRUE);
  }

  /**
   * TODO: Not finished.
   *
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @return \Drupal\Core\Database\StatementInterface|int|string|null
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function clearFieldSharedStorageData($entity_type_id, $field_name) {
    $id_key = $this->entityTypeManager->getDefinition($entity_type_id)->getKey('id');
    return $this->doClearFieldStorageData($entity_type_id, $field_name, FALSE, TRUE);
  }

  /**
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @throws \Exception
   */
  public function updateEntityFieldStorage($entity_type_id, $field_name) {
    if (!$this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id)) {
      return;
    }
    $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    if (empty($field_definitions[$field_name])) {
      throw new \Exception("$entity_type_id field \"$field_name\" is not defined");
    }
    $this->entityDefinitionUpdateManager->updateFieldStorageDefinition($field_definitions[$field_name]);
    $this->entityFieldManager->clearCachedFieldDefinitions();
  }

  /**
   * Install a base entity field for an entity type.
   *
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @param $module
   *   The module providing the field.
   * @throws \Exception
   */
  public function installEntityFieldStorage($entity_type_id, $field_name, $module) {
    if ($this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id)) {
      return;
    }

    $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    if (empty($field_definitions[$field_name])) {
      throw new \Exception("$entity_type_id field \"$field_name\" is not defined");
    }
    $this->entityDefinitionUpdateManager->installFieldStorageDefinition($field_name, $entity_type_id, $module, $field_definitions[$field_name]);
  }

  /**
   * Uninstall a base entity field for an entity type.
   *
   * @param $entity_type_id
   *   The entity type id.
   * @param $field_name
   *   The field machine name.
   * @throws \Exception
   */
  public function uninstallEntityFieldStorage($entity_type_id, $field_name) {
    if (!$this->entityDefinitionUpdateManager->getFieldStorageDefinition($field_name, $entity_type_id)) {
      return;
    }

    $field_definitions = $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
    if (empty($field_definitions[$field_name])) {
      throw new \Exception("$entity_type_id field \"$field_name\" is not defined");
    }
    $this->entityDefinitionUpdateManager->uninstallFieldStorageDefinition($field_definitions[$field_name]);
  }

  public function importFieldStorageConfig($entity_type_id, $field_name) {
    $config_name = "field.storage.$entity_type_id.$field_name";
    $config_active = $this->configFactory->getEditable($config_name);
    $config_path = Settings::get('config_sync_directory');
    $source = new FileStorage($config_path);
    $config_staged = $source->read($config_name);
    $config_active->setData($config_staged);
    $config_active->save();
  }

  public function entityFieldNeedsUpdates($entity_type_id, $field_name) {
    $update_list = $this->entityDefinitionUpdateManager->getChangeList();
    return NestedArray::keyExists($update_list, [$entity_type_id, 'field_storage_definitions', $field_name]);
  }

  private function getStorageDefinitions($entity_type_id, $last) {
    return $last
      ? $this->entityLastInstalledSchemaRepository->getLastInstalledFieldStorageDefinitions($entity_type_id)
      : $this->entityFieldManager->getFieldStorageDefinitions($entity_type_id);
  }

  private function getStorageDefinition($entity_type_id, $field_name, $last) {
    $storage_definitions = $this->getStorageDefinitions($entity_type_id, $last);
    return $storage_definitions[$field_name] ?? NULL;
  }

  private function getColumnDefinitions($entity_type_id, $field_name, $last) {
    $storage_definition = $this->getStorageDefinition($entity_type_id, $field_name, $last);
    return $storage_definition->getColumns();
  }

  private function getTableMapping($entity_type_id, $last) {
    $storage_definitions = $this->getStorageDefinitions($entity_type_id, $last);
    return $this->entityTypeManager->getStorage($entity_type_id)->getTableMapping($storage_definitions);
  }

  private function getColumnNames($entity_type_id, $field_name, $revision, $shared, $last, $include_id_key = FALSE) {
    $storage_definition = $this->getStorageDefinition($entity_type_id, $field_name, $last);
    $table_mapping = $this->getTableMapping($entity_type_id, $last);
    $column_definitions = $this->getColumnDefinitions($entity_type_id, $field_name, $last);

    $column_names = array_map(function($column_name) use ($table_mapping, $storage_definition, $shared) {
      return $table_mapping->getFieldColumnName($storage_definition, $column_name, $shared, $table_mapping);
    }, array_keys($column_definitions));

    if (!$include_id_key) {
      return $column_names;
    }

    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
    $id_key = $entity_type->getKey('id');
    $id_columns = $shared ? [$id_key] : ($revision ? ['entity_id', 'revision_id'] : ['entity_id']);
    // Make sure we add the type in case we need to repopulate a dedicated
    // table. Fetching from a dedicated table will already have the
    // "bundle" column.
    if ($shared && ($bundle = $entity_type->getKey('bundle'))) {
      $column_names[] = $bundle;
    }
    return array_merge($id_columns, $column_names);
  }

  /**
   * Get the full database column name for a column.
   *
   * @see DefaultTableMapping::getFieldColumnName()
   */
  private function getFieldColumnName(FieldStorageDefinitionInterface $storage_definition, $property_name, $shared, TableMappingInterface $table_mapping) {
    $field_name = $storage_definition->getName();

    if ($shared) {
      $column_name = count($storage_definition->getColumns()) == 1 ? $field_name : $field_name . '__' . $property_name;
    }
    else {
      if ($property_name == TableMappingInterface::DELTA) {
        $column_name = 'delta';
      }
      else {
        $column_name = !in_array($property_name, $table_mapping->getReservedColumns()) ? $field_name . '_' . $property_name : $property_name;
      }
    }
    return $column_name;
  }

  private function getTableName($entity_type_id, $field_name, $revision, $shared, $last) {
    /** @var DefaultTableMapping $table_mapping */
    $table_mapping = $this->getTableMapping($entity_type_id, $last);
    $storage_definition = $this->getStorageDefinition($entity_type_id, $field_name, $last);
    $is_deleted = $storage_definition->isDeleted();

    if ($shared && $revision) {
      return $table_mapping->getRevisionDataTable() ?? $table_mapping->getRevisionTable();
    }
    if ($shared && !$revision) {
      return $table_mapping->getDataTable() ?? $table_mapping->getBaseTable();
    }

    return $revision
      ? $table_mapping->getDedicatedRevisionTableName($storage_definition, $is_deleted)
      : $table_mapping->getDedicatedDataTableName($storage_definition, $is_deleted);
  }

  protected function doClearFieldStorageData($entity_type_id, $field_name, $revision, $shared) {
    $table_name = $this->getTableName($entity_type_id, $field_name, $revision, $shared, TRUE);
    if ($shared) {
      $query = $this->database->update($table_name);
      $column_names = $this->getColumnNames($entity_type_id, $field_name, $revision, $shared, TRUE);
      $nullify_columns = array_map(function() { return NULL; }, array_flip($column_names));
      $query->fields($nullify_columns);
    }
    else {
      $query = $this->database->delete($table_name);
    }
    return $query->execute();
  }

  protected function doFetchFieldStorageData($entity_type_id, $field_name, $revision, $shared) {
    $table_name = $this->getTableName($entity_type_id, $field_name, $revision, $shared, TRUE);
    $query = $this->database->select($table_name, 't');
    $or = $query->orConditionGroup();
    foreach ($this->getColumnNames($entity_type_id, $field_name, $revision, $shared, TRUE) as $column_name) {
      $or->isNotNull($column_name);
    }
    $query->condition($or);

    if ($shared) {
      $columns = $this->getColumnNames($entity_type_id, $field_name, $revision, $shared, TRUE, TRUE);
      $query->fields('t', $columns);
    }
    else {
      $query->fields('t');
    }
    return $query->execute()->fetchAll();
  }

  /**
   * TODO: Finish update for shared tables.
   *
   * @param array $data
   * @param $entity_type_id
   * @param $field_name
   * @param $revision
   * @param $shared
   * @throws \Exception
   */
  protected function doPopulateFieldStorageData(array $data, $entity_type_id, $field_name, $revision, $shared, $mapping_callback = NULL) {
    // Update.
    if ($shared) {
      $table_name = $this->getTableName($entity_type_id, $field_name, $revision, $shared, FALSE);
      $column_names = $this->getColumnNames($entity_type_id, $field_name, $revision, $shared, FALSE);
      $query = $this->database->update($table_name);
    }
    else {
      $table_name = $this->getTableName($entity_type_id, $field_name, $revision, $shared, FALSE);
      foreach ($data as $datum) {
        $datum = (array) $datum;
        if (is_callable($mapping_callback)) {
          $mapping_callback($datum);
        }
        $this->database->insert($table_name)->fields($datum)->execute();
      }
    }
  }
}
