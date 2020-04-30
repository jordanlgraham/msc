<?php

namespace Drupal\geolocation_geometry_germany_zip_codes\Plugin\geolocation\GeolocationGeometryData;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeFileException;
use Drupal\geolocation_geometry_data\GeolocationGeometryDataBase;

/**
 * Import Zip Code geometries in Germany.
 *
 * @GeolocationGeometryData(
 *   id = "germany_zip_codes",
 *   name = @Translation("Germany Zip Codes"),
 *   description = @Translation("Geometries of all zip in Germany."),
 * )
 */
class GermanyZipCodes extends GeolocationGeometryDataBase {

  /**
   * {@inheritdoc}
   */
  public $sourceUri = 'https://www.suche-postleitzahl.org/download_v1/wgs84/mittel/plz-5stellig/shapefile/plz-5stellig.shp.zip';

  /**
   * {@inheritdoc}
   */
  public $sourceFilename = 'plz-5stellig.shp.zip';

  /**
   * {@inheritdoc}
   */
  public $localDirectory = 'plz';

  /**
   * {@inheritdoc}
   */
  public $shapeFilename = 'plz-5stellig.shp';

  /**
   * {@inheritdoc}
   */
  public function import() {
    parent::import();
    $taxonomy_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $logger = \Drupal::logger('geolocation_geometry_germany_zip_codes');

    try {
      while ($record = $this->shapeFile->getRecord(ShapeFile::GEOMETRY_GEOJSON_GEOMETRY)) {
        if ($record['dbf']['_deleted']) {
          continue;
        }
        else {
          /** @var \Drupal\taxonomy\TermInterface $term */
          $term = $taxonomy_storage->create([
            'vid' => 'germany_zip_codes',
            'name' => utf8_decode($record['dbf']['plz']),
          ]);
          $term->set('field_geometry_data_geometry', [
            'geojson' => $record['shp'],
          ]);
          $term->set('field_city', $record['dbf']['note']);
          $term->save();
        }
      }
    }
    catch (ShapeFileException $e) {
      $logger->warning($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

}
