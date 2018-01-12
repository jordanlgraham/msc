<?php

namespace Drupal\real_magnet\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Utility\Html;
use Psr\Log\LoggerInterface;

/**
 * Class RealMagnetSend.
 *
 * @package Drupal\real_magnet\Form
 */
class RealMagnetSend extends FormBase {

  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * @var \Drupal\Core\Entity\EntityViewBuilderInterface
   */
  protected $node_viewer;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client_factory'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')->get('real_magnet')
    );
  }

  public function __construct(ClientFactory $clientFactory, EntityTypeManagerInterface $entityTypeManager, LoggerInterface $logger) {
    $this->client = $clientFactory->fromOptions([]);
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->node_viewer = $entityTypeManager->getViewBuilder('node');
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'real_magnet_send';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, NodeInterface $node = NULL) {

    $form['newsletter_nid'] = [
      '#type' => 'hidden',
      '#value' => $node->id(),
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
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $realMagnetConfig = $this->config('real_magnet.realmagnetsettings');
    $username = $realMagnetConfig->get('real_magnet_username');
    $password = $realMagnetConfig->get('real_magnet_password');
    $newsletter_nid = $form['newsletter_nid']['#value'];
    // If credentials have been configured.
    if(!empty($username) && !empty($password)) {
      $this->realMagnetAuth($username, $password, $newsletter_nid);
    }
    // Else credentials have not been configured.
    else {
      drupal_set_message($this->t('It appears you have not configured your Real Magnet credentials. 
      Confirm credential settings here: /admin/config/real_magnet'), 'error');
    }
  }

  // We have to authenticate using Real Magnet API call and get the session id
  private function realMagnetAuth($username, $password, $newsletter_nid) {
    try {
      $request = $this->client->post('https://dna.magnetmail.net/ApiAdapter/Rest/Authenticate/', [
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
      // If we get a valid response back from real magnet endpoint.
      if($login_id != '0' && !empty($session_id) && !empty($user_id)) {
        $this->realMagnetPost($login_id, $session_id, $user_id, $username, $password, $newsletter_nid);
      }
      // Else the real magnet endpoint is probably down.
      else {
        drupal_set_message(Html::escape($this->t('Real Magnet refused connection. Service may be down. Try again later and contact 
        RealMagnet.com if problem persists.')), 'error');
      }

    }
    catch (\Exception $e) {
      $message = Html::escape($this->t('Real Magnet refused connection. Service may be down. Try again later and contact RealMagnet.com if problem persists.'));
      drupal_set_message(Html::escape($message), 'error');
      $this->logger->error($message);
      return false;
    }

  }

  private function realMagnetPost($login_id, $session_id, $user_id, $username, $password, $newsletter_nid) {
    $realMagnetConfig = $this->config('real_magnet.realmagnetsettings');
    $template_id = $realMagnetConfig->get('real_magnet_template_id');

    // Load the node so we can send it as message body.
    $node = $this->node_storage->load($newsletter_nid);
    // Use 'Email' view display.
    $node_array = $this->node_viewer->view($node, 'email');
    // Use 'Text' view display.
    $text_array = $this->node_viewer->view($node, 'text');
    // Capture rendered and themed html.
    $node_html = drupal_render($node_array);
    $text_html = drupal_render($text_array);
    // Capture the node title for use as MessageName.
    $message_name_array = $node->get('title')->getValue();
    $message_name = $message_name_array[0]['value'];
    // Capture the current date and time for use in MessageName to satisfy Real Magnet's unique MessageName constraint.
    $now = date("m-d-y h:i");
    // Concatenate title and date for unique MessageName.
    $full_message_name = $message_name . ' ' . $now;
    // Now we post the newsletter to Real Magnet
    try {
      $request = $this->client->post('https://dna.magnetmail.net/ApiAdapter/Rest/CreateMessage/', [
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
          "TextVersion" => $text_html
        ]
      ]);
      // Confirmation and error handling.
      $response = json_decode($request->getBody());
      if ($response->Error != '0') {
        drupal_set_message(Html::escape($this->t('Real Magnet refused this newsletter. Reason: @message', ['@message' => Html::escape($response->Message)])), 'error');
      }
      else {
        drupal_set_message($this->t('Success! Login to https://www.magnetmail.net to view and distribute newsletter via email.'), 'status');
      }
    }
    catch (\Exception $e) {
      $message = Html::escape($this->t('Real Magnet refused connection. Service may be down. Try again later and contact RealMagnet.com if problem persists.'));
      drupal_set_message(Html::escape($message), 'error');
      $this->logger->error($message);
      return false;
    }
  }

}