<?php

namespace Drupal\apitools\Testing;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\apitools\Traits\ArraySubset;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\InvalidArgumentException;
use weitzman\DrupalTestTraits\ExistingSiteBase as WeitzmanExistingSiteBase;

abstract class ExistingSiteBase extends WeitzmanExistingSiteBase {

  /**
   * Asserts that an array has a specified subset.
   *
   * @param array|\ArrayAccess $subset
   * @param array|\ArrayAccess $array
   *
   * @throws ExpectationFailedException
   * @throws \SebastianBergmann\RecursionContext\InvalidArgumentException
   */
  public static function assertArraySubset($subset, $array, bool $checkForObjectIdentity = false, string $message = ''): void {
    if (!(\is_array($subset) || $subset instanceof \ArrayAccess)) {
      throw InvalidArgumentException::create(
        1,
        'array or ArrayAccess'
      );
    }

    if (!(\is_array($array) || $array instanceof \ArrayAccess)) {
      throw InvalidArgumentException::create(
        2,
        'array or ArrayAccess'
      );
    }

    $constraint = new ArraySubset($subset, $checkForObjectIdentity);

    static::assertThat($array, $constraint, $message);
  }

  /**
   * Search current page for text by CSS selector provided.
   *
   * @param $selector
   *   CSS Selector passed to \Behat\Mink\Element\ElementInterface::findAll()
   * @param $text
   *   The string of text to search for
   */
  protected function assertPageNotContainsText($selector, $text) {
    $results = $this->getSession()->getPage()->findAll('css', $selector);
    $elements = [];
    foreach ($results as $element) {
      $elements[] = $element->getText();
    }

    $this->assertNotContains($text, $elements);
  }

  /**
   * Search current page for text by CSS selector provided.
   *
   * @param $selector
   *   CSS Selector passed to \Behat\Mink\Element\ElementInterface::findAll()
   * @param $text
   *   The string of text to search for
   */
  protected function assertPageContainsText($selector, $text) {
    $results = $this->getSession()->getPage()->findAll('css', $selector);
    $elements = [];
    foreach ($results as $element) {
      $elements[] = $element->getText();
    }

    $this->assertContains($text, $elements);
  }

  /**
   * Asserts that an array has a specified key.
   *
   * @param mixed             $key
   * @param array|\ArrayAccess $array
   * @param string            $message
   */
  protected function assertArrayHasKeys($keys, $array, $message = '') {
    foreach ($keys as $key) {
      $this->assertArrayHasKey($key, $array, $message);
    }
  }

  /**
   * Asserts that an array has a specified key.
   *
   * @param mixed             $key
   * @param array|\ArrayAccess $array
   * @param string            $message
   */
  protected function assertArrayNotHasKeys($keys, $array, $message = '') {
    foreach ($keys as $key) {
      $this->assertArrayNotHasKey($key, $array, $message);
    }
  }

  protected function entityReload(EntityInterface $entity) {
    $id = $entity->id();
    $entity_type = $entity->getEntityTypeId();
    $entity_storage = $this->entityTypeManager->getStorage($entity_type);
    $entity_storage->resetCache([$entity->id()]);
    return $entity_storage->load($id);
  }

  protected function uuidToId($entity_type, $uuid) {
    /** @var EntityRepositoryInterface $repository */
    $repository = \Drupal::service('entity.repository');
    $entity = $repository->loadEntityByUuid($entity_type, $uuid);
    return $entity ? $entity->id() : FALSE;
  }

  protected function getFixturesPath($module_name = 'jocrf_core') {
    /** @var ModuleHandler $module_handler */
    $module_handler = \Drupal::service('module_handler');
    $module_path = $module_handler->getModule($module_name)->getPath();
    /** @var FileSystem $file_system */
    $file_system = \Drupal::service('file_system');
    $path = $file_system->realpath($module_path);
    return "$path/tests/fixtures/";
  }

  protected function getTestData($name, $path = NULL, $ext = NULL) {
    $path = $path ? $path : $this->getFixturesPath();
    $ext = $ext ? $ext : 'yml';
    $file = file_get_contents($path . "{$name}.{$ext}");
    $data = Yaml::decode($file);
    $this->assertNotNull($data, "Encoded yml file $name is not null");
    return $data;
  }

  protected function convertToArray($object) {
    return Json::decode(Json::encode($object));
  }

  protected function exportToYaml($php_array) {
    // Either convert object to array, or, ensure all recursive values are arrays.
    $php_array = $this->convertToArray($php_array);
    $encode = Yaml::encode($php_array);
    return $encode;
  }
}
