<?php

namespace Drupal\forward\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Plugin implementation of the Forward field type.
 *
 * @FieldType(
 *   id = "forward",
 *   label = @Translation("Forward"),
 *   description = @Translation("A field for creating a Forward link."),
 *   default_widget = "forward",
 *   default_formatter = "forward_link",
 *   cardinality = 1,
 * )
 */
class ForwardItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'display' => [
          'description' => 'A flag indicating whether Forward should be displayed.',
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'default' => 1,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['display'] = DataDefinition::create('boolean')
      ->setLabel(t('Display'))
      ->setRequired(FALSE);

    return $properties;
  }

}
