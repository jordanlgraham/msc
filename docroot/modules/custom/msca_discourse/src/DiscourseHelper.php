<?php

namespace Drupal\msca_discourse;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Cviebrock\DiscoursePHP\SSOHelper;
use Drupal\user\UserDataInterface;
use GuzzleHttp\ClientInterface;

class DiscourseHelper {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var SSOHelper
   */
  protected $helper;

  /**
   * @var ClientInterface
   */
  protected $http;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  protected $userData;

  const SSO_SECRET_STATE_KEY = 'msca_discourse.sso_secret';

  const SESSION_DATA_KEY = 'msca_discourse.sso';

  public function __construct(StateInterface $state, ClientFactory $factory, ConfigFactoryInterface $config,
                              UserDataInterface $userData) {
    $this->config = $config->get('msca_discourse.config');
    $this->state = $state;
    $this->http = $factory->fromOptions(['base_uri' => $this->config->get('url')]);
    $this->userData = $userData;
  }

  /**
   * Get a Discourse SSO helper class.
   *
   * @param null $sso_secret
   *
   * @return \Cviebrock\DiscoursePHP\SSOHelper
   */
  public function getHelper($sso_secret = NULL) {
    if (!isset($this->helper)) {
      if (!$sso_secret) {
        $sso_secret = $this->state->get(self::SSO_SECRET_STATE_KEY);
      }
      $sso = new SSOHelper();
      $sso->setSecret($sso_secret);
      $this->helper = $sso;
    }
    return $this->helper;
  }

  /**
   * @param string $payload
   * @param string $nonce
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return string
   */
  public function getRedirect($payload, $nonce, AccountInterface $account) {
    $return_url = $this->getHelper()->getReturnSSOURL($payload);
    $param = $this->getHelper()
      ->getSignInString($nonce, $account->id(), $account->getEmail(), [
        'username' => $account->getAccountName(),
        'name' => $account->getDisplayName(),
        'require_activation' => TRUE,
      ]);
    return $return_url . '?' . $param;
  }

  /**
   * Log a user out of Discourse.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return string
   * @throws \Exception
   */
  public function logoutUser(AccountInterface $account) {
    $id = $this->userData->get('msca_discourse', $account->id(), 'discourse_id');
    if (!$id) {
      $id = $this->getDiscourseId($account);
    }
    if (!$id) {
      throw new \Exception("Discourse ID not found for user {$account->id()}");
    }
    return $this->request('POST', '/admin/users/' . $id . '/log_out');
  }

  /**
   * Get the Discourse user ID by email address.
   *
   * @param AccountInterface $account
   *
   * @return bool|int
   */
  protected function getDiscourseId(AccountInterface $account) {
    $email = $account->getEmail();
    $users = $this->request('GET', "/admin/users/list/active.json",
      ['query' => ["filter" => $email, 'show_emails' => TRUE]]);
    $users = Json::decode($users);
    $id = FALSE;
    if (!empty($users)) {
      if (count($users) === 1) {
        $id = end($users)['id'];
      }
      else {
        foreach($users as $user) {
          if ($user['email'] === $email) {
            $id = $user['id'];
          }
        }
      }
    }
    if ($id) {
      $this->userData->set('msca_discourse', $account->id(), 'discourse_id', $id);
    }
    return $id;
  }

  /**
   * Make a Discourse API request.
   *
   * @param $method
   * @param $endpoint
   * @param array $options
   *
   * @return string
   */
  private function request($method, $endpoint, $options = []) {
    $defaults = [
      'query' => [
        'api_key' => $this->config->get('api_key'),
        'api_username' => $this->config->get('api_username'),
      ],
    ];
    $options = NestedArray::mergeDeepArray([$options, $defaults], TRUE);
    return $this->http->request($method, $endpoint, $options)->getBody()->getContents();
  }
}
