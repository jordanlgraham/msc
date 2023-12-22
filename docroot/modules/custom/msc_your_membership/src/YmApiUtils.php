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
  public function getMembersInfo() {
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
}
