<?php

namespace Drupal\forward\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\file\Entity\File;
use Drupal\forward\Services\ForwardLinkGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Forward Link formatter.
 *
 * @FieldFormatter(
 *   id = "forward_link",
 *   label = @Translation("Forward Link"),
 *   field_types = {
 *     "forward"
 *   }
 * )
 */
class ForwardLinkFormatter extends FormatterBase implements ContainerFactoryPluginInterface {

  /**
   * The Forward link generation service.
   *
   * @var \Drupal\forward\Services\ForwardLinkGeneratorInterface
   */
  protected $linkGenerator;

  /**
   * Constructs a ForwardLinkFormatter object.
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
   * @param \Drupal\forward\Services\ForwardLinkGeneratorInterface $link_generator
   *   The Forward link generation service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    ForwardLinkGeneratorInterface $link_generator) {

    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->linkGenerator = $link_generator;
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
      $container->get('forward.link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'title' => t('Forward this [forward:entity-type] to a friend'),
      'style' => 2,
      'icon' => '',
      'nofollow' => TRUE,
    ] + parent::defaultSettings();
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
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $element = [];
    $settings = $this->getSettings();

    $element['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $settings['title'],
      '#description' => $this->t('Set the text of the Forward link title. Replacement tokens may be used.'),
      '#required' => TRUE,
    ];
    $element['style'] = [
      '#type' => 'radios',
      '#title' => $this->t('Style'),
      '#default_value' => $settings['style'],
      '#options' => [
        0 => $this->t('Text only'),
        1 => $this->t('Icon only'),
        2 => $this->t('Icon and text'),
      ],
      '#description' => $this->t('Select the visual style of the link.'),
    ];
    $element['icon'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Path to custom icon'),
      '#default_value' => $settings['icon'],
      '#description' => $this->t('The path to your custom link icon instead of the default icon. Example: sites/default/files/icon.png'),
      '#element_validate' => [[get_class($this), 'validateIconPath']],
    ];
    $element['nofollow'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Add a nofollow tag to the Forward link'),
      '#default_value' => $settings['nofollow'],
    ];

    return $element;
  }

  /**
   * Ensure the custom icon path is valid if provided.
   *
   * @param array $element
   *   The validated element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The state of the form.
   */
  public static function validateIconPath(array $element, FormStateInterface $form_state) {
    $plugin_name = $form_state->get('plugin_settings_edit');
    $key = ['fields', $plugin_name, 'settings_edit_form', 'settings', 'icon'];
    $icon = $form_state->getValue($key);
    if ($icon) {
      $image = File::create();
      $image->setFileUri($icon);
      $filename = \Drupal::service('file_system')->basename($image->getFileUri());
      $image->setFilename($filename);
      $errors = file_validate_is_image($image);
      if (count($errors)) {
        $form_state->setError($element, t('The specified icon is not a valid image. Please double check the path.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [];
    $summary[] = $this->t('Title: %title', ['%title' => $this->getSetting('title')]);
    switch ($this->getSetting('style')) {
      case 0:
        $summary[] = $this->t('Style: Text only');
        break;

      case 1:
        $summary[] = $this->t('Style: Icon only');
        break;

      case 2:
        $summary[] = $this->t('Style: Icon and text');
        break;
    }
    if ($this->getSetting('style') && $this->getSetting('icon')) {
      $summary[] = $this->t('Icon: %icon', ['%icon' => $this->getSetting('icon')]);
    }
    if ($this->getSetting('nofollow')) {
      $summary[] = $this->t('Tag: A nofollow tag is added to the link');
    }
    return $summary;
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
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      $display = $item->get('display')->getValue();

      // Render unless directed otherwise.
      if (!empty($display)) {
        $entity = $item->getEntity();

        $elements[$delta] = $this->linkGenerator->generate($entity, $settings);
      }
    }

    return $elements;
  }

}
