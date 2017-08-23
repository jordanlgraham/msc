<?php

namespace Drupal\msca_discourse\Access;

use Cviebrock\DiscoursePHP\SSOHelper;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\State\StateInterface;
use Drupal\msca_discourse\DiscourseHelper;
use Symfony\Component\HttpFoundation\Request;

class ValidPayload implements AccessInterface {

  /**
   * @var \Cviebrock\DiscoursePHP\SSOHelper
   */
  protected $helper;

  public function __construct(DiscourseHelper $discourseHelper) {
    $this->helper = $discourseHelper->getHelper();
  }

  public function access(Request $request) {
    $payload = $request->get('sso');
    $signature = $request->get('sig');

    // Validate the payload.
    return AccessResult::allowedIf($this->helper->validatePayload($payload, $signature));
  }
}
