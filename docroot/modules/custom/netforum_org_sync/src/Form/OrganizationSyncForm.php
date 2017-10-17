<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Component\Datetime\TimeInterface;
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
      $this->syncAll();
      return;
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
    $i = 2008;
    $this_year = date('Y');
    while($i <= $this_year) {
      $k = 1;
      while($k <= 12) {
        //make a unix timestamp from yyyy-mm-dd
        $start_date = strtotime($i . '-' . $k . '-' . '1');
        $end_date = $start_date + (86400 * 31);
        $this->logger->info('Syncing Organizations from @start to @end',
          ['@start' => date('Y-m-d', $start_date), '@end' => date('Y-m-d', $end_date)]);
        $this->sync->syncOrganizations($start_date, $end_date);
        $k++;
      }
      $i++;
    }

  }

}

