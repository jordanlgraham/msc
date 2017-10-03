<?php

namespace Drupal\netforum_soap;

class SoapHelper {

  /*
   * A helper function that cleans up the output of fields returned from SoapClient calls.
   * If an XML field returns a value, it simply returns back the value. If the value is empty,
   * it returns an empty string.
   */
  public static function cleanSoapField($field, $type = 'string') {
    if ($type == 'boolean') {
      if (!empty($field)) {
        return '1';
      }
      else {
        return '0';
      }
    }
    elseif ($type == 'array') {
      if (!empty($field)) {
        if(stristr($field,',')) {
          return explode(',', $field);
        } else {
          return array($field);
        }
      } else {
        return array();
      }
    }
    else {
      if (!empty($field)) {
        return $field;
      }
      else {
        return '';
      }
    }
  }

  /*
   * A helper function that validates a url received from a NetForum SOAP
   * transaction before
   */
  public static function checkURLValidity($url) {
    if (filter_var($url, FILTER_VALIDATE_URL) === FALSE) {
      return '';
    }
    else {
      return $url;
    }
  }
}
