<?php

namespace Drupal\netforum_event_sync\Form;

use Drupal;
use Drupal\Core\Form\FormBase;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\netforum_event_sync\EventSync;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a NetForum Event Sync form.
 */
class EventSyncForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs an EventSyncForm object.
   * 
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'netforum_event_sync_event_sync';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $eventTypes = \Drupal::service('netforum_event_sync.event_sync')->getEventTypes();

    $form['event_types'] = [
      '#type' => 'select',
      '#title' => $this->t('Event Type(s)'),
      '#description' => $this->t('Select one or more event types to sync'),
      '#multiple' => TRUE,
      '#options' => $eventTypes,
    ];

    $form['date'] = [
      '#type' => 'date',
      '#title' => $this->t('Sync From Date'),
      '#description' => $this->t('Enter starting date for event sync')
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    // Placeholder.
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $batch = $this->generateBatchByDate($form_state);

    try {
      batch_set($batch);
    }
    catch (\Exception $exception) {
      $message = 'Unable to complete event sync. See logs for error.';
      $this->messenger->addError($this->t($message));
      $form_state->setRebuild(TRUE);
    }
  }
  
  /**
   * {@inheritdoc}
   */
  public function generateBatchByDate($form_state) {
    $operations = [];

    $start_date = time();
    if(!empty($form_state->getValue('date'))) {
      $start_date = strtotime($form_state->getValue('date'));
    }

    $event_type_tids = $form_state->getValue('event_types');
    $types = [];
    $terms = $this->entityTypeManager->getStorage('taxonomy_term')->loadMultiple($event_type_tids);
    foreach ($terms as $tid => $term) {
      $types[$tid] = $term->label();
    }
    $start = new \DateTime();
    $start->setTimestamp($start_date);
    $start->setTime('0', '0', '0');
    $operations[] = [self::class . '::importEventsBatch', [$types, $start->getTimestamp()]];
    
    $batch = [
      'title' => $this->t('Sync events'),
      'operations' => $operations,
      'finished' => 'netforum_event_sync_finished',
    ];

    return $batch;
  }

  /**
   * {@inheritdoc}
   */
  public static function importEventsBatch($eventTypes, $start_date, &$context) {
    $events = [];
    /** @var EventSync $sync */
    $sync = \Drupal::service('netforum_event_sync.event_sync');
    $start_formatted = date(EventSync::DATE_FORMAT, $start_date);
    $context['message'] = Html::escape("Syncing from $start_formatted");
    try {
      $events = $sync->getEvents($start_date);
      if (empty($events)) {
        return TRUE;
      }
    }
    catch (\Exception $exception) {
      $msg = $exception->getMessage();
      $context['results']['errors']['events'][] = "Error retrieving event changes for period $start_formatted to $end_formatted: $msg";
      return TRUE;
    }
    
    if (!isset($context['sandbox']['pointer']) && is_array($events)) {
      $context['sandbox']['pointer'] = 0;
      $context['sandbox']['count'] = count($events);
    }

    // Process the events 50 at a time.
    $start = $context['sandbox']['pointer'];
    $end = $context['sandbox']['pointer'] + 50;
    for ($i = $start; $i < $end; $i++) {
      if (!isset($events[$i])) {
        break;
      }
      $event = $events[$i];
      try {
        $node = $sync->syncEvent($event);
        if (is_object($node)) {
          $context['results']['success'][] = $node->id();
        }
      }
      catch (\Exception $exception) {
        $context['results']['errors']['sync'][] = "Error syncing event {$event['event_cst_key']}: " .
          $exception->getMessage();
      }
      $context['sandbox']['pointer']++;
    }

    if ($context['sandbox']['pointer'] === $context['sandbox']['count']) {
      $context['finished'] = 1;
      unset($context['sandbox']);
    }
    else {
      $context['message'] = $context['message'] . ": Completed {$context['sandbox']['pointer']} of {$context['sandbox']['count']}";
      $context['finished'] = $context['sandbox']['pointer']/$context['sandbox']['count'];
    }
  }

}
