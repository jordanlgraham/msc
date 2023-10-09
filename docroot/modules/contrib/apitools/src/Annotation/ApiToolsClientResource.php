<?php

namespace Drupal\apitools\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a ApiToolsClientResource item annotation object.
 *
 * @see \Drupal\apitools\ClientResourceManager
 * @see plugin_api
 *
 * @Annotation
 */
class ApiToolsClientResource extends Plugin {


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
   * Remote client or server object.
   *
   * @var string
   */
  public $type;

  /**
   * Machine name of the entity type this wraps if used.
   *
   * @var string
   */
  public $baseEntityType;

}
