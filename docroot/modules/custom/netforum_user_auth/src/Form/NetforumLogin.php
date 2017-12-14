<?php

namespace Drupal\netforum_user_auth\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\netforum_user_auth\Auth;
use Drupal\netforum_user_auth\Controller\NetforumSso;
use Symfony\Component\DependencyInjection\ContainerInterface;

class NetforumLogin extends FormBase {

  /**
   * @var \Drupal\netforum_user_auth\Auth
   */
  private $auth;

  /**
   * {@inheritdoc}
   */
  public function __construct(Auth $auth) {
    $this->auth = $auth;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('netforum_user_auth.auth')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'netforum_user_login';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('E-mail'),
      '#placeholder' => $this->t('E-Mail Address'),
      '#description' => $this->t('Enter your Netforum E-mail address.'),
      '#required' => TRUE,
      '#default_value' => $this->currentUser()->getEmail(),
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#title' =>$this->t('Password'),
      '#placeholder' => $this->t('Password'),
      '#description' => $this->t('Enter your Netforum password.'),
      '#required' => TRUE,
    ];

    $form['redirect'] = [
      '#type' => 'value',
      '#value' => $this->getRequest()->get(NetforumSso::NETFORUM_REDIRECT_URL_KEY),
    ];

    $form['token'] = [
      '#type' => 'value',
      '#value' => '',
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
    // Get SSO token.
    try {
      $token = $this->auth->getSsoToken($form_state->getValue('email'), $form_state->getValue('pass'));
      $form_state->setValue('token', $token);
    }
    catch (\Exception $exception) {
      $form_state->setErrorByName('', $this->t('Unable to log in. Please check your E-mail address and password and try again.'));
      watchdog_exception('netforum_user_auth', $exception);
      $this->logger('netforum_user_auth')->error('Unable to validate SSO for user @mail', ['@mail' => $form_state->getValue('email')]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $token = $form_state->getValue('token');
      $redirect = $form_state->getValue('redirect');
      $url = $this->auth->getSsoUrl($token, $redirect);
      $response = new TrustedRedirectResponse($url->toString());
      $form_state->setResponse($response);
    }
    catch (\Exception $exception) {
      watchdog_exception('netforum_user_auth', $exception);
      $this->logger('netforum_user_auth')->error('Unable to complete SSO for user @mail', ['@mail' => $form_state->getValue('email')]);
    }
  }

}
