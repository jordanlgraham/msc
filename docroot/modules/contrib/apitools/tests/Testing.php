<?php

namespace Drupal\apitools;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;

class Testing {
  public static function run() {
    $client_manager = \Drupal::service('plugin.manager.apitools_client');
    $client_manager->clearCachedDefinitions();
    /** @var \Drupal\apitools_test\Plugin\ApiTools\Client $client */
    $client = $client_manager->load('apitools_test_client');
    $client->oauth();
  }

}
