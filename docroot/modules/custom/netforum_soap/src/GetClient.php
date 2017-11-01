<?php

namespace Drupal\netforum_soap;

/**
 * Class GetClient.
 *
 * @package Drupal\netforum_soap
 */
use SoapClient;
use SoapHeader;
use Exception;
class GetClient {
  public $auth_headers;
  /**
   * Constructs a new GetClient object.
   */
  public function __construct() {
    $auth_headers = $this->getAuthHeaders();
    return $auth_headers;
  }

  public function getAuthHeaders($token = false) {
    if(!$token) {
      $client = $this->getClient();
      if($client) {
        $params = array(
          'userName' => \Drupal::config('netforum_soap.netforumconfig')
            ->get('api_username'),
          'password' => \Drupal::config('netforum_soap.netforumconfig')
            ->get('api_password'),
        );
        try {
          $response_headers = '';
          $response = $client->__soapCall('Authenticate', array('parameters' => $params), NULL, NULL, $response_headers);
          $token = $response_headers['AuthorizationToken']->Token;
        } catch (Exception $e) {
          $message = t('Failed to retrieve token.');
          \Drupal::logger('msc_netforum_soap')->error($message);
          watchdog_exception('msc_netforum_soap', $e);
          return FALSE;
        }
      } else {
        $message = t('Cannot get client');
        \Drupal::logger('msc_netforum_soap')->error($message);
        die();
      }
    }
    return new SoapHeader('http://www.avectra.com/OnDemand/2005/', 'AuthorizationToken', array('Token' => $token), TRUE);
  }

  private function getClientFromLocalWSDL() {
    try {
      $client = new SoapClient('netForumXMLOnDemand.xml', array('trace' => 1));
      return $client;
    }
    catch (Exception $e) {
      $message = t('Unable to create SoapClient from local WSDL');
      \Drupal::logger('msc_netforum_soap')->error($message);
      return false;
    }
  }

  //Returns a SOAP client loaded up with the NetForum WSDL
  //and the trace option for simpler debugging.
  public function getClient() {
    $wsdl = \Drupal::config('netforum_soap.netforumconfig')->get('wsdl_address');
    try{
      $client = new SoapClient($wsdl, array('trace' => 1));
      return $client;
    }
    catch(Exception $e) {
      $message = t('Unable to connect to WSDL file.');
      \Drupal::logger('msc_netforum_soap')->error($message);
      $client = $this->getClientFromLocalWSDL();
      return $client;
    }
  }

  //This function seriously just returns an empty string. It's used for
  //the SoapClient function __soapCall(), which requires all parameters to be
  //variables.
  public function getResponseHeaders() {
    return '';
  }
}
