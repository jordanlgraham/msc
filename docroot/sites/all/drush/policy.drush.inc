<?php
/**
 * Implements hook_drush_command_alter().
 */
function policy_drush_command_alter(&$command) {
  if ($command['commandfile'] !== 'config_split' ||
    drush_get_context('DRUSH_DRUPAL_SITE') !== 'foundation') {
    return;
  }
  // Force config_split commands being run on the foundation site to use the
  // foundation split.
  if (empty($command['arguments']) || $command['arguments'] !== 'foundation') {
    $command['arguments'] = array('foundation');
  }
}
