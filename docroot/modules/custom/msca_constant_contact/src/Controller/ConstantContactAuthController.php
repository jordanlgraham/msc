<?php

namespace Drupal\msca_constant_contact\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\msca_constant_contact\ConstantContactAuth;
use Drupal\msca_discourse\DiscourseHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ConstantContactAuthController extends ControllerBase {

  public function authResponse() {
    $code = \Drupal::request()->get('code');
    $redirectUri = 'https://www.maseniorcare.org/admin/config/services/constantcontact/authresponse';
    $pubkey = '9fdc2e05-0e19-4847-b6e0-17e49f9f47a4';
    $secret = 'vR3zg9VuBZzrt0ykhNkPEQ';
    $token = $this->getAccessToken($redirectUri, $pubkey, $secret, $code);
    $_SESSION['token'] = json_decode($token)->access_token;
    $path = '/admin/content';
    drupal_set_message($this->t('You have successfully authenticated with Constant Contact. To create a email campaign to Constant Contact, go to any Newsletter node, click Edit, and click the Send to Constant Contact tab.'));
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
