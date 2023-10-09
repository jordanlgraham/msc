<?php

namespace Drupal\apitools\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines an APITools Client annotation object.
 *
 * @see \Drupal\apitools\ClientManager
 * @see plugin_api
 *
 * @Annotation
 */
class ApiToolsClient extends Plugin {


  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The label of the plugin.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * Configuration options to include on the client config form.
   *
   * @var array
   */
  public $config;

  /**
   * A machine name ID to associate the @ApiToolsClientResource.
   *
   * @var string
   */
  public $api;

}
