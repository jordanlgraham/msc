<?php

namespace Drupal\apitools\Api\Client;

use Drupal\apitools\ClientResourceManagerInterface;
use Drupal\Component\Plugin\Exception\InvalidDecoratedMethod;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Security\UntrustedCallbackException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Base class for Controller plugins.
 *
 * @method ClientResourceInterface create(array $values = [])
 * @method ClientResourceInterface get($id, array $options = [])
 * @method ClientResourceInterface[] getAll(array $options = [])
 */
abstract class ClientResourceControllerBase implements ClientResourceControllerInterface {

  use DependencySerializationTrait;

  /**
   * @var \Drupal\apitools\Api\Client\ClientInterface
   */
  protected $client;

  protected $manager;

  protected $modelPluginId;

  protected $contexts = [];

  protected $callerClientProperty;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, ClientResourceManagerInterface $manager) {
    $this->manager = $manager;
    $this->modelPluginId = $configuration['id'] ?? NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, array $configuration) {
    return new static(
      $configuration,
      $container->get('plugin.manager.apitools_client_resource')
    );
  }

  /**
   * Set by the client itself to communicate with the right merchant account.
   */
  public function setClient(ClientInterface $client) {
    $this->client = $client;
    return $this;
  }

  /**
   * Set by the client itself to communicate with the right merchant account.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Create a new ModelInterface instance.
   */
  protected function doCreate(array $values = []) {
    return $this->getResource($this->modelPluginId, $values);
  }

  /**
   * Fetch an existing ModelInterface by id.
   */
  protected function doGet($id, array $options = []) {
    $data = $this->sendRequest('get', 'get', $id, $options);
    return $data ? $this->getResource($this->modelPluginId, $data) : FALSE;
  }

  /**
   * Fetch all existing ModelInterface objects.
   */
  protected function doGetAll(array $options = []) {
    if (!$records = $this->sendRequest('getAll', 'get', NULL, $options)) {
      return [];
    }
    $models = [];
    foreach ($records as $data) {
      $models[$data['id']] = $this->getResource($this->modelPluginId, $data);
    }
    return $models;
  }

  protected function sendRequest($controller_method, $client_method, $id = NULL, $options = []) {
    if (!$path = $this->buildPath($controller_method, $id)) {
      throw new InvalidDecoratedMethod($this->t('No path defined for @method', ['@method' => $controller_method]));
    }
    return $this->request($client_method, $path, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function __call($name, $arguments) {
    // Allow controller to call defined "model_properties" if they are defined.
    $func = 'do' . ucwords($name);
    if (method_exists($this, $func)) {
      return call_user_func_array([$this, $func], $arguments);
    }
    throw new UntrustedCallbackException($name);
  }

  public function request($method, $path, array $options = []) {
    $response = $this->client->{$method}($path, $options);
    return $this->processResponse($response);
  }

  /**
   * Process the response after every request.
   *
   * @param $response
   *   Response object depends on api implementation.
   * @return mixed
   */
  protected function processResponse($response) {
    return $response;
  }

  protected function getResource($plugin_id, array $data = []) {
    $values = [];
    if (!empty($data)) {
      $values['data'] = $data;
      if (!empty($data['id'])) {
        $values['model_id'] = $data['id'];
      }
    }
    if ($this->hasContexts()) {
      $values['contexts'] = $this->contexts;
    }
    return $this->manager->getResource($plugin_id, $values)->setController($this);
  }

  /**
   * @param $context_name
   *   Machine name of the context to be stored.
   * @param $context_value
   *   Mixed, string or ClientResourceInterface.
   *
   * @return $this
   */
  public function setContext($context_name, $context_value) {
    $this->contexts[$context_name] = $context_value;
    return $this;
  }

  public function getContext($context_name) {
    if (!$this->hasContext($context_name)) {
      return FALSE;
    }
    return $this->contexts[$context_name];
  }

  public function hasContexts() {
    return !empty($this->contexts);
  }

  public function hasContext($plugin_id) {
    return !empty($this->contexts) && !empty($this->contexts[$plugin_id]);
  }

  public function clearContexts() {
    $this->contexts = [];
    $this->callerClientProperty = NULL;
  }

  protected function processPath($path, $find, $replace) {
    return str_replace([$find], [$replace], $path);
  }

  /**
   * Main method called, like ->get() or ->getAll().
   */
  protected function buildPath($method, $id = NULL) {
    // If this is being called by a ModelInterface.
    if ($context = $this->getCallerContext()) {
      $path = $this->getCallerContextPath($method);
      // If it is being called by a ModelInterface, but has no path.
      if (!$path) {
        // TODO: Maybe throw an exception?
        return FALSE;
      }
      $path = $this->processPath($path, "{{$context->getMachineName()}_id}", $context->id);
      if ($id) {
        $machine_name = $this->manager->getResource($this->modelPluginId)->getMachineName();
        $path = $this->processPath($path, "{{$machine_name}_id}", $id);
      }

      return $path;
    }

    if (!$this->callerClientProperty) {
      return FALSE;
    }
    $config = $this->manager->getDefinition($this->modelPluginId);
    if (empty($config['client_properties'][$this->callerClientProperty])) {
      return FALSE;
    }
    $paths = $config['client_properties'][$this->callerClientProperty];
    $path = !empty($paths[$method]) ? $paths[$method] : FALSE;
    if ($path) {
      if ($id) {
        $machine_name = $this->manager->getResource($this->modelPluginId)->getMachineName();
        $path = $this->processPath($path, "{{$machine_name}_id}", $id);
      }
      return $path;
    }
    return FALSE;
  }

  private function contextValueIsClientObject($value) {
    return is_object($value) && ($value instanceof ClientResourceInterface);
  }

  protected function getCallerContext() {
    foreach ($this->contexts as $key => $value) {
      if (!$this->contextValueIsClientObject($value)) {
        continue;
      }
      $config = $this->manager->getDefinition($key);
      if (!empty($config['model_properties'][$this->modelPluginId])) {
        return $value;
      }
    }
    return FALSE;
  }

  public function setCallerClientProperty($prop) {
    $this->callerClientProperty = $prop;
    return $this;
  }

  protected function getCallerContextPath($method) {
    $paths = [];
    foreach ($this->contexts as $key => $value) {
      if (!$this->contextValueIsClientObject($value)) {
        continue;
      }
      $config = $this->manager->getDefinition($key);
      if (empty($config['model_properties'][$this->modelPluginId])) {
        continue;
      }
      $paths = $config['model_properties'][$this->modelPluginId];
    }
    return !empty($paths[$method]) ? $paths[$method] : FALSE;
  }
}

