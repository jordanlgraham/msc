<?php

namespace Drupal\msca_access\EventSubscriber;

use Drupal\Core\Url;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;

class AccessDeniedRedirectSubscriber extends HttpExceptionSubscriberBase {

  use StringTranslationTrait;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $user;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs an AccessDeniedRedirectSubscriber object.
   * 
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(AccountInterface $user, MessengerInterface $messenger) {
    $this->user = $user;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('messenger')
    );
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
      $this->messenger->addMessage($this->t('Please login to access this page.'));
      $event->setResponse($returnResponse);
    }
  }

}
