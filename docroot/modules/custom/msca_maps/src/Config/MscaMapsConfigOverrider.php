<?php

namespace Drupal\msca_maps\Config;

use Drupal\key\KeyRepository;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;

/**
 * MscaMapsConfigOverrider service.
 */
class MscaMapsConfigOverrider implements ConfigFactoryOverrideInterface {

  /**
   * The key.repository service.
   *
   * @var \Drupal\key\KeyRepository
   */
  protected $keyRepository;

  /**
   * Constructs a MscaMapsConfigOverrider object.
   *
   * @param \Drupal\key\KeyRepository $key_repository
   *   The key.repository service.
   */
  public function __construct(KeyRepository $key_repository) {
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    if (in_array('geolocation_google_maps.settings', $names)) {
      $overrides['geolocation_google_maps.settings']['google_map_api_key'] = preg_replace( "/\r|\n/", "", $this->keyRepository->getKey('google_maps_api_key')->getKeyValue());
    }
    return $overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheSuffix() {
    return 'ConfigExampleOverrider';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritdoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
