#Composer Instructions

- Do not use drush to download modules.
- Instead, use composer. To install a module with composer, cd to the docroot directory and type ‘composer require drupal/module_name’
- Once a module is downloaded, you can enable or uninstall it with drush.
- To remove a module, type ‘compose remove drupal/module_name’
- To update a module (or core), type ‘composer update drupal/module_name’
- When you add or update a module, commit the module files and the composer.json and composer.lock files to the repo


The reason for using composer is that in Drupal 8 some modules (like geocoder) use composer to require libraries, so if your project isn’t set up to use composer those modules basically aren’t usable