<?php

namespace Drupal\netforum_event_sync;

use DateTime;
use \Exception;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Drupal\netforum_soap\GetClient;
use Drupal\Core\State\StateInterface;
use Drupal\geocoder\GeocoderInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class EventSync {

  /**
   * The country manager service.
   *
   * @var \Drupal\Core\Locale\CountryManagerInterface
   */
  protected $countryManager;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $term_storage;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\netforum_soap\GetClient
   */
  protected $get_client;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\geocoder\GeocoderInterface
   */
  protected $geocoder;

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  const LAST_SYNC_STATE_KEY = 'netforum_event_sync.last_sync';
  const CRON_STATE_KEY = 'netforum_event_sync.event_sync';
  const DATE_FORMAT = 'm/d/Y H:i:s A';

  public function __construct(EntityTypeManagerInterface $entityTypeManager,
                              GetClient $getClient,
                              LoggerInterface $logger,
                              DateFormatterInterface $dateFormatter,
                              StateInterface $state,
                              GeocoderInterface $geocoder,
                              CountryManagerInterface $countryManager) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->term_storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->get_client = $getClient;
    $this->state = $state;
    $this->geocoder = $geocoder;
    $this->entityTypeManager = $entityTypeManager;
    $this->countryManager = $countryManager;
  }

  /**
   * @param $cst_key string UUID NetForum gives for Organizations
   * @param $type string content type to be loaded or saved (either facility or vendor)
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  private function loadOrCreateEventNode($evt_key) {
    //search for a node with the $cst_key so we can perform an update action.
    $query = $this->node_storage->getQuery();
    $query->condition('status', 1);
    $query->condition('type', 'education_events');
    $query->condition('field_event_key', $evt_key);
    $entity_ids = $query->execute();

    //This function simply returns the first node found.
    //Couple notes:
    // 1) This function should only ever return one node, since it's checking
    //    using the $evt_key UUID
    // 2) This function uses array_values to get the first nid, since the nid
    //    is used as the array index, so it could be anything.

    if (!empty(array_values($entity_ids)[0])) {
      $nid = array_values($entity_ids)[0];
    }
    if(!empty($nid)) {
      $node = $this->node_storage->load($nid);
    } else {
      $node = $this->node_storage->create(['type' => 'education_events']);
    }
    return $node;
  }
  /*
   * This function cleans up the formatting that you get back from NetForum.
   * Here's the format you get:
   * <evt_start_date>3/23/2017 12:00:00 AM</evt_start_date>
   * <evt_start_time>09:30am</evt_start_time>
   *
   * So this function takes the date itself from the front of the evt_start_date
   * and appends the evt_start_time to it, then returns that as a formatted
   * date object
   */

  private function formatNetForumDateTime($date, $time) {
    $date_obj = new DateTime($date);
    $utc = new DateTimeZone('UTC');
    $time_obj = DateTime::createFromFormat('g:ia', $time);
    $date_obj->setTime($time_obj->format('H'), $time_obj->format('i'));
    $date_obj->setTimezone($utc);
    $formatted = $date_obj->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    return $formatted;
  }

  private function loadOrCreateEventTermsByName($terms) {
    $tids = array();
    foreach ($terms as $term) {
      $term_load = $this->term_storage->loadByProperties(['name' => $term, 'vid' => 'event_types']);
      if(!empty($term_load)) {
        $tids[] = array_pop($term_load);
      } else {
        $new_term = $this->term_storage->create(['name' => $term, 'vid' => 'event_types']);
        try {
          $new_term->save();
          $tids[] = $new_term->id();
        } catch(EntityStorageException $e) {
          $this->logger->error('Entity storage exception saving term: @err. Term: <pre>@term</pre>',
            ['@err' => $e->getMessage(), '@term' => print_r($new_term, TRUE)]);
        }
      }
    }
    return $tids;
  }

  /**
   * {@inheritdoc}
   */
  public function syncEvent($event) {
    $evt_key = $event['evt_key'];
    $node = $this->loadOrCreateEventNode($evt_key);
    // Event nodes get manually updated, so don't blow away changes.
    if (!$node->isNew()) {
      return NULL;
    }
    $node->body->value = $event['description']; //formatted text
    $node->title = $event['name'];
    $node->field_date = $this->formatNetForumDateTime($event['start_date'], $event['start_time']); //date w/time 2017-08-15T18:00:00
    $node->field_end_date = $this->formatNetForumDateTime($event['end_date'], $event['end_time']); //date w/time 2017-08-15T18:00:00
    $node->field_event_category = $this->loadOrCreateEventTermsByName(array($event['event_category'])); //taxonomy
    $node->field_event_key = $evt_key; //text
    $node->status = 1;
    if (!empty($event['location'])) {
      $node->field_location->set(0, $event['location']);
    }
    $node->save();
    return $node;
  }

  private function createOrUpdateEvents(array $events) {
    foreach($events as $evt_key => $event) {
      $node = $this->loadOrCreateEventNode($evt_key);
      // Event nodes get manually updated, so don't blow away changes.
      if (!$node->isNew()) {
        continue;
      }
      $node->body->value = $event['description']; //formatted text
      $node->title = $event['name'];
      $node->field_date = $this->formatNetForumDateTime($event['start_date'], $event['start_time']); //date w/time 2017-08-15T18:00:00
      $node->field_end_date = $this->formatNetForumDateTime($event['end_date'], $event['end_time']); //date w/time 2017-08-15T18:00:00
      $node->field_event_category = $this->loadOrCreateEventTermsByName(array($event['event_category'])); //taxonomy
      $node->field_event_key = $evt_key; //text
      $node->status = 1;
      if (!empty($event['location'])) {
        $node->field_location->set(0, $event['location']);
      }
      $node->save();
    }
  }

  //This function does some crude cleanup of the html NetForum's functions return.
  //The str_replace on \n\n\n\n\n is simply because, after stripping html, a ton of
  //new lines are left at the beginning and end of these strings. We do want to keep
  //other new lines so that CKEditor's auto-paragraph function operates and the text
  //looks reasonably presentable.
  private function cleanupNetForumHTML($html) {
    return str_replace("\n\n\n\n\n", "", strip_tags($html));
  }

  /**
   * Sync events from Netforum into nodes.
   *
   * @param int $timestamp
   *  Unix timestamp to start event sync from.
   */
  public function syncEvents($timestamp = NULL) {
    if (!$timestamp) {
      $timestamp = $this->state->get(self::LAST_SYNC_STATE_KEY, strtotime('1/1/2017'));
    }
    // Get stored event types.
    $event_types = $this->getEventTypes();

    $netforum_service = $this->get_client;
    $response_headers = $netforum_service->getResponseHeaders();
    $client = $netforum_service->getClient();
    
    if(!empty($event_types)) {
      // Build an array of events keyed by Event Key <evt_key> so we can
      // save or update them.
      $events = [];
      $i = 0;
      foreach ($event_types as $type) {
        $params = array(
          'typeCode' => $type,
          'szRecordDate' => $this->dateFormatter->format($timestamp, 'custom', 'm/d/Y'),
        );
        try {
          $response = $client->__soapCall('GetEventListByType', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $response_headers);
          if(!empty($response->GetEventListByTypeResult->any)) {
            // Let's make things easy on ourselves and turn this XML into an
            // array. Note that this should be replaced with the Serialization
            // API since it handles this sort of thing.
            $xml = simplexml_load_string($response->GetEventListByTypeResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);
            $plugins = ['googlemaps' => 'googlemaps'];
            if(!empty($array['Result'])) {
              foreach ($array['Result'] as $result) {
                if (!empty($result['evt_key'])) {
                  if(!empty($result['evt_location_html']) && is_string($result['evt_location_html'])) {
                    $location = $this->cleanupNetForumHTML($result['evt_location_html']);
                    // Attempt to geocode the location string.
                    $geocoded = $this->geocoder->geocode($location, $plugins, []);
                    if ($geocoded) {
                      $geocoded_location = $geocoded->first();
                      $admin_levels = $geocoded_location->getAdminLevels();
                      $location = [
                        'address_line1' => $geocoded_location->getStreetNumber() . ' ' . $geocoded_location->getStreetName(),
                        'locality' => $geocoded_location->getLocality(),
                        'postal_code' => $geocoded_location->getPostalCode(),
                        'country_code' => $geocoded_location->getCountryCode(),
                        'administrative_area' => $admin_levels->first()->getCode(),
                      ];
                    }
                    else {
                      $location = [];
                    }
                  } else {
                    $location = [];
                  }
                  if(!empty($result['prd_description_html'])) {
                    $description = $this->cleanupNetForumHTML($result['prd_description_html']);
                  } else {
                    $description = '';
                  }
                  $events[(string) $result['evt_key']] = [
                    'name' => (string) $result['prd_name'],
                    'start_date' => (string) $result['evt_start_date'],
                    'end_date' => (string) $result['evt_end_date'],
                    'start_time' => (string) $result['evt_start_time'],
                    'end_time' => (string) $result['evt_end_time'],
                    'event_category' => (string) $result['etp_code'],
                    'description' => $description,
                    'location' => $location,
                  ];
                  $i++;
                }
              }
            }
          }
        } catch (Exception $e) {
          watchdog_exception('netforum_event_sync', $e, 'Error retrieving @type type events.', ['@type' => $type]);
        }
      }
      $this->createOrUpdateEvents($events);
      return $i;
    }
  }

  /**
   * Obtain array of events from Netforum.
   *
   * @param int $timestamp
   *  Unix timestamp to start event sync from.
   */
  public function getEvents($timestamp = NULL) {
    $events = [];
    if (!$timestamp) {
      $timestamp = $this->state->get(self::LAST_SYNC_STATE_KEY, strtotime('1/1/2017'));
    }
    // Get stored event types.
    $event_types = $this->getEventTypes();
    $netforum_service = $this->get_client;
    $response_headers = $netforum_service->getResponseHeaders();
    $client = $netforum_service->getClient();
    
    if(!empty($event_types)) {
      // Build an array of events keyed by Event Key <evt_key> so we can
      // save or update them.
      $events = [];
      $i = 0;
      foreach ($event_types as $type) {
        $params = array(
          'typeCode' => $type,
          'szRecordDate' => $this->dateFormatter->format($timestamp, 'custom', 'm/d/Y'),
        );
        try {
          $response = $client->__soapCall('GetEventListByType', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $response_headers);
          if(!empty($response->GetEventListByTypeResult->any)) {
            // Convert xml response to array.
            $xml = simplexml_load_string($response->GetEventListByTypeResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);
            $plugins = ['googlemaps' => 'googlemaps'];
            if(!empty($array['Result'])) {
              // If there is only one event in $array['Result'], wrap it in an
              // array.
              $array['Result'] = (array_key_first($array['Result']) !== 'evt_key')
                ? $array['Result']
                : [$array['Result']];

              foreach ($array['Result'] as $result) {
                if (!empty($result['evt_key'])) {
                  if(!empty($result['evt_location_html']) && is_string($result['evt_location_html'])) {
                    $location = $this->cleanupNetForumHTML($result['evt_location_html']);
                    // Attempt to geocode the location string.
                    $geocoded = $this->geocoder->geocode($location, $plugins, []);
                    if ($geocoded) {
                      $geocoded_location = $geocoded->first();
                      $address = $geocoded_location->getFormattedAddress();
                      $addressBits = explode(',', $address);
                      $countries = $this->countryManager->getList();
                      $location = [
                        'address_line1' => !empty($addressBits[0]) ? $addressBits[0] : '',
                        'locality' => !empty($addressBits[1]) ? trim($addressBits[1]) : '',
                        'postal_code' => !empty($addressBits[2]) ? substr(trim($addressBits[2]), strpos(trim($addressBits[2]), " ") + 1) : '',
                        'country_code' => !empty($addressBits[3]) ? 'US' : '',
                        'administrative_area' => !empty($addressBits[2]) ? substr(trim($addressBits[2]), 0, strpos(trim($addressBits[2]), " ")) : '',
                      ];
                    }
                    else {
                      $location = [];
                    }
                  } else {
                    $location = [];
                  }
                  if(!empty($result['prd_description_html'])) {
                    $description = $this->cleanupNetForumHTML($result['prd_description_html']);
                  } else {
                    $description = '';
                  }
                  $events[] = [
                    'evt_key' => $result['evt_key'],
                    'name' => (string) $result['prd_name'],
                    'start_date' => (string) $result['evt_start_date'],
                    'end_date' => (string) $result['evt_end_date'],
                    'start_time' => (string) $result['evt_start_time'],
                    'end_time' => (string) $result['evt_end_time'],
                    'event_category' => (string) $result['etp_code'],
                    'description' => $description,
                    'location' => $location,
                  ];
                  $i++;
                }
              }
            }
          }
        } catch (Exception $e) {
          watchdog_exception('netforum_event_sync', $e, 'Error retrieving @type type events.', ['@type' => $type]);
        }
      }
    }
    return $events;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventTypes() {
    $term_data = [];
    $vid = 'event_types';
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $term_data[$term->tid] =  $term->name;
    }
    return $term_data;
  }

}
