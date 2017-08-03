<?php

/**
 * Implements hook_drush_init().
 */
function policy_drush_init() {
  $command = drush_get_command();
  // Don't allow core config commands to be used on the foundation site.
  $site = drush_get_context('DRUSH_DRUPAL_SITE');
  if ($site !== 'foundation') {
    return;
  }
  if ($command['command'] == 'config-import' || $command['command'] == 'config-export') {
    return drush_set_error('NOT_ALLOWED', dt('You must use config-split commands to manage configuration.'));
  }
}

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
