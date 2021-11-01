<?php

namespace Drupal\netforum_org_sync;

use Drupal\geocoder\GeocoderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class Geocode {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Constructs a Geocode object.
   * 
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\geocoder\GeocoderInterface $geocoder
   *   The gecoder service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory, TimeInterface $time, GeocoderInterface $geocoder) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->config = $configFactory->get('mapquest.open');
    $this->entityTypeManager = $entityTypeManager;
    $this->time = $time;
    $this->geocoder = $geocoder;
  }

  public function setCoordinates($node) {
    if (!empty($node->field_address->postal_code)) {
      $merp = 'derp';
      $address = $node->field_address->getString();
      $provider_ids = ['googlemaps'];
      $providers = $this->entityTypeManager->getStorage('geocoder_provider')->loadMultiple($provider_ids);
      $addressCollection = $this->geocoder->geocode($address, $providers);
      if (!empty($addressCollection)) {
        $locations = $addressCollection->all();
        $location = reset($locations);
        $node->set('field_ge', ['lat' => $location->getCoordinates()->getLatitude(), 'lng' => $location->getCoordinates()->getLongitude()]);

        // Create a new revision.
        $node->setNewRevision(TRUE);
        $node->revision_log = 'Created revision for node ' . $node->Id() . ', setting correct geo coordinates.';
        $request_time = $this->time->getRequestTime();
        $node->setRevisionCreationTime($request_time);
        $node->setRevisionUserId(1);
        // This is called from a hook_entity_presave, so no need to save here.
      }
    }
  }
}
