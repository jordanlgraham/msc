<?php

namespace Drupal\msc_ym_soap\Form;

use Drupal\msc_ym_soap\GetClient;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\user\UserStorageInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure MSC YourMembership Soap settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'msc_ym_soap_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['msc_ym_soap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('msc_ym_soap.settings');
    $form['wsdl_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('YourMembership WSDL Address'),
      '#description' => $this->t('The full URL to the YourMembership WSDL. Unless you have a specific configuration, this should be https://YourMembership.avectra.com/xWeb/YourMembershipXMLOnDemand.asmx?WSDL'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => true,
      '#default_value' => $config->get('wsdl_address'),
    ];
    $form['api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Username'),
      '#description' => $this->t('The YourMembership API Username (must have the ability to call methods)'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => true,
      '#default_value' => $config->get('api_username'),
    ];
    $form['api_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Password'),
      '#description' => $this->t('The YourMembership API Password'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => true,
      '#default_value' => $config->get('api_password'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->getValue('example') != 'example') {
      $form_state->setErrorByName('example', $this->t('The value is not correct.'));
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('msc_ym_soap.settings')
      ->set('wsdl_address', $form_state->getValue('wsdl_address'))
      ->set('api_username', $form_state->getValue('api_username'))
      ->set('api_password', $form_state->getValue('api_password'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
