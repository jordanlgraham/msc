<?php

declare(strict_types=1);

namespace Drupal\Tests\entity_embed\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\entity_embed\Form\EntityEmbedDialog;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\Tests\TestFileCreationTrait;

/**
 * @group entity_embed
 */
class EntityEmbedDialogTest extends EntityEmbedFilterTestBase {

  use TestFileCreationTrait;


  /**
   * @dataProvider removalOfEmptyAttributesData
   */
  public function testRemovalOfEmptyAttributes(array $attributes, array $expected) {
    $this->container->get('module_installer')->install(
      ['entity_embed_test']
    );

    $form = [];
    $form_state = new FormState();

    // Create a sample media entity to be embedded.
    File::create([
      'uri' => $this->getTestFiles('image')[0]->uri,
    ])->save();
    $this->media = Media::create([
      'uuid' => '45b2094f-20e2-40e5-b8e6-00bd4fd959e2',
      'bundle' => 'image',
      'name' => 'Screaming hairy armadillo',
      'field_media_image' => [
        [
          'target_id' => 1
        ],
      ],
    ]);
    $this->media->save();

    $EntityEmbedDialog = EntityEmbedDialog::create($this->container);

    $form_state->setValue('attributes', $attributes);
    $response = $EntityEmbedDialog->submitEmbedStep($form, $form_state);
    $commands = $response->getCommands();
    foreach ($commands as $command) {
      if ($command['command'] === 'editorDialogSave') {
        $this->assertSame($expected, $command['values']['attributes']);
      }
    }
  }

  /**
   * Provides test data for ::testRemovalOfEmptyAttributes.
   *
   * @see \Drupal\Tests\entity_embed\Kernel\EntityEmbedDialogTest::testRemovalOfEmptyAttributes
   */
  public function removalOfEmptyAttributesData(): array {
    $attributes = [
      'data-entity-type' => 'media',
      'data-entity-uuid' => '45b2094f-20e2-40e5-b8e6-00bd4fd959e2',
      'data-langcode' => 'en',
      'data-entity-embed-display' => 'view_mode:media.full',
      'data-embed-button' => 'test_media_entity_embed',
    ];
    # 测试 / Тестирование -> Test
    return [
      'Test with empty alt and title.' => [
        'attributes' => $attributes + ['data-entity-embed-display-settings' => ['alt' => '', 'title' => '']],
        'expected' => $attributes,
      ],
      'Test with non-empty alt and title.' => [
        'attributes' => $attributes + ['data-entity-embed-display-settings' => ['alt' => '测试', 'title' => 'Тестирование']],
        'expected' => $attributes + ['alt' => '测试', 'title' => 'Тестирование'],
      ],
      'Test with non-empty alt and title and display settings.' => [
        'attributes' => $attributes +
            ['data-entity-embed-display-settings' => ['alt' => 'ALT', 'title' => 'TITLE', 'setting' => 'SETTING']],
        'expected' => $attributes +
          ['data-entity-embed-display-settings' => '{"setting":"SETTING"}'] +
          ['alt' => 'ALT', 'title' => 'TITLE'] ,
      ],
    ];
  }

}
