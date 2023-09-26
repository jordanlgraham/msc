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
class PruneOrgsForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an PruneOrgsForm object.
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
      '#description' => $this->t('Leave blank to sync until now.')
    ];
    $form['sync_all'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Prune All Organizations'),
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
    // Load all published nodes of type 'facility'.
    $nids = \Drupal::entityQuery('node')
      ->condition('type', 'facility')
      ->condition('status', 1)
      ->execute();
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($nids);

    // Create a batch that loops through $nodes and creates an operation using
    // this class' 'pruneOrgsBatch' method.
    $operations = [];
    foreach ($nodes as $node) {
      $operations[] = [self::class . '::pruneOrgsBatch', [$node]];
    }
    $batch = [
      'title' => $this->t('Prune organizations'),
      'operations' => $operations,
      'finished' => 'netforum_prune_orgs_finished',
    ];

    try {
      batch_set($batch);
    }
    catch (\Exception $exception) {
      $message = 'Unable to complete organization sync. See logs for error.';
      $this->messenger->addError($this->t($message));
      $form_state->setRebuild(TRUE);
    }
  }



  /**
   * {@inheritdoc}
   */
  public function pruneOrgsBatch(&$context) {
    /** @var OrgSync $sync */
    $sync = \Drupal::service('netforum_org_sync.org_sync');
    try {
      $sync->pruneOrganizations($context['sandbox']['node']);
    }
    catch (\Exception $exception) {
      $msg = $exception->getMessage();
      $context['results']['errors']['orgs'][] = "Error pruning organization {$context['sandbox']['node']->id()}: $msg";
      return TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
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
      // Use usort to sort the $org array by 'cst_name_cp'.
      usort($orgs, [self::class, 'compareByCstNameCp']);
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
