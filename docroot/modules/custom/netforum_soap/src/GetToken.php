<?php

namespace Drupal\netforum_soap;

/**
 * Class GetToken.
 *
 * @package Drupal\netforum_soap
 */
use SoapClient;
use SoapHeader;
use Exception;
class GetToken {
  public $auth_headers;
  /**
   * Constructs a new GetToken object.
   */
  public function __construct() {
    $auth_headers = $this->getAuthHeaders();
    return $auth_headers;
  }
  public function getAuthHeaders() {

    $client = $this->getClient();
    $params = array(
      'userName' => \Drupal::config('netforum_soap.netforumconfig')->get('api_username'),
      'password' => \Drupal::config('netforum_soap.netforumconfig')->get('api_password'),
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
      \Drupal::logger('msc_netforum_soap')->error($message);
      return false;
    }
  }
  public function getClient() {
    $wsdl = \Drupal::config('netforum_soap.netforumconfig')->get('wsdl_address');
    try{
      $client = new SoapClient($wsdl, array('trace' => 1));
      return $client;
    }
    catch(Exception $e) {
      $message = t('Unable to connect to WSDL file.');
      \Drupal::logger('msc_netforum_soap')->error($message);
      return false;
    }
  }
}
