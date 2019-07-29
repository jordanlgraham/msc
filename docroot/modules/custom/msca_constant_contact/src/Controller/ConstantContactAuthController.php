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
    $_SESSION['cc_code'] = \Drupal::request()->get('code');
    $a = 1;
//    $authService = \Drupal::service('constant_contact_auth');
//    return $authService->auth();
  }

  public function createEmailCampaign() {
    $authService = \Drupal::service('constant_contact_auth');
    $nid = \Drupal::request()->get('node');
    /** @var ConstantContactAuth $authService */
    $authService->createEmailCampaign($nid);
  }
}
