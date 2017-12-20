<?php

namespace Drupal\msca_access\EventSubscriber;

use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AccessDeniedRedirectSubscriber extends HttpExceptionSubscriberBase {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  public function __construct(AccountInterface $user) {
    $this->user = $user;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats() {
    return ['html'];
  }

  public function on403(GetResponseForExceptionEvent $event) {
    $request = $event->getRequest();
    $route_name = $request->attributes->get('_route');
    // Don't redirect on the login page (endless loop).
    $is_not_login = ($route_name !== 'user.login');
    if ($is_not_login && $this->user->isAnonymous()) {
      $query = $request->query->all();
      $query['destination'] = Url::fromRoute('<current>')->toString();
      $login_uri = Url::fromRoute('user.login', [], ['query' => $query])->toString();
      $returnResponse = new RedirectResponse($login_uri);
      drupal_set_message($this->t('Please login to access this page.'));
      $event->setResponse($returnResponse);
    }
  }

}
