<?php

namespace Drupal\netforum_user_auth\Form;

use Exception;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\UserStorageInterface;
use Drupal\user\UserInterface;
use Drupal\netforum_soap\GetClient;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class UserAuthForm.
 *
 * @package Drupal\netforum_user_auth\Form
 */
class UserAuthForm extends FormBase {

  //Typical Drupal $user object
  protected $userStorage;

  //Netforum SOAP client from netforum_soap module.
  protected $get_client;

  public function __construct(UserStorageInterface $userStorage, GetClient $getClient) {
    $this->userStorage = $userStorage;
    $this->get_client = $getClient;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('user'),
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
        //At some point in the project, we may need to make this configurable,
        //but for now this seems the most common scenario.
        $form_state->setRedirect('<front>');
        return;
      } else {

      }
    }
  }

  //Creates a new user account with the credentials passed in the NetForum Login
  //form. We may need to pull in more information like First Name and Last Name,
  //in which case this module should be updated to include a $params array as
  //the last argument so we can easily loop through it and create the user account
  //with those fields.
  private function createUserFromNetForumUser($email, $password) {
    $created_user = $this->userStorage->create([
      'email' => $email,
      'password' => $password,
      'username' => $email,
      'name' => $email,
    ]);
    $created_user->enforceIsNew(TRUE);
    $created_user->activate();
    $created_user->save();
    $this->userStorage->save($created_user);

//    exit();
    return $created_user;
  }

  private function Auth($email, $password) {

    $netforum_soap_result = $this->CheckEWebUser($email, $password);
    if($netforum_soap_result) {
      $user = $this->userStorage->loadByProperties(['mail' => $email]);
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
      //CheckEWebUser simply attempts to authenticate based on the passed credentials.
      $response = $client->__soapCall('CheckEWebUser', array('parameters' => $params), NULL, $auth_headers, $response_headers);
      if (!empty($response->CheckEWebUserResult->any)) {
        $xml = simplexml_load_string($response->CheckEWebUserResult->any);
        //Could probably be better handled with the Serialization API
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
