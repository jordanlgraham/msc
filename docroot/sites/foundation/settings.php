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
$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/config/foundation';

$settings['config_exclude_modules'] = ['devel', 'stage_file_proxy', 'netforum_soap', 'twig_vardumper', 'geolocation_google_maps', 'twig_xdebug', 'shield'];

/**
 * Load local development override configuration, if available.
 *
 * Create a settings.local.php file to override variables on secondary (staging,
 * development, etc.) installations of this site.
 *
 * Typical uses of settings.local.php include:
 * - Disabling caching.
 * - Disabling JavaScript/CSS compression.
 * - Rerouting outgoing emails.
 *
 * Keep this code block at the end of this file to take full effect.
 */
if (file_exists($app_root . '/' . $site_path . '/settings.local.php')) {
  include $app_root . '/' . $site_path . '/settings.local.php';
}
