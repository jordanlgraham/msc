<?php

namespace Drupal\msca_job\Controller;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Controller\ControllerBase;

class PostJob extends ControllerBase {

  /**
   * Page controller
   */
  public function postJob() {
    $build = [];

    $values = array('type' => 'job_posting', '#job_post_form' => TRUE);

    $node = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->create($values);

    $form = \Drupal::entityTypeManager()
      ->getFormObject('node', 'default')
      ->setEntity($node);

    $build['form'] = $this->formBuilder()->getForm($form);

    $build['#cache'] = [
      'max-age' => Cache::PERMANENT,
    ];

    return $build;
  }
}
