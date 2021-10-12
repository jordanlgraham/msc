<?php

namespace Drupal\forward\Services;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Utility\LinkGenerator;
use Drupal\Core\Utility\Token;
use Drupal\forward\Form\ForwardForm;
use Egulias\EmailValidator\EmailValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Defines a class for building markup for a Forward inline form on an entity.
 */
class ForwardFormBuilder implements ForwardFormBuilderInterface {

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * The flood interface.
   *
   * @var \Drupal\Core\Flood\FloodInterface
   */
  protected $floodInterface;

  /**
   * The account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected $accountSwitcher;

  /**
   * The render service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The event dispatcher service.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The mail service.
   *
   * @var \Drupal\Core\Mail\MailManager
   */
  protected $mailer;

  /**
   * The link generation service.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $linkGenerator;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The email validation service.
   *
   * @var Egulias\EmailValidator\EmailValidator
   */
  protected $emailValidator;

  /**
   * Constructs a ForwardFormBuilder object.
   *
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   * @param Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The core route match interface.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   * @param \Drupal\Core\Flood\FloodInterface $flood_interface
   *   The flood interface.
   * @param \Drupal\Core\Session\AccountSwitcherInterface $account_switcher
   *   The account switcher service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render service.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Mail\MailManager $mailer
   *   The mail service.
   * @param \Drupal\Core\Utility\LinkGenerator $link_generator
   *   The link generation service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param Egulias\EmailValidator\EmailValidator $email_validator
   *   The email validation service.
   */
  public function __construct(FormBuilderInterface $form_builder,
    RouteMatchInterface $route_match,
    ModuleHandlerInterface $module_handler,
    EntityTypeManagerInterface $entity_type_manager,
    RequestStack $request_stack,
    Connection $database,
    Token $token_service,
    FloodInterface $flood_interface,
    AccountSwitcherInterface $account_switcher,
    RendererInterface $renderer,
    ContainerAwareEventDispatcher $event_dispatcher,
    MailManager $mailer,
    LinkGenerator $link_generator,
    TimeInterface $time,
    EmailValidator $email_validator) {

    $this->time = $time;
    $this->formBuilder = $form_builder;
    $this->routeMatch = $route_match;
    $this->moduleHandler = $module_handler;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->database = $database;
    $this->tokenService = $token_service;
    $this->floodInterface = $flood_interface;
    $this->accountSwitcher = $account_switcher;
    $this->renderer = $renderer;
    $this->eventDispatcher = $event_dispatcher;
    $this->mailer = $mailer;
    $this->linkGenerator = $link_generator;
    $this->emailValidator = $email_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder'),
      $container->get('current_route_match'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('request'),
      $container->get('database'),
      $container->get('token'),
      $container->get('flood'),
      $container->get('account.switcher'),
      $container->get('renderer'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.mail'),
      $container->get('link_generator'),
      $container->get('datetime.time'),
      $container->get('email_validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(EntityInterface $entity) {
    $render_array = [];

    if ($entity->access('view')) {
      $form = new ForwardForm(
        $entity,
        $this->routeMatch,
        $this->moduleHandler,
        $this->entityTypeManager,
        $this->requestStack,
        $this->database,
        $this->tokenService,
        $this->floodInterface,
        $this->accountSwitcher,
        $this->renderer,
        $this->eventDispatcher,
        $this->mailer,
        $this->linkGenerator,
        $this->time,
        $this->emailValidator
      );

      $render_array = $this->formBuilder->getForm($form);
    }

    return $render_array;
  }

  /**
   * {@inheritdoc}
   */
  public function buildInlineForm(EntityInterface $entity) {
    $render_array = [];

    if ($entity->access('view')) {
      $form = new ForwardForm(
        $entity,
        $this->routeMatch,
        $this->moduleHandler,
        $this->entityTypeManager,
        $this->requestStack,
        $this->database,
        $this->tokenService,
        $this->floodInterface,
        $this->accountSwitcher,
        $this->renderer,
        $this->eventDispatcher,
        $this->mailer,
        $this->linkGenerator,
        $this->time,
        $this->emailValidator
      );

      $details = TRUE;
      $render_array = $this->formBuilder->getForm($form, $details);
    }

    return $render_array;
  }

}
