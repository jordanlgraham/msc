<?php

namespace Drupal\netforum_user_auth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class UserPassword extends ControllerBase {

  /**
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   */
  public function netforumPass(Request $request) {
    $url = $this->config('netforum_user_auth.config')->get('password_reset');
    if (UrlHelper::isValid($url, TRUE) && ($request->get('redirect') !== 'false')) {
      return new TrustedRedirectResponse('https://netforum.avectra.com/eWeb/DynamicPage.aspx?WebCode=ForgotPassword&Site=MSCA');
    }
    return $this->formBuilder()->getForm('\Drupal\user\Form\UserPasswordForm');
  }
}
