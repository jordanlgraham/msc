<?php

namespace Drupal\msc_your_membership\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\State\StateInterface;
use Drupal\geocoder\GeocoderInterface;
use Drupal\msc_your_membership\YmApiUtils;
use Drupal\ymapi\Plugin\ApiTools\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for MSC Your Membership routes.
 */
class GetProfileController extends ControllerBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The date formatter.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * The ymapi.client service.
   *
   * @var \Drupal\ymapi\Plugin\ApiTools\Client
   */
  protected $client;

  /**
   * The logger.channel.msc_your_membership service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $mscYourMembership;

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * The geocoder service.
   *
   * @var \Drupal\geocoder\GeocoderInterface
   */
  protected $geocoder;

  /**
   * The msc_your_membership.ymapi_utils service.
   *
   * @var \Drupal\msc_your_membership\YmApiUtils
   */
  protected $ymapiUtils;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter.
   * @param \Drupal\ymapi\Plugin\ApiTools\Client $client
   *   The ymapi.client service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $msc_your_membership
   *   The logger.channel.msc_your_membership service.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   * @param \Drupal\geocoder\GeocoderInterface $geocoder
   *   The geocoder service.
   * @param \Drupal\msc_your_membership\YmApiUtils $ymapi_utils
   *   The msc_your_membership.ymapi_utils service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DateFormatterInterface $date_formatter, Client $client, LoggerChannelInterface $msc_your_membership, StateInterface $state, GeocoderInterface $geocoder, YmApiUtils $ymapi_utils) {
    $this->entityTypeManager = $entity_type_manager;
    $this->dateFormatter = $date_formatter;
    $this->client = $client;
    $this->mscYourMembership = $msc_your_membership;
    $this->state = $state;
    $this->geocoder = $geocoder;
    $this->ymapiUtils = $ymapi_utils;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('date.formatter'),
      $container->get('ymapi.client'),
      $container->get('logger.channel.msc_your_membership'),
      $container->get('state'),
      $container->get('geocoder'),
      $container->get('msc_your_membership.ymapi_utils')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    $ym_api_utils = \Drupal::service('msc_your_membership.ymapi_utils');
    // Get $profileId from the url parameter.
    $profileId = \Drupal::routeMatch()->getParameter('id');
    /* @var array $profile */
    $profile = $ym_api_utils->getMemberProfile($profileId);
    // Build an html table of the information in the profile.
    $profile_table = [
      '#type' => 'table',
      '#header' => [
        $this->t('Field'),
        $this->t('Value'),
      ],
    ];

    foreach ($profile as $field => $value) {
      $this->addProfileData($profile_table, $field, $value);
    }

    // Render the table directly
    $build['profile'] = [
      '#type' => 'item',
      '#markup' => $this->t('Profile:'),
    ];
    $build['profile_table'] = [
      '#type' => 'table',
      '#caption' => $this->t('Profile Table'),
      '#header' => $profile_table['#header'],
      '#rows' => $profile_table,
    ];

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

  function addProfileData(&$profile_table, $field, $value) {
    if (is_array($value)) {
      foreach ($value as $key => $subValue) {
        $this->addProfileData($profile_table, $key, $subValue);
      }
    } else {
      $profile_table[] = [
        $field,
        $value,
      ];
    }
  }

}
