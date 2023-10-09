<?php

namespace Drupal\apitools\Api\Client;

use Drupal\Component\Utility\NestedArray;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

trait MalleableObjectTrait {

  /**
   * Array of loaded values.
   *
   * @var array
   */
  protected $values = [];

  protected $processed = [];

  /**
   * Magic method: Gets a property value.
   *
   * @param string $name
   *   The name of the property to get; e.g., 'title' or 'name'.
   *
   * @return mixed
   *   The property value.
   */
  public function __get($name) {
    return $this->get($name);
  }

  /**
   * Magic method: Sets a property value.
   *
   * @param $name
   *   The name of the property to set; e.g., 'title' or 'name'.
   * @param mixed $value
   *   The value to set, or NULL to unset the property. Optionally, a typed
   *   data object implementing Drupal\Core\TypedData\TypedDataInterface may be
   *   passed instead of a plain value.
   */
  public function __set($name, $value) {
    return $this->set($name, $value);
  }

  /**
   * Main public method to set value.
   *
   * @param $name
   *   Object property name.
   * @param mixed ...$args
   *   Optional arguments to pass to get function.
   *
   * @return mixed
   */
  public function set($name, ...$args) {
    if ($method = $this->getPropertyMethod('set', $name)) {
      return call_user_func_array([$this, $method], $args);
    }
    if (count($args) > 1) {
      throw new \Exception('Multiple arguments passed to set with no dynamic method available');
    }
    $value = reset($args);
    return $this->setValue($name, $value);
  }

  /**
   * Main public method to get a value.
   *
   * @param $name
   *   Object property name.
   * @param mixed ...$args
   *   Optional arguments to pass to get function.
   *
   * @return mixed
   */
  public function get($name, ...$args) {
    if ($method = $this->getPropertyMethod('get', $name)) {
      return call_user_func_array([$this, $method], $args);
    }
    return $this->getValue($name);
  }

  protected function getValue($name) {
    if (is_array($name)) {
      return NestedArray::getValue($this->values, $name);
    }
    return $this->values[$name] ?? NULL;
  }

  /**
   * Public method to add a dynamic property.
   *
   * @param $name
   *   The name of the property.
   * @param mixed ...$args
   *   Optional additional values handled by ::save or ::add[NAME].
   *
   * @return array
   *   A value to collect on ::save() or another custom method.
   */
  public function add($name, ...$args) {
    return call_user_func_array([$this, 'doAddOrDel'], array_merge(['add', $name], $args));
  }

  /**
   * Public method to delete a dynamic property.
   *
   * @param $name
   *   The name of the property.
   * @param mixed ...$args
   *   Optional additional values handled by ::add(), or ::del().
   *
   * @return mixed
   *   A value to collect on ::save() or another custom method.
   */
  public function del($name, ...$args) {
    return call_user_func_array([$this, 'doAddOrDel'], array_merge(['del', $name], $args));
  }

  /**
   * @param $action
   *   Whether performing an add or del.
   * @param $name
   *   The current object name to add or del.
   * @param ...$args
   *   Optional additional values handled by ::add(), or ::del().
   *
   * @return mixed
   *   A value to collect on ::save() or another custom method.
   */
  private function doAddOrDel($action, $name, ...$args) {
    $values = &$this->values['_' . $action][$name];
    if (!$values) {
      $values = [];
    }
    if ($method = $this->getPropertyMethod($action, $name)) {
      $value = call_user_func_array([$this, $method], $args);
    }
    if ($value) {
      // Allow declared methods to do a pass-through, and/or process data later.
      $values[] = $value;
    }
    return $this;
  }

  /**
   * Helper method to populate values array.
   *
   * @param $name
   *   Object property name.
   * @param $value
   *   Mixed property value.
   * @return array
   */
  protected function setValue($name, $value) {
    $this->values[$name] = $value;
    return $this->values[$name];
  }

  /**
   * Convert property string to method name if it exists for the current object.
   *
   * @param $type
   *   Method type, either get or set.
   * @param $name
   *   Requested property name.
   * @return bool|string
   */
  protected function getPropertyMethod($type, $name) {
    if (empty($name) || !is_string($name)) {
      return FALSE;
    }
    $converter = new CamelCaseToSnakeCaseNameConverter(NULL, FALSE);
    $method = $type . $converter->denormalize($name);
    return method_exists($this, $method) ? $method : FALSE;
  }

  /**
   * Pass values to be added or deleted to a save or delete function.
   *
   * @param $action
   *   The action to process, "add" or "del".
   * @param $prefix
   *   The custom matching prefix like "saveObject".
   */
  protected function processChangeMethod($action, $prefix) {
    if (!empty($this->values['_' . $action])) {
      // TODO: Changes this from $entites to $values because it could be anything.
      foreach ($this->values['_' . $action] as $method_type => $entities) {
        // TODO: Method type should be changed to something reflecting "order" or "payment".
        if (!$method = $this->getPropertyMethod($prefix, $method_type)) {
          continue;
        }
        foreach ($entities as $index => $entity) {
          $value = $this->{$method}($entity);
          unset($this->values['_' . $action][$method_type][$index]);
          $this->processed['_' . $action][$method_type][$index] = $value;
        }
      }
    }
  }

  public function last($action, $type) {
    if (!$processed = $this->getProcessed($action, $type)) {
      return NULL;
    }
    return end($processed);
  }

  protected function getProcessedItem($action, $type, $index = 0) {
    if (!$processed = $this->getProcessed($action, $type)) {
      return NULL;
    }
    return $processed[$index] ?? NULL;
  }

  protected function getProcessed($action, $type) {
    if (empty($this->processed['_' . $action]) || empty($this->processed['_' . $action][$type])) {
      return NULL;
    }
    return $this->processed['_' . $action][$type];
  }
}
