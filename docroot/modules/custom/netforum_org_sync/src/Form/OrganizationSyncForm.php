<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
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

  public function __construct(ConfigFactoryInterface $config_factory, OrgSync $orgSync,
                              StateInterface $state, TimeInterface $time) {
    $this->sync = $orgSync;
    $this->state = $state;
    $this->time = $time;
    parent::__construct($config_factory);
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('netforum_org_sync.org_sync'),
      $container->get('state'),
      $container->get('datetime.time')
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
    try {
      $this->sync->syncOrganizations();
      $this->state->set(OrgSync::CRON_STATE_KEY, $this->time->getRequestTime());
      drupal_set_message($this->t('Organizations successfully synced.'));
    }
    catch (\Exception $exception) {
      drupal_set_message($this->t('Unable to complete organization sync. See logs for error.'), 'error');
    }
  }

}
