<?php

namespace Drupal\msc_ym_auth;

use Drupal\externalauth\ExternalAuthInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Provides authentication logic integrated with YourMembership.
 */
class Auth {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The externalauth.externalauth service.
   *
   * @var \Drupal\externalauth\ExternalAuthInterface
   */
  protected $externalauth;

  const AUTH_PROVIDER = 'yourMembership';
  // See https://ws.yourmembership.com/json/metadata?op=MemberPasswordReset.
  const RESET_PASSWORD_URL = 'https://ws.yourmembership.com/Ams/MemberPasswordReset';

  /**
   * Constructs an Auth object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\externalauth\ExternalAuthInterface $externalauth
   *   The externalauth.externalauth service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ExternalAuthInterface $externalauth) {
    $this->entityTypeManager = $entity_type_manager;
    $this->externalauth = $externalauth;
  }

  /**
   * @param string $email
   * @param string $password
   *
   * @return bool|\Drupal\user\UserInterface
   */
  public function authenticate($email, $password) {

  }

  /**
   * @param string $email
   * @param string $password
   *
   * @return array|bool
   */
  private function CheckEWebUser($email, $password) {
    $users = &drupal_static(__FUNCTION__, []);
    if (isset($users[$email])) {
      return $users[$email];
    }
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
            'receives_benefits' => (bool)$array['Result']['cst_receives_benefits_flag'],
            'force_password_change' => (bool)$array['Result']['cst_web_force_password_change'],
            'cst_key' => $array['Result']['cst_key'],
          );
          $users[$email] = $attributes;
          return $attributes;
        }
        return false;
      }
    }
    catch(\Exception $e) {
      return false;
    }
  }

}
