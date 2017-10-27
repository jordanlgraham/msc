<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Component\Datetime\DateTimePlus;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\SafeMarkup;
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
      $this->sync->syncOrganizations($start_date, $end_date);
      $this->state->set(OrgSync::CRON_STATE_KEY, $this->time->getRequestTime());
      drupal_set_message($this->t('Organizations successfully synced.'));
    }
    catch (\Exception $exception) {
      drupal_set_message($this->t('Unable to complete organization sync. See logs for error.'), 'error');
    }
  }

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

    if ($context['sandbox'][$sandbox_key]['pointer'] === $context['sandbox'][$sandbox_key]['count'] || $sandbox_key === '13596948001362114000') {
      $context['finished'] = 1;
      unset($context['sandbox'][$sandbox_key]);
    }
    else {
      $context['message'] = $context['message'] . ": Completed {$context['sandbox'][$sandbox_key]['pointer']} of {$context['sandbox'][$sandbox_key]['count']}";
      $context['finished'] = $context['sandbox'][$sandbox_key]['pointer']/$context['sandbox'][$sandbox_key]['count'];
    }
  }

  public static function importFinished() {
    $q = func_get_args();
    $debug = TRUE;
  }

}

