<?php

namespace Drupal\facility_search\Plugin\geolocation\Geocoder;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\geolocation\GeocoderBase;
use Geocoder\Model\AddressCollection;
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

class MSCGeocoder extends GeocoderBase implements ContainerFactoryPluginInterface {
  /** @var GeocoderInterface */
  protected $geocoder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, GeocoderInterface $geocoder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->geocoder = $geocoder;
  }


  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('geocoder')
    );
  }

  public function formAttachGeocoder(array &$render_array, $element_name) {
    $render_array['geocode_postal'] = array(
      '#type' => 'textfield',
      '#title' => t('Postal code'),
      '#attributes' => array(
        'placeholder' => t('Postal code'),
      ),
    );

    $render_array['geocode_state'] = array(
      '#type' => 'hidden',
      '#default_value' => 1,
    );
  }

  /**
   * @inheritdoc
   */
  public function formValidateInput(FormStateInterface $form_state) {
    $input = $form_state->getUserInput();
    if (
      (!empty($input['geocode_postal']) || !empty($input['proximity']))
      && (!isset($input['op']) || $input['op'] != 'Reset')
    ) {
      $geocode = TRUE;
      // Validate the user has entered a postal code.
      if (empty($input['geocode_postal'])) {
        $form_state->setErrorByName('geocode_postal', t('Please enter a valid postal code.'));
        $geocode = FALSE;
      }
      if (empty($input['proximity'])) {
        $form_state->setErrorByName('proximity', t('Please select a radius to search within.'));
        $geocode = FALSE;
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
    $plugins = ['googlemaps'];
    /** @var AddressCollection $addressCollection */
    $addressCollection = $this->geocoder->geocode($address, $plugins, []);
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
