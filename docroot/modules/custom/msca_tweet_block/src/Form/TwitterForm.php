<?php

namespace Drupal\msca_tweet_block\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

  /**
   * Class TwitterForm.
   */
  class TwitterForm extends FormBase {


  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs an AccessDeniedRedirectSubscriber object.
   * 
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'twitter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('msca_tweet_block.settings');

    $form['access_token'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token'),
      '#description' => $this->t('Twitter Access Token'),
      '#maxlength' => 140,
      '#size' => 64,
      '#default_value' => $config->get('access_token'),
    ];
    $form['access_token_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Token Secret'),
      '#description' => $this->t('Twitter API Access Token Secret'),
      '#maxlength' => 140,
      '#size' => 64,
      '#default_value' => $config->get('access_token_secret'),
    ];
    $form['twitter_api_consumer_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twitter API Consumer Key'),
      '#description' => $this->t('Twitter API Consumer Key'),
      '#maxlength' => 140,
      '#size' => 64,
      '#default_value' => $config->get('twitter_api_consumer_key'),

    ];
    $form['twitter_api_consumer_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Consumer Secret'),
      '#description' => $this->t('Twitter API Consumer Secret'),
      '#maxlength' => 140,
      '#size' => 64,
      '#default_value' => $config->get('twitter_api_consumer_secret'),
    ];
    $form['twitter_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Twitter URL'),
      '#description' => $this->t('URL of Twitter Account'),
      '#maxlength' => 140,
      '#size' => 64,
      '#default_value' => $config->get('twitter_url'),
    ];
    $form['facebook_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facebook URL'),
      '#description' => $this->t('URL of Facebook Account'),
      '#maxlength' => 140,
      '#size' => 64,
      '#default_value' => $config->get('facebook_url'),
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
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $config = \Drupal::service('config.factory')
      ->getEditable('msca_tweet_block.settings');

    $config
      ->set('access_token', $form_state->getValue('access_token'))
      ->set('access_token_secret', $form_state->getValue('access_token_secret'))
      ->set('consumer_key', $form_state->getValue('twitter_api_consumer_key'))
      ->set('consumer_secret', $form_state->getValue('twitter_api_consumer_secret'))
      ->set('facebook_url', $form_state->getValue('facebook_url'))
      ->set('twitter_url', $form_state->getValue('twitter_url'))
      ->save();
      $message = 'Twitter API Values Updated';
      $this->messenger->addMessage($this->t($message));

  }

}
