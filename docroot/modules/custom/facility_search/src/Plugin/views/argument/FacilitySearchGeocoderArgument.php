<?php

namespace Drupal\facility_search\Plugin\views\argument;

use Drupal\geolocation\GeocoderManager;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geolocation\Plugin\views\argument\ProximityArgument;

/**
 * Argument handler for geolocation.
 *
 * @ingroup views_argument_handlers
 *
 * @ViewsArgument("facility_search_geolocation_geocoder_argument")
 */
class FacilitySearchGeocoderArgument extends ProximityArgument implements ContainerFactoryPluginInterface {


  /**
   * The GeocoderManager object.
   *
   * @var \Drupal\geolocation\GeocoderManager
   */
  protected $geocoderManager;

  /**
   * Constructs a Handler object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\geolocation\GeocoderManager $geocoder_manager
   *   Geocoder manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderManager $geocoder_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->geocoderManager = $geocoder_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.geolocation.geocoder')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    return array_replace_recursive(
      parent::defineOptions(),
      [
        'geocoder' => ['default' => 'google_geocoding_api'],
        'geocoder_settings' => ['default' => []],
      ]
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $geocoder_options = [];
    foreach ($this->geocoderManager->getDefinitions() as $id => $definition) {
      if (empty($definition['frontendCapable'])) {
        continue;
      }
      $geocoder_options[$id] = $definition['name'];
    }

    if ($geocoder_options) {
      $form['geocoder'] = [
        '#type' => 'select',
        '#options' => $geocoder_options,
        '#title' => $this->t('Geocoder plugin'),
        '#default_value' => $this->options['geocoder'],
        '#ajax' => [
          'callback' => [get_class($this->geocoderManager), 'addGeocoderSettingsFormAjax'],
          'wrapper' => 'argument-geocoder-plugin-settings',
          'effect' => 'fade',
        ],
      ];

      if (!empty($this->options['geocoder'])) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(
          $this->options['geocoder'],
          $this->options['geocoder_settings'] ?: []
        );
      }
      elseif (current(array_keys($geocoder_options))) {
        $geocoder_plugin = $this->geocoderManager->getGeocoder(current(array_keys($geocoder_options)));
      }

      if (!empty($geocoder_plugin)) {
        $geocoder_settings_form = $geocoder_plugin->getOptionsForm();
        if ($geocoder_settings_form) {
          $form['geocoder_settings'] = $geocoder_settings_form;
        }
      }

      if (empty($form['geocoder_settings'])) {
        $form['geocoder_settings'] = [
          '#type' => 'html_tag',
          '#tag' => 'span',
          '#value' => $this->t("No settings available."),
        ];
      }

      $form['geocoder_settings'] = array_replace_recursive($form['geocoder_settings'], [
        '#flatten' => TRUE,
        '#prefix' => '<div id="argument-geocoder-plugin-settings">',
        '#suffix' => '</div>',
      ]);
    }
  }

  /**
   * Processes the passed argument into an array of relevant geolocation data.
   *
   * @return array|bool
   *   The calculated values.
   */
  public function getParsedReferenceLocation() {
    // Cache the values so this only gets processed once.
    static $values;

    if (!isset($values)) {
      $values = [];
      $queryParams = \Drupal::request()->query->all();
      if (empty($queryParams['center'])) {
        return $values;
      }
      $values = [
        'address' => $queryParams['center']['geocoder']['geolocation_geocoder_address'],
        'lat' => $queryParams['center']['coordinates']['lat'],
        'lng' => $queryParams['center']['coordinates']['lng'],
        'distance' => floatval($queryParams['proximity']),
        'unit' => 'mi',
        'operator' => '<=',
      ];
    }
    return $values;
  }

  public function getValue() {
    $queryParams = \Drupal::request()->query->all();
    if (empty($queryParams['center'])) {
      return '';
    }
    $value = $queryParams['center']['geocoder']['geolocation_geocoder_address'];
    return $value;
  }

}
