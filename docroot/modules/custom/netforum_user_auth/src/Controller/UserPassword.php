<?php

namespace Drupal\netforum_user_auth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\netforum_user_auth\Auth;
use Symfony\Component\HttpFoundation\Request;

class UserPassword extends ControllerBase {

  /**
   * Redirect the user to a different password reset page if
   *  the config is available.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   */
  public function netforumPass(Request $request) {
    $url = Auth::RESET_PASSWORD_URL;
    return new TrustedRedirectResponse($url);
  }
}
