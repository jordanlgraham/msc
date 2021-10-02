<?php

namespace Drupal\msca_constant_contact\Controller;

use Drupal;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\msca_discourse\DiscourseHelper;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\msca_constant_contact\ConstantContactAuth;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConstantContactAuthController extends ControllerBase {

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

  public function authResponse() {
    $code = \Drupal::request()->get('code');

    $host = \Drupal::request()->getSchemeAndHttpHost();
    $redirectUri = $host . '/admin/config/services/constantcontact/authresponse';
    // $redirectUri = 'https://www.maseniorcare.org/admin/config/services/constantcontact/authresponse';
    $pubkey = '9fdc2e05-0e19-4847-b6e0-17e49f9f47a4';
    $secret = 'vR3zg9VuBZzrt0ykhNkPEQ';
    // If testing in a lando-based local dev environment, set the alternate
    // credentials.
    $lando_info = json_decode(getenv('LANDO_INFO'), TRUE);
    if (!empty($lando_info)) {
      $pubkey = '5e36e7a6-5c46-4de6-bdbc-66de3921192b';
      $secret = 'fs2XuQcOI2KathBXXIg9MA';
    }
    $token = $this->getAccessToken($redirectUri, $pubkey, $secret, $code);
    $_SESSION['token'] = json_decode($token)->access_token;
    $path = '/admin/content';
    $message = 'You have successfully authenticated with Constant Contact. To create a email campaign to Constant Contact, go to any Newsletter node, click Edit, and click the Send to Constant Contact tab.';
    $this->messenger->addMessage($this->t($message));
    $url = Url::fromUserInput(($path))->toString();
    $redirect = new RedirectResponse($url);
    $redirect->send();
  }

  public function createEmailCampaign() {
    $authService = \Drupal::service('constant_contact_auth');
    $nid = \Drupal::request()->get('node');
    /** @var ConstantContactAuth $authService */
    $authService->createEmailCampaign($nid);
  }

  private function getAccessToken($redirectURI, $clientId, $clientSecret, $code) {
    // Use cURL to get access token and refresh token
    $ch = curl_init();

    // Define base URL
    $base = 'https://idfed.constantcontact.com/as/token.oauth2';

    // Create full request URL
    $url = $base . '?code=' . $code . '&redirect_uri=' . $redirectURI . '&grant_type=authorization_code&scope=campaign_data';
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set authorization header
    // Make string of "API_KEY:SECRET"
    $auth = $clientId . ':' . $clientSecret;
    // Base64 encode it
    $credentials = base64_encode($auth);
    // Create and set the Authorization header to use the encoded credentials
    $authorization = 'Authorization: Basic ' . $credentials;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization));

    // Set method and to expect response
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Make the call
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
}
