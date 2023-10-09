<?php

namespace Drupal\apitools;

interface ClientManagerInterface {

  /**
   * @return ClientResourceManagerInterface
   */
  public function getClientResourceManager();

  /**
   * @param $id
   * @param array $options
   *
   * @return mixed
   */
  public function load($id, array $options = []);

  public function resetCache($id);
}
