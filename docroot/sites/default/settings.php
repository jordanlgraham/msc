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
  $config['google_analytics.settings']['account'] = "UA-176994039-1";
  // Set a different Google Analytics key for Mass Senior Care Foundation site.
  if (stristr($_SERVER["HTTP_HOST"], 'maseniorcarefoundation')) {
    $config['google_analytics.settings']['account'] = "UA-177034085-1";
  }
}


// Disable the shield module on the Acquia production environment.
if (isset($_ENV['AH_SITE_ENVIRONMENT'])) {
  switch ($_ENV['AH_SITE_ENVIRONMENT']) {
      case 'prod':
      // Disable Shield on prod by setting the
      // shield user variable to NULL
      $config['shield.settings']['credentials']['shield']['user'] = NULL;
      break;
  }
}

// Include local settings.
require $app_root . '/sites/settings.common.php';
// Required for config sync.
$settings['install_profile'] = 'standard';

// Scaffolding for Lando-based local development.
$lando_info = json_decode(getenv('LANDO_INFO'), TRUE);
if (!empty($lando_info)) {
  $base_url = "https://msc.lndo.site";

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
  $config_directories['sync'] = '/app/config/default';

  // Load settings.local.php if it exists.
  if (file_exists(__DIR__ . '/settings.local.php')) {
    include __DIR__ . '/settings.local.php';
  }

  // Disable Shield on local dev by setting the
  // shield user variable to NULL
  // $config['shield.settings']['credentials']['shield']['user'] = NULL;
}

$settings['config_exclude_modules'] = ['devel', 'stage_file_proxy', 'netforum_soap', 'twig_vardumper', 'geolocation_google_maps', 'twig_xdebug'];

$settings['config_sync_directory'] = dirname(DRUPAL_ROOT) . '/config/default';
