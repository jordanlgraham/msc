<?php

namespace Drupal\real_magnet\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RealMagnetSettings.
 *
 * @package Drupal\real_magnet\Form
 */
class RealMagnetSettings extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'real_magnet.realmagnetsettings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'real_magnet_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('real_magnet.realmagnetsettings');
    $form['real_magnet_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Real Magnet Username'),
      '#description' => $this->t('Enter the username used for authenticating on Real Magnet system.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('real_magnet_username'),
      '#required' => TRUE,
    ];
    $form['real_magnet_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Real Magnet Password'),
      '#description' => $this->t('Enter the password used for authentication on Real Magnet system.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('real_magnet_password'),
      '#required' => TRUE,
    ];
    $form['real_magnet_template_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Real Magnet Template ID'),
      '#description' => $this->t('The ID of the Real Magnet template that will be populated with newsletter content.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('real_magnet_template_id'),
      '#required' => TRUE,
    ];
    $form['real_magnet_login_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Real Magnet Login ID'),
      '#description' => $this->t('Get this value from the Authenticate API method.'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('real_magnet_login_id'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('real_magnet.realmagnetsettings')
      ->set('real_magnet_username', $form_state->getValue('real_magnet_username'))
      ->set('real_magnet_password', $form_state->getValue('real_magnet_password'))
      ->set('real_magnet_template_id', $form_state->getValue('real_magnet_template_id'))
      ->set('real_magnet_login_id', $form_state->getValue('real_magnet_login_id'))
      ->save();
  }

}
