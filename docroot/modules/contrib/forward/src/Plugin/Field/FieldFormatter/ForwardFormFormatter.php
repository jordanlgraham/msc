<?php

namespace Drupal\forward\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\forward\Services\ForwardFormBuilderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Forward Form formatter.
 *
 * @FieldFormatter(
 *   id = "forward_form",
 *   label = @Translation("Forward Form"),
 *   field_types = {
 *     "forward"
 *   }
 * )
 */
class ForwardFormFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The Forward form builder service.
   *
   * @var \Drupal\forward\Services\ForwardFormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a ForwardFormFormatter object.
   *
   * @param string $plugin_id
   *   The plugin_id for the formatter.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the formatter is associated.
   * @param array $settings
   *   The formatter settings.
   * @param string $label
   *   The formatter label display setting.
   * @param string $view_mode
   *   The view mode.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\forward\Services\ForwardFormBuilderInterface $form_builder
   *   The Forward form builder.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ForwardFormBuilderInterface $form_builder) {

    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('forward.form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition) {
    return \Drupal::currentUser()->hasPermission('access forward');
  }

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {
    foreach ($entities_items as $item) {
      // If the display field is empty, it means the edit widget
      // has never been used for this entity. In this case,
      // default to display the link.  The user can then edit
      // the entity and hide the link if needed.
      if ($item->isEmpty()) {
        $display = TRUE;
        $item->appendItem($display);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];

    foreach ($items as $delta => $item) {
      $display = $item->get('display')->getValue();

      // Render unless directed otherwise.
      if (!empty($display)) {
        $entity = $item->getEntity();
        $elements[$delta] = $this->formBuilder->buildInlineForm($entity);
      }
    }

    return $elements;
  }

}
