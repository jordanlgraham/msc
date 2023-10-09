<?php

namespace Drupal\apitools_test\Plugin\ApiTools;

use Drupal\apitools\Api\Client\ClientBase;
use Drupal\Component\Serialization\Json;

/**
 * Creates a new Zoo test api client.
 *
 * @ApiToolsClient(
 *   id = "zooapi",
 *   admin_label = @Translation("Zoo API"),
 *   api = "zoo",
 *   config = {
 *     "client_id" = @Translation("Client ID"),
 *     "client_secret" = @Translation("Client Secret"),
 *     "base_uri" = @Translation("Base URI"),
 *     "grant_type" = @Translation("Grant type")
 *   }
 * )
 */
class Client extends ClientBase {

  private $testUsername;
  private $testPassword;
  private $testClientId;
  private $testClientSecret;
  private $testScope;

  /**
   * {@inheritdoc}
   */
  public function init(array $options = []) {
    $url = \Drupal::request()->getSchemeAndHttpHost();

    $this->options->add([
      'base_uri' => $url,
      'base_path' => '',
    ]);

    return parent::init($options);
  }

    public function setTestCredentials($credentials) {
      $this->testUsername = $credentials['username'];
      $this->testPassword = $credentials['pass'];
      $this->testClientId = $credentials['client_id'];
      $this->testClientSecret = $credentials['client_secret'];
      $this->testScope = $credentials['client_scope'];
    }

  protected function auth() {
    $url = \Drupal::request()->getSchemeAndHttpHost();
    $access_token = $this->getToken('access_token');
    if (!$access_token) {
      $options = [
        'form_params' => [
          'grant_type' => 'client_credentials',
          'scopes' => $this->testScope,
          'client_id' => $this->testClientId,
          'client_secret' => $this->testClientSecret,
          'username' => $this->testUsername,
          'password' => $this->testPassword,
        ],
      ];
      $response = $this->request('post', $url . '/oauth/token', $options);
      if (!empty($response['access_token'])) {
        $this->setToken('access_token', $response['access_token']);
      }
      else {
        $this->clearToken('access_token');
      }
    }

    $this->options->set('headers', [
      'Authorization' => 'Bearer ' . $this->getToken('access_token'),
    ]);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function postRequest($response) {
    return Json::decode($response);
  }


}
