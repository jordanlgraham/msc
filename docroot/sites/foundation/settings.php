<?php
// Include acquia cloud settings.
if (file_exists('/var/www/site-php')) {
  require '/var/www/site-php/msca/foundation-settings.inc';
}
// Include the default settings file.
// require $app_root . '/sites/settings.common.php';
// Enable the foundation config split.
// $config['config_split.config_split.foundation']['status'] = TRUE;
