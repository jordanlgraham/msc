<?php

namespace Drupal\msc_your_membership\Controller;

use Drupal\key\KeyRepositoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\msc_your_membership\YmApiUtils;
use Http\Client\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for MSC Your Membership routes.
 */
class MscYourMembershipController extends ControllerBase {

  const CLIENT_ID = 131001;
  const BASE_URL = 'https://api.yourmembership.com';
  const BASE_PATH = '/Ams';

  /**
   * The Key Repository service.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Constructs a new MscYourMembershipController object.
   *
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   The Key Repository service.
   */
  public function __construct(KeyRepositoryInterface $key_repository) {
    $this->keyRepository = $key_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('key.repository')
    );
  }

  /**
   * Builds the response.
   */
  public function build() {
    $ym_api_utils = \Drupal::service('msc_your_membership.ymapi_utils');
    $event_categories = [];
    $response = $ym_api_utils->getEventCategories();
    if (!empty($event_categories['EventCategoryList'])) {
      $event_categories = $response['EventCategoryList'];
      // Process the event categories.
      $tids = $ym_api_utils->loadOrCreateEventTermsByName($event_categories);
    }
    // Render the event categories array as a table.
    $build['event_categories'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Event ID'),
        $this->t('Event Name'),
        $this->t('Event Start Date'),
        $this->t('Event End Date'),
      ],
      '#rows' => $tids,
    ];

    // $client = \Drupal::service('ymapi.client');

    // try {
    //   $client->get('Events', [
    //     'parameters' => [
    //       'PageNumber' => 1,
    //       'PageSize' => 100,
    //     ]
    //   ]);
    //   \Drupal::messenger()->addStatus(t('Success'));
    // } catch (RequestException $exception) {
    //   // YM api already logs errors, but you could log more.
    //   \Drupal::messenger()->addWarning(t('Something hasn\'t worked.'));
    // }

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Yo quiero tacos'),
    ];

    return $build;
  }

}
