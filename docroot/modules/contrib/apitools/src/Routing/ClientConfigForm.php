<?php

namespace Drupal\apitools\Routing;

use Drupal\apitools\ClientManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Routing\Route;

class ClientConfigForm implements ContainerInjectionInterface {

  /**
   * @var \Drupal\apitools\ClientManagerInterface
   */
  protected $clientManager;

  /**
   * @param \Drupal\apitools\ClientManagerInterface $client_manager
   */
  public function __construct(ClientManagerInterface $client_manager) {
    $this->clientManager = $client_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.apitools_client')
    );
  }

  /**
   * Returns an array of route objects.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   An array of route objects.
   */
  public function routes() {
    $routes = [];

    foreach ($this->clientManager->getDefinitions() as $definition) {
      $client_plugin_id = $definition['id'];
      $client = $this->clientManager->load($client_plugin_id);
      $routes['apitools.client_config_form.' . $client_plugin_id] = new Route(
        '/admin/config/services/' . $client_plugin_id,
        [
          '_form' => '\Drupal\apitools\Form\ClientConfigForm',
          '_title_callback' => '\Drupal\apitools\Form\ClientConfigForm::title',
          'plugin_id' => $client_plugin_id,
        ],
        [
          '_permission' => $client->getAdminPermission(),
        ]
      );
    }
    return $routes;
  }
}