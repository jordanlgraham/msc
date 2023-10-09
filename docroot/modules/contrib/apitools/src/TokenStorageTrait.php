<?php

namespace Drupal\apitools;

use Drupal\Component\Datetime\Time;
use Drupal\Core\TempStore\SharedTempStore;

trait TokenStorageTrait {

  /**
   * @var SharedTempStore
   */
  protected $tempStore;

  /**
   * Set the current time service with the current class.
   *
   * @param \Drupal\Component\Datetime\Time $time
   *
   * @return void
   */
  protected function setTime(Time $time) {
    $this->time = $time;
  }

  /**
   * Gets the current time service.
   *
   * @return \Drupal\Component\Datetime\Time|mixed
   */
  protected function getTime() {
    if (!isset($this->time)) {
      return \Drupal::service('datetime.time');
    }
    return $this->time;
  }

  /**
   * Set the storage with the current class or a factory class.
   *
   * @param \Drupal\Core\TempStore\SharedTempStore $shared_temp_store
   *
   * @return $this
   */
  public function setTempStore(SharedTempStore $shared_temp_store) {
    $this->tempStore = $shared_temp_store;
    return $this;
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   * @param $token_value
   *   The token string value.
   *
   * @return $this
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setToken($token_name, $token_value) {
    $this->tempStore->set($token_name, $token_value);
    return $this;
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   *
   * @return $this
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function clearToken($token_name) {
    $this->tempStore->delete($token_name);
    return $this;
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   * @param $seconds
   *   Integer of seconds from current request time.
   */
  protected function setTokenExpiresIn($token_name, $seconds) {
    $this->setTokenExpiresTimestamp($token_name, $this->getTime()->getRequestTime() + $seconds);
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   * @param $timestamp
   *   The date timestamp the token expires.
   *
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function setTokenExpiresTimestamp($token_name, $timestamp) {
    $this->tempStore->set("{$token_name}_expires", $timestamp);
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   *
   * @return mixed
   */
  protected function getTokenExpiresTimestamp($token_name) {
    return $this->tempStore->get("{$token_name}_expires");
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   *
   * @return bool
   */
  protected function hasTokenExpiration($token_name) {
    $expires = $this->getTokenExpiresTimestamp($token_name);
    return isset($expires);
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   *
   * @return bool
   */
  protected function isTokenExpired($token_name) {
    $expires = $this->getTokenExpiresTimestamp($token_name);
    return $this->getTime()->getRequestTime() > $expires;
  }

  /**
   * @param $token_name
   *   The token machine name in the tempstore.
   *
   * @return mixed
   * @throws \Drupal\Core\TempStore\TempStoreException
   */
  protected function getToken($token_name) {
    if ($this->hasTokenExpiration($token_name) && $this->isTokenExpired($token_name)) {
      $this->clearToken($token_name);
    }
    return $this->tempStore->get($token_name);
  }


}