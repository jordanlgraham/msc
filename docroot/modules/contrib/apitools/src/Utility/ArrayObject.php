<?php

namespace Drupal\apitools\Utility;

use \ArrayObject as PhpArrayObject;

class ArrayObject extends PhpArrayObject {

  /**
   * @param $values
   *
   * @return static
   */
  public static function create($values) {
    return new static($values);
  }

  /**
   * Convert a saved string value to an array.
   *
   * @see ListItemBase::extractAllowedValues().
   */
  public static function fromTextarea($string) {
    $values = [];

    $list = explode("\n", $string);
    $list = array_map('trim', $list);
    $list = array_filter($list, 'strlen');

    $generated_keys = $explicit_keys = FALSE;
    foreach ($list as $position => $text) {
      // Check for an explicit key.
      $matches = [];
      if (!preg_match('/(.*)\|(.*)/', $text, $matches)) {
        continue;
      }

      // Trim key and value to avoid unwanted spaces issues.
      $key = trim($matches[1]);
      $value = trim($matches[2]);
      $explicit_keys = TRUE;

      $values[$key] = $value;
    }

    return new static($values);
  }

  /**
   * @return string
   */
  public function toStorageString() {
    $values = $this->getArrayCopy();
    $lines = [];
    foreach ($values as $key => $value) {
      $lines[] = "$key|$value";
    }
    return implode("\n", $lines);
  }

  /**
   * Turns an array into a message string.
   * e.g "Jane, Bob, and Mary"
   *
   * @return mixed|null
   */
  public function toMessageString($separator = ", ", $final = " and ") {
    $copy = $this->getArrayCopy();
    $end = end($copy);
    $beg = reset($copy);
    $reduce = array_reduce($this->getArrayCopy(), function ($carry, $value) use ($beg, $end, $separator, $final) {
      $glue = $value == $end ? $final : ($value == $beg ? "" : $separator);
      return $carry . $glue . $value;
    }, "");
    return $reduce;
  }

  /**
   * Simplify an array into an option list array.
   *
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
}
