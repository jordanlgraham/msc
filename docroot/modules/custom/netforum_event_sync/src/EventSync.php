<?php

namespace Drupal\netforum_event_sync;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\netforum_soap\GetClient;
use \Exception;
use DateTime;

class EventSync {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $term_storage;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

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

  const LAST_SYNC_STATE_KEY = 'netforum_event_sync.last_sync';

  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory,
                              GetClient $getClient, LoggerInterface $logger, DateFormatterInterface $dateFormatter,
                              StateInterface $state) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->term_storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->config = $configFactory->get('netforum_event_sync.eventsync');
    $this->logger = $logger;
    $this->get_client = $getClient;
    $this->dateFormatter = $dateFormatter;
    $this->state = $state;
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
  //
  private function formatNetForumDateTime($date, $time) {
    $raw_date = new DateTime($date);
    $timestamp = $raw_date->format('Y-m-d') . ' ' . $time;
    return date('Y-m-d\TH:i:s', strtotime($timestamp));
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

  private function createOrUpdateEvents(array $events) {
    foreach($events as $evt_key => $event) {
      $node = $this->loadOrCreateEventNode($evt_key);
      //todo: body field
      $node->body->value = $event['description']; //formatted text
      $node->title = $event['name'];
      $node->field_date = $this->formatNetForumDateTime($event['start_date'], $event['start_time']); //date w/time 2017-08-15T18:00:00
      $node->field_end_date = $this->formatNetForumDateTime($event['end_date'], $event['end_time']); //date w/time 2017-08-15T18:00:00
      $node->field_event_category = $this->loadOrCreateEventTermsByName(array($event['event_category'])); //taxonomy
      $node->field_event_key = $evt_key; //text
      $node->field_event_location = $event['location'];
      $node->status = 1;
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
    //get stored event types
    $event_types = explode("\n", str_replace("\r", "", $this->config->get('event_types')));
    $netforum_service = $this->get_client;
    $response_headers = $netforum_service->getResponseHeaders();
    $client = $netforum_service->getClient();
    //store all the customer keys from the GetOrganizationByType calls
    if(!empty($event_types)) {
      //Build an array of events keyed by Event Key <evt_key> so we can
      //save or update them.
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
            //Let's make things easy on ourselves and turn this XML into an array.
            $xml = simplexml_load_string($response->GetEventListByTypeResult->any);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);
            if(!empty($array['Result'])) {
              foreach ($array['Result'] as $result) {
                if (!empty($result['evt_key'])) {
                  if(!empty($result['evt_location_html']) && is_string($result['evt_location_html'])) {
                    $location = $this->cleanupNetForumHTML($result['evt_location_html']);
                  } else {
                    $location = '';
                  }
                  if(!empty($result['prd_description_html'])) {
                    $description = $this->cleanupNetForumHTML($result['prd_description_html']);
                  } else {
                    $description = '';
                  }
                  $events[(string) $result['evt_key']] = [
                    'name' => (string) $result['prd_name'],
                    'location' => $location,
                    'start_date' => (string) $result['evt_start_date'],
                    'end_date' => (string) $result['evt_end_date'],
                    'start_time' => (string) $result['evt_start_time'],
                    'end_time' => (string) $result['evt_end_time'],
                    'event_category' => (string) $result['etp_code'],
                    'description' => $description,
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

}
