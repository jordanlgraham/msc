<?php

namespace Drupal\msca_discourse\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\msca_discourse\DiscourseHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class DiscourseSso extends ControllerBase {

  /**
   * @var \Cviebrock\DiscoursePHP\SSOHelper
   */
  protected $helper;

  /**
   * @var \Drupal\msca_discourse\DiscourseHelper
   */
  protected $discourse;

  /**
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('msca_discourse.sso_helper'),
      $container->get('datetime.time')
    );
  }

  public function __construct(DiscourseHelper $discourseHelper, TimeInterface $time) {
    $this->helper = $discourseHelper->getHelper();
    $this->discourse = $discourseHelper;
    $this->time = $time;
  }

  public function sso(Request $request) {
    $payload = $request->get('sso');
    $nonce = $this->helper->getNonce($payload);

    if ($this->currentUser()->isAuthenticated()) {
      $user = $this->currentUser();
      $url = $this->discourse->getRedirect($payload, $nonce, $user);
      return new TrustedRedirectResponse($url);
    }
    else {
      $session_data = ['payload' => $payload, 'nonce' => $nonce, 'time' => $this->time->getRequestTime()];
      $request->getSession()->set(DiscourseHelper::SESSION_DATA_KEY, $session_data);
      $redirect = Url::fromRoute('user.login')->toString();
      return new RedirectResponse($redirect);
    }
  }
}
