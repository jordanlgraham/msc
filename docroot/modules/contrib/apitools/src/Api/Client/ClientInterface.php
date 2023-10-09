<?php

namespace Drupal\apitools\Api\Client;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;

/**
 * Interface ClientInterface.
 */
interface ClientInterface extends ContainerFactoryPluginInterface, PluginInspectionInterface {

  /**
   * Initialize variables and functionality when client is loaded.
   *
   * @see \Drupal\apitools\ClientManager::load()
   *
   * @param array $options
   *   An array of options passed to ClientManager::load()
   * @return $this
   */
  public function init(array $options = []);

  /**
   * Send an http request.
   *
   * @param $method
   *   Request method like GET or POST.
   * @param $path
   *   The relative path after the base path.
   * @param array $options
   *   Array of options like query string.
   * @return mixed
   */
  public function request($method, $path, $options = []);

  /**
   * Perform PUT request.
   *
   * @param $path
   *   The relative path after the base path.
   * @param array $options
   *   Array of options like query string.
   * @return mixed
   */
  public function put($path, $options = []);

  /**
   * Perform PATCH request.
   *
   * @param $path
   *   The relative path after the base path.
   * @param array $options
   *   Array of options like query string.
   * @return mixed
   */
  public function patch($path, $options = []);

  /**
   * Perform GET request.
   *
   * @param $path
   *   The relative path after the base path.
   * @param array $options
   *   Array of options like query string.
   * @return mixed
   */
  public function get($path, $options = []);

  /**
   * Perform POST request.
   *
   * @param $path
   *   The relative path after the base path.
   * @param array $options
   *   Array of options like query string.
   * @return mixed
   */
  public function post($path, $options = []);

  /**
   * Perform DELETE request.
   *
   * @param $path
   *   The relative path after the base path.
   * @param array $options
   *   Array of options like query string.
   * @return mixed
   */
  public function delete($path, $options = []);

  /**
   * Get the config file name for this client plugin.
   *
   * @return string
   */
  public function getConfigName();

  /**
   * Get the editable config object for this client plugin.
   *
   * @return \Drupal\Core\Config\ImmutableConfig
   */
  public function config();
}
