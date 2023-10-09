<?php

namespace Drupal\apitools;

use Drupal\apitools\Annotation\ApiToolsClientResource;
use Drupal\apitools\Api\Client\ClientResourceControllerDefault;
use Drupal\apitools\Api\Client\ClientResourceControllerInterface;
use Drupal\apitools\Api\Client\ClientResourceInterface;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

/**
 * Provides the client object plugin manager.
 */
class ClientResourceManager extends DefaultPluginManager implements ClientResourceManagerInterface, ContainerAwareInterface {

  use ContainerAwareTrait;

  /**
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  protected $classResolver;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\apitools\Api\Client\ClientResourceControllerInterface[]
   */
  protected $controllers = [];

  /**
   * Constructs a new ResourceManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   * @param \Drupal\Core\DependencyInjection\ClassResolverInterface $class_resolver
   *   The class resolver service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, ClassResolverInterface $class_resolver, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct('Plugin/ApiTools', $namespaces, $module_handler, ClientResourceInterface::class, ApiToolsClientResource::class);

    $this->classResolver = $class_resolver;
    $this->entityTypeManager = $entity_type_manager;

    $this->alterInfo('apitools_client_object_info');
    $this->setCacheBackend($cache_backend, 'apitools_client_object_plugins');
  }

  public function getDefinitionsByType($type) {
    $definitions = [];
    foreach ($this->getDefinitions() as $machine_name => $definition) {
      if (empty($definition['type'])) {
        continue;
      }
      $definition_type = $definition['type'];
      $definitions[$definition_type][$machine_name] = $definition;
    }
    return !empty($definitions[$type]) ? $definitions[$type] : [];
  }
  /**
   * {@inheritdoc}
   */
  public function getDefinitionByMethod($client_method) {
    foreach ($this->getDefinitions() as $definition) {
      if (!isset($definition['client_properties'])) {
        continue;
      }
      if (!isset($definition['client_properties'][$client_method]) && !in_array($client_method, $definition['client_properties'])) {
        continue;
      }
      return $definition;
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResource($resource_name, array $values = []) {
    if ($definition = $this->getDefinition($resource_name)) {
      $configuration = array_merge($definition, $values);
      return $this->createInstance($resource_name, $configuration);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceController($resource_name) {
    if ($definition = $this->getDefinition($resource_name)) {
      $class = !empty($definition['controller']) ? $definition['controller'] : ClientResourceControllerDefault::class;
      return $this->createControllerInstance($class, $definition);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceControllerByMethod($client_method) {
    if ($definition = $this->getDefinitionByMethod($client_method)) {
      return $this->getResourceController($definition['id']);
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    foreach ($definitions as $plugin_id => &$definition) {
      if (!empty($definition['client_property'])) {
        $definition['client_properties'] = [$definition['client_property']];
        unset($definition['client_property']);
      }
    }
    parent::alterDefinitions($definitions);
  }

  /**
   * Create a new ResourceControllerInterface object.
   *
   * @param $class
   *   Class reference string.
   * @param array $definition
   *   Plugin definition array.
   *
   * @return \Drupal\apitools\Api\Client\ClientResourceControllerInterface|bool
   */
  public function createControllerInstance($class, array $definition = []) {
    if (is_subclass_of($class, ClientResourceControllerInterface::class)) {
      $controller = $class::createInstance($this->container, $definition);
    }
    else {
      $controller = new $class($definition, $this);
    }
    return $controller;
  }

  public function load($plugin_id, $entity_id) {
    $definition = $this->getDefinition($plugin_id);
    if (empty($definition['base_entity_type'])) {
      return FALSE;
    }
    $entity_type = $definition['base_entity_type'];
    if (!$entity = $this->entityTypeManager->getStorage($entity_type)->load($entity_id)) {
      return FALSE;
    }
    return $this->createInstance($plugin_id, [
      'entity' => $entity,
    ]);
  }

  public function loadByEntity($plugin_id, $entity) {
    return $this->createInstance($plugin_id, [
      'entity' => $entity,
    ]);
  }
}
