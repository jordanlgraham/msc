<?php

namespace Drupal\msca_constant_contact\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\msca_discourse\DiscourseHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class ConstantContactAuthTestController extends ControllerBase {

  public function auth() {
    $authService = \Drupal::service('constant_contact_auth');
    return $authService->auth();
  }
}
