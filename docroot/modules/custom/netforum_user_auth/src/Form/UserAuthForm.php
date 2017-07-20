<?php

namespace Drupal\netforum_user_auth\Form;

use Exception;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\User\Entity\User;

/**
 * Class UserAuthForm.
 *
 * @package Drupal\netforum_user_auth\Form
 */
class UserAuthForm extends FormBase {


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_auth_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email_address'] = [
      '#type' => 'email',
      '#title' => $this->t('Email Address'),
      '#description' => $this->t('NetForum Email Address'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#description' => $this->t('NetForum Password'),
      '#maxlength' => 64,
      '#size' => 64,
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
    // Display result.
    foreach ($form_state->getValues() as $key => $value) {
      if($key == 'email_address') {
        $email = $value;
      }
      if($key == 'password') {
        $password = $value;
      }
    }

    if(!empty($email) && !empty($password)) {
      $auth = $this->Auth($email, $password);
      if($auth) {
        $form_state->setRedirect('<front>');
        return;
      } else {

      }
    }
  }

  private function Auth($email, $password) {

    $netforum_soap_result = $this->CheckEWebUser($email, $password);
    if($netforum_soap_result) {
      $user = user_load_by_mail($email);
      if(!$user) {
        $user = User::create();

        // Mandatory fields.
        $user->setPassword($password);
        $user->enforceIsNew();
        $user->setEmail($email);
        $user->setUsername($email);
        $user->activate();

        // Save user account.
        $result = $user->save();
      }
      //whether they exist or have just been created, log the user in.
      user_login_finalize($user);
      return true;
    } else {
      drupal_set_message(t('Email address and Password do not match a record in Netforum'),'warning');
      return false;
    }
  }
  private function CheckEWebUser($email, $password) {
    $netforum_service = \Drupal::service('netforum_soap.get_token');
    $client = $netforum_service->GetClient();
    $responseHeaders = '';
    $params = array(
      'szEmail' => $email,
      'szPassword' => $password,
    );
    try {
      $response = $client->__soapCall('CheckEWebUser', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $responseHeaders);
      if (!empty($response->CheckEWebUserResult->any)) {
        $xml = simplexml_load_string($response->CheckEWebUserResult->any);
        $json = json_encode($xml);
        $array = json_decode($json, TRUE);
        if (!empty($array['@attributes']['recordReturn']) && $array['@attributes']['recordReturn'] == '1') {
          return true;
        }
        return false;
      }
    }
    catch(Exception $e) {
      return false;
    }
  }

}
