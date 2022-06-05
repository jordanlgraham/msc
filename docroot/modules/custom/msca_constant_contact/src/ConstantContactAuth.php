<?php

namespace Drupal\msca_constant_contact;

use Drupal\Core\Url;
use GuzzleHttp\Client;
use Drupal\Component\Utility\Random;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ConstantContactAuth {

  use StringTranslationTrait;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * ConstantContactAuth constructor.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->base_uri = 'https://api.cc.email/v3';
    $this->http_client = new Client();
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

  public function auth() {
    $pubkey = '9fdc2e05-0e19-4847-b6e0-17e49f9f47a4';
    // If testing in a lando-based local dev environment, set the alternate
    // public key value.
    $lando_info = json_decode(getenv('LANDO_INFO'), TRUE);
    if (!empty($lando_info)) {
      $pubkey = '5e36e7a6-5c46-4de6-bdbc-66de3921192b';
    }

    // Build the request url.
    $baseUrl = 'https://authz.constantcontact.com/oauth2/default/v1/authorize?';
    $client = 'client_id=' . $pubkey;
    $host = \Drupal::request()->getSchemeAndHttpHost();
    $redirectUri = '&redirect_uri=' . $host . '/admin/config/services/constantcontact/authresponse';
    $responseType = '&response_type=code';
    $scope = '&scope=campaign_data';
    $sessionString = '&state=' . $this->generateRandomString(23, TRUE);
    $url = $baseUrl . $client . $redirectUri . $responseType  . $scope . $sessionString;

    return new TrustedRedirectResponse($url);
  }

  /**
   * Generate a random string with letters, numbers and url-safe special chars.
   *
   * @param int $length
   * @return string
   */
  public function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-._~()\'!*:@,;';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
      $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
  }

  public function createEmailCampaign($nid) {
    $url = \Drupal::request()->getSchemeAndHttpHost() . '/admin/content';
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
      \Drupal::logger('msca_constant_contact')->notice("ConstantContactAuth::createEmailCampaign() - line 88:" . $message);
      $this->messenger->addError($this->t('Sending to Constant Contact failed for the following reason: ' . $message));
      $this->sendRedirect($url);
    }
    catch (\GuzzleHttp\Exception\ClientException $e) {
      $message = $e->getMessage();
      \Drupal::logger('msca_constant_contact')->notice("ConstantContactAuth line 99: " . $message);
      $this->messenger->addError($this->t('Sending to Constant Contact failed for the following reason: ' . $message));
      $this->sendRedirect($url);
    }
    $this->messenger->addStatus($this->t('A new Constant Contact Email Campaign has been successfully created. Please proceed to the <a href="https://campaign-ui.constantcontact.com/campaign/dashboard">Constant Contact Campaign Dashboard</a>.'));
    $this->sendRedirect($url);
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
      "email_campaign_activities": [{
		        "format_type": 5,
		        "from_email": "mailroom@maseniorcare.org",
		        "reply_to_email": "mailroom@maseniorcare.org",
            "from_name": "Massachusetts Senior Care Association",
            "subject": "' . $subject . '",
            "html_content": "<html><head></head><body style=\"font-family: sans-serif\"><OpenTracking /><style type=\"text/css\">body, table, td {font-family: sans-serif !important;}</style>' . $node_html . '</body></html>",
            "preheader": "",
            "physical_address_in_footer": {
              "address_line1": "800 South Street, Suite 280",
              "address_line2": "",
              "address_optional": "",
              "city": "Waltham",
              "country_code": "US",
              "country_name": "United States",
              "organization_name": "Massachusetts Senior Care Association",
              "postal_code": "02453",
              "state_code": "MA"
            }
          }]
      }';
  }

  public function sendRedirect(string $url) {
    $response = new RedirectResponse($url);
    $request = \Drupal::request();
    // Save the session so things like messages get saved.
    $request->getSession()->save();
    $response->prepare($request);
    // Make sure to trigger kernel events.
    \Drupal::service('kernel')->terminate($request, $response);
    $response->send();
  }
}

