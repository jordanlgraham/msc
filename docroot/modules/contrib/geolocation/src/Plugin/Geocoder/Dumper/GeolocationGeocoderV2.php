<?php

namespace Drupal\geolocation\Plugin\Geocoder\Dumper;

use Geocoder\Location;
use Geocoder\Model\Address;
use Drupal\geocoder\DumperBase;

/**
 * Provides a geolocation geocoder dumper plugin.
 *
 * @GeocoderDumper(
 *   id = "geolocation",
 *   name = "Geolocation Geocoder V2"
 * )
 */
class GeolocationGeocoderV2 extends DumperBase {

  /**
   * {@inheritdoc}
   */

  public function schmump(Address $address) {
    $data = $address->toArray();
    $lat = $data['latitude'];
    $lng = $data['longitude'];

    unset($data['latitude'], $data['longitude'], $data['bounds']);

    return [
      'lat' => $lat,
      'lng' => $lng,
      'lat_sin' => sin(deg2rad($lat)),
      'lat_cos' => cos(deg2rad($lat)),
      'lng_rad' => deg2rad($lng),
      'data' => $data,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function dump(Location $location) {
    return $this->getHandler()->dump($location);
  }

}
