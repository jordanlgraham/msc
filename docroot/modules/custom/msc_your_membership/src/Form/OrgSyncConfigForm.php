<?php

namespace Drupal\msc_your_membership\Form;

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
   * Constructs an OrgSyncForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager, OrgSync $orgSync) {
    $this->entityTypeManager = $entityTypeManager;
    $this->orgSync = $orgSync;
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

    $form['org_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Organization Type(s)'),
      '#description' => $this->t('A list of Organization Types to search for in the GetOrganizationByType API call (i.e. Assisted Living)'),
      '#multiple' => TRUE,
      '#options' => $facilityTypes,
    ];
    $form['start_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Sync Start Date'),
    ];
    $form['end_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Sync End Date'),
      '#description' => $this->t('Leave blank to sync until now.')
    ];
    $form['sync_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sync Everything'),
      '#description' => $this->t('Ignore Dates Above and Completely Re-sync'),
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
    // Set error if 'Sync Everything' checkbox is empty and start and end dates
    // are the same.
    $values = $form_state->getValues();
    if (empty($values['org_types'])) {
      $form_state->setErrorByName('org_types', $this->t('No org types have been selected.'));
    }

    // Return error if no organization types have been selected.
    if (empty($values['org_types']) && !$values['sync_all']) {
      $form_state->setErrorByName('org_types', $this->t('Please select some org type(s) or check the \'Sync Everything\' box.'));
    }

    // If $form_state has sync_all == 1, don't validate start and end dates.
    if ($values['sync_all']) {
      return;
    }

    // Handle errors relating to the start and end dates.
    switch ($values['start_date'] == $values['end_date']) {
      case TRUE:
        $form_state->setErrorByName('end_date', $this->t('Start and end dates need to have different values.'));
        break;

      default:
        $time = [];
        foreach (['start_date', 'end_date'] as $endpoint) {
          $time[] = strtotime($values[$endpoint]);
        }
        if ($time[1] && $time[1] < $time[0]) {
          $form_state->setErrorByName('end_date', $this->t('End date must be after start date.'));
        }
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

    // By default, set both endpoints to current timestamp.
    $start_date = $end_date = time();
    if (!empty($form_state->getValue('start_date'))) {
      $start_date = strtotime($form_state->getValue('start_date'));
    }
    if (!empty($form_state->getValue('end_date'))) {
      $end_date = strtotime($form_state->getValue('end_date'));
    }

    $org_type_tids = $form_state->getValue('org_types');
    $types = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($org_type_tids);
    foreach ($terms as $tid => $term) {
      $types[$tid] = $term->label();
    }
    $start = new \DateTime();
    $start->setTimestamp($start_date);
    $start->setTime('0', '0', '0');
    $end = new \DateTime();
    $end->setTimestamp($end_date);
    $end->setTime('23', '59', '59');
    $diff = $start->diff($end);
    if ($diff->m === 0) {
      $operations[] = [self::class . '::importOrgsBatch', [$types, $start->getTimestamp(), $end->getTimestamp()]];
    } else {
      // For each month difference, add an operation.
      for ($i = 0; $i < $diff->m; $i++) {
        $operations[] = [self::class . '::importOrgsBatch', [$types, $start->getTimeStamp(), $start->modify('+1 month')->getTimestamp()]];
      }
      // If there are extra days, fill in the remainder of the days in one last op.
      // Otherwise, fill in the last day (the object will be set to 12AM that morning).
      $operations[] = [self::class . '::importOrgsBatch', [$types, $start->getTimestamp(), $end->getTimestamp()]];
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
  public static function importOrgsBatch($facilityTypes, $start_date, $end_date, &$context) {
    // @todo: Inject the msc_your_membership.org_sync service.
    /** @var OrgSync $sync */
    $sync = \Drupal::service('msc_your_membership.org_sync');
    $start_formatted = date(OrgSync::DATE_FORMAT, $start_date);
    $end_formatted = date(OrgSync::DATE_FORMAT, $end_date);
    $context['message'] = Html::escape("Syncing $start_formatted to $end_formatted");
    try {
      $orgs = $sync->getOrganizationChanges($start_date, $end_date);
      if (empty($orgs)) {
        return TRUE;
      }
      // Use usort to sort the $org array by 'cst_name_cp'.
      usort($orgs, [self::class, 'compareByCstNameCp']);
    } catch (\Exception $exception) {
      $msg = $exception->getMessage();
      $context['results']['errors']['orgs'][] = "Error retrieving organization changes for period $start_formatted to $end_formatted: $msg";
      return TRUE;
    }
    $sandbox_key = $start_date . $end_date;
    if (!isset($context['sandbox'][$sandbox_key]['pointer'])) {
      $context['sandbox'][$sandbox_key]['pointer'] = 0;
      $context['sandbox'][$sandbox_key]['count'] = count($orgs);
    }
    // Process the organizations 50 at a time.
    $start = $context['sandbox'][$sandbox_key]['pointer'];
    $end = $context['sandbox'][$sandbox_key]['pointer'] + 50;
    for ($i = $start; $i < $end; $i++) {
      if (!isset($orgs[$i])) {
        break;
      }
      $organization = $orgs[$i];
      try {
        $node = $sync->syncOrganization($organization, $facilityTypes);
        if (is_object($node)) {
          $context['results']['success'][] = $node->id();
        }
      } catch (\Exception $exception) {
        $context['results']['errors']['sync'][] = "Error syncing organization {$organization['org_cst_key']}: " .
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

  /**
   * Custom comparison function to sort by 'cst_name_cp'.
   *
   * @param array $a
   * @param array $b
   * @return int
   */
  public static function compareByCstNameCp($a, $b) {
    return strcmp($a['cst_name_cp'], $b['cst_name_cp']);
  }

}
