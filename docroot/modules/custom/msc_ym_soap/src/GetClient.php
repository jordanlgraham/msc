<?php

namespace Drupal\msc_ym_soap;

use SoapClient;
use SoapHeader;
use Exception;

/**
 * Service description.
 */
class GetClient {
  const SSO = 'sso';
  const XML = 'xml';

  public function getAuthHeaders($token = false) {
    if(!$token) {
      $client = $this->getClient();
      if($client) {
        $params = array(
          'userName' => \Drupal::config('msc_ym_soap.settings')
            ->get('api_username'),
          'password' => \Drupal::config('msc_ym_soap.settings')
            ->get('api_password'),
        );
        try {
          $response_headers = '';
          $response = $client->__soapCall('Authenticate', array('parameters' => $params), NULL, NULL, $response_headers);
          $token = $response_headers['AuthorizationToken']->Token;
        } catch (Exception $e) {
          $message = t('Failed to retrieve token.');
          \Drupal::logger('msc_ym_soap')->error($message);
          watchdog_exception('msc_ym_soap', $e);
          return FALSE;
        }
      } else {
        $message = t('Cannot get client');
        \Drupal::logger('msc_ym_soap')->error($message);
        die();
      }
    }
    return new SoapHeader('http://www.avectra.com/OnDemand/2005/', 'AuthorizationToken', array('Token' => $token), TRUE);
  }

  public function getSsoAuthHeaders($token = FALSE) {
    if(!$token) {
      $client = $this->getClient(self::SSO);
      if($client) {
        $params = array(
          'userName' => \Drupal::config('msc_ym_soap.settings')
            ->get('api_username'),
          'password' => \Drupal::config('msc_ym_soap.settings')
            ->get('api_password'),
        );
        try {
          $response_headers = '';
          $response = $client->__soapCall('Authenticate', array('parameters' => $params), NULL, NULL, $response_headers);
          $token = $response->AuthenticateResult;
        } catch (Exception $e) {
          $message = t('Failed to retrieve token.');
          \Drupal::logger('msc_ym_soap')->error($message);
          watchdog_exception('msc_ym_soap', $e);
          return FALSE;
        }
      } else {
        $message = t('Cannot get client');
        \Drupal::logger('msc_ym_soap')->error($message);
        die();
      }
    }
    return new SoapHeader('http://www.avectra.com/OnDemand/2005/', 'AuthorizationToken', array('Token' => $token));
  }

  private function getClientFromLocalWSDL($type = self::XML) {
    try {
      if ($type === self::SSO) {
        $wsdl = drupal_get_path('module', 'msc_ym_soap') . '/src/signon.xml';
        $client = new SoapClient($wsdl, array('trace' => 1));
      }
      else {
        $wsdl = drupal_get_path('module', 'msc_ym_soap') . '/src/ymXMLOnDemand.xml';
        $client = new SoapClient($wsdl, array('trace' => 1));
      }
      return $client;
    }
    catch (Exception $e) {
      $message = t('Unable to create SoapClient from local WSDL');
      \Drupal::logger('msc_ym_soap')->error($message);
      return false;
    }
  }

  //Returns a SOAP client loaded up with the NetForum WSDL
  //and the trace option for simpler debugging.
  public function getClient($method_type = self::XML) {
    if ($method_type === self::SSO) {
      $wsdl = \Drupal::config('msc_ym_soap.settings')->get('wsdl_sso_address');
    }
    else {
      $wsdl = \Drupal::config('msc_ym_soap.settings')->get('wsdl_address');
    }
    try{
      $client = new SoapClient($wsdl, array('trace' => 1));
      return $client;
    }
    catch(Exception $e) {
      $message = t('Unable to connect to WSDL file.');
      \Drupal::logger('msc_ym_soap')->error($message);
      $client = $this->getClientFromLocalWSDL($method_type);
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
