<?php

namespace Drupal\forward\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Form\BaseFormIdInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Mail\MailManager;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\Core\Utility\Token;
use Drupal\forward\Event\EntityForwardEvent;
use Drupal\forward\Event\EntityPreforwardEvent;
use Egulias\EmailValidator\EmailValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Forward a page to a friend.
 */
class ForwardForm extends FormBase implements BaseFormIdInterface {

  /**
   * The entity being forwarded.
   *
   * @var Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

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
   * @var \Symfony\Component\EventDispatcher
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
   * @var \Drupal\Core\Utility\LinkGeneratorInterface
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
   * The settings for this form.
   *
   * @var array
   */
  protected $settings;

  /**
   * Whether the form is built in a details element.
   *
   * @var bool
   */
  protected $details;

  /**
   * Constructs a Forward Form.
   *
   * @param mixed $entity
   *   The entity being forwarded or NULL.
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
   * @param \Drupal\Core\Utility\LinkGeneratorInterface $link_generator
   *   The link generation service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param Egulias\EmailValidator\EmailValidator $email_validator
   *   The email validation service.
   */
  public function __construct(
    $entity,
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
    LinkGeneratorInterface $link_generator,
    TimeInterface $time,
    EmailValidator $email_validator) {

    $this->time = $time;
    $this->entity = $entity ?? $route_match->getParameter('entity');
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

    $settings = $this->config('forward.settings')->get();
    $this->settings = $settings;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      NULL,
      $container->get('current_route_match'),
      $container->get('module_handler'),
      $container->get('entity_type.manager'),
      $container->get('request_stack'),
      $container->get('database'),
      $container->get('token'),
      $container->get('flood'),
      $container->get('account_switcher'),
      $container->get('renderer'),
      $container->get('event_dispatcher'),
      $container->get('plugin.manager.mail'),
      $container->get('link_generator'),
      $container->get('datetime.time'),
      $container->get('email.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    if (is_null($this->entity)) {
      throw new \UnexpectedValueException('Null value for entity in Forward Form');
    }

    return 'forward_form_' . $this->entity->getEntityTypeId() . '_' . $this->entity->id();
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFormId() {
    return 'forward_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['forward.form'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $details = FALSE) {
    $this->details = $details;
    $form_state->set('#entity', $this->entity);
    $settings = $this->settings;
    $token = $this->getToken($form_state);
    $langcode = $this->entity->language()->getId();

    // Build the form.
    $form['#title'] = $this->tokenService->replace($settings['forward_form_title'], $token, ['langcode' => $langcode]);
    if ($details) {
      // Inline form inside a details element.
      $form['message'] = [
        '#type' => 'details',
        '#title' => $this->tokenService->replace($settings['forward_form_title'], $token, ['langcode' => $langcode]),
        '#description' => '',
        '#open' => FALSE,
      ];
    }
    else {
      // Set the page title dynamically.
      $form['#title'] = $this->tokenService->replace($settings['forward_form_title'], $token, ['langcode' => $langcode]);
    }
    $form['message']['instructions'] = [
      '#markup' => $this->tokenService->replace($settings['forward_form_instructions'], $token, ['langcode' => $langcode]),
    ];
    $form['message']['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your email address'),
      '#maxlength' => 254,
      '#required' => TRUE,
    ];
    $form['message']['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Your name'),
      '#maxlength' => 128,
      '#required' => TRUE,
    ];
    if ($settings['forward_max_recipients'] > 1) {
      $form['message']['recipient'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Send to email address'),
        '#default_value' => '',
        '#cols' => 50,
        '#rows' => 2,
        '#description' => $this->t('Enter multiple addresses on separate lines or separate them with commas.'),
        '#required' => TRUE,
      ];
    }
    else {
      $form['message']['recipient'] = [
        '#type' => 'email',
        '#title' => $this->t('Send to'),
        '#maxlength' => 254,
        '#description' => $this->t('Enter the email address of the recipient.'),
        '#required' => TRUE,
      ];
    }
    if ($settings['forward_form_display_page']) {
      $form['message']['page'] = [
        '#type' => 'item',
        '#title' => $this->t('You are going to email the following:'),
        '#markup' => $this->linkGenerator->generate($this->entity->label(), $this->entity->toUrl()),
      ];
    }
    if ($settings['forward_form_display_subject']) {
      $form['message']['subject'] = [
        '#type' => 'item',
        '#title' => $this->t('The message subject will be:'),
        '#markup' => $this->tokenService->replace($settings['forward_email_subject'], $token, ['langcode' => $langcode]),
      ];
    }
    if ($settings['forward_form_display_body']) {
      $form['message']['body'] = [
        '#type' => 'item',
        '#title' => $this->t('The introductory message text will be:'),
        '#markup' => $this->tokenService->replace($settings['forward_email_message'], $token, ['langcode' => $langcode]),
      ];
    }
    if ($settings['forward_personal_message']) {
      $form['message']['message'] = [
        '#type' => 'textarea',
        '#title' => $this->t('Your personal message'),
        '#default_value' => '',
        '#cols' => 50,
        '#rows' => 5,
        '#description' => $settings['forward_personal_message_filter'] ? $this->t('These HTML tags are allowed in this field: @tags.', ['@tags' => $settings['forward_personal_message_tags']]) : $this->t('HTML is not allowed in this field.'),
        '#required' => ($settings['forward_personal_message'] == 2),
      ];
    }
    if ($settings['forward_form_allow_plain_text']) {
      $form['message']['email_format'] = [
        '#type' => 'select',
        '#title' => $this->t('E-mail format'),
        '#default_value' => 'html',
        '#options' => [
          'html' => $this->t('HTML'),
          'plain_text' => $this->t('Plain text'),
        ],
        '#required' => TRUE,
      ];
    }
    if ($details) {
      // When using details, place submit button inside it.
      $form['message']['actions'] = ['#type' => 'actions'];
      $form['message']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send Message'),
        '#weight' => 100,
      ];
    }
    else {
      // When using a separate form page, use actions directly.
      $form['actions'] = ['#type' => 'actions'];
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Send Message'),
      ];
    }

    // Default name and email address to logged in user.
    if ($this->currentUser()->isAuthenticated()) {
      if ($this->currentUser()->hasPermission('override email address')) {
        $form['message']['email']['#default_value'] = $this->currentUser()->getEmail();
      }
      else {
        // User not allowed to change sender email address.
        $form['message']['email']['#type'] = 'hidden';
        $form['message']['email']['#value'] = $this->currentUser()->getEmail();
      }
      $form['message']['name']['#default_value'] = $this->currentUser()->getDisplayName();
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!$this->currentUser()->hasPermission('override flood control')) {
      $event = $this->getFloodControlEventName();
      if (!$this->floodInterface->isAllowed($event, $this->settings['forward_flood_control_limit'])) {
        $message = new FormattableMarkup($this->settings['forward_flood_control_error'], ['@number' => $this->settings['forward_flood_control_limit']]);
        $form_state->setErrorByName('', $message);
      }
    }

    $recipients = $this->splitEmailAddresses($form_state->getValue('recipient'));
    if (count($recipients) > $this->settings['forward_max_recipients']) {
      $message = $this->getStringTranslation()->formatPlural(
        $this->settings['forward_max_recipients'],
        "You can't send email to more than 1 recipient at a time.",
        "You can't send email to more than @count recipients at a time."
      );
      $form_state->setErrorByName('recipient', $message);
    }

    foreach ($recipients as $recipient) {
      if (!$this->emailValidator->isValid($recipient)) {
        $message = $this->t('The email address %mail is not valid.', ['%mail' => $recipient]);
        $form_state->setErrorByName('recipient', $message);
      }
    }
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $entity = $this->entity;

    // Get form values.
    $recipients = $this->splitEmailAddresses($form_state->getValue('recipient'));

    // Decide if we're sending HTML or plain text. Default to HTML.
    $email_format = 'html';
    if ($form_state->getValue('email_format') == 'plain_text') {
      $email_format = 'plain_text';
    }

    // Use the entity language to drive translation.
    $langcode = $entity->language()->getId();

    // Switch to anonymous user session if logged in,
    // unless bypassing access control.
    $switched = FALSE;
    if ($this->currentUser()
      ->isAuthenticated() && empty($this->settings['forward_bypass_access_control'])) {
      $this->accountSwitcher->switchTo(new AnonymousUserSession());
      $switched = TRUE;
    }

    try {
      // Build the message subject line.
      $token = $this->getToken($form_state);
      $params['subject'] = $this->tokenService->replace($this->settings['forward_email_subject'], $token, ['langcode' => $langcode]);

      // Build the entity content.
      $view_mode = '';
      $elements = [];
      if ($entity->access('view')) {
        $view_builder = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId());
        foreach (['forward', 'teaser', 'full'] as $view_mode) {
          if ($this->isValidDisplay($entity, $view_mode)) {
            $elements = $view_builder->view($entity, $view_mode, $langcode);
          }
          if (!empty($elements)) {
            break;
          }
        }
      }
      // Render the page content.
      $content = $this->renderer->render($elements);

      // Build the header line.
      $markup = $this->tokenService->replace(
        $this->settings['forward_email_message'],
        $token,
        ['langcode' => $langcode]
      );
      $header = ['#markup' => $markup];

      // Build the footer line.
      $markup = $this->tokenService->replace(
        $this->settings['forward_email_footer'],
        $token,
        ['langcode' => $langcode]
      );
      $footer = ['#markup' => $markup];

      // Build the personal message if present.
      $message = '';
      if ($this->settings['forward_personal_message']) {
        if ($this->settings['forward_personal_message_filter']) {
          // HTML allowed in personal message, so filter
          // out anything but the allowed tags.
          $raw_values = $form_state->getUserInput();
          $allowed_tags = explode(',', $this->settings['forward_personal_message_tags']);
          $message = !empty($raw_values['message']) ? Xss::filter($raw_values['message'], $allowed_tags) : '';
          $message = ['#markup' => nl2br($message)];
        }
        else {
          // HTML not allowed in personal message, so use the
          // sanitized version converted to plain text.
          $message = ['#plain_text' => $form_state->getValue('message')];
        }
      }

      // Build the email body.
      $recipient = implode(',', $recipients);
      $render_array = [
        '#theme' => 'forward',
        '#email' => $form_state->getValue('email'),
        '#recipient' => $recipient,
        '#header' => $header,
        '#message' => $message,
        '#settings' => $this->settings,
        '#entity' => $entity,
        '#content' => $content,
        '#view_mode' => $view_mode,
        '#email_format' => $email_format,
        '#footer' => $footer,
      ];

      // Allow modules to alter the render array for the message.
      $this->moduleHandler->alter('forward_mail_pre_render', $render_array, $form_state);

      // Render the message.
      $params['body'] = $this->renderer->render($render_array);

      // Apply filters such as Pathologic for link correction.
      $format_setting = "forward_filter_format_$email_format";
      if ($this->settings[$format_setting]) {
        // This filter was setup by the Forward administrator for this
        // purpose only, whose permission to run the filter was checked
        // at that time. Therefore, no need to check filter access again here.
        $params['body'] = check_markup($params['body'], $this->settings[$format_setting], $langcode);
      }

      // Allow modules to alter the final message body.
      $this->moduleHandler->alter('forward_mail_post_render', $params['body'], $form_state);
    }
    catch (Exception $e) {
      if ($switched) {
        $this->accountSwitcher->switchBack();
        $switched = FALSE;
      }
      $this->logger('forward')->error($e->getMessage());
    }

    // Switch back to logged in user if necessary.
    if ($switched) {
      $this->accountSwitcher->switchBack();
    }

    // Build the from email address and Reply-To.
    $from = $this->settings['forward_email_from_address'];
    if (empty($from)) {
      $from = $this->config('system.site')->get('mail');
    }
    if (empty($from)) {
      $site_mail = ini_get('sendmail_from');
    }
    $params['headers']['Reply-To'] = trim(Unicode::mimeHeaderEncode($form_state->getValue('name')) . ' <' . $form_state->getValue('email') . '>');

    // Handle plain text vs. HTML setting.
    if ($email_format == 'plain_text') {
      $params['plain_text'] = TRUE;
    }

    // Prepare for Event dispatch.
    $account = $this->entityTypeManager->getStorage('user')
      ->load($this->currentUser()->id());

    // Event dispatch - before forwarding.
    $event = new EntityPreforwardEvent($account, $entity, [
      'account' => $account,
      'entity' => $entity,
    ]);
    $this->eventDispatcher->dispatch(EntityPreforwardEvent::EVENT_NAME, $event);

    // Send the email to the recipient. Set the key so the Forward mail plugin
    // is only used if the default plugin is still the core PHP Mail plugin.
    // If another module such as SMTP has been enabled, then that will be used.
    $mail_configuration = $this->config('system.mail')->get('interface');
    $key = ($mail_configuration['default'] == 'php_mail') ? 'send_entity' : 'mail_entity';
    foreach ($recipients as $recipient) {
      $this->mailer->mail('forward', $key, $recipient, $langcode, $params, $from);
    }

    // Log this for tracking purposes.
    $this->logEvent($entity);

    // Register event for flood control.
    $event = $this->getFloodControlEventName();
    $this->floodInterface->register($event);

    // Event dispatch - after forwarding.
    $event = new EntityForwardEvent($account, $entity, [
      'account' => $account,
      'entity' => $entity,
    ]);
    $this->eventDispatcher->dispatch(EntityForwardEvent::EVENT_NAME, $event);

    // Allow modules to post process the forward.
    $this->moduleHandler->invokeAll('forward_entity', [
      $account,
      $entity,
      $form_state,
    ]);

    // Display a confirmation message.
    $message = $this->tokenService->replace($this->settings['forward_form_confirmation'], $token, ['langcode' => $langcode]);
    if ($message) {
      $this->messenger()->addMessage($message);
    }

    // Redirect back to entity page unless a redirect is already set.
    if (!$this->details) {
      if (!$form_state->getRedirect()) {
        $form_state->setRedirectUrl($entity->toUrl());
      }
    }
  }

  /**
   * Clean a string.
   */
  protected function cleanString($string) {
    // Strip embedded URLs.
    $string = preg_replace('|https?://www\.[a-z\.0-9]+|i', '', $string);
    $string = preg_replace('|www\.[a-z\.0-9]+|i', '', $string);
    return $string;
  }

  /**
   * Splits a string into email addresses via comma or newline separators.
   *
   * @param string $text
   *   The string that contains one or more email addresses.
   *
   * @return array
   *   A array of unique email addresses.
   */
  protected function splitEmailAddresses($text) {
    $emails = preg_split('/[;, \r\n]+/', $text);
    $emails = array_filter($emails);
    $emails = array_unique($emails);
    return $emails;
  }

  /**
   * Get a token.
   */
  protected function getToken(FormStateInterface $form_state = NULL) {
    $token = [];
    if ($form_state && $form_state->getValue('name')) {
      // Harden the name field against abuse.
      // @see https://www.drupal.org/node/2793891
      $token = ['forward' => ['sender_name' => $this->cleanString($form_state->getValue('name'))]];
    }
    elseif ($this->currentUser()->isAuthenticated()) {
      $token = [
        'forward' => [
          'sender_name' => $this->currentUser()
            ->getDisplayName(),
        ],
      ];
    }
    if ($form_state && $form_state->getValue('email')) {
      $token['forward']['sender_email'] = $form_state->getValue('email');
    }
    elseif ($this->currentUser()->isAuthenticated()) {
      $token['forward']['sender_email'] = $this->currentUser()->getEmail();
    }
    if ($form_state) {
      $token['forward']['entity'] = $form_state->get('#entity');
    }
    if ($form_state && $form_state->getValue('recipient')) {
      $token['forward']['recipients'] = $this->splitEmailAddresses($form_state->getValue('recipient'));
    }
    // Allow other modules to add more tokens.
    if ($extra_tokens = $this->moduleHandler->invokeAll('forward_token', [$form_state])) {
      $token += $extra_tokens;
    }
    return $token;
  }

  /**
   * Get the event name used for Flood control.
   */
  protected function getFloodControlEventName() {
    return 'forward.send';
  }

  /**
   * Determine if a given display is valid for an entity.
   */
  protected function isValidDisplay(EntityInterface $entity, $view_mode) {
    // Assume the display is valid.
    $valid = FALSE;

    // Build display name.
    if ($entity->getEntityType()->hasKey('bundle')) {
      // Bundled entity types, e.g. node.
      $display_name = $entity->getEntityTypeId() . '.' . $entity->bundle() . '.' . $view_mode;
    }
    else {
      // Entity types without bundles, e.g. user.
      $display_name = $entity->getEntityTypeId() . '.' . $view_mode;
    }

    // Attempt load.
    $display = $this->entityTypeManager->getStorage('entity_view_display')->load($display_name);
    if ($display) {
      // If the display loads, it exists in system
      // configuration, and status can be checked.
      if ($display->status()) {
        $valid = TRUE;
      }
    }

    return $valid;
  }

  /**
   * Logging.
   */
  protected function logEvent(EntityInterface $entity) {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_id = $entity->id();

    $uid = $this->currentUser()->id();
    $path = substr($entity->toUrl()->toString(), 1);
    $ip_address = $this->requestStack->getCurrentRequest()->getClientIp();
    $timestamp = $this->time->getRequestTime();

    // Insert into log.
    $this->database->insert('forward_log')
      ->fields([
        'type' => $entity_type,
        'id' => $entity_id,
        'path' => $path,
        'action' => 'SENT',
        'timestamp' => $timestamp,
        'uid' => $uid,
        'hostname' => $ip_address,
      ])
      ->execute();

    // Update statistics.
    $this->database->merge('forward_statistics')
      ->key([
        'type' => $entity_type,
        'bundle' => $bundle,
        'id' => $entity_id,
      ])
      ->fields([
        'forward_count' => 1,
        'last_forward_timestamp' => $timestamp,
      ])
      ->expression('forward_count', 'forward_count + 1')
      ->execute();
  }

}
