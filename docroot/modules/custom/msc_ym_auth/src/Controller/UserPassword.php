<?php

namespace Drupal\msc_ym_auth\Controller;

use Drupal\Core\Url;
use Drupal\msc_ym_auth\Auth;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Routing\TrustedRedirectResponse;

class UserPassword extends ControllerBase {

  /**
   * Redirect the user to a different password reset page if
   *  the config is available.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   */
  public function ymPass(Request $request) {
    $url = Auth::RESET_PASSWORD_URL;
    return new TrustedRedirectResponse($url);
  }
}
