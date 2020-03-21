<?php
// On Acquia Cloud, this include file configures Drupal to use the correct
// database in each site environment (Dev, Stage, or Prod). To use this
// settings.php for development on your local workstation, set $db_url
// (Drupal 5 or 6) or $databases (Drupal 7 or 8) as described in comments above.
if (file_exists('/var/www/site-php')) {
  require('/var/www/site-php/msca/msca-settings.inc');
}

// Google Analytics Config for Production Only
if (!empty($_ENV['AH_PRODUCTION'])) {
  $config['google_analytics.settings']['account'] = "UA-19805426-1";
}

// Include local settings.
require $app_root . '/sites/settings.common.php';
// Required for config sync.
$settings['install_profile'] = 'standard';

// Scaffolding for Lando-based local development.
$lando_info = json_decode(getenv('LANDO_INFO'), TRUE);
if (!empty($lando_info)) {
  $base_url = "https://msca.lndo.site";

  // Database credentials
  $databases['default']['default'] = array(
    'database' => $lando_info['database']['creds']['database'],
    'username' => $lando_info['database']['creds']['user'],
    'password' => $lando_info['database']['creds']['password'],
    'host' => 'database',
    'driver' => 'mysql',
  );

  // File system settings
  $conf['file_temporary_path'] = '/tmp';
  $settings['file_private_path'] = '/app/private-files/';

  // Trusted host pattern settings.
  $settings['trusted_host_patterns'][] = '\.lndo\.site$';

  // Set config directory in Lando local dev environment.
  $config_directories['sync'] = '/app/config/sync';
}


// <DDSETTINGS>
// Please don't edit anything between <DDSETTINGS> tags.
// This section is autogenerated by Acquia Dev Desktop.
if (isset($_SERVER['DEVDESKTOP_DRUPAL_SETTINGS_DIR']) && file_exists($_SERVER['DEVDESKTOP_DRUPAL_SETTINGS_DIR'] . '/cld_devcloud_msca_dev_default.inc')) {
  require $_SERVER['DEVDESKTOP_DRUPAL_SETTINGS_DIR'] . '/cld_devcloud_msca_dev_default.inc';
}
// </DDSETTINGS>
