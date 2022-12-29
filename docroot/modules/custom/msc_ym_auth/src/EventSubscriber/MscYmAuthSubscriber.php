<?php

namespace Drupal\msc_ym_auth\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * YourMembership Auth event subscriber.
 */
class MscYmAuthSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('user.pass')) {
      $route->setDefault('_controller', '\Drupal\msc_ym_auth\Controller\UserPassword::ymPass');
      $route->setDefault('_form', NULL);
    }
  }

}
