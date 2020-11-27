<?php

namespace Drupal\netforum_user_auth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\netforum_user_auth\Form\NetforumLogin;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class NetforumSso extends ControllerBase {

  const NETFORUM_SESSION_KEY = 'netforum.redirect';
  const NETFORUM_REDIRECT_URL_KEY = 'nfUrl';

  public function login(Request $request) {
    return $this->formBuilder()->getForm(NetforumLogin::class);
  }

  protected function netforumRedirect($request) {
    $url = $this->getRedirect($request);
    return new TrustedRedirectResponse($url);
  }

  protected function getLogin(Request $request) {
    $dest_url = $this->getRedirect($request);
    $redirect = Url::fromRoute('user.login', [self::NETFORUM_REDIRECT_URL_KEY => $dest_url])->toString();
    return new RedirectResponse($redirect);
  }

  private function getRedirect(Request $request) {
    return $request->get(self::NETFORUM_REDIRECT_URL_KEY);
  }
}
