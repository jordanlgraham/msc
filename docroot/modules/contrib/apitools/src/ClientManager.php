<?php

namespace Drupal\apitools;

use Drupal\apitools\Annotation\ApiToolsClient;
use Drupal\apitools\Api\Client\ClientInterface;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\key\KeyRepositoryInterface;
use GuzzleHttp\HandlerStack;

/**
 * Provides the Client annotation plugin manager.
 */
class ClientManager extends DefaultPluginManager implements ClientManagerInterface {

  use StringTranslationTrait;

  /**
   * @var \GuzzleHttp\HandlerStack
   */
  protected $http;

  /**
   * @var \Drupal\Core\Http\ClientFactory
   */
  protected $clientFactory;

  /**
   * @var \Drupal\apitools\ClientResourceManagerInterface
   */
  protected $clientResourceManager;

  /**
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  protected $clients = [];

  /**
   * Constructs a new ClientManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \GuzzleHttp\HandlerStack $handler_stack
   *
   * @param \Drupal\Core\Http\ClientFactory $client_factory
   * @param \Drupal\apitools\ClientResourceManagerInterface $client_resource_manager
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   * @param \Drupal\Core\TempStore\SharedTempStoreFactory $temp_store_factory
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, HandlerStack $handler_stack, ClientFactory $client_factory, ClientResourceManagerInterface $client_resource_manager, KeyRepositoryInterface $key_repository, SharedTempStoreFactory $temp_store_factory, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct('Plugin/ApiTools', $namespaces, $module_handler, ClientInterface::class, ApiToolsClient::class);

    $this->alterInfo('apitools_client_info');
    $this->setCacheBackend($cache_backend, 'apitools_client_plugins');
    $this->http = $handler_stack;
    $this->clientResourceManager = $client_resource_manager;
    $this->clientFactory = $client_factory;
    $this->keyRepository = $key_repository;
    $this->tempStoreFactory = $temp_store_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  public function getAdminPermissions() {
    $permissions = [];

    foreach ($this->getDefinitions() as $plugin_id => $definition) {
      $client = $this->load($plugin_id);
      $permission = $client->getAdminPermission();
      $permissions[$permission] = [
        'title' => $this->t('Administer <em>@label</em> client settings', [
          '@label' => $client->label(),
        ]),
        'restrict access' => TRUE,
        'provider' => $definition['provider'],
      ];
    }

    return $permissions;
  }

  public function getClientResourceManager() {
    return $this->clientResourceManager;
  }

  public function getClientFactory() {
    return $this->clientFactory;
  }

  public function getTokenTempStore($id) {
    return $this->tempStoreFactory->get($id . '_tokens');
  }

  /**
   * @param $id
   *   Client plugin ID.
   * @param array $options
   *   An array of options to be passed to init.
   * @return ClientInterface
   * @throws PluginNotFoundException
   */
  public function load($id, array $options = []) {
    if (isset($this->clients[$id])) {
      return $this->clients[$id];
    }
    $this->clients[$id] = $this->createInstance($id)->init($options);
    return $this->clients[$id];
  }

  public function resetCache($id) {
    if (isset($this->clients[$id])) {
      unset($this->clients[$id]);
    }
    return $this;
  }
}
