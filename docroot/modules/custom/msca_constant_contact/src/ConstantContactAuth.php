<?php

namespace Drupal\msca_constant_contact;

use Drupal\Core\Routing\TrustedRedirectResponse;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ConstantContactAuth {

  /**
   * ConstantContactAuth constructor.
   */
  public function __construct() {
    $this->base_uri = 'https://api.cc.email/v3';
    $this->http_client = new Client();
  }

  public function auth() {
    // https://api.cc.email/v3/idfed?client_id=9fdc2e05-0e19-4847-b6e0-17e49f9f47a4&redirect_uri=https%3A%2F%2Fwww.maseniorcare.org&response_type=token&scope=contact_data+campaign_data

    $pubkey = '5e36e7a6-5c46-4de6-bdbc-66de3921192b';
//    $pubkey = '9fdc2e05-0e19-4847-b6e0-17e49f9f47a4';

    $baseUrl = 'https://api.cc.email/v3/idfed?';
    $client = 'client_id=' . $pubkey;
    $redirectUri = '&redirect_uri=http://msca.docksal/admin/config/services/constantcontact/authresponse';
    $responseType = '&response_type=code';
    $scope = '&scope=campaign_data';
    $url = $baseUrl . $client . $redirectUri . $responseType . $scope;
    return new TrustedRedirectResponse($url);
  }

  public function createEmailCampaign($nid) {
//    $realMagnetConfig = $this->config('real_magnet.realmagnetsettings');
//    $template_id = $realMagnetConfig->get('real_magnet_template_id');

    // Load the node so we can send it as message body.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $node_viewer = \Drupal::entityTypeManager()->getViewBuilder('node');
    // Use 'Email' view display.
    $node_array = $node_viewer->view($node, 'email');
    // Capture rendered and themed html.
    $node_html = \Drupal::service('renderer')->render($node_array);
//    $node_html = preg_replace('/<!--(.|\s)*?-->/', '', $node_html);
    $node_html = str_replace(array("\r", "\n"), '', $node_html);
    $node_html = str_replace('src="/', 'src="https://www.maseniorcare.org/', $node_html);
    $node_html = str_replace('href="/', 'href="https://www.maseniorcare.org/', $node_html);
    $node_html = str_replace('<p>', '<p style="margin: 0">', $node_html);
    $node_html = str_replace("article", "div", $node_html);
    $node_html = str_replace("aside", "div", $node_html);
    $node_html = str_replace("header", "div", $node_html);
    $node_html = str_replace("section", "div", $node_html);
    $node_html = str_replace("<div", '<table cellpadding="0" cellspacing="0" border="0" style="width: 100%; font-family: sans-serif"><tr><td', $node_html );
    $node_html = str_replace("</div>", "</td></tr></table>", $node_html);
    $node_html = str_replace('"', '\"', $node_html);
    $subject = $node->getTitle();
    $json = '{
  "name": "' . $subject . rand(10000, 99999) . '",
  "email_campaign_activities": [
    {
      "format_type": 1,
      "from_name": "Massachusetts Senior Care",
      "from_email": "aantolini@maseniorcare.org",
      "reply_to_email": "aantolini@maseniorcare.org",
      "subject": "' . $subject . '",
      "html_content": "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\"><html><head></head><body style=\"font-family: sans-serif\"><!--[if mso]>
      <style type=\"text/css\">
body, table, td {font-family: sans-serif !important;}
</style>
<![endif]--><meta http-equiv=\"Content-Type\" content=\"text/html charset=UTF-8\" />' . $node_html . '</body></html>",
      "physical_address_in_footer": {
        "address_line1": "800 South St #280",
        "address_line2": "",
        "address_line3": "",
        "address_optional": "",
        "city": "Waltham",
        "country_code": "US",
        "country_name": "United States",
        "organization_name": "Massachusetts Senior Care Association",
        "postal_code": "02453",
        "state_code": "MA",
        "state_name": "Massachusetts",
        "state_non_us_name": ""
      },
      "document_properties": {
        "style_content": ".white{color: #ffffff;}",
        "text_content": "<Text><Greetings/></Text>",
        "permission_reminder_enabled": "true",
        "permission_reminder": "Hi, just a reminder that you\'re receiving this email because you have expressed an interest in our company.",
        "view_as_webpage_enabled": "false",
        "view_as_webpage_text": "Having trouble viewing this email?",
        "view_as_webpage_link_name": "Click here to view this email as a web page",
        "greeting_salutation": "Hi,",
        "greeting_name_type": "F",
        "greeting_secondary": "Greetings!",
        "forward_email_link_enabled": "true",
        "forward_email_link_name": "Forward email",
        "subscribe_link_enabled": "false",
        "subscribe_link_name": "Subscribe to my email list!",
        "letter_format": "XHTML"
      }
    }
  ]
}';
//    $json = json_encode($json);
    $base_url = 'https://api.cc.email/v3/';
    $client = new Client([
      'base_uri' => $base_url,
    ]);

    try {
      $response = $client->post('emails', [
        'headers' => [
          'Authorization' => 'Bearer ' . $_SESSION['token'],
          'Content-Type' => 'application/json',
          'Accept' => 'application/json',
        ],
        'body' => $json,
      ]);
    }
    catch (\GuzzleHttp\Exception\ServerException $e) {
      $message = $e->getMessage();
      drupal_set_message('Sending to Constant Contact failed for the following reason: ' . $message, 'error');
      $redirect = new RedirectResponse($_SERVER["HTTP_REFERER"]);
      $redirect->send();
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      $message = $e->getMessage();
      drupal_set_message('Sending to Constant Contact failed for the following reason: ' . $message, 'error');
      $redirect = new RedirectResponse($_SERVER["HTTP_REFERER"]);
      $redirect->send();
    }
    drupal_set_message(t('A new Constant Contact Email Campaign has been successfully created. Please proceed to the <a href="https://campaign-ui.constantcontact.com/campaign/dashboard">Constant Contact Campaign Dashboard</a>.'));
    $redirect = new RedirectResponse($_SERVER["HTTP_REFERER"]);
    $redirect->send();
  }
}
