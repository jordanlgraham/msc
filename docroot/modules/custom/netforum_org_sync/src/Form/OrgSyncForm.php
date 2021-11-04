<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\Html;
use Drupal\netforum_org_sync\OrgSync;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form to initiate Netforum organization sync.
 */
class OrgSyncForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an OrgSyncForm object.
   * 
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'org_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    
    $form['item'] = [
      '#type' => 'item',
      '#markup' => '<h2>New form</h2>'
    ];

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
    ];
    $form['sync_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Sync Everything'),
      '#description' => $this->t('Ignore Dates Above and Completely Re-sync'),
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Go',
    ];

    return $form;

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
    }
    catch (\Exception $exception) {
      $message = 'Unable to complete organization sync. See logs for error.';
      $this->messenger->addError($this->t($message));
      $form_state->setRebuild(TRUE);
    }
  }

  public function getFacilityTypes() {
    $term_data = [];
    $vid = 'facility_type';
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadTree($vid);
    foreach ($terms as $term) {
      $term_data[$term->tid] =  $term->name;
    }
    return $term_data;
  }

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
      'finished' => 'netforum_org_sync_finished',
    ];

    return $batch;
  }

  public function generateBatchByDate($form_state) {
    $operations = [];
    $start_date = false;
    $end_date = false;

    if(!empty($form_state->getValue('start_date'))
      && !empty($form_state->getValue('end_date'))){
      $start_date = strtotime($form_state->getValue('start_date'));
      $end_date = strtotime($form_state->getValue('end_date'));
    }

    $types = $this->sync->typesToSync();
    $start = new \DateTime();
    $start->setTimestamp($start_date);
    $start->setTime('0', '0', '0');
    $end = new \DateTime();
    $end->setTimestamp($end_date);
    $end->setTime('23', '59', '59');
    $diff = $start->diff($end);
    if ($diff->m === 0) {
      $operations[] = [self::class . '::importOrgsBatch', [$types, $start->getTimestamp(), $end->getTimestamp()]];
    }
    else {
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
      'finished' => 'netforum_org_sync_finished',
    ];
  }

  public static function importOrgsBatch($facilityTypes, $start_date, $end_date, &$context) {
    /** @var OrgSync $sync */
    $sync = \Drupal::service('netforum_org_sync.org_sync');
    $start_formatted = date(OrgSync::DATE_FORMAT, $start_date);
    $end_formatted = date(OrgSync::DATE_FORMAT, $end_date);
    $context['message'] = Html::escape("Syncing $start_formatted to $end_formatted");
    try {
      $orgs = $sync->getOrganizationChanges($start_date, $end_date);
      if (empty($orgs)) {
        return TRUE;
      }
    }
    catch (\Exception $exception) {
      $msg = $exception->getMessage();
      $context['results']['errors']['orgs'][] = "Error retrieving organization changes for period $start_formatted to $end_formatted: $msg";
      return TRUE;
    }
    $sandbox_key = $start_date.$end_date;
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
      }
      catch (\Exception $exception) {
        $context['results']['errors']['sync'][] = "Error syncing organization {$organization['org_cst_key']}: " .
          $exception->getMessage();
      }
      $context['sandbox'][$sandbox_key]['pointer']++;
    }

    if ($context['sandbox'][$sandbox_key]['pointer'] === $context['sandbox'][$sandbox_key]['count']) {
      $context['finished'] = 1;
      unset($context['sandbox'][$sandbox_key]);
    }
    else {
      $context['message'] = $context['message'] . ": Completed {$context['sandbox'][$sandbox_key]['pointer']} of {$context['sandbox'][$sandbox_key]['count']}";
      $context['finished'] = $context['sandbox'][$sandbox_key]['pointer']/$context['sandbox'][$sandbox_key]['count'];
    }
  }

}
