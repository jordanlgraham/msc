<?php
namespace Drupal\wsclient;

/**
 * Custom exception class to enhance default PHP exceptions.
 */
class WSClientException extends Exception {

  /**
   * @param $msg
   *   The exception message containing placeholder as t().
   * @param $args
   *   Replacement arguments such as for t().
   */
  function __construct($msg, $args = array()) {
    $message = t($msg, $args);
    parent::__construct($message);
  }
}
