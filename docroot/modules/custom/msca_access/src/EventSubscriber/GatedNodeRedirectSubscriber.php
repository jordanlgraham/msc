<?php

namespace Drupal\msca_access\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class GatedNodeRedirectSubscriber implements EventSubscriberInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => [
        ['redirectGatedNodes']
      ],
    ];
  }

  /**
   * Check a request to see if it's a gated node.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   */
  public function redirectGatedNodes(GetResponseEvent $event) {
    $request = $event->getRequest();

    if ($request->attributes->get('_route') !== 'entity.node.canonical') {
      return;
    }
    /** @var \Drupal\node\NodeInterface $node */
    $node = $request->attributes->get('node');
    /** @var \Drupal\Core\Session\AccountProxyInterface $user */
    $user = \Drupal::currentUser();
    if (!$node->hasField('field_gated') ||
      (int)$node->field_gated->value !== 1 ||
      $user->hasPermission('access gated content') ||
      $user->hasPermission('bypass node access')) {
      return;
    }
    $dest_url = Url::fromRoute('entity.node.canonical', ['node' => $node->id()]);
    $redirect_url = Url::fromRoute('msca_access.login', [], ['query' => ['destination' => $dest_url->toString()]]);
    $response = new CacheableRedirectResponse($redirect_url->toString());
    $response->addCacheableDependency($node);
    $md = $response->getCacheableMetadata();
    $md->addCacheableDependency($user);
    $md->addCacheContexts(['user.permissions']);
    $event->setResponse($response);
  }

}
