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
    $member_types = $ym_api_utils->getMemberTypes();

    // Build an html table of member types with fields ID, TypeCode, Name, IsDefault, Visibility, PresetType and SortOrder.
    $member_types_table = [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Type Code'),
        $this->t('Name'),
        $this->t('Is Default'),
        $this->t('Visibility'),
        $this->t('Preset Type'),
        $this->t('Sort Order'),
      ],
    ];
    foreach ($member_types as $row) {
      $member_types_table['rows'][] = [
        'id' => ['data' => $row['ID']],
        'type_code' => ['data' => $row['TypeCode']],
        'name' => ['data' => $row['Name']],
        'is_default' => ['data' => $row['IsDefault']],
        'visibility' => ['data' => $row['Visibility']],
        'preset_type' => ['data' => $row['PresetType']],
        'sort_order' => ['data' => $row['SortOrder']],
      ];
    }
    // Render the table directly
    $build['member_types'] = [
      '#type' => 'table',
      '#caption' => $this->t('Member Types Table'),
      '#header' => $member_types_table['#header'],
      '#rows' => $member_types_table['rows'],
    ];

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('Yo quiero tacos'),
    ];

    return $build;
  }

}
