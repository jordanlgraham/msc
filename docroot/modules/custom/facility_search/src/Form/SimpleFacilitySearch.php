<?php

namespace Drupal\facility_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\geocoder\GeocoderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SimpleFacilitySearch extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * SimpleFacilitySearch constructor.
   *
   * @param \Drupal\geocoder\GeocoderInterface $geocoder
   *   The gecoder service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(GeocoderInterface $geocoder, EntityTypeManagerInterface $entity_type_manager) {
    $this->geocoder = $geocoder;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('geocoder'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facility_search_simple';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['keys'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facility Search by Name, City, or Zip Code'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Facility Name, City, or Zip Code'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#prefix' => '<div class="facility-search-submit">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $query = [];
    // Redirect the user to the view with the exposed filter set.
    $keys = $form_state->getValue('keys');
    $addressInMA = FALSE;
    // If $keys can be geocoded and is in MA, use geocoded location as filter.
    // Otherwise pass $keys as the title.
    $provider_ids = ['googlemaps'];
    $providers = $this->entityTypeManager->getStorage('geocoder_provider')->loadMultiple($provider_ids);
    $addressCollection = $this->geocoder->geocode($keys, $providers);
    
    // Try any locations in $addressCollection to see if they're in MA.
    foreach ($addressCollection->all() as $location) {
      // Is this $location in MA?
      $formattedAddress = $location->getFormattedAddress();
      if (!empty($formattedAddress)) {
        $addressArray = explode(',', $formattedAddress);
        // Skip this location if it's not in MA.
        switch (is_numeric($keys)) {
          case TRUE:
            if (count($addressArray) !== 3
              || $addressArray[2] !== ' USA'
              || $addressArray[1] !== ' MA ' . $keys
              ) {
                continue 2;
            }

            break;

          default:
            if (count($addressArray) !== 3
              || $addressArray[2] !== ' USA'
              || $addressArray[1] !== ' MA'
              ) {
                continue 2;
            }
        }

        // This is a MA address. Pass its info as a proximity filter.
        $query = [
          'center' => [
            'coordinates' => [
              'lat' => $location->getCoordinates()->getLatitude(),
              'lng' => $location->getCoordinates()->getLongitude(),
            ],
            'geocoder' => [
              'geolocation_geocoder_address' => $location->getFormattedAddress(),
            ],
          ],
          'proximity' => 5,
        ];
        $addressInMA = TRUE;
        break; // Stop looping through $addressCollection.
      }
    }

    // See if the user entered part of a facility name.
    if (!$addressInMA) {
      $storage = $this->entityTypeManager->getStorage('node');
      $dbQuery = $storage->getQuery();
      $nids = $dbQuery
        ->condition('type', 'facility')
        ->condition('title', $keys, 'CONTAINS')
        ->execute();
  
      if (!empty($nids)) {
        $query = [
          'title' => $keys,
        ];
      }
    }
    
    $form_state->setRedirect('view.facility_search.page_1', [], ['query' => $query]);
  }

}