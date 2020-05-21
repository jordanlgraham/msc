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
    $pubkey = '9fdc2e05-0e19-4847-b6e0-17e49f9f47a4';

    $baseUrl = 'https://api.cc.email/v3/idfed?';
    $client = 'client_id=' . $pubkey;
    $redirectUri = '&redirect_uri=https://www.maseniorcare.org/admin/config/services/constantcontact/authresponse';
    $responseType = '&response_type=code';
    $scope = '&scope=campaign_data';
    $url = $baseUrl . $client . $redirectUri . $responseType . $scope;
    return new TrustedRedirectResponse($url);
  }

  public function createEmailCampaign($nid) {
    // Load the node so we can send it as message body.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    $node_viewer = \Drupal::entityTypeManager()->getViewBuilder('node');
    // Use 'Email' view display.
    $node_array = $node_viewer->view($node, 'email');
    // Capture rendered and themed html.
    $node_html = \Drupal::service('renderer')->render($node_array);
    $node_html = $this->cleanHtml($node_html);
    /** @var \Drupal\node\NodeStorageInterface $node */
    $subject = $node->getTitle();
    $json = $this->buildJson($subject, $node_html);

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
      \Drupal::logger('msca_constant_contact')->notice("62:" . $message);
      print_r($message);
      drupal_set_message('Sending to Constant Contact failed for the following reason: ' . $message, 'error');
      $redirect = new RedirectResponse($_SERVER["HTTP_REFERER"]);
      $redirect->send();
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      $message = $e->getMessage();
      \Drupal::logger('msca_constant_contact')->notice("70: " . $message);
      print_r($message);
      drupal_set_message('Sending to Constant Contact failed for the following reason: ' . $message, 'error');
      $redirect = new RedirectResponse($_SERVER["HTTP_REFERER"]);
      $redirect->send();
    }
    drupal_set_message(t('A new Constant Contact Email Campaign has been successfully created. Please proceed to the <a href="https://campaign-ui.constantcontact.com/campaign/dashboard">Constant Contact Campaign Dashboard</a>.'));
    $redirect = new RedirectResponse($_SERVER["HTTP_REFERER"]);
    $redirect->send();
  }

  /**
   * Perform general cleanup functions on the $node_html to prepare it for email.
   *
   * @param $node_html
   *
   * @return mixed
   */
  private function cleanHtml($node_html) {
    // Get rid of excess linebreaks.
    $node_html = str_replace(array("\r", "\n"), '', $node_html);
    // Ensure all images and links point to production.
    $node_html = str_replace('src="/', 'src="https://www.maseniorcare.org/', $node_html);
    $node_html = str_replace('href="/', 'href="https://www.maseniorcare.org/', $node_html);
    // Reset paragraph margins to 0, which can have default margins in Outlook.
    $node_html = str_replace('<p>', '<p style="margin: 0">', $node_html);
    // Replace html5 nodes with divs
    $node_html = str_replace("<article", "<div", $node_html);
    $node_html = str_replace("<aside", "<div", $node_html);
    $node_html = str_replace("<header", "<div", $node_html);
    $node_html = str_replace("<section", "<div", $node_html);
    $node_html = str_replace("</article", "</div", $node_html);
    $node_html = str_replace("</aside", "</div", $node_html);
    $node_html = str_replace("</header", "</div", $node_html);
    $node_html = str_replace("</section", "</div", $node_html);
    $node_html = str_replace("http://msca.docksal", "https://www.maseniorcare.org", $node_html);
    $node_html = str_replace("https://msca.docksal", "https://www.maseniorcare.org", $node_html);
    // Replace all divs with tables.
    $node_html = str_replace("<div", '<table cellpadding="0" cellspacing="0" border="0" style="width: 100%; font-family: Helvetica, Arial, sans-serif"><tr><td', $node_html );
    $node_html = str_replace("</div>", "</td></tr></table>", $node_html);
    $node_html = preg_replace("/(™|®|©|&trade;|&reg;|&copy;|&#8482;|&#174;|&#169;)/", "", $node_html);
    // Replace Windows smart quotes.
    //    $search = [chr(145), chr(146), chr(147), chr(148), chr(151)];
    //    $replace = ["'", "'", '"', '"', '-'];
    //    $node_html = str_replace($search, $replace, $node_html);
    $node_html = str_replace('!important', '', $node_html);

    // Escape double-quote characters to prepare it as part of a JSON string.
    $node_html = str_replace('"', '\"', $node_html);
    $node_html = preg_replace('/<!--(.|\s)*?-->/', '', $node_html);
    // Replace trademarks, registered trademarks, copyright symbols.

    return $node_html;
  }

  private function buildJson($subject, $node_html) {
    return '{
  "name": "' . $subject . rand(10000, 99999) . '",
  "email_campaign_activities": [
    {
      "format_type": 1,
      "from_name": "Massachusetts Senior Care Association",
      "from_email": "mailroom@maseniorcare.org",
      "reply_to_email": "mailroom@maseniorcare.org",
      "subject": "' . $subject . '",
      "html_content": "<html><head></head><body style=\"font-family: sans-serif\"><OpenTracking />[[trackingImage]]<style type=\"text/css\">body, table, td {font-family: sans-serif !important;}</style>' . $node_html . '</body></html>",
      "physical_address_in_footer": {
        "address_line1": "800 South Street, Suite 280",
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
        "forward_email_link_enabled": "false",
        "forward_email_link_name": "Forward email",
        "subscribe_link_enabled": "false",
        "subscribe_link_name": "Subscribe to my email list!",
        "letter_format": "HTML"
      }
    }
  ]
}';
  }
}

