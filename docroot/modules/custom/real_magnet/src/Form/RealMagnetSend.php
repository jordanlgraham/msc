<?php

namespace Drupal\real_magnet\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class RealMagnetSend.
 *
 * @package Drupal\real_magnet\Form
 */
class RealMagnetSend extends FormBase {

  //$realMagnetConfig = \Drupal::config('real_magnet.realmagnetsettings');

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'real_magnet_send';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $newsletter_nid  = \Drupal::routeMatch()->getParameter('node');

    $form['newsletter_nid'] = [
      '#type' => 'hidden',
      '#value' => $newsletter_nid,
    ];

    $form['send_to_real_magnet'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send to Real Magnet'),
      '#description' => $this->t('Click to send this Newsletter node content to Real Magnet service. You must login to Real Magnet in order to send as bulk email.'),
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
    $realMagnetConfig = $this->config('real_magnet.realmagnetsettings');
    $username = $realMagnetConfig->get('real_magnet_username');
    $password = $realMagnetConfig->get('real_magnet_password');
    $newsletter_nid = $form['newsletter_nid']['#value'];
    if(!empty($username) && !empty($password)) {
      $this->realMagnetAuth($username, $password, $newsletter_nid);
    }
    else {
      drupal_set_message(t('Real Magnet refused connection. Your credentials may be wrong or
      incomplete. Confirm credential settings here: /admin/config/real_magnet'), 'error');
    }
  }

  // We have to authenticate using Real Magnet API call and get the session id
  private function realMagnetAuth($username, $password, $newsletter_nid) {
    $client = \Drupal::httpClient();

    try {
      $request = $client->post('https://dna.magnetmail.net/ApiAdapter/Rest/Authenticate/', [
        'auth' => [$username, $password],
        'json' => [
          "Password" => $password,
          "UserName" => $username
        ]
      ]);

      $response = json_decode($request->getBody());
      $login_id = $response->LoginID;
      $session_id = $response->SessionID;
      $user_id = $response->UserID;

      if($login_id != '0' && !empty($session_id) && !empty($user_id)) {
        $this->realMagnetPost($login_id, $session_id, $user_id, $username, $password, $newsletter_nid);
      }
      else {
        drupal_set_message(t('Real Magnet refused connection. Service may be down. Try again later and contact 
        RealMagnet.com if problem persists.'), 'error');
      }

    }
    catch (RequestException $e) {
      watchdog_exception('real_magnet', $e->getMessage());
    }

  }

    private function realMagnetPost($login_id, $session_id, $user_id, $username, $password, $newsletter_nid) {
      $client = \Drupal::httpClient();
      $realMagnetConfig = $this->config('real_magnet.realmagnetsettings');
      $template_id = $realMagnetConfig->get('real_magnet_template_id');

      // Load the node so we can send it as message body.
      $node = \Drupal::entityTypeManager()->getStorage('node')->load($newsletter_nid);
      // Use 'Email' view display.
      $node_array = node_view($node, 'email');
      // Capture rendered and themed html.
      $node_html = drupal_render($node_array);
      // Capture the node title for use as MessageName.
      $message_name_array = $node->get('title')->getValue();
      $message_name = $message_name_array[0]['value'];
      // Capture the node's version ID for use in MessageName to satisfy Real Magnet's unique MessageName constraint.
      $message_vid_array = $node->get('vid')->getValue();
      $message_vid = $message_vid_array[0]['value'];
      // Concatenate node title and vid for unique MessageName.
      $full_message_name = $message_name . '- version ' . $message_vid;
      // Now we post the newsletter to Real Magnet
      try {
        $request = $client->post('https://dna.magnetmail.net/ApiAdapter/Rest/CreateMessage/', [
          'auth' => [$username, $password],
          'json' => [
            "SessionID" => $session_id,
            "UserID" => $user_id,
            "AutoUnsubscribeLink" => true,
            "CopyPasteTemplate" => true,
            "HTMLVersion" => $node_html,
            "LoginID" => $login_id,
            "MessageName" => $full_message_name,
            "SubjectLine" => "MSCA E-News Update",
            "TemplateID" => $template_id,
            "TextVersion" => "Testing"
          ]
        ]);
        // Confirmation and error handling.
        $response = json_decode($request->getBody());
        if ($response->Error == '1') {
          drupal_set_message(t('Real Magnet refused this newsletter. Reason: ' . $response->Message), 'error');
        }
        else {
          drupal_set_message(t('Success! Login to ReaLMagnet.com to view and distribute newsletter via email.'), 'status');
        }
      }
      catch (RequestException $e) {
        watchdog_exception('real_magnet', $e->getMessage());
      }
    }

}

