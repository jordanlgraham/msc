<?php

namespace Drupal\msc_your_membership\Form;

use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\msc_your_membership\OrgSync;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure MSC Your Membership settings for this site.
 */
class OrgSyncConfigForm extends ConfigFormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Undocumented variable
   *
   * @var \Drupal\msc_your_membership\OrgSync
   */
  protected $orgSync;

  /**
   * Undocumented variable
   *
   * @var \Drupal\msc_your_membership\YmApiUtils
   */
  protected $ymApiUtils;

  /**
   * Constructs an OrgSyncForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, OrgSync $orgSync) {
    $this->entityTypeManager = $entityTypeManager;
    $this->orgSync = $orgSync;
    $this->ymApiUtils = \Drupal::service('msc_your_membership.ymapi_utils');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('msc_your_membership.org_sync')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'msc_your_membership_org_sync_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['msc_your_membership.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFacilityTypes() {
    $term_data = [];
    $vid = 'facility_type';
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $term_data[$term->tid] =  $term->name;
    }
    return $term_data;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $facilityTypes = $this->getFacilityTypes();

    $form['sync_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sync all the things'),
      '#description' => $this->t('Perform a complete sync of all organizations in YM.'),
    ];

    $form['org_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Organization Type(s)'),
      '#description' => $this->t('A list of Organization Types to search for in the GetOrganizationByType API call (i.e. Assisted Living)'),
      '#multiple' => TRUE,
      '#options' => $facilityTypes,
      '#states' => [
        'invisible' => [
          ':input[name="sync_all"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="sync_all"]' => ['checked' => FALSE],
        ],
      ],
    ];
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Sync only changes since this date'),
      '#states' => [
        'invisible' => [
          ':input[name="sync_all"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="sync_all"]' => ['checked' => FALSE],
        ],
      ],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Go'),
        '#button_type' => 'primary',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    // If $form_state has sync_all == 1, don't validate the other fields.
    if ($values['sync_all']) {
      return;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Sync all option overrides other settings.
    $syncAll = !empty($form_state->getValue('sync_all')) && $form_state->getValue('sync_all') == 1;

    switch ($syncAll) {
      case TRUE:
        $batch = $this->generateSyncAllBatch();
        break;

      default:
        $batch = $this->generateBatchByDate($form_state);
    }

    try {
      batch_set($batch);
    } catch (\Exception $exception) {
      $message = 'Unable to complete organization sync. See logs for error.';
      $this->messenger->addError($this->t($message));
      $form_state->setRebuild(TRUE);
    }
  }
  /**
   * {@inheritdoc}
   */
  public function generateSyncAllBatch() {
    $operations = [];

    // Get all facility type term names.
    $typesArray = $this->getFacilityTypes();
    $types = array_values($typesArray);

    $start = new \DateTime('2008-01-01');
    $end = new \DateTime('12AM tomorrow');
    // $end = new \DateTime('2010-12-31');
    $interval = \DateInterval::createFromDateString('1 month');
    $period = new \DatePeriod($start, $interval, $end);
    /** @var \DateTime $dt */
    foreach ($period as $dt) {
      $operations[] = [self::class . '::importOrgsBatch', [$types, $dt->getTimeStamp(), $dt->modify('+1 month')->getTimestamp()]];
    }
    $batch = [
      'title' => $this->t('Sync organizations'),
      'operations' => $operations,
      'finished' => 'msc_your_membership_sync_finished',
    ];

    return $batch;
  }

  /**
   * {@inheritdoc}
   */
  public function generateBatchByDate($form_state) {
    $operations = [];
    // Develop an array of facility type term names.
    $org_type_tids = $form_state->getValue('org_types');
    foreach ($org_type_tids as $tid) {
      $term = Term::load($tid);
      if ($term) {
        $facilityTypes[] = $term->get('field_member_type_code')->value;
      }
    }

    // Set $startDate to now by default.
    $startDate = time();
    if (!empty($form_state->getValue('start_date'))) {
      $startDate = strtotime($form_state->getValue('start_date'));
    }
    // Format $startDate with ISO 8601 format.
    $startDate = date('c', $startDate);

    // Get an array of ProfileIDs from YM since $startDate.
    $profileIds = $this->orgSync->getProfileIdsSince($startDate);
    $operations[] = [self::class . '::processProfilesBatch', [$profileIds, $facilityTypes, $startDate]];


    $batch = [
      'title' => $this->t('Sync organizations'),
      'operations' => $operations,
      'finished' => 'msc_your_membership_sync_finished',
    ];

    return $batch;
  }

  /**
   * {@inheritdoc}
   */
  public static function processProfilesBatch(array $profileIds, array $facilityTypes, $startDate, &$context) {
    // @todo: Inject the msc_your_membership.org_sync service.
    /** @var OrgSync $sync */
    $sync = \Drupal::service('msc_your_membership.org_sync');
    $context['message'] = Html::escape("Syncing changes since $startDate.");

    $sandbox_key = $startDate;
    if (!isset($context['sandbox'][$sandbox_key]['pointer'])) {
      // Set the pointer to the index of the first element of $profileIds.
      $context['sandbox'][$sandbox_key]['pointer'] = array_key_first($profileIds);
      $context['sandbox'][$sandbox_key]['count'] = count($profileIds);
    }
    // Process the organizations in batches.
    $batchSize = 50;
    $start = $context['sandbox'][$sandbox_key]['pointer'];
    $end = $context['sandbox'][$sandbox_key]['pointer'] + $batchSize;
    for ($i = $start; $i < $end; $i++) {
      if (!isset($profileIds[$i])) {
        break;
      }
      // @todo: uncomment next line and remove line setting static profileId.
      // $profileId = $profileIds[$i];
      $profileId = 74666099;
      try {
        // Only process if this profile is in $facilityTypes.
        try {
         // Get the member profile for $profileId.
         $profile = \Drupal::service('msc_your_membership.ymapi_utils')->getMemberProfile($profileId);
         if (empty($profile)) {
           return TRUE;
         }
         // Only process $profile if in_array($org['MemberTypeCode'], $facilityTypes).
         if (!in_array($profile['MemberAccountInfo']['MemberTypeCode'], $facilityTypes)) {
           return TRUE;
         }
        } catch (\Exception $exception) {
         $msg = $exception->getMessage();
         $context['results']['errors']['orgs'][] = "Error retrieving organization changes for period beginning at $startDate: $msg";
         return TRUE;
        }
        $node = $sync->syncOrganization($profile);
        if (is_object($node)) {
          $context['results']['success'][] = $node->id();
        }
      } catch (\Exception $exception) {
        $context['results']['errors']['sync'][] = "Error syncing organization with profile ID {$profileId}.";
        $exception->getMessage();
      }
      $context['sandbox'][$sandbox_key]['pointer']++;
    }

    if ($context['sandbox'][$sandbox_key]['pointer'] === $context['sandbox'][$sandbox_key]['count']) {
      $context['finished'] = 1;
      unset($context['sandbox'][$sandbox_key]);
    } else {
      $context['message'] = $context['message'] . ": Completed {$context['sandbox'][$sandbox_key]['pointer']} of {$context['sandbox'][$sandbox_key]['count']}";
      $context['finished'] = $context['sandbox'][$sandbox_key]['pointer'] / $context['sandbox'][$sandbox_key]['count'];
    }
  }

}
