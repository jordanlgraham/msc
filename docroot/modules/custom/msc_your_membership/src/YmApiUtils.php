<?php

namespace Drupal\msc_your_membership;

use Drupal\node\NodeInterface;
use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\ymapi\Plugin\ApiTools\Client;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class YmApiUtils.
 */
class YmApiUtils {

  /**
   * Drupal\key\KeyRepositoryInterface definition.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Drupal\ymapi\Plugin\ApiTools\Client definition.
   *
   * @var \Drupal\ymapi\Plugin\ApiTools\Client
   */
  protected $ymApiClient;

  /**
   * Drupal\Core\Config\ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Component\Datetime\TimeInterface definition.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $datetimeTime;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new ZoomApiUtils object.
   */
  public function __construct(KeyRepositoryInterface $key_repository, Client $ymapi_client, ConfigFactoryInterface $config_factory, TimeInterface $datetime_time, MessengerInterface $messenger) {
    $this->keyRepository = $key_repository;
    $this->ymApiClient = $ymapi_client;
    $this->configFactory = $config_factory;
    $this->datetimeTime = $datetime_time;
    $this->messenger = $messenger;
  }

  /**
   * Gets all of the events from YM.
   *
   * @return array
   *   An array of events.
   */
  public function getEvents() {
    $events = [];
    $notDone = TRUE;
    $pageNumber = 1;
    while ($notDone) {
      $eventBatch = $this->ymApiClient->get('/Events', [
        'parameters' => [
          'PageNumber' => $pageNumber,
          'PageSize' => 100,
        ]
      ]);
      // If $eventBatch['EventsList'] is not empty, merge with $events
      // otherwise we're done.
      if (!empty($eventBatch['EventsList'])) {
        // Iterate over $eventBatch['EventsList'] and add to $events.
        foreach ($eventBatch['EventsList'] as $event) {
          $newEvents[$event['EventId']] = $event;
        }
        $events = array_merge($events, $newEvents);
        $pageNumber++;
      }
      else {
        $notDone = FALSE;
      }
    }

    return $events;
  }

  /**
   * Gets all of the event categories from YM.
   *
   * @return array
   *   An array of event categories.
   */
  public function getEventCategories() {
    return $this->ymApiClient->get('/EventCategories', []);
  }

  /**
   * {@inheritDoc}
   */
  public function getMemberTypes() {
    $memberTypes = [];
    $response = $this->ymApiClient->get('/MemberTypes', []);
    if (!empty($response['MemberTypes'])) {
      $memberTypes = $response['MemberTypes'];
    }
    return $memberTypes;
  }

  /**
   * Cleans HTML before sending to Zoom.
   *
   * @param string $markup
   *   Markup from a drupal field.
   *
   * @return string
   *   Clean up teaser for zoom.
   */
  public function cleanTeaser($markup) {
    if (empty($markup)) {
      return '';
    }
    $replaceArr = ['<p>', '</p>', '</p> <p>'];
    $replacementArr = ['', '', PHP_EOL . PHP_EOL];
    $cleanTeaser = str_replace($replaceArr, $replacementArr, $markup);
    $cleanTeaser = strip_tags($markup);
    return $cleanTeaser;
  }

  /**
   * {@inheritDoc}
   */
  public function getMemberProfile(int $profileId) {
    return $this->ymApiClient->get('/People', [
      'parameters' => [
        'ProfileID' => $profileId,
      ]
    ]);
  }

  /**
   * {@inheritDoc}
   */
  public function getProfileIdsSince(string $startDate) {
    $membersLastUpdated = [];
    $done = FALSE;
    $counter = 0;
    while (!$done) {
      $counter++;
      $response = $this->ymApiClient->get('/PeopleIDs', [
        'parameters' => [
          'UserType' => 'All',
          'PageNumber' => $counter,
          'PageSize' => 10000,
          'TimeStamp' => $startDate,
        ]
      ]);
      if (!empty($response['IDList'])) {
        $membersLastUpdated = array_merge(
          $membersLastUpdated, $response['IDList']
        );
      } else {
        $done = TRUE;
      }
    }
    return $membersLastUpdated;
  }

  /**
   * {@inheritDoc}
   */
  public function getYmIndeces($org) {
    $ymIndices = [
      'field_populations_served' => [
        'type' => 'list_string',
        'name' => 'Populations Served',
      ],
      'field_languages_spoken' => [
        'type' => 'list_string',
        'name' => 'Language',
      ],
      'field_licensed_nursing_facility_beds' => [
        'type' => 'integer',
        'name' => 'LNFB',
      ],
      'field_medicaid_certified' => [
        'type' => 'boolean',
        'name' => 'MedCert',
      ],
      'field_medicare_certified' => [
        'type' => 'boolean',
        'name' => 'MedicareCert',
      ],
      'field_pace_program' => [
        'type' => 'boolean',
        'name' => 'Pace',
      ],
      'field_services' => [
        'type' => 'list_string',
        'name' => 'Services',
      ],
      'field_populations_served' => [
        'type' => 'list_string',
        'name' => 'PopServed',
      ],
      'field_specialized_unit' => [
        'type' => 'boolean',
        'name' => 'SpecializedUnits',
      ],
      'field_va_contract' => [
        'type' => 'boolean',
        'name' => 'VAServices',
      ],
      'field_state_id' => [
        'type' => 'string',
        'name' => 'StateID',
      ],
      'field_congressional_district' => [
        'type' => 'string',
        'name' => 'Congressional District',
      ],
      'field_county' => [
        'type' => 'list_string',
        'name' => 'County',
      ],
      'field_owner' => [
        'type' => 'string',
        'name' => 'Owner',
      ],
      'field_number_of_employees' => [
        'type' => 'string',
        'name' => 'Number Of Employees',
      ],
      'field_chapter_affiliate' => [
        'type' => 'list_string',
        'name' => 'Chapter',
      ],
      'field_massmap_member' => [
        'type' => 'boolean',
        'name' => 'MassMapMember',
      ],
      'field_for_profit' => [
        'type' => 'boolean',
        'name' => 'ForProfit',
      ],
      'field_ahca_member' => [
        'type' => 'boolean',
        'name' => 'AHCA',
      ],
      'field_cna_training_site' => [
        'type' => 'boolean',
        'name' => 'CNATraining',
      ],
      'field_administrator_in_training' => [
        'type' => 'boolean',
        'name' => 'AdmininTraining',
      ],
      'field_ncal_member' => [
        'type' => 'boolean',
        'name' => 'NCAL',
      ],
      'field_assisted_living_beds' => [
        'type' => 'integer',
        'name' => 'ALB',
      ],
      'field_dementia_care_beds' => [
        'type' => 'integer',
        'name' => 'DCB',
      ],
      'field_dph_region' => [
        'type' => 'string',
        'name' => 'DPHRegion',
      ],
      'field_ep_region' => [
        'type' => 'string',
        'name' => 'EPRegion',
      ],
      'field_hospital_based_nf_tcu_beds' => [
        'type' => 'integer',
        'name' => 'Hospital Based NF (TCU) Beds',
      ],
      'field_independent_living_beds' => [
        'type' => 'integer',
        'name' => 'ILB',
      ],
      'field_licensed_rest_home_beds' => [
        'type' => 'integer',
        'name' => 'LRHB',
      ],
      'field_manager' => [
        'type' => 'string',
        'name' => 'Manager',
      ],
      'field_medicaid_occupancy_percent' => [
        'type' => 'string',
        'name' => 'MedOCC',
      ],
      'field_number_of_residents' => [
        'type' => 'string',
        'name' => 'Number of Residents',
      ],
      'field_one_bedroom' => [
        'type' => 'integer',
        'name' => 'One Bedroom',
      ],
      'field_representative_district' => [
        'type' => 'string',
        'name' => 'RepDistrict',
      ],
      'field_retirement_community_aff' => [
        'type' => 'string',
        'name' => 'Retirement Community Affiliation',
      ],
      'field_senate_district' => [
        'type' => 'string',
        'name' => 'SenateDistrict',
      ],
      'field_studio' => [
        'type' => 'integer',
        'name' => 'Studio',
      ],
      'field_total_annual_admissions' => [
        'type' => 'integer',
        'name' => 'Total Annual Admissions',
      ],
      'field_two_bedroom' => [
        'type' => 'integer',
        'name' => 'Two Bedroom',
      ],
      'field_wib_region' => [
        'type' => 'string',
        'name' => 'WIBregion',
      ],
      'field_primary_services' => [
        'type' => 'list_string',
        'name' => 'APS',
      ],
      'field_additional_services' => [
        'type' => 'list_string',
        'name' => 'OtherService',
      ],
      // @todo: calculate this field
      // 'field_all_services' => [
      //   'type' => 'list_string',
      //   'name' => 'Associate Other Service',
      // ],
      'field_facebook' => [
        'type' => 'link',
        'name' => 'FacebookPage',
      ],
      'field_twitter' => [
        'type' => 'link',
        'name' => 'TwitterPage',
      ],
      'field_linkedin' => [
        'type' => 'link',
        'name' => 'LinkedInPage',
      ],
      'field_customer_key' => [
        'type' => 'string',
        'name' => 'NFPCustomerKey',
      ],
    ];

    // Concern ourselves with two content types.
    $bundles = ['facility', 'vendor'];

    // Iterate over ymIndices and add an 'index' element for each.
    foreach ($ymIndices as $key => $field) {
      $index = $this->getYmProfileIndex($field['name'], $org);
      $ymIndices[$key]['index'] = $index;

      // Determine whether the field exists on the bundle.
      $ymIndices[$key]['bundles'] = [];
      foreach ($bundles as $bundle) {
        // Determine whether the field exists on the bundle.
        $fieldExists = \Drupal::entityTypeManager()
          ->getStorage('field_config')
          ->loadByProperties([
            'field_name' => $key,
            'entity_type' => 'node',
            'bundle' => $bundle,
          ]);

        if (!empty($fieldExists)) {
          // The field exists in the bundle.
          $ymIndices[$key]['bundles'][] = $bundle;
        }
      }
    }

    return $ymIndices;
  }

  /**
   * {@inheritDoc}
   */
  public function getYmProfileIndex($name, $profile) {
    $index = 0;
    foreach ($profile['MemberCustomFieldResponses'] as $field) {
      if ($field['FieldCode'] == $name) {
        return $index;
      }
      $index++;
    }
    return FALSE;
  }
}
