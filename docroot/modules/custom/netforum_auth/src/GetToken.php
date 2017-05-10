<?php

namespace Drupal\netforum_auth;

/**
 * Class GetToken.
 *
 * @package Drupal\netforum_auth
 */
class GetToken {
  public $auth_headers;
  /**
   * Constructs a new GetToken object.
   */
  public function __construct() {
    $auth_headers = $this->getAuthHeaders();
    return $auth_headers;
  }
  private function getAuthHeaders() {
    $client = $this->getClient();
    $params = array(
      'userName' => \Drupal::config('netforum_auth.netforumconfig')->get('api_username'),
      'password' => \Drupal::config('netforum_auth.netforumconfig')->get('api_password'),
    );
    try {
      $response_headers = '';
      $response = $client->__soapCall('Authenticate', array('parameters' => $params), NULL, NULL, $response_headers);
      $token = $response_headers['AuthorizationToken']->Token;
      $xwebNamespace = $response->AuthenticateResult;

      return new SoapHeader($xwebNamespace, 'AuthorizationToken', array('Token' => $token), TRUE);
    }
    catch(Exception $e) {
      $message = t('Failed to retrieve token.');
      \Drupal::logger('msc_netforum_auth')->error($message);
      return false;
    }
  }
  private function getClient() {
    $wsdl = \Drupal::config('netforum_auth.netforumconfig')->get('wsdl_address');
    try{
      $client = new SoapClient($wsdl, array('trace' => 1));
      return $client;
    }
    catch(Exception $e) {
      $message = t('Unable to connect to WSDL file.');
      \Drupal::logger('msc_netforum_auth')->error($message);
      return false;
    }
  }
}
