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
   * Creates a meeting in Zoom.
   *
   * @param object $entity
   *   A webinar node.
   *
   * @return mixed
   *   Zoom Meeting ID or False.
   */
  public function createZoomMeeting(NodeInterface $entity) {
    $owner = $entity->field_zoom_webinar_owner->getString();
    $timezone = $this->configFactory->get('system.date')->get('timezone.default');

    // Default webinar values.
    $meetingValues = [
      'json' => [
        'topic' => $entity->getTitle(),
        'type' => 2,
        'start_time' => $entity->field_date->value . 'Z',
        'duration' => '60',
        'timezone' => $timezone,
        'password' => $this->keyRepository->getKey('zoom_webinar_pw')->getKeyValue(),
        'agenda' => $this->cleanTeaser($entity->get('field_teaser')->value),
        'recurrence' => [
          'type' => 1,
          'repeat_interval' => 1,
          'end_date_time' => $entity->field_date->end_value . 'Z',
        ],
        'settings' => [
          'mute_upon_entry' => 'true',
          'approval_type' => 0,
          'registration_type' => 1,
          'audio' => 'both',
          'auto_recording' => 'cloud',
          'close_registration' => 'true',
          'contact_name' => 'The NCEO',
          'contact_email' => $owner,
          'registrants_email_notification' => 'true',
        ],
      ]
    ];

    // Send webinar to Zoom.
    try {
      $zoomRequest = $this->ymApiClient->post('/users/' . $owner . '/meetings', $meetingValues);
      $this->messenger->addStatus(t('This meeting was successfully created in Zoom.'));
    } catch (RequestException $exception) {
      $this->messenger->addWarning(t('This meeting could not be created in Zoom.'));
      return FALSE;
    }

    // Do not keep going.
    if (empty($zoomRequest['id'])) {
      $this->messenger->addWarning(t('This meeting could not be created in Zoom.'));
      return FALSE;
    }

    return $zoomRequest['id'];
  }

  /**
   * Creates a webinar in Zoom.
   *
   * @param object $entity
   *   A webinar node.
   *
   * @return mixed
   *   Zoom Webinar ID or False.
   */
  public function createZoomWebinar(NodeInterface $entity) {
    $timezone = $this->configFactory->get('system.date')->get('timezone.default');
    // Default webinar values.
    $webinarValues = [
      'json' => [
        'topic' => $entity->getTitle(),
        'type' => 5,
        'start_time' => $entity->field_date->value . 'Z',
        'duration' => '60',
        'timezone' => $timezone,
        'password' => $this->keyRepository->getKey('zoom_webinar_pw')->getKeyValue(),
        'agenda' => $this->cleanTeaser($entity->get('field_teaser')->value),
        'recurrence' => [
          'type' => 1,
          'repeat_interval' => 1,
          'end_date_time' => $entity->field_date->end_value . 'Z',
        ],
        'settings' => [
          'host_video' => 'false',
          'panelists_video' => 'false',
          'practice_session' => 'true',
          'hd_video' => 'true',
          'approval_type' => 0,
          'registration_type' => 1,
          'audio' => 'both',
          'auto_recording' => 'cloud',
          'enforce_login' => 'false',
          'enforce_login_domains' => '',
          'alternative_hosts' => '',
          'close_registration' => 'true',
          'show_share_button' => 'false',
          'allow_multiple_devices' => 'false',
        ],
      ]
    ];
    $owner = $entity->field_zoom_webinar_owner->getString();

    // Send webinar to Zoom.
    try {
      $zoomRequest = $this->ymApiClient->post('/users/' . $owner . '/webinars', $webinarValues);
      $this->messenger->addStatus(t('This webinar was successfully created in Zoom.'));
    } catch (RequestException $exception) {
      $this->messenger->addWarning(t('This webinar could not be created in Zoom.'));
      return FALSE;
    }

    // Do not keep going.
    if (empty($zoomRequest['id'])) {
      $this->messenger->addWarning(t('This webinar could not be created in Zoom.'));
      return FALSE;
    }

    return $zoomRequest['id'];
  }

  /**
   * Updates Registrant Questions in Zoom.
   *
   * @param int $zoomWebinarId
   *   A zoom webinar id.
   *
   * @return bool
   *   True if success. False if not.
   */
  public function updateWebinarQuestions($zoomWebinarId) {
    // Add registration questions to the webinar in zoom.
    $questions = [
      'json' => [
        'questions' => [
          [
            'field_name' => 'last_name',
            'required' => 1,
          ],
          [
            'field_name' => 'org',
            'required' => 0,
          ],
        ],
        'custom_questions' => [
          [
            'title' => 'Would you like CE Credit for today\'s webinar?',
            'type' => 'multiple',
            'required' => 0,
            'answers' => [
              0 => 'CEP',
              1 => 'SHRM',
              2 => 'Generic',
              3 => 'IRS',
            ],
          ],
          [
            'title' => 'If IRS, Please enter a PTIN',
            'type' => 'short',
            'required' => 0,
          ],
        ],
      ]
    ];

    try {
      $this->ymApiClient->patch('patch', '/webinars/' . $zoomWebinarId . '/registrants/questions', $questions);
      return TRUE;
    } catch (RequestException $exception) {
      return FALSE;
    }
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
}
