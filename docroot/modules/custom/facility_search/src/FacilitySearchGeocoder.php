<?php

namespace Drupal\facility_search;

use Drupal\node\NodeInterface;
use Drupal\example\ExampleInterface;
use Drupal\geocoder\GeocoderInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * FacilitySearchGeocoder service.
 */
class FacilitySearchGeocoder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The geocoder service.
   *
   * @var \Drupal\example\ExampleInterface
   */
  protected $geocoder;

  /**
   * Constructs a FacilitySearchGeocoder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\geocoder\GeocoderInterface $geocoder
   *   The geocoder service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory, TimeInterface $time, GeocoderInterface $geocoder) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->time = $time;
    $this->geocoder = $geocoder;
  }

  /**
   * @param NodeInterface $node
   *   The facility node to geocode.
   */
  public function setCoordinates($node) {
    $merp = 'derp';
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
        // Remember to $node->save() after calling this method.
      }
    }
  }
}
