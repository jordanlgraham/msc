<?php

namespace Drupal\Tests\apitools\Functional;

use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Url;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\simple_oauth\Functional\SimpleOauthTestTrait;

class ClientTest extends BrowserTestBase {

  use SimpleOauthTestTrait;

  /**
   * {@inheritdoc}
   *
   * Browser tests are run in separate processes to prevent collisions between
   * code that may be loaded by tests.
   */
  protected $runTestInSeparateProcess = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'options',
    'serialization',
    'text',
    'user',
    'rest',
    'apitools_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * @var \Drupal\consumers\Entity\ConsumerInterface
   */
  protected $consumer;

  protected $adminAccount;

  protected $adminPass;

  protected $userAccount;

  protected $userPass;

  protected $clientSecret;

  protected function setUp(): void {
    parent::setUp();

    $this->clientSecret = $this->randomString();

    $this->setUpKeys();

    $this->adminAccount = $this->drupalCreateUser([], 'test_admin_user', TRUE);
    $this->adminPass = $this->adminAccount->passRaw;

    $scope = Oauth2Scope::create([
      'name' => 'test:scope',
      'description' => 'Test scope description',
      'grant_types' => [
        'refresh_token' => [
          'status' => TRUE,
          'description' => 'Test scope description refresh_token',
        ],
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test scope description authorization_code',
        ],
        'client_credentials' => [
          'status' => TRUE,
          'description' => 'Test scope description client_credentials',
        ],
      ],
      'umbrella' => FALSE,
      'granularity' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'permission' => 'administer zooapi client',
    ]);
    $scope->save();

    $this->consumer = Consumer::create([
      'client_id' => 'zoo_auth',
      'user_id' => $this->adminAccount->id(),
      'label' => 'Zoo Authorization',
      'confidential' => 1,
      'secret' => $this->clientSecret,
      'grant_types' => [
        'client_credentials',
        'refresh_token',
      ],
      'scopes' => [$scope->id()],
    ]);
    $this->consumer->save();

    $this->scope = "{$scope->getName()}";
  }

  public function testConfigForm() {
    $this->drupalLogin($this->adminAccount);
    $config_url = Url::fromRoute('apitools.client_config_form.zooapi');
    $this->drupalGet($config_url->toString());
    $this->assertSession()->pageTextContains('Zoo API Client Settings');

    $edit = [
      'config[base_uri]' => $this->baseUrl,
      'config[client_id]' => 'zoo_auth',
      'config[client_secret]' => $this->clientSecret,
      'config[grant_type]' => 'client_credentials',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $zooapi = \Drupal::service('plugin.manager.apitools_client')
      ->resetCache('zooapi')
      ->load('zooapi');
    $configuration = $zooapi->getConfiguration();

    $this->assertEquals([
      'client_id' => 'zoo_auth',
      'client_secret' => $this->clientSecret,
      'grant_type' => 'client_credentials',
      'base_uri' => $this->baseUrl,
    ], $configuration);
  }

  public function testClientConnection() {
    $this->userAccount = $this->drupalCreateUser(['administer zooapi client'], 'zookeeper');
    $this->userPass = $this->userAccount->passRaw;

    $zooapi = \Drupal::service('plugin.manager.apitools_client')->load('zooapi');
    $zooapi->setTestCredentials([
      'username' => $this->adminAccount->getAccountName(),
      'pass' => $this->adminPass,
      'client_id' => 'zoo_auth',
      'client_secret' => $this->clientSecret,
      'client_scope' => $this->scope,
    ]);

    $user = $zooapi->get('user/' . $this->adminAccount->id(), [
      'query' => [
        '_format' => 'json'
      ]
    ]);

    $this->assertArrayHasKey('uid', $user);
    $this->assertEquals($this->adminAccount->id(), $user['uid'][0]);
  }
}