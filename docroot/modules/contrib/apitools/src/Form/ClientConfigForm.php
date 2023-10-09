<?php

namespace Drupal\apitools\Form;

use Drupal\apitools\Api\Client\ClientInterface;
use Drupal\apitools\ClientManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginWithFormsInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ClientConfigForm extends ConfigFormBase {

  /**
   * @var \Drupal\apitools\Api\Client\ClientInterface
   */
  protected $client;

  /**
   * @var \Drupal\apitools\ClientManagerInterface
   */
  protected $clientManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientManagerInterface $client_manager) {
    parent::__construct($config_factory);
    $this->clientManager = $client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('plugin.manager.apitools_client')
    );
  }

  /**
   * @return \Drupal\apitools\Api\Client\ClientInterface
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Page callback for the client plugin config form.
   *
   * @param $plugin_id
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  public function title($plugin_id) {
    return $this->t('@client_label Client Settings', [
      '@client_label' => $this->getClientLabel($plugin_id) ?? $plugin_id,
    ]);
  }

  /**
   * Get client plugin label.
   *
   * @param $plugin_id
   *
   * @return mixed|null
   */
  private function getClientLabel($plugin_id) {
    if (!$client = $this->clientManager->load($plugin_id)) {
      return NULL;
    }
    return $client->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'client_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [$this->client->getConfigName()];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $plugin_id = NULL) {
    $this->client = $this->clientManager->load($plugin_id);
    $form = $this->getPluginForm($this->client)->buildConfigurationForm($form, $form_state);


    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#submit' => ['::submitForm'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $this->getPluginForm($this->client)->validateConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->getPluginForm($this->client)->submitConfigurationForm($form, $form_state);
    $values = $form_state->cleanValues()->getValue('config');
    $config = $this->config($this->client->getConfigName());
    foreach ($values as $key => $value) {
      $config->set($key, $value);
    }
    $config->save();
  }

  /**
   * Retrieves the plugin form for a given client.
   */
  protected function getPluginForm(ClientInterface $client) {
    if ($client instanceof PluginWithFormsInterface) {
      return \Drupal::service('plugin_form.factory')->createInstance($client, 'configure');
    }
    return $client;
  }
}
