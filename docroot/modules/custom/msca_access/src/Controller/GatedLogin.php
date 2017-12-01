<?php

namespace Drupal\msca_access\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;

class GatedLogin extends ControllerBase {
  public function page() {
    $build = [];
    $build['page'] = [
      '#theme' => 'gated_page',
      '#form' => $this->formBuilder()->getForm(\Drupal\user\Form\UserLoginForm::class),
    ];
    $build['#cache'] = [
      'contexts' => ['user.roles:anonymous'],
      'tags' => [],
      'max-age' => Cache::PERMANENT,
      'keys' => ['msca_access_gated_login'],
    ];
    return $build;
  }
}
