<?php

namespace Drupal\username_enumeration_prevention;

use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Modifies user-related routes to respond with 404 rather than 403.
 *
 * @package Drupal\username_enumeration_prevention
 */
class UserRouteEventSubscriber implements EventSubscriberInterface {

  /**
   * Route provider.
   *
   * @var \Drupal\Core\Routing\RouteProviderInterface
   */
  protected $routeProvider;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(RouteProviderInterface $routeProvider, EntityTypeManagerInterface $entityTypeManager) {
    $this->routeProvider = $routeProvider;
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public function onException(GetResponseForExceptionEvent $event) {
    $userLinkTemplates = $this->entityTypeManager->getDefinition('user')
      ->getLinkTemplates();

    // @todo Cache the result of this variable somewhere.
    $userRouteIds = ['user.cancel_confirm'];
    foreach ($userLinkTemplates as $path) {
      $routes = $this->routeProvider->getRoutesByPattern($path);
      $userRouteIds = array_merge($userRouteIds, array_keys($routes->all()));
    }

    $routeMatch = RouteMatch::createFromRequest($event->getRequest());
    if ($event->getException() instanceof AccessDeniedHttpException && in_array($routeMatch->getRouteName(), $userRouteIds)) {
      $event->setException(new NotFoundHttpException());
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::EXCEPTION] = 'onException';
    return $events;
  }

}
