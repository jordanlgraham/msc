<?php

namespace Drupal\real_magnet\Access;

use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\Routing\Route;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

class SendAccess implements AccessInterface {

  public function access(AccountInterface $account, Route $route, NodeInterface $node) {
    return AccessResult::allowedIf($node->getType() == 'newsletter');
  }
}