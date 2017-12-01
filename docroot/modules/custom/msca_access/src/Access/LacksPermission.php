<?php

namespace Drupal\msca_access\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

class LacksPermission implements AccessInterface {

  public function access(Route $route, AccountInterface $account) {
    $permission = $route->getRequirement('_lacks_permission');

    if ($permission === NULL) {
      return AccessResult::neutral();
    }

    return AccessResult::allowedIf(!$account->hasPermission($permission));
  }

}
