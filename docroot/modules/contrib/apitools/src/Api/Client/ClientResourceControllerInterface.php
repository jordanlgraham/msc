<?php

namespace Drupal\apitools\Api\Client;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines an interface for model controllers.
 *
 * This interface can be implemented by entity handlers that require
 * dependency injection.
 *
 * @ingroup apitools
 */
interface ClientResourceControllerInterface {

  /**
   * Instantiates a new instance of this model controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container this object should use.
   * @param array $configuration
   *   Plugin configuration to pass to new instance.
   *
   * @return ClientResourceControllerInterface
   *   A new instance of the model controller.
   * @see \Drupal\Core\Entity\EntityHandlerInterface::createInstance()
   *
   */
  public static function createInstance(ContainerInterface $container, array $configuration);

  /**
   * Routes dynamic methods to custom defined functions.
   *
   * @param $name
   *   Method name.
   * @param $arguments
   *   Method arguments.
   * @return mixed
   */
  public function __call($name, $arguments);
}

