<?php

namespace Drupal\apitools\Utility;

class ArrayIterator extends \ArrayIterator {

  /**
   * @param array $array
   * @param $key
   * @param $value
   */
  public static function getOptionsList(array $array, $key, $value) {
    return (new static($array))->toOptionsList($key, $value);
  }

  /**
   * @param $key
   * @param $value
   *
   * @return mixed
   */
  public function toOptionsList($key, $value) {
    return array_reduce($this->getArrayCopy(), function($carry, $item) use ($key, $value) {
      $carry[$item[$key]] = $item[$value];
      return $carry;
    }, []);
  }

  /**
   * @param $size
   * @param bool $preserve_keys
   *
   * @return array
   */
  public function chunk($size, $preserve_keys = FALSE) {
    $sets = array_chunk($this->getArrayCopy(), $size, $preserve_keys);
    foreach ($sets as &$set) {
      $set = new static($set);
    }
    return $sets;
  }
}
