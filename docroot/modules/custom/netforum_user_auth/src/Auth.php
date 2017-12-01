<?php

namespace Drupal\netforum_user_auth;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\externalauth\ExternalAuthInterface;
use Drupal\netforum_soap\GetClient;

class Auth {

  /**
   * @var \Drupal\user\UserStorageInterface
   */
  private $userStorage;

  /**
   * @var \Drupal\netforum_soap\GetClient
   */
  private $get_client;

  /**
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  private $externalAuth;

  const AUTH_PROVIDER = 'netforum';

  public function __construct(EntityTypeManagerInterface $entityTypeManager, GetClient $getClient,
                              ExternalAuthInterface $externalAuth) {
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->get_client = $getClient;
    $this->externalAuth = $externalAuth;
  }

  /**
   * @param string $email
   * @param string $password
   *
   * @return bool|\Drupal\user\UserInterface
   */
  public function authenticate($email, $password) {
    $user_attributes = $this->CheckEWebUser($email, $password);
    if(!empty($user_attributes)) {
      $existing = $this->userStorage->loadByProperties(['mail' => $email]);
      // User already has an MSCA account, link it with Netforum via email address.
      if ($existing) {
        $account = end($existing);
        $this->externalAuth->linkExistingAccount($email, self::AUTH_PROVIDER, $account);
        return $this->externalAuth->userLoginFinalize($account, $email, self::AUTH_PROVIDER);
      }
      else {
        $roles = [];
        if ($user_attributes['member']) {
          $roles[] = 'member';
        }
        return $this->externalAuth->loginRegister($email, self::AUTH_PROVIDER, [
          'name' => $email,
          'mail' => $email,
          'pass' => $password,
          'roles' => $roles,
        ]);
      }
    } else {
      return false;
    }
  }

  /**
   * @param string $email
   * @param string $password
   *
   * @return array|bool
   */
  private function CheckEWebUser($email, $password) {
    $client = $this->get_client->GetClient();
    $params = array(
      'szEmail' => $email,
      'szPassword' => $password,
    );
    $auth_headers = $this->get_client->getAuthHeaders();
    $response_headers = $this->get_client->getResponseHeaders();
    try {
      //CheckEWebUser simply attempts to authenticate based on the passed credentials.
      $response = $client->__soapCall('CheckEWebUser', array('parameters' => $params), NULL, $auth_headers, $response_headers);
      if (!empty($response->CheckEWebUserResult->any)) {
        $xml = simplexml_load_string($response->CheckEWebUserResult->any);
        //Could probably be better handled with the Serialization API
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);
        if (!empty($array['@attributes']['recordReturn']) && $array['@attributes']['recordReturn'] == '1') {
          $attributes = array(
            'name' => $array['Result']['cst_name_cp'],
            'member' => (bool)$array['Result']['cst_member_flag'],
          );
          return $attributes;
        }
        return false;
      }
    }
    catch(\Exception $e) {
      return false;
    }
  }

  public function userIsMember($email, $password) {
    $attributes = $this->CheckEWebUser($email, $password);
    if ($attributes) {
      return $attributes['member'];
    }
    return FALSE;
  }

}
