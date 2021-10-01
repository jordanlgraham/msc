CONTENTS OF THIS FILE
---------------------

* Introduction
* Requirements
* Installation
* Configuration
* Maintainers


INTRODUCTION
------------

This Recreate Block Content module recreates the block content for exported
custom block. It does not export the content. The block is created as a
placeholder with no content.

Since 8.x-2.0, the module now supports any module that declares dependencies on
blocks. That means that it works with Panels (but not with panelizer, since it
doesn't add a dependency to blocks). This version also creates better titles for
content blocks when possible and displays messages on the site or via drush when
a block is created.

If you are looking for modules that exports the block content, take a look on
Fixed block content (https://www.drupal.org/project/fixed_block_content) or
Simple block (https://www.drupal.org/project/simple_block). However if you
prefer to not have content on your version control system but need to export
content, take a look on Deploy (https://www.drupal.org/project/deploy).

 * For a full description of the module, visit the project page:
   https://www.drupal.org/project/recreate_block_content

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/recreate_block_content


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


INSTALLATION
------------

 * Install as you would normally install a contributed Drupal module. Visit
   https://www.drupal.org/node/1897420 for further information.


CONFIGURATION
-------------

The module has no menu or modifiable settings. There is no configuration. When
enabled, recreate block content by clearing caches via drush or via Drupal
admin.


MAINTAINERS
-----------

Current maintainers:
 * Joao Sausen - https://www.drupal.org/u/joao-sausen
