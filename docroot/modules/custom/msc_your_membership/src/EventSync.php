<?php

namespace Drupal\msc_your_membership;

use DateTime;
use Exception;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Drupal\Core\State\StateInterface;
use Drupal\geocoder\GeocoderInterface;
use Drupal\ymapi\Plugin\ApiTools\Client;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;

class EventSync {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\ymapi\Plugin\ApiTools\Client
   */
  protected $client;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\geocoder\GeocoderInterface
   */
  protected $geocoder;

  /**
   * @var \Drupal\msc_your_membership\YMApiUtils
   */
  protected $ymApiUtils;

  /**
   * @var \Symfony\Component\Serializer\SerializerInterface
   */
  protected $serializer;

  const LAST_SYNC_STATE_KEY = 'msc_your_membership.last_sync';
  const CRON_STATE_KEY = 'msc_your_membership.event_sync';
  const DATE_FORMAT = 'm/d/Y H:i:s A';

  public function __construct(EntityTypeManagerInterface $entityTypeManager, Client $client, LoggerInterface $logger, DateFormatterInterface $dateFormatter, StateInterface $state, GeocoderInterface $geocoder, YMApiUtils $ymapi_utils) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->client = $client;
    $this->state = $state;
    $this->geocoder = $geocoder;
    $this->entityTypeManager = $entityTypeManager;
    $this->ymApiUtils = $ymapi_utils;
  }

  /**
   * Load or create an event node.
   *
   * @param string $EventId
   *   The event ID from Your Membership
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  private function loadOrCreateEventNode($EventId) {
    //search for a node with the $EventId so we can perform an update action.
    $query = $this->node_storage->getQuery();
    $query->condition('status', 1);
    $query->condition('type', 'education_events');
    $query->condition('field_event_key', $EventId);
    $query->accessCheck(FALSE);
    $entity_ids = $query->execute();

    // This function simply returns the first node found.
    // Notes:
    // 1) This function should only ever return one node, since it's checking
    //    using the $EventId UUID
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
   * This function cleans up the formatting that you get back from YourMembership.
   * Here's the format you get:
   *  "StartDate" => "2023-09-27T00:00:00.0000000", // Note: no time zone.
   *  "StartTime" => "9/27/2023 9:00:00 AM",
   *
   * So this function takes the date itself from the front of the evt_start_date
   * and appends the evt_start_time to it, then returns that as a formatted
   * date object
   */
  private function formatYMDateTime($date, $time) {
    // Set default value for $formattedTime to the beginning of the epoch.
    $formatted = '1970-01-01T00:00:00';
    // Create DateTimeZone objects for the actual time zone and UTC which is
    // used for storage in Drupal.
    $actualTimeZone = new DateTimeZone('America/New_York');
    $storageTimeZone = new DateTimeZone('UTC');
    // Create DateTime objects from the date and time strings.
    $dateObject = DateTime::createFromFormat("Y-m-d\TH:i:s.u", $date, $actualTimeZone);
    if ($dateObject !== false) {
      $dateObject->setTimezone($storageTimeZone);
    }
    $timeObject = DateTime::createFromFormat("n/j/Y g:i:s A", $time,$actualTimeZone);
    if ($timeObject !== false && $dateObject !== false) {
      $timeObject->setTimezone($storageTimeZone);
      $dateObject->setTime($timeObject->format('H'), $timeObject->format('i'));
      $formatted = $dateObject->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    }

    return $formatted;
  }

  /**
   * {@inheritdoc}
   */
  private function loadOrCreateEventTermsByName($terms) {
    $tids = array();
    foreach ($terms as $term) {
      $term_load = $this->termStorage->loadByProperties(['name' => $term, 'vid' => 'event_types']);
      if(!empty($term_load)) {
        $tids[] = array_pop($term_load);
      } else {
        $new_term = $this->termStorage->create(['name' => $term, 'vid' => 'event_types']);
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
    $EventId = $event['EventId'];
    $node = $this->loadOrCreateEventNode($EventId);
    // Event nodes get manually updated, so don't blow away changes.
    if (!$node->isNew()) {
      return NULL;
    }
    $node->body->value = $event['description']; //formatted text
    $node->title = $event['name'];
    $node->field_date = $this->formatYMDateTime($event['start_date'], $event['start_time']); //date w/time 2017-08-15T18:00:00
    $node->field_end_date = $this->formatYMDateTime($event['end_date'], $event['end_time']); //date w/time 2017-08-15T18:00:00
    $node->field_event_category = $this->loadOrCreateEventTermsByName(array($event['event_category'])); //taxonomy
    $node->field_event_key = $EventId; //text
    $node->status = 1;
    if (!empty($event['location'])) {
      $node->field_location->set(0, $event['location']);
    }
    $node->save();
    return $node;
  }

  /**
   * {@inheritdoc}
   */
  private function createOrUpdateEvents(array $events) {
    foreach($events as $EventId => $event) {
      $node = $this->loadOrCreateEventNode($EventId);
      // Event nodes get manually updated, so don't blow away changes.
      if (!$node->isNew()) {
        continue;
      }
      $node->body->value = $event['description']; //formatted text
      $node->title = $event['name'];
      $node->field_date = $this->formatYMDateTime($event['start_date'], $event['start_time']); //date w/time 2017-08-15T18:00:00
      $node->field_end_date = $this->formatYMDateTime($event['end_date'], $event['end_time']); //date w/time 2017-08-15T18:00:00
      $node->field_event_category = $this->loadOrCreateEventTermsByName(array($event['event_category'])); //taxonomy
      $node->field_event_key = $EventId; //text
      $node->status = 1;
      if (!empty($event['location'])) {
        $node->field_location->set(0, $event['location']);
      }
      $node->save();
    }
  }

  /**
   * {@inheritdoc}
   *
   * This function does some crude cleanup of the html YourMembership's
   * functions return. The str_replace on \n\n\n\n\n is simply because, after
   * stripping html, a ton of new lines are left at the beginning and end of
   * these strings. We do want to keep other new lines so that CKEditor's auto-
   * paragraph function operates and the text looks reasonably presentable.
   */
  private function cleanupYourMembershipHTML($html) {
    return str_replace("\n\n\n\n\n", "", strip_tags($html));
  }

  /**
   * Sync events from Your Membership into nodes.
   *
   * @param int $timestamp
   *  Unix timestamp to start event sync from.
   */
  public function syncEvents($timestamp = NULL) {
    if (!$timestamp) {
      $timestamp = $this->state->get(self::LAST_SYNC_STATE_KEY, strtotime('1/1/2017'));
    }

    $events = $this->getEvents($timestamp);
    $eventCount = 0;
    if (empty($events)) {
      return $eventCount;
    }

    $this->createOrUpdateEvents($events);
    return $eventCount;
  }

  /**
   * Obtain array of events from Netforum.
   *
   * @param int $timestamp
   *  Unix timestamp to start event sync from.
   */
  public function getEvents($timestamp = NULL) {
    $processedEvents = [];
    $events = [];
    if (!$timestamp) {
      $timestamp = $this->state->get(self::LAST_SYNC_STATE_KEY, strtotime('1/1/2017'));
    }

    // Get an array of events from Your Membership.
    $events = $this->ymApiUtils->getEvents();
    $plugins = ['googlemaps' => 'googlemaps'];

    // Bail if we found no events.
    if (empty($events)) {
      return $processedEvents;
    }

    foreach ($events as $eventId => $event) {
      // Don't process events that haven't changed since the last sync.
      if (strtotime($event['DateCached']) < $timestamp) {
        continue;
      }

      // Clean up markup from $event['Location'].
      if (!empty($event['Location'])) {
        $location = $this->cleanupYourMembershipHTML($event['Location']);
      }
      // Attempt to geocode the location string.
      $geocoded = $this->geocoder->geocode($location, $plugins, []);
      if ($geocoded) {
        $geocoded_location = $geocoded->first();
        $address = $geocoded_location->getFormattedAddress();
        $addressBits = explode(',', $address);
        $location = [
          'address_line1' => !empty($addressBits[0]) ? $addressBits[0] : '',
          'locality' => !empty($addressBits[1]) ? trim($addressBits[1]) : '',
          'postal_code' => !empty($addressBits[2]) ? substr(trim($addressBits[2]), strpos(trim($addressBits[2]), " ") + 1) : '',
          'country_code' => !empty($addressBits[3]) ? 'US' : '',
          'administrative_area' => !empty($addressBits[2]) ? substr(trim($addressBits[2]), 0, strpos(trim($addressBits[2]), " ")) : '',
        ];
      } else {
        $location = [];
      }

      // Process information from the $event['Description'] field.
      if (!empty($event['Description'])) {
        $description = $this->cleanupYourMembershipHTML($event['Description']);
      } else {
        $description = '';
      }

      // Process information from the $event['Categories'] field.
      if (!empty($event['Categories'])) {
        // Get the YM event category list.
        $event_categories = $this->getEventCategories();
        // Process the event categories.
        $tids = $this->loadOrCreateEventTermsByName($event_categories);
        $event['EventCategories'] = $tids;
      } else {
        $event['EventCategories'] = [];
      }

      // Add the event to $processedEvents.
      $processedEvents[$eventId] = [
        'name' => $event['Name'],
        'start_date' => $event['StartDate'],
        'end_date' => $event['EndDate'],
        'start_time' => $event['StartTime'],
        'end_time' => $event['EndTime'],
        'event_category' => $tids,
        'description' => $description,
        'location' => $location,
      ];
    }

    return $processedEvents;
  }

  /**
   * {@inheritdoc}
   */
  public function getEventCategories() {
    $ym_api_utils = $this->ymApiUtils;
    $event_categories = [];
    $response = $ym_api_utils->getEventCategories();
    if (!empty($event_categories['EventCategoryList'])) {
      $event_categories = $response['EventCategoryList'];
    }
    return $event_categories;
  }

}
