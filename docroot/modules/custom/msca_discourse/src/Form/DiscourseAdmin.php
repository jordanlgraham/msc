<?php

namespace Drupal\msca_discourse\Form;

use Drupal\Component\Utility\Random;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\msca_discourse\DiscourseHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DiscourseAdmin extends ConfigFormBase {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $stateService;

  public function __construct(StateInterface $state) {
    $this->stateService = $state;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'msca_discourse_admin';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('msca_discourse.config');

    $form['logout'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Auto logout'),
      '#description' => $this->t('Automatically log users out of Discourse when they log out of Drupal'),
      '#default_value' => $config->get('logout'),
    ];

    $logout_states = [
      'required' => [
        ':input[name="logout"]' => ['checked' => TRUE],
      ],
    ];

    $form['url'] = [
      '#type' => 'url',
      '#title' => $this->t('Discourse URL'),
      '#description' => $this->t('URL of the Discourse installation.'),
      '#default_value' => $config->get('url'),
      '#states' => $logout_states,
    ];

    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Key'),
      '#default_value' => $config->get('api_key'),
      '#states' => $logout_states,
    ];

    $form['api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Username'),
      '#default_value' => $config->get('api_username'),
      '#states' => $logout_states,
    ];

    $form['sso_secret'] = [
      '#type' => 'item',
      '#title' => $this->t('SSO Secret'),
      '#description' => $this->t('Copy and paste this value into the "sso_secret" field in Discourse admin.'),
      '#markup' => '<strong>' . $this->stateService->get(DiscourseHelper::SSO_SECRET_STATE_KEY) . '</strong>',
      '#disabled' => TRUE,
    ];

    $url = Url::fromRoute('msca_discourse.sso', [], ['absolute' => TRUE])->toString();
    $form['sso_url'] = [
      '#type' => 'item',
      '#title' => $this->t('SSO Url'),
      '#markup' => '<strong>' . $url . '</strong>',
      '#description' => $this->t('Copy and paste this value into the "sso_url" field in Discourse admin.')
    ];

    $form += parent::buildForm($form, $form_state);

    $form['actions']['regenerate'] = [
      '#type' => 'submit',
      '#value' => $this->t('Regenerate SSO Secret'),
      '#regenerate' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    $values = $form_state->getValues();
    if ($values['logout'] &&
      (empty($values['url']) || empty($values['api_key']) || empty($values['api_username']))) {
      $form_state->setError($form['logout'], $this->t('Auto logout requires a valid URL, API key, and API username.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $submit = $form_state->getTriggeringElement();
    if (isset($submit['#regenerate'])) {
      $sso_secret = self::generateSsoSecret();
      $this->stateService->set(DiscourseHelper::SSO_SECRET_STATE_KEY, $sso_secret);
      drupal_set_message($this->t('SSO secret updated.'));
    }
    else {
      // Update the configuration.
      $config = $this->configFactory()->getEditable('msca_discourse.config');
      $config->set('logout', $form_state->getValue('logout'));
      $config->set('url', $form_state->getValue('url'));
      $config->set('api_key', $form_state->getValue('api_key'));
      $config->set('api_username', $form_state->getValue('api_username'));
      $config->save();
      parent::submitForm($form, $form_state);
    }
  }

  public static function generateSsoSecret() {
    $random = new Random();
    return $random->name(32);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['msca_discourse.config'];
  }
}
