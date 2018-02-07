<?php

namespace Drupal\netforum_user_auth\Controller;

use Drupal\Core\Access\CsrfTokenGenerator;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\netforum_user_auth\Auth;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ExternalAuth extends ControllerBase {

  /**
   * @var \Drupal\Core\Access\CsrfTokenGenerator
   */
  private $csrf;

  /**
   * @var \Drupal\netforum_user_auth\Auth
   */
  private $auth;

  public function __construct(CsrfTokenGenerator $csrfTokenGenerator, Auth $auth) {
    $this->csrf = $csrfTokenGenerator;
    $this->auth = $auth;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('csrf_token'),
      $container->get('netforum_user_auth.auth')
    );
  }

  public function page(Request $request) {
    $build = [];

    $token = $request->get('token');

    $destination = $request->get('redirect');

    $nfDestination = $request->get('nfUrl');

    if (empty($token) || (empty($destination) && empty($nfDestination))) {
      throw new NotFoundHttpException();
    }

    if ($nfDestination) {
      $url = Url::fromUri(urldecode($nfDestination));
    }
    else {
      if ($destination === '<front>') {
        $url = Url::fromRoute('<front>');
      }
      else {
        $url = Url::fromUserInput($destination);
      }
    }

    $build['link'] = [
      '#type' => 'link',
      '#title' => $this->t('Click here if you are not redirected in 10 seconds.'),
      '#url' => $url,
      '#attributes' => [
        'id' => 'redirect-link',
      ],
    ];

    /** @var \Drupal\Core\Access\CsrfTokenGenerator $generator */
    // Generate variables to pass to the SSO script.
    $generator = \Drupal::service('csrf_token');
    $csrf = $generator->get($token);
    $login_url = 'https://netforum.avectra.com/eWeb/?Site=MSCA&' . urldecode($token);

    $settings = [
      'nfsso' => [
        'expire_endpoint' => Url::fromRoute('netforum_user_auth.expire_token')->toString(),
        'sso_token' => $token,
        'csrf_token' => $csrf,
        'login_url' => $login_url,
      ],
    ];

    $build['#attached']['library'][] = 'netforum_user_auth/external-sso';
    $build['#attached']['drupalSettings'] = $settings;

    return $build;
  }

  /**
   * Expire the token used for SSO so it can't be re-used.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   */
  public function expire(Request $request) {
    $csrf = $request->get('csrf');
    $token = $request->get('token');
    if (!$this->csrf->validate($csrf, $token)) {
      throw new AccessDeniedHttpException();
    }
    // Expire the token on Netforum.
    $token = explode('=', urldecode($token));
    try {
      $this->auth->expireSsoToken($token[1]);
    }
    catch (\Exception $exception) {

    }
    // Return 200 so the JS can complete the redirect.
    return new JsonResponse();
  }
}
