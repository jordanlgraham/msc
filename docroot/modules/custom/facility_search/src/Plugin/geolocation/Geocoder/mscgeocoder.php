<?php

namespace Drupal\facility_search\Plugin\geolocation\Geocoder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geolocation\GeocoderBase;
use Drupal\geolocation\GeocoderCountryFormattingManager;
use Geocoder\Model\AddressCollection;
use Drupal\Core\Config\Config;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\geocoder\GeocoderInterface;

/**
 * Uses the geocoder's Google Maps integration.
 *
 * @Geocoder(
 *   id = "msc_geocoder",
 *   name = @Translation("MSC Geocoding API"),
 *   description = @Translation("Uses the geocoder module's Google Maps integration."),
 *   locationCapable = true,
 *   boundaryCapable = false,
 * )
 */

class mscGeocoder extends GeocoderBase implements ContainerFactoryPluginInterface {
  /** @var GeocoderInterface */
  protected $geocoder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, Config $config, GeocoderInterface $geocoder, GeocoderCountryFormattingManager $formattingManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $formattingManager);
    $this->geocoder = $geocoder;
  }


  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory')->get('geolocation.settings'),
      $container->get('geocoder'),
      $container->get('plugin.manager.geolocation.geocoder_country_formatting')
    );
  }

  public function formAttachGeocoder(array &$render_array, $element_name) {
    $zip = '';
    if (!empty($_GET["geocode_postal"])) {
      $zip = $_GET["geocode_postal"];
    }
    $render_array['geocode_postal'] = array(
      '#type' => 'textfield',
      '#title' => t('Zip Code'),
      '#default_value' => $zip,
    );

    $render_array['geocode_state'] = array(
      '#type' => 'hidden',
      '#default_value' => 1,
    );

    $options = [];
    for ($i = 1; $i < 21; $i++) {
      $options[$i*5] = $this->t('@num miles', ['@num' => $i*5]);
    }

    $render_array['proximity'] = array(
      '#type' => 'select',
      '#title' => $this->t('Proximity'),
      '#empty_option' => $this->t('-Distance-'),
      '#default_value' => 5,
      '#options' => $options,
    );
  }

  /**
   * @inheritdoc
   */
  public function formValidateInput(FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    if (
      (!empty($input['geocode_postal']) || !empty($input['proximity']))
      && (!isset($input['op']) || $input['op'] !== 'Reset')
    ) {
      $geocode = FALSE;
      if (!empty($input['proximity']) && !empty($input['geocode_postal'])) {
        $geocode = TRUE;
      }
      if (!$geocode) {
        return FALSE;
      }
      $location_data = $this->geocode($input['geocode_postal']);
      if (empty($location_data)) {
        $form_state->setErrorByName('', t('Unable to perform search.  Please check your location and try again.'));
      }
      else {
        $form_state->setUserInput(['geocoded' => $location_data]);
      }
    }
  }

  /**
   * @inheritdoc
   */
  public function formProcessInput(array &$input, $element_name) {
    // Get the address from the input.
    $location_data = $this->geocode($input['geocode_postal']);
    if (!$location_data) {
      $input['geocode_state'] = 0;
      return FALSE;
    }
    $input['geocode_state'] = 1;
    return $location_data;
  }

  /**
   * @inheritdoc
   */
  public function geocode($address) {
    $plugins = ['googlemaps' => 'googlemaps'];
    /** @var AddressCollection $addressCollection */
    $addressCollection = $this->geocoder->geocode($address, $plugins, []);
    exit();
    if (!$addressCollection) {
      $location = FALSE;
    }
    else {
      $address_array = $addressCollection->first()->toArray();
      $location = [
        'location' => [
          'lat' => $address_array['latitude'],
          'lng' => $address_array['longitude'],
        ],
      ];
    }
    $this->location_data = $location;
    return $location;
  }
}
