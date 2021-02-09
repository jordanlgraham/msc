<?php

namespace Drupal\Tests\forward\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;

/**
 * Provides a base class for testing the Forward module.
 */
abstract class ForwardTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['forward', 'node', 'user', 'forward_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * A simple user with 'access content' permission.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $webUser;

  /**
   * A user with 'access content' and 'access forward' permissions.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $forwardUser;

  /**
   * An user with permissions to administer Mollom.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $adminUser;

  /**
   * Perform any initial set up tasks that run before every test method.
   */
  protected function setUp() {
    parent::setUp();

    // Create Basic page and Article node types.
    if ($this->profile != 'standard') {
      $this->drupalCreateContentType([
        'type' => 'page',
        'name' => 'Basic page',
        'display_submitted' => FALSE,
      ]);
      $this->drupalCreateContentType([
        'type' => 'article',
        'name' => 'Article',
      ]);
    }

    $this->entityType = 'node';
    $this->bundle = 'article';
    $this->fieldName = mb_strtolower($this->randomMachineName());

    $field_storage = FieldStorageConfig::create([
      'field_name' => $this->fieldName,
      'entity_type' => $this->entityType,
      'type' => 'forward',
    ]);
    $field_storage->save();

    $instance = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => $this->bundle,
      'label' => $this->randomMachineName(),
    ]);
    $instance->save();

    $values = [
      'targetEntityType' => $this->entityType,
      'bundle' => $this->bundle,
      'mode' => 'default',
      'status' => TRUE,
    ];

    $this->display = \Drupal::entityTypeManager()
      ->getStorage('entity_view_display')
      ->load('node.article.default');

    $this->display
      ->setComponent($this->fieldName, [
        'type' => 'forward_link',
        'settings' => [
          'title' => 'Forward this [forward:entity-type] to a friend',
          'style' => 2,
          'icon' => '',
          'nofollow' => TRUE,
        ],
      ]
    );
    $this->display->save();

    // Create test users.
    $this->webUser = $this->drupalCreateUser([
      'access content',
    ]);
    $this->forwardUser = $this->drupalCreateUser([
      'access content',
      'access forward',
    ]);

    $permissions = [
      'access forward',
      'administer forward',
      'administer users',
      'bypass node access',
    ];
    $this->adminUser = $this->drupalCreateUser($permissions);
  }

}
