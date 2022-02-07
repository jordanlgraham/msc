<?php
// Include acquia cloud settings.
if (file_exists('/var/www/site-php')) {
  require '/var/www/site-php/msca/foundation-settings.inc';
}
// Include the default settings file.
// require $app_root . '/sites/settings.common.php';
// Enable the foundation config split.
// $config['config_split.config_split.foundation']['status'] = TRUE;

$settings['container_yamls'][] = DRUPAL_ROOT . '/sites/foundation/local.services.yml';

// Disable Shield module on production environment.
if (isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  switch ($_ENV['AH_SITE_ENVIRONMENT']) {
      case 'prod':
      // Set the shield user variable to NULL.
      $config['shield.settings']['credentials']['shield']['user'] = NULL;
      break;
  }
}
$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/config/default';
