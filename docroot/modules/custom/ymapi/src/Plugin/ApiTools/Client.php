<?php

namespace Drupal\ymapi\Plugin\ApiTools;

use Drupal\Component\Datetime\Time;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Component\Serialization\Json;
use Drupal\apitools\Api\Client\ClientBase;
use Drupal\apitools\ClientManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\apitools\ClientResourceManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a client to connect to the Your Membership API.
 *
 * @ApiToolsClient(
 *   id = "ymapi",
 *   admin_label = @Translation("YmAPI"),
 *   api = "ymapi",
 *   config = {
 *     "account_id" = @Translation("Account ID"),
 *     "client_id" = @Translation("Client ID"),
 *     "client_secret" = {
 *       "type" = "key_select",
 *       "title" = @Translation("Client Secret"),
 *     },
 *     "event_secret_token" = {
 *       "type" = "key_select",
 *       "title" = @Translation("Event Secret Token"),
 *     },
 *     "event_verification_token" = {
 *       "type" = "key_select",
 *       "title" = @Translation("Event Verification Token (Deprecated August 2023)"),
 *     },
 *     "base_uri" = @Translation("Base URI"),
 *     "base_path" = @Translation("Base Path"),
 *     "auth_token_url" = @Translation("Auth Token URL")
 *   }
 * )
 */
class Client extends ClientBase {

  /**
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientManagerInterface $client_manager, ClientResourceManagerInterface $resource_manager, ConfigFactoryInterface $config_factory, KeyRepositoryInterface $key_repository, Time $time, LoggerChannelInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $client_manager, $resource_manager, $config_factory, $key_repository, $time);
    $this->logger = $logger;
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
      $container->get('datetime.time'),
      $container->get('logger.channel.ymapi')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'base_uri' => 'https://ws.yourmembership.com',
      'base_path' => 'Ams',
      'https://ws.yourmembership.com/Ams/Authenticate' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function auth() {
    if ($access_token = $this->ensureAccessToken()) {
      $this->options->set('headers', [
        'x-ss-id' => $access_token,
      ]);
    }

    return $this;
  }

  /**
   * Ensures the request is made with an access token.
   *
   * @return string
   *   A Your Membership access token.
   */
  private function ensureAccessToken() {
    if (!$this->getToken('access_token')) {
      $payload = [
        'ClientID' => $this->getConfigValue('account_id'),
        'Username' => $this->getConfigValue('client_id'),
        'Password' => $this->getConfigValue('client_secret'),
        'UserType' => 'Admin',
      ];

      $headers = [
        'Authorization' => 'Bearer Token',
        'Content-Type' => 'application/json',
        'Accept' => '*/*',
        'Accept-Encoding' => 'gzip, deflate, br',
        'Connection' => 'keep-alive',
      ];

      $options = [
        'body' => json_encode($payload),
        'headers' => $headers
      ];

      $response_data = $this->request('POST', $this->getConfigValue('auth_token_url'), $options);

      if (!empty($response_data['SessionId']) && !empty($response_data['FailedLoginReason']) && $response_data['FailedLoginReason'] === 'None') {
        $this->setToken('x-ss-id', $response_data['SessionId']);
      }
    }
    return $this->getToken('x-ss-id');
  }

  /**
   * {@inheritdoc}
   */
  public function request($method, $path, $options = []) {
    // Make the HTTP request using dynamic method invocation.
    $url = UrlHelper::isExternal($path) ? $path : $this->url($path);
    // If $this->options->parameters has 'headers' element, add it to $options['headers'].
    if ($this->options->has('headers')) {
      $options['headers'] = $this->options->get('headers') + [
        'Accept' => '*/*',
        'Connection' => 'keep-alive'
      ];
    }
    try {
      $response = $this->httpClient->{$method}($url, $options);
      $response = $response->getBody()->getContents();
    } catch (\Exception $e) {
      return $this->onRequestError($e);
    }

    return $this->postRequest($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function postRequest($response) {
    return Json::decode($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function onRequestError(\Exception $e) {
    // Log Any exceptions.
    $this->logger->error('Failed to complete Your Membership API Task "%error"', ['%error' => $e->getMessage()]);
    throw $e;
  }

  /**
   * Checks for required config and then attempts to create an access token.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function validateConfiguration() {
    $account_id = $this->getConfigValue('account_id');
    $client_id = $this->getConfigValue('client_id');
    $client_secret = $this->getConfigValue('client_secret');
    if (empty($account_id) || empty($client_id) || empty($client_secret)) {
      return FALSE;
    }
    return $this->ensureAccessToken();
  }

  /**
   * {@inheritdoc}
   */
  public function get($path, $options = []) {
    return $this->auth()->request('get', $path, $options);
  }
}
