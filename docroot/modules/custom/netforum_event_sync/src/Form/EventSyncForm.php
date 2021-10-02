<?php

namespace Drupal\netforum_event_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\netforum_event_sync\EventSync;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EventSyncForm.
 */
class EventSyncForm extends ConfigFormBase {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * @var \Drupal\netforum_event_sync\EventSync
   */
  protected $sync;

  /**
   * Constructs an EventSyncForm object.
   * 
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   */
  public function __construct(ConfigFactoryInterface $configFactory, EventSync $sync, MessengerInterface $messenger) {
    parent::__construct($configFactory);
    $this->sync = $sync;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('netforum_event_sync.event_sync'),
      $container->get('messenger')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'netforum_event_sync.eventsync',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'event_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('netforum_event_sync.eventsync');
    $form['event_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Event Types'),
      '#description' => $this->t('A list of event types to pull'),
      '#default_value' => $config->get('event_types'),
    ];

    $form['sync_date'] = [
      '#type' => 'date',
      '#title' => $this->t('Sync date'),
      '#description' => $this->t('A date to manually sync events from. This will not affect future sync operations.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('netforum_event_sync.eventsync')
      ->set('event_types', $form_state->getValue('event_types'))
      ->save();
    try {
      $timestamp = NULL;
      if ($date = $form_state->getValue('sync_date')) {
        $dateObj = date_create($date);
        $timestamp = $dateObj->getTimestamp();
      }
      $count = $this->sync->syncEvents($timestamp);
      $message = $this->t('Synced @count events.', ['@count' => $count]);
      $this->messenger->addMessage($message);
    }
    catch (\Exception $exception) {
      $message = $this->t('Error syncing events: @err', ['@err' => $exception->getMessage()]);
      $this->messenger->addError($message);
      watchdog_exception('netforum_event_sync', $exception, 'Error syncing events');
    }
  }
}
