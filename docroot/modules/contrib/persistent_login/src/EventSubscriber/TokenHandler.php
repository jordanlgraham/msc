<?php

namespace Drupal\persistent_login\EventSubscriber;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\SessionConfigurationInterface;
use Drupal\persistent_login\CookieHelperInterface;
use Drupal\persistent_login\PersistentToken;
use Drupal\persistent_login\TokenException;
use Drupal\persistent_login\TokenManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event Subscriber to handle loading and setting tokens.
 *
 * @package Drupal\persistent_login
 */
class TokenHandler implements EventSubscriberInterface {

  /**
   * The token manager service.
   *
   * @var \Drupal\persistent_login\TokenManager
   */
  protected $tokenManager;

  /**
   * The cookie helper service.
   *
   * @var \Drupal\persistent_login\CookieHelper
   */
  protected $cookieHelper;

  /**
   * The session configuration.
   *
   * @var \Drupal\Core\Session\SessionConfigurationInterface
   */
  protected $sessionConfiguration;

  /**
   * The Entity Type Manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Config Factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Current User.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The persistent token of the current request.
   *
   * @var \Drupal\persistent_login\PersistentToken|null
   */
  protected $token;

  /**
   * Construct a token manager object.
   *
   * @param \Drupal\persistent_login\TokenManager $token_manager
   *   The token manager service.
   * @param \Drupal\persistent_login\CookieHelperInterface $cookie_helper
   *   The cookie helper service.
   * @param \Drupal\Core\Session\SessionConfigurationInterface $session_configuration
   *   The session configuration.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface|null $config_factory
   *   The Config Factory service.
   * @param \Drupal\Core\Session\AccountProxyInterface|null $current_user
   *   The Current User.
   */
  public function __construct(
    TokenManager $token_manager,
    CookieHelperInterface $cookie_helper,
    SessionConfigurationInterface $session_configuration,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory = NULL,
    AccountInterface $current_user = NULL
  ) {
    $this->tokenManager = $token_manager;
    $this->cookieHelper = $cookie_helper;
    $this->sessionConfiguration = $session_configuration;
    $this->entityTypeManager = $entity_type_manager;

    if (empty($config_factory)) {
      @trigger_error('config_factory will be a required parameter in persistent_login 2.x', E_USER_DEPRECATED);
      $config_factory = \Drupal::service('config.factory');
    }
    $this->configFactory = $config_factory;

    if (empty($current_user)) {
      @trigger_error('current_user will be a required parameter in persistent_login 2.x', E_USER_DEPRECATED);
      $current_user = \Drupal::currentUser();
    }
    $this->currentUser = $current_user;
  }

  /**
   * Specify subscribed events.
   *
   * @return array
   *   The subscribed events.
   */
  public static function getSubscribedEvents() {
    $events = [];

    // Must occur after AuthenticationSubscriber.
    $events[KernelEvents::REQUEST][] = ['loadTokenOnRequestEvent', 299];
    $events[KernelEvents::RESPONSE][] = ['setTokenOnResponseEvent'];

    return $events;
  }

  /**
   * Load a token on this request, if a persistent cookie is provided.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function loadTokenOnRequestEvent(RequestEvent $event) {

    if (!$event->isMainRequest()) {
      return;
    }

    $request = $event->getRequest();

    if ($this->cookieHelper->hasCookie($request)) {
      $this->token = $this->getTokenFromCookie($request);

      // Only validate the token if a user session has not been started.
      if (!empty($this->token) && $this->currentUser->isAnonymous()) {
        $this->token = $this->tokenManager->validateToken($this->token);

        if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
          try {
            // @todo make sure we are starting the user session properly.
            /** @var \Drupal\User\UserInterface $user */
            $user = $this->entityTypeManager->getStorage('user')
              ->load($this->token->getUid());
            user_login_finalize($user);
          }
          catch (PluginException $e) {
          }
        }
      }
    }
  }

  /**
   * Set or clear a token cookie on this response, if required.
   *
   * @param \Symfony\Component\HttpKernel\Event\ResponseEvent $event
   *   The response event.
   */
  public function setTokenOnResponseEvent(ResponseEvent $event) {

    if (!$event->isMainRequest()) {
      return;
    }

    if ($this->token) {
      $request = $event->getRequest();
      $response = $event->getResponse();
      $sessionOptions = $this->sessionConfiguration->getOptions($request);

      // New or updated token.
      if ($this->token->getStatus() === PersistentToken::STATUS_VALID) {
        $config = $this->configFactory->get('persistent_login.settings');
        if ($config->get('extend_lifetime') && $config->get('lifetime') > 0) {
          $this->token = $this->token->setExpiry(new \DateTime("now +" . $config->get('lifetime') . " day"));
        }

        $this->token = $this->tokenManager->updateToken($this->token);

        $response->headers->setCookie(
          Cookie::create(
            $this->cookieHelper->getCookieName($request),
            $this->token,
            $this->token->getExpiry(),
            '/', // @todo Path should probably match the base path.
            $sessionOptions['cookie_domain'],
            $sessionOptions['cookie_secure']
          )
        );
        $response->setPrivate();
      }
      elseif ($this->token->getStatus() === PersistentToken::STATUS_INVALID) {
        // Invalid token, or manually cleared token (e.g. user logged out).
        $this->tokenManager->deleteToken($this->token);
        $response->headers->clearCookie(
          $this->cookieHelper->getCookieName($request),
          '/', // @todo Path should probably match the base path.
          $sessionOptions['cookie_domain'],
          $sessionOptions['cookie_secure']
        );
        $response->setPrivate();
      }
      else {
        // Ignore token if status is STATUS_NOT_VALIDATED.
      }
    }
  }

  /**
   * Create a token object from the cookie provided in the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request that contains a persistent login cookie.
   *
   * @return \Drupal\persistent_login\PersistentToken|null
   *   A new PersistentToken object, or NULL if the cookie value was not valid.
   */
  public function getTokenFromCookie(Request $request) {
    $cookieValue = $this->cookieHelper->getCookieValue($request);
    // Token values are 43-character base-64 encoded, URL-safe strings.
    // @see \Drupal\Component\Utility\Crypt::hmacBase64()
    if (empty($cookieValue) || !preg_match('<[a-z0-9_-]+:[a-z0-9+_-]+>i', $cookieValue)) {
      return NULL;
    }
    return PersistentToken::createFromString($cookieValue);
  }

  /**
   * Create and store a new token for the specified user.
   *
   * @param int $uid
   *   The user id to associate the token to.
   */
  public function setNewSessionToken($uid) {
    try {
      $this->token = $this->tokenManager->createNewTokenForUser($uid);
    }
    catch (TokenException $e) {
      // Ignore error creating new token.
    }
  }

  /**
   * Mark the user's current token as invalid.
   *
   * This will cause the token to be removed from the database at the end of the
   * request.
   */
  public function clearSessionToken() {
    if ($this->token) {
      $this->token = $this->token->setInvalid();
    }
  }

}
