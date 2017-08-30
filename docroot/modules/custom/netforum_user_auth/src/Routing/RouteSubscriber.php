<?php

namespace Drupal\netforum_user_auth\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.pass')) {
      $route->setDefault('_controller', '\Drupal\netforum_user_auth\Controller\UserPassword::netforumPass');
      $route->setDefault('_form', NULL);
    }
  }

}
