<?php

namespace Drupal\ymapi\Controller;

use Psr\Log\LoggerInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\key\KeyRepositoryInterface;
use Drupal\ymapi\Event\YmApiEvents;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\ymapi\Event\YmApiWebhookEvent;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class YmApiWebhooksController.
 */
class YmApiController extends ControllerBase {

  /**
   * The Immutable Config Object.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The KeyRepositoryInterface.
   *
   * @var \Drupal\key\KeyRepositoryInterface
   */
  protected $keyRepository;

  /**
   * Psr\Log\LoggerInterface definition.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Symfony\Component\HttpFoundation\RequestStack definition.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Symfony\Component\EventDispatcher\EventDispatcherInterface definition.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * Enable or disable debugging.
   *
   * @var bool
   */
  protected $debug = FALSE;

  /**
   * Your Membership webhook secret token.
   *
   * @var string
   */
  protected $webhookSecretToken;

  /**
   * Your Membership webhook verification token.
   *
   * @var string
   */
  protected $webhookVerificationToken;

  /**
   * Constructs a new WebhookController object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger interface.
   * @param \Drupal\key\KeyRepositoryInterface $key_repository
   *   Key repository interface.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory interface.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request stack.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   Event dispatcher interface.
   */
  public function __construct(
    LoggerInterface $logger,
    KeyRepositoryInterface $key_repository,
    ConfigFactoryInterface $config_factory,
    RequestStack $request_stack,
    EventDispatcherInterface $event_dispatcher
  ) {
    $this->logger = $logger;
    $this->requestStack = $request_stack;
    $this->eventDispatcher = $event_dispatcher;
    $this->keyRepository = $key_repository;
    $this->config = $config_factory->get('apitools.client.ymapi');
    $this->webhookSecretToken = $this->getKeyValue('event_secret_token');
    $this->webhookVerificationToken = $this->getKeyValue('event_verification_token');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('logger.channel.ymapi'),
      $container->get('key.repository'),
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('event_dispatcher')
    );
  }

  /**
   * Capture the incoming payload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A simple JSON response.
   */
  public function capture(Request $request) {
    // Capture the payload.
    $payload = $request->getContent();

    // Check if the payload is empty.
    if (empty($payload)) {
      $message = 'The Your Membership webhook payload was missing.';
      $this->logger->notice($message);
      $response = [
        'success' => FALSE,
        'message' => $message,
        'data' => [],
      ];
      return new JsonResponse($response, 400);
    }

    // JSON decode the payload.
    // @todo Error check the payload for more certainty.
    $data = Json::decode($payload);

    // Ability to debug the incoming payload.
    if ($this->debug) {
      $this->logger->debug('<pre><code>' . print_r($data, TRUE) . '</code></pre>');
    }

    // Dispatch Event.
    // Allows other modules to respond.
    // Var $data['event'] = Name of webhook event from Your Membership.
    // Var $data['payload'] = Payload data from Your Membership.
    // Var $request = The complete request from Your Membership.
    $dispatch = new YmApiWebhookEvent($data['event'], $data['payload'], $request);
    $this->eventDispatcher->dispatch($dispatch, YmApiEvents::WEBHOOK_POST);

    // Check to see if this is a validation event and respond appropriately.
    if ($this->isValidationEvent($data)) {
      return $this->validate($data);
    }

    $response = [
      'success' => TRUE,
      'message' => 'Webhook payload captured!',
      'data' => [],
    ];
    return new JsonResponse($response);
  }

  /**
   * Compares local webhook token to incoming.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   AccessResult allowed or forbidden.
   */
  public function authorize() {
    $request = $this->requestStack->getCurrentRequest();

    // This method will become default check ofter Oct. 2023.
    if (
      $this->hasHeader($request, 'x-zm-signature')
      && !empty($this->webhookSecretToken)
    ) {
      $zoomSignature = $this->getHeader($request, 'x-zm-signature');
      $createdSignature = $this->createSignature($request);
      // Check to see if signatures match.
      if ($zoomSignature === $createdSignature) {
        return AccessResult::allowed();
      }
    }

    // Authorization header is set and matches. Allow.
    if (
      $this->hasHeader($request, 'authorization') &&
      $this->getHeader($request, 'authorization') === $this->webhookVerificationToken
    ) {
      return AccessResult::allowed();
    }

    $this->logger->notice('The Your Membership API webhook post could not be verified with the Event Secret Token or Event Verification Token. Check to see if the Your Membership API module has been configured correctly.');
    return AccessResult::forbidden();
  }

  /**
   * Validates the webhook.
   *
   * See https://marketplace.zoom.us/docs/api-reference/webhook-reference/
   *
   * @param array $data
   *   The decoded JSON payload from the webhook.
   *
   * @return array
   *   An array to match the validation response expected by zoom.
   */
  public function validate(array $data) {
    if (empty($this->webhookSecretToken)) {
      $this->logger->notice('The Event Secret Token must be set in order to validate your webhook.');
    }
    $plainToken = $data['payload']['plainToken'];
    $encryptedToken = hash_hmac(
      'sha256',
      $plainToken,
      $this->webhookSecretToken
    );
    $response = [
      'plainToken' => $plainToken,
      'encryptedToken' => $encryptedToken,
    ];

    return new JsonResponse($response);
  }

  /**
   * Checks to see if the event is a validation event.
   *
   * See https://marketplace.zoom.us/docs/api-reference/webhook-reference/
   *
   * @param array $data
   *   The decoded JSON payload from the webhook.
   *
   * @return bool
   *   TRUE if equal to endpoint.url_validation | FALSE otherwise.
   */
  public function isValidationEvent(array $data) {
    if (
      isset($data['event'], $data['payload']['plainToken'])
      && $data['event'] === 'endpoint.url_validation'
    ) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Create a signature to compare to the one Your Membership sent.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return string
   *   The created signature for comparison.
   */
  protected function createSignature(Request $request) {
    $timestamp = $this->getHeader($request, 'x-zm-request-timestamp');
    $body = Json::decode($request->getContent());
    // Json::encode is not flexible enough for our needs.
    $body = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    // Create a signature to compare with the incoming signature.
    $message = "v0:$timestamp:$body";
    $hashForVerify = hash_hmac('sha256', $message, $this->webhookSecretToken);
    return "v0=$hashForVerify";
  }

  /**
   * Checks for a specific header in the incoming request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param string $header
   *   The name of the header being checked.
   *
   * @return bool
   *   TRUE if it exists. FALSE otherwise.
   */
  protected function hasHeader(Request $request, $header) {
    // Check for the authorization header provided by Your Membership.
    if ($request->headers->has($header)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Gets the value of a specific header from the incoming request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   * @param string $header
   *   The name of the header being checked.
   *
   * @return string
   *   The value of the header.
   */
  protected function getHeader(Request $request, $header) {
    return $request->headers->get($header);
  }

  /**
   * Return a KeyValue.
   *
   * @param string $whichConfig
   *   Name of the config in which the key name is stored.
   *
   * @return mixed
   *   Null or string.
   */
  protected function getKeyValue($whichConfig) {
    if (empty($this->config->get($whichConfig))) {
      return NULL;
    }
    $whichKey = $this->config->get($whichConfig);
    $keyValue = $this->keyRepository->getKey($whichKey)->getKeyValue();

    if (empty($keyValue)) {
      return NULL;
    }

    return $keyValue;
  }
}
