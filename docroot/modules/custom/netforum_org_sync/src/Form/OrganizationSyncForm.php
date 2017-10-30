<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Drupal\netforum_org_sync\OrgSync;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Class OrganizationSyncForm.
 *
 * @package Drupal\netforum_org_sync\Form
 */

class OrganizationSyncForm extends ConfigFormBase {

  /**
   * @var \Drupal\netforum_org_sync\OrgSync
   */
  protected $sync;

  protected $state;

  protected $time;
  /**
   * @var LoggerInterface
   */
  private $logger;

  public function __construct(ConfigFactoryInterface $config_factory, OrgSync $orgSync,
                              StateInterface $state, TimeInterface $time, LoggerInterface $logger) {
    $this->sync = $orgSync;
    $this->state = $state;
    $this->time = $time;
    $this->logger = $logger;
    parent::__construct($config_factory);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('netforum_org_sync.org_sync'),
      $container->get('state'),
      $container->get('datetime.time'),
      $container->get('logger.factory')->get('netforum_org_sync')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'netforum_org_sync.organizationsync',
    ];
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
    $config = $this->config('netforum_org_sync.organizationsync');
    $form['org_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Organization Type(s)'),
      '#description' => $this->t('A list of Organization Types to search for in the GetOrganizationByType API call (i.e. Assisted Living)'),
      '#default_value' => $config->get('org_types'),
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
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('netforum_org_sync.organizationsync')
      ->set('org_types', $form_state->getValue('org_types'))
      ->save();
    //if we sync all, we want to start cycling
    if(!empty($form_state->getValue('sync_all')) && $form_state->getValue('sync_all') == 1) {
      return $this->syncAll();
    }
    $start_date = false;
    $end_date = false;
    if(!empty($form_state->getValue('start_date'))
      && !empty($form_state->getValue('end_date'))){
      $start_date = strtotime($form_state->getValue('start_date'));
      $end_date = strtotime($form_state->getValue('end_date'));
    }
    try {
      $this->syncByDate($start_date, $end_date);
    }
    catch (\Exception $exception) {
      drupal_set_message($this->t('Unable to complete organization sync. See logs for error.'), 'error');
      $form_state->setRebuild(TRUE);
    }
  }

  /**
   * Set a batch to sync all dates.
   */
  protected function syncAll() {
    $ops = [];
    $types = $this->sync->typesToSync();
    $start = new \DateTime('2008-01-01');
    $end = new \DateTime('12AM tomorrow');
    $interval = \DateInterval::createFromDateString('1 month');
    $period = new \DatePeriod($start, $interval, $end);
    /** @var \DateTime $dt */
    foreach ($period as $dt) {
      $ops[] = [self::class . '::importOrgsBatch', [$types, $dt->getTimeStamp(), $dt->modify('+1 month')->getTimestamp()]];
    }
    $batch = [
      'title' => $this->t('Sync organizations'),
      'finished' => self::class . '::importFinished',
      'operations' => $ops,
    ];
    batch_set($batch);
  }

  /**
   * Set a batch for syncing between two dates.
   *
   * @param $start_date
   * @param $end_date
   */
  protected function syncByDate($start_date, $end_date) {
    $ops = [];
    $types = $this->sync->typesToSync();
    $start = new \DateTime();
    $start->setTimestamp($start_date);
    $start->setTime('0', '0', '0');
    $end = new \DateTime();
    $end->setTimestamp($end_date);
    $end->setTime('23', '59', '59');
    $diff = $start->diff($end);
    if ($diff->m === 0) {
      $ops[] = [self::class . '::importOrgsBatch', [$types, $start->getTimestamp(), $end->getTimestamp()]];
    }
    else {
      // For each month difference, add an operation.
      for ($i = 0; $i < $diff->m; $i++) {
        $ops[] = [self::class . '::importOrgsBatch', [$types, $start->getTimeStamp(), $start->modify('+1 month')->getTimestamp()]];
      }
      // If there are extra days, fill in the remainder of the days in one last op.
      // Otherwise, fill in the last day (the object will be set to 12AM that morning).
      $ops[] = [self::class . '::importOrgsBatch', [$types, $start->getTimestamp(), $end->getTimestamp()]];

    }
    $batch = [
      'title' => $this->t('Sync organizations'),
      'finished' => self::class . '::importFinished',
      'operations' => $ops,
    ];
    batch_set($batch);
  }

  /*******************
   * Batch functions *
   *******************/

  public static function importOrgsBatch($facility_types, $start_date, $end_date, &$context) {
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
        $node = $sync->syncOrganization($organization, $facility_types);
        if (is_object($node)) {
          $context['results']['success'][] = $node->id();
        }
      }
      catch (\Exception $exception) {
        $context['results']['errors']['sync'][] = "Error syncing organization {$organization['org_cst_key']}: " .
          $exception->getMessage();
        $debug = TRUE;
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
   * Batch finished callback.
   *
   * @param $success
   * @param $results
   * @param $operations
   * @param $timer
   */
  public static function importFinished($success, $results, $operations, $timer) {
    if (!$success) {
      drupal_set_message(t('Sync failed.'));
      return;
    }
    drupal_set_message(t('Synced @count organizations in @time', ['@count' => count($results['success']), '@time' => $timer]));
    if (!empty($results['error'])) {
      foreach ($results['error'] as $err) {
        drupal_set_message(t('Error: %err', ['%err' => $err]), 'error');
      }
    }
  }

}

