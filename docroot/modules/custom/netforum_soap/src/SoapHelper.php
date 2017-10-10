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

  /*
   * A helper function that makes a full social media url from a handle.
   * Possible values for $type are:
   *   facebook
   *   twitter
   *   linkedin
   */
  public function URLfromSocialHandle($handle, $type) {
    if(!empty($handle)) {
      switch($type) {
        case 'facebook':
          //as a precaution, we still want to make sure this is a legit url
          return $this->checkURLValidity('https://www.facebook.com/' . $handle);
          //no "break;" because return kicks us out of this function.
        case 'twitter':
          return $this->checkURLValidity('https://www.twitter.com/' . $handle);
        case 'linkedin':
          return $this->checkURLValidity('https://www.linkedin.com/in/' . $handle);
      }
    }
    else {
      return '';
    }
  }
}
