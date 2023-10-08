<?php

namespace Drupal\msc_ym_auth\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for YourMembership Auth routes.
 */
class MscYmAuthController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
    ];

    return $build;
  }

}
