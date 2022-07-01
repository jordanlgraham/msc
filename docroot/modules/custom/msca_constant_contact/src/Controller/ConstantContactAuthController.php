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

    $pubkey = '313f267c-9d51-46c2-8466-f1a8bec2705c';
    $secret = Drupal::service('key.repository')->getKey('constant_contact_secret')->getKeyValue();
    $secret = substr($secret, 0, -1);
    $token = $this->getAccessToken($redirectUri, $pubkey, $secret, $code);
    $_SESSION['token'] = json_decode($token)->access_token;
    $path = '/admin/content';
    $message = 'You have successfully authenticated with Constant Contact. To create a email campaign to Constant Contact, go to any Newsletter node, click Edit, and click the Send to Constant Contact tab.';
    $this->messenger->addMessage($this->t($message));
    $url = Url::fromUserInput(($path))->toString();
    $redirect = new RedirectResponse($url);
    return $redirect->send();
  }

  public function createEmailCampaign() {
    $authService = \Drupal::service('constant_contact_auth');
    $nid = \Drupal::request()->get('node');
    /** @var ConstantContactAuth $authService */
    $authService->createEmailCampaign($nid);
  }

  /*
   * This function can be used to exchange an authorization code for an access token.
   * Make this call by passing in the code present when the account owner is redirected back to you.
   * The response will contain an 'access_token' and 'refresh_token'
   *
   * @param $redirectURI - URL Encoded Redirect URI
   * @param $clientId - API Key
   * @param $clientSecret - API Secret
   * @param $code - Authorization Code
   * @return string - JSON String of results
   */
  private function getAccessToken($redirectURI, $clientId, $clientSecret, $code) {
    // Use cURL to get access token and refresh token
    $ch = curl_init();

    // Define base URL.
    $base = 'https://authz.constantcontact.com/oauth2/default/v1/token';

    // Create full request URL.
    $url = $base . '?code=' . $code . '&redirect_uri=' . $redirectURI . '&grant_type=authorization_code';
    curl_setopt($ch, CURLOPT_URL, $url);

    // Set authorization header.
    // Make string of "API_KEY:SECRET".
    $auth = $clientId . ':' . $clientSecret;
    // Base64 encode it.
    $credentials = base64_encode($auth);
    // Create and set the Authorization header to use the encoded credentials, and set the Content-Type header.
    $authorization = 'Authorization: Basic ' . $credentials;
    curl_setopt($ch, CURLOPT_HTTPHEADER, array($authorization, 'Content-Type: application/x-www-form-urlencoded'));

    // Set method and to expect response
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Make the call
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
  }
}
