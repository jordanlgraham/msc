<?php

namespace Drupal\netforum_org_sync;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class Geocode {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * Constructs a Geocode object.
   * 
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->config = $configFactory->get('mapquest.open');
    $this->time = $time ?: \Drupal::service('datetime.time');
  }

  public function getCurlOptions($zip) {
    $config = $this->config;
    $key = $config->get('key');
    return [
      CURLOPT_URL => "https://www.mapquestapi.com/geocoding/v1/address?key=$key&location=$zip",
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 30,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "GET",
      CURLOPT_POSTFIELDS => "",
    ];
  }

  public function setCoordinates($node) {
    if (!empty($node->field_address->postal_code)) {
      $zip = $node->field_address->postal_code;
      $coordinates = $this->getCoordinates($zip);
      $node->set('field_ge', ['lat' => (int) $coordinates['latitude'], 'lng' => (int) $coordinates['longitude']]);

      // Create a new revision.
      $node->setNewRevision(TRUE);
      $node->revision_log = 'Created revision for node ' . $node->Id() . ', setting correct geo coordinates.';
      $request_time = $this->time->getRequestTime();
      $node->setRevisionCreationTime($request_time);
      $node->setRevisionUserId(1);
    }
  }

  public function getCoordinates($zip) {
    $curl = curl_init();
    $coordinates = array();
    $options = $this->getCurlOptions($zip);
    curl_setopt_array($curl, $options);

    $response = curl_exec($curl);
    $response_json = json_decode($response);

    // Filter out results for ZIP codes that come from other countries.
    foreach ($response_json->results[0]->locations as $location) {
      if ($location->adminArea1 == "US" && $location->adminArea3 = "MA") {
        $coordinates['latitude'] = $location->latLng->lat;
        $coordinates['longitude'] = $location->latLng->lng;
      }
    }

    $err = curl_error($curl);

    curl_close($curl);
    return $coordinates;
  }

}
