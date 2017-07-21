<?php

namespace Drupal\netforum_user_auth\Form;

use Exception;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserStorageInterface;
use Drupal\netforum_soap\GetClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserAuthForm.
 *
 * @package Drupal\netforum_user_auth\Form
 */
class UserAuthForm extends FormBase {


  //Typical Drupal $user object
  protected $user;

  //Netforum SOAP client from netforum_soap module.
  protected $get_client;

  public function __construct(UserStorageInterface $userStorage, GetClient $getClient) {
    $this->user = $userStorage;
    $this->get_client = $getClient;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('user'),
      $container->get('netforum_soap.get_client')
    );
  }

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

  private function createUserFromNetForumUser($email, $password) {
    $this->user->create();

    // Mandatory fields.
    $this->user->setPassword($password);
    $this->user->enforceIsNew();
    $this->user->setEmail($email);
    $this->user->setUsername($email);
    $this->user->activate();

    // Save user account.
    $this->user->save();
  }

  private function Auth($email, $password) {

    $netforum_soap_result = $this->CheckEWebUser($email, $password);
    if($netforum_soap_result) {
      $user = user_load_by_mail($email);
      if(!$user) {
        $user = $this->createUserFromNetForumUser($email, $password);
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
    $client = $this->get_client->GetClient();
    $params = array(
      'szEmail' => $email,
      'szPassword' => $password,
    );
    $auth_headers = $this->get_client->getAuthHeaders();
    $response_headers = $this->get_client->getResponseHeaders();
    try {
      $response = $client->__soapCall('CheckEWebUser', array('parameters' => $params), NULL, $auth_headers, $response_headers);
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
