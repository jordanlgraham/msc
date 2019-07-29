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
    $redirectUri = '&redirect_uri=http://msca.docksal/admin/config/services/constantcontact/authresponse?test';
    $responseType = '&response_type=code';

    $url = $baseUrl . $client . $redirectUri . $responseType;
    return new TrustedRedirectResponse($url);
  }

  public function createEmailCampaign($nid) {
//    $realMagnetConfig = $this->config('real_magnet.realmagnetsettings');
//    $template_id = $realMagnetConfig->get('real_magnet_template_id');

    // Load the node so we can send it as message body.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $node_viewer = \Drupal::entityTypeManager()->getViewBuilder('node');
    // Use 'Email' view display.
    $node_array = $node_viewer->view($node, 'full');
    // Use 'Text' view display.
//    $text_array = $this->node_viewer->view($node, 'text');
    // Capture rendered and themed html.
    $node_html = \Drupal::service('renderer')->render($node_array);
    $node_html = preg_replace('/<!--(.|\s)*?-->/', '', $node_html);
    $node_html = str_replace(array("\r", "\n"), '', $node_html);
    $node_html = str_replace('"', '\"', $node_html);
    $node_html = str_replace("article", "div", $node_html);
    $node_html = str_replace("aside", "div", $node_html);
    //    $text_html = drupal_render($text_array);
    // Capture the node title for use as MessageName.
//    $message_name_array = $node->get('title')->getValue();
//    $message_name = $message_name_array[0]['value'];
    // Capture the current date and time for use in MessageName to satisfy Real Magnet's unique MessageName constraint.
//    $now = date("m-d-y h:i");
    $subject = $node->getTitle();
    $json = '{
  "name": "' . $subject . '",
  "email_campaign_activities": [
    {
      "format_type": 1,
      "from_name": "Massachusetts Senior Care",
      "from_email": "aantolini@maseniorcare.org",
      "reply_to_email": "aantolini@maseniorcare.org",
      "subject": "' . $subject . '",
      "html_content": "<html><body>' . $node_html . '</body></html>",
      "physical_address_in_footer": {
        "address_line1": "123 Maple Street",
        "address_line2": "Unit 1",
        "address_line3": "string",
        "address_optional": "Near Boston Fire Station",
        "city": "Boston",
        "country_code": "US",
        "country_name": "United States",
        "organization_name": "Jake Dodge\'s Pancakes",
        "postal_code": "02451",
        "state_code": "MA",
        "state_name": "string",
        "state_non_us_name": "Victoria"
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
    $post = json_encode($json);
  }
}
