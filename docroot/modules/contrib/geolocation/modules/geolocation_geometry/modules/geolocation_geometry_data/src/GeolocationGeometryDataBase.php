<?php

namespace Drupal\geolocation_geometry_data;

use ShapeFile\ShapeFile;
use ShapeFile\ShapeFileException;

/**
 * Class ShapeFileImportBatch.
 *
 * @package Drupal\geolocation_geometry_data
 */
abstract class GeolocationGeometryDataBase {

  /**
   * URI to archive.
   *
   * @var string
   */
  public $sourceUri = '';

  /**
   * Filename of archive.
   *
   * @var string
   */
  public $sourceFilename = '';

  /**
   * Directory extract of archive.
   *
   * @var string
   */
  public $localDirectory = '';

  /**
   * Extracted filename.
   *
   * @var string
   */
  public $shapeFilename = '';

  /**
   * Shape file.
   *
   * @var \ShapeFile\ShapeFile|null
   */
  public $shapeFile;

  /**
   * Return this batch.
   *
   * @return array
   *   Batch return.
   */
  public function getBatch() {
    $operations = [
      [[$this, 'download'], []],
      [[$this, 'import'], []],
    ];

    return [
      'title' => t('Import Shapefile'),
      'finished' => [$this, 'finished'],
      'operations' => $operations,
      'progress_message' => t('Finished step @current / @total.'),
      'init_message' => t('Import is starting.'),
      'error_message' => t('Something went horribly wrong.'),
    ];
  }

  /**
   * Download batch callback.
   *
   * @return bool
   *   Batch return.
   */
  public function download() {
    $destination = \Drupal::service('file_system')->getTempDirectory() . '/' . $this->sourceFilename;

    if (!is_file($destination)) {
      $client = \Drupal::httpClient();
      $client->get($this->sourceUri, ['save_to' => $destination]);
    }

    if (!empty($this->localDirectory) && substr(strtolower($this->sourceFilename), -3) === 'zip') {
      $zip = new \ZipArchive();
      $res = $zip->open($destination);
      if ($res === TRUE) {
        $zip->extractTo(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->localDirectory);
        $zip->close();
      }
      else {
        return FALSE;
      }
    }

    return TRUE;
  }

  /**
   * Import batch callback.
   *
   * @return bool
   *   Batch return.
   */
  public function import() {
    $logger = \Drupal::logger('geolocation_geometry_data');

    if (empty($this->shapeFilename)) {
      return FALSE;
    }

    try {
      $this->shapeFile = new ShapeFile(\Drupal::service('file_system')->getTempDirectory() . '/' . $this->localDirectory . '/' . $this->shapeFilename);
    }
    catch (ShapeFileException $e) {
      $logger->warning($e->getMessage());
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Batch result handling.
   *
   * @param mixed $result
   *   Batch result.
   *
   * @return bool
   *   Result.
   */
  public function finished($result) {
    return TRUE;
  }

}
