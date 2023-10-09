<?php

namespace Drupal\apitools\Api\Client;

use Drupal\Component\Datetime\Time;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Drupal\Core\Plugin\PluginWithFormsTrait;
use Drupal\apitools\ClientManagerInterface;
use Drupal\apitools\ClientResourceManagerInterface;
use Drupal\apitools\TokenStorageTrait;
use Drupal\apitools\Utility\ParameterBag;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Serializer\SerializerAwareTrait;

/**
 * Class ClientBase.
 */
abstract class ClientBase extends PluginBase implements ConfigurableInterface, PluginWithFormsInterface, PluginFormInterface, ClientInterface {

  use PluginWithFormsTrait;
  use TokenStorageTrait;
  use SerializerAwareTrait;

  /**
   * @var ParameterBag
   */
  protected $params;

  /**
   * @var ParameterBag
   */
  protected $options;

  /**
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * @var \Drupal\apitools\ClientResourceManagerInterface
   */
  protected $resourceManager;

    /**
     * @var \Drupal\apitools\ClientManagerInterface
     */
  protected $manager;

  protected $apiName;

  protected $controllers;

  private $configKey = 'config';

  /**
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * @var \Drupal\Component\Datetime\Time
   */
  protected $time;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientManagerInterface $client_manager, ClientResourceManagerInterface $resource_manager, ConfigFactoryInterface $config_factory, KeyRepositoryInterface $key_repository, Time $time) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->manager = $client_manager;
    $this->resourceManager = $resource_manager;
    $this->apiName = !empty($plugin_definition['api']) ? $plugin_definition['api'] : NULL;
    $this->options = new ParameterBag();
    $this->params = new ParameterBag();
    $this->configFactory = $config_factory;
    $this->keyRepository = $key_repository;
    $this->setTime($time);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.apitools_client'),
      $container->get('plugin.manager.apitools_client_resource'),
      $container->get('config.factory'),
      $container->get('key.repository'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(array $options = []) {
    // TODO: Need to merge in $options argument with $this->options without being overridden later on
    if (!isset($this->tempStore)) {
      $this->tempStore = $this->manager->getTokenTempStore($this->getPluginId());
    }
    if (!isset($this->httpClient)) {
      $this->httpClient = $this->manager->getClientFactory()->fromOptions($this->options->all());
    }

    $this->initializeConfiguration();

    if ($this->hasConfigValue('base_path')) {
      $this->options->set('base_path', $this->getConfigValue('base_path'));
    }
    if ($this->hasConfigValue('base_uri')) {
      $this->options->set('base_uri', $this->getConfigValue('base_uri'));
    }

    return $this;
  }

  /**
   * Get client label for forms and permissions.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|mixed
   */
  public function label() {
    $definition = $this->getPluginDefinition();
    return $definition['admin_label'] ?? $this->getPluginId();
  }

  public function getAdminPermission() {
    $definition = $this->getPluginDefinition();
    $plugin_id = $this->getPluginId();
    return $definition['admin_permission'] ?? "administer $plugin_id client";
  }

  /**
   * {@inheritdoc}
   */
  public function getConfigName() {
    return 'apitools.client.' . $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function config() {
    return $this->configFactory->get($this->getConfigName());
  }

  public function __get($prop) {
    if (method_exists($this, $prop)) {
      return call_user_func_array([$this, $prop], []);
    }
    if ($controller = $this->getResourceController($prop)) {
       return $controller;
    }
    if (!empty($this->pluginDefinition['client_default_controller'])) {
      return $this->resourceManager->createControllerInstance($this->pluginDefinition['client_default_controller'])
        ->setClient($this)
        ->setCallerClientProperty($prop);
    }
    return FALSE;
  }

  protected function getResourceController($prop) {
    if (!$class = $this->resourceManager->getResourceControllerByMethod($prop, $this->apiName)) {
      return NULL;
    }
    return $class->setClient($this)->setCallerClientProperty($prop);
  }

  /**
   * Authenticate the client and set tokens.
   *
   * @return $this
   */
  abstract protected function auth();

  /**
   * Alter a request response before it's returned from ->request().
   *
   * @param $response
   *   The returned value from $response->getBody()->getContent().
   *
   * @return mixed
   */
  protected function postRequest($response) {
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function request($method, $path, $options = []) {
    $request_options = clone $this->options;
    $request_options->replace(NestedArray::mergeDeep($request_options->all(), $options));
    $url = UrlHelper::isExternal($path) ? $path : $this->url($path);
    try {
      $response = $this->httpClient->{$method}($url, $request_options->all());
      $response = $response->getBody()->getContents();
    }
    catch (\Exception $e) {
      return $this->onRequestError($e);
    }
    return $this->postRequest($response);
  }

  /**
   * Allows for clients to choose whether to throw exceptions or not.
   *
   * @param \Exception $e
   *   The request exception.
   *
   * @return mixed
   */
  protected function onRequestError(\Exception $e) {
    watchdog_exception('apitools', $e);

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function put($path, $options = []) {
    return $this->auth()->request('put', $path, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function patch($path, $options = []) {
    return $this->auth()->request('patch', $path, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function get($path, $options = []) {
    return $this->auth()->request('get', $path, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function post($path, $options = []) {
    return $this->auth()->request('post', $path, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function delete($path, $options = []) {
    return $this->auth()->request('delete', $path, $options);
  }

  /**
   * Get the full url with base uri and base path.
   *
   * @param $path
   *   The resource path.
   *
   * @return string
   */
  public function url($path) {
    $url = $this->options->get('base_uri');
    $base_path = $this->options->get('base_path');
    return join('/', array_filter([$url, $base_path, $path]));
  }


  /**
   * TODO: Move all this to base ClientBase class.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *
   * @return array
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form[$this->configKey] = [
      '#type' => 'container',
      '#tree' => TRUE,
    ];
    foreach ($this->getConfigurationDefinitions() as $id => $value) {
      if (is_array($value)) {
        $form[$this->configKey][$id] = [];
        $element = &$form[$this->configKey][$id];
        foreach ($value as $prop => $val) {
          $element['#' . $prop] = $val;
        }
        if (!isset($element['#type'])) {
          $element['#type'] = 'textfield';
        }

        $element['#default_value'] = $this->config()->get($id) ?? NULL;

        if ($element['#type'] == 'key_select') {
          $element['#empty_option'] = $this->t('-None-');
        }
      }
      else {
        $form[$this->configKey][$id] = [
          '#title' => $value,
          '#type' => 'textfield',
          '#default_value' => $this->config()->get($id) ?? NULL,
        ];
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  private function getConfigType($config_name) {
    $definition = $this->getConfigurationDefinitions();
    if (!isset($definition[$config_name])) {
      throw new \Exception('Invalid config name');
    }
    $config_definition = $definition[$config_name];
    return is_array($config_definition) && isset($config_definition['type']) ? $config_definition['type'] : 'textfield';
  }

  private function getConfigKeyValue($key_name) {
    $key = $this->keyRepository->getKey($key_name);
    $is_multivalue = $key->getKeyType()->getPluginDefinition()['multivalue']['enabled'];
    return $is_multivalue ? $key->getKeyValues() : $key->getKeyValue();
  }

  protected function hasConfigValue($config_name) {
    $config = $this->getConfigurationDefinitions();
    return key_exists($config_name, $config);
  }

  protected function getConfigValue($config_name) {
    $config = $this->getConfiguration();
    if (!$config_value = $config[$config_name] ?? NULL) {
      return $config_value;
    }
    $config_type = $this->getConfigType($config_name);
    if ($config_type === 'key_select') {
      return $this->getConfigKeyValue($config_value);
    }
    return $config_value;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * Get the configuration definitions in the plugin definition.
   *
   * @return array
   */
  public function getConfigurationDefinitions() {
    $definition = $this->getPluginDefinition();
    return $definition[$this->configKey] ?? [];
  }

  /**
   * Merge config API, default, and constructor configurations.
   *
   * @return void
   */
  private function initializeConfiguration() {
    $config = $this->config()->getRawData();
    $configuration = NestedArray::mergeDeep($this->defaultConfiguration(), array_filter($config), $this->configuration);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }
}