<?php

namespace Drupal\apitools;

interface ClientResourceManagerInterface {

  /**
   * Create a new ApiTools Resource object.
   *
   * @param $model_name
   *   The plugin id for the ApiTools Resource.
   * @param array $values
   *   An array of data associated with the ApiTools Resource.
   *
   * @return \Drupal\apitools\Api\Client\ClientResourceInterface|bool
   */
  public function getResource($model_name, array $values = []);

  /**
   * @param $model_name
   *   The plugin id for the ApiTools Resource.
   *
   * @return \Drupal\apitools\Api\Client\ClientResourceControllerInterface|bool
   */
  public function getResourceController($model_name);

  /**
   * @param $client_method
   *   The machine name used for the client method.
   *
   * @return \Drupal\apitools\Api\Client\ClientResourceControllerInterface|bool
   */
  public function getResourceControllerByMethod($client_method);

  /**
   * Get a plugin definition by a client method.
   *
   * @param $client_method
   *   The method defined in client_properties.
   *
   * @return array|bool
   */
  public function getDefinitionByMethod($client_method);
}
