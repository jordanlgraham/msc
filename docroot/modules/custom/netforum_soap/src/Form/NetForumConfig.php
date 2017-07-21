<?php

namespace Drupal\netforum_soap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserStorageInterface;
use Drupal\netforum_soap\GetClient;

/**
 * Class NetForumConfig.
 *
 * @package Drupal\netforum_soap\Form
 */
class NetForumConfig extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'netforum_soap.netforumconfig',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'net_forum_config';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('netforum_soap.netforumconfig');
    $form['wsdl_address'] = [
      '#type' => 'textfield',
      '#title' => $this->t('NetForum WSDL Address'),
      '#description' => $this->t('The full URL to the NetForum WSDL. Unless you have a specific configuration, this should be https://netforum.avectra.com/xWeb/netForumXMLOnDemand.asmx?WSDL'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => true,
      '#default_value' => $config->get('wsdl_address'),
    ];
    $form['api_username'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Username'),
      '#description' => $this->t('The NetForum API Username (must have the ability to call methods)'),
      '#maxlength' => 64,
      '#size' => 64,
      '#required' => true,
      '#default_value' => $config->get('api_username'),
    ];
    $form['api_password'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Password'),
      '#description' => $this->t('The NetForum API Password'),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('netforum_soap.netforumconfig')
      ->set('wsdl_address', $form_state->getValue('wsdl_address'))
      ->set('api_username', $form_state->getValue('api_username'))
      ->set('api_password', $form_state->getValue('api_password'))
      ->save();
  }

}
