<?php

namespace Drupal\services\Plugin\ServiceDefinition;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\node\Entity\Node;
use Drupal\services\ServiceDefinitionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * @ServiceDefinition(
 *   id = "alias_get",
 *   methods = {
 *     "GET"
 *   },
 *   translatable = true,
 *   deriver = "\Drupal\services\Plugin\Deriver\AliasGet"
 * )
 */
class AliasGet extends ServiceDefinitionBase implements ContainerFactoryPluginInterface {

  /**
   * @var Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  protected $entity;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static($configuration, $plugin_id, $plugin_definition, $container->get('path_alias.manager'));
  }

  /**
   * @param array $configuration
   * @param string $plugin_id
   * @param mixed $plugin_definition
   * @param Drupal\path_alias\AliasManagerInterface $alias_manager
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AliasManagerInterface $alias_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function processRequest(Request $request, RouteMatchInterface $route_match, SerializerInterface $serializer) {
    // The query string parameter 'path' must exist in order to load the
    // node that correlates to path value provided.
    if (!$request->query->has('path')) {
      throw new HttpException(404);
    }
    $this->buildResponseObject($request);

    return $this->entity->toArray();
  }

  /**
   * Builds the response object to be returned. This will be a node.
   *
   * @param $request
   *
   * @return \Symfony\Component\HttpFoundation\Response
   */
  public function buildResponseObject($request) {
    $alias = $request->query->get('path');
    $path = $this->aliasManager->getPathByAlias($alias);

    // If $path does not contain /node/ it's a result of no alias existing
    // for any nodes in Drupal.
    if (!strstr('/node/', $path)) {
      throw new HttpException(404);
    }

    $parts = explode('/', $path);
    $this->entity = Node::load($parts[2]);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // @todo Change the autogenerated stub.
    return parent::getCacheContexts();
  }

  /**
   *
   */
  public function getCacheTags() {
    $tags = [];
    // Applied contexts can affect the cache tags when this plugin is
    // involved in caching, collect and return them.
    if ($this->entity instanceof CacheableDependencyInterface) {
      $tags = Cache::mergeTags($tags, $this->entity->getCacheTags());
    }
    /** @var \Drupal\Core\Cache\CacheableDependencyInterface $context */
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    // @todo Change the autogenerated stub.
    return parent::getCacheMaxAge();
  }

}
