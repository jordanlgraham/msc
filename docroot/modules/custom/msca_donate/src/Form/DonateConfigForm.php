<?php

namespace Drupal\msca_donate\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class DonateConfigForm extends ConfigFormBase {

  const CONFIG_KEY = 'msca_donate.config';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'msca_donate_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_KEY];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $cf = $this->config(self::CONFIG_KEY);

    $form['sandbox_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sandbox key'),
      '#required' => TRUE,
      '#default_value' => !empty($cf->get('sandbox_key')) ? $cf->get('sandbox_key') : '',
    ];

    $form['production_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Production key'),
      '#required' => TRUE,
      '#default_value' => !empty($cf->get('production_key')) ? $cf->get('production_key') : '',
    ];

    $form['mode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Mode'),
      '#options' => [
        'sandbox' => $this->t('Sandbox'),
        'production' => $this->t('Production'),
      ],
      '#default_value' => $cf->get('mode'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    try {
      $config = $this->configFactory()->getEditable(self::CONFIG_KEY);
      $config->setData([
        'sandbox_key' => $form_state->getValue('sandbox_key'),
        'production_key' => $form_state->getValue('production_key'),
        'mode' => $form_state->getValue('mode'),
      ]);
      $config->save();
    }
    catch (\Exception $exception) {
      watchdog_exception('msca_donate', $exception);
      \Drupal\Core\Form\drupal_set_message($this->t('Error saving config'));
    }
    parent::submitForm($form, $form_state);
  }

}
