<?php

namespace Drupal\msca_job\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Routing\RequestContext;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class PostJobConfig extends FormBase {

  /**
   * @var \Drupal\Core\Path\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * @var \Drupal\Core\Routing\RequestContext
   */
  protected $requestContext;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'msca_job_config';
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(StateInterface $state, RequestContext $context, AliasManagerInterface $aliasManager,
                              PathValidatorInterface $pathValidator, MessengerInterface $messenger) {
    $this->state = $state;
    $this->requestContext = $context;
    $this->aliasManager = $aliasManager;
    $this->pathValidator = $pathValidator;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('state'),
      $container->get('router.request_context'),
      $container->get('path_alias.manager'),
      $container->get('path.validator'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['redirect'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Redirect URL'),
      '#description' => $this->t('Where to redirect the user after they submit the job post form.'),
      '#field_prefix' => $this->requestContext->getCompleteBaseUrl(),
    ];

    $form['notify'] = [
      '#type' => 'email',
      '#title' => $this->t('Notification email'),
      '#description' => $this->t('Email address to send the job posting email'),
    ];

    foreach ($form as $form_key => &$item) {
      if ($default = $this->state->get('msca_job_config_' . $form_key)) {
        $item['#default_value'] = $default;
      }
    }

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $form_state->setValueForElement($form['redirect'], $this->aliasManager->getPathByAlias($form_state->getValue('redirect')));
    // Validate redirect path.
    if (($value = $form_state->getValue('redirect')) && $value[0] !== '/') {
      $form_state->setErrorByName('redirect', $this->t("The path '%path' has to start with a slash.", ['%path' => $form_state->getValue('redirect')]));

    }
    if (!$this->pathValidator->isValid($form_state->getValue('redirect'))) {
      $form_state->setErrorByName('redirect', $this->t("The path '%path' is either invalid or you do not have access to it.", ['%path' => $form_state->getValue('redirect')]));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->state->setMultiple([
      'msca_job_config_redirect' => $form_state->getValue('redirect'),
      'msca_job_config_notify' => $form_state->getValue('notify'),
    ]);
    $message = 'The configuration options have been saved.';
    $this->messenger->addMessage($this->t($message));
  }

}
