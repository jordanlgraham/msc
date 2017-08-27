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
}
