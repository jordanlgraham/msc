<?php

namespace Drupal\apitools\Plugin\Action;

use Drupal\apitools\WorkflowStateActionEvent;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldUpdateActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @Action(
 *   id = "workflow_state_update",
 *   label = @Translation("Workflow state update"),
 * )
 */
class WorkflowStateAction extends FieldUpdateActionBase implements ContainerFactoryPluginInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): WorkflowStateAction {
    if (method_exists(parent::class, 'create')) {
      $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    }
    else {
      $instance = new static(
        $configuration,
        $plugin_id,
        $plugin_definition
      );
    }
    $instance->eventDispatcher = $container->get('event_dispatcher');
    if (!isset($configuration['field_name'])) {
      throw new InvalidPluginDefinitionException($plugin_id, sprintf('The "%s" plugin did not specify a "field_name"', $plugin_id));
    }
    if (!isset($configuration['state_id']) && !isset($configuration['workflow_id'])) {
      throw new InvalidPluginDefinitionException($plugin_id, sprintf('The "%s" plugin requires either "workflow_id" or "state_id"', $plugin_id));
    }
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  protected function getFieldsToUpdate() {
    // TODO: Finish allowing 'workflow_id'
    $fields = [
      $this->configuration['field_name'] => $this->configuration['state_id'],
    ];
    return $fields;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = parent::access($object, $account, TRUE);
    // TODO:
    // - Create a new class that is commerce order entity specific
    // - Create config to enable/disable the use of the bypass permission.
    if ($result->isAllowed()) {
      $is_valid_state = $this->isValidStateIdForEntity($object);
      $result = AccessResult::allowedIfHasPermission($account, 'bypass bulk action workflow guards')
        ->orIf(AccessResult::allowedIf($is_valid_state));
      if (!$is_valid_state && $result->isAllowed()) {
        $this->messenger()->addWarning($this->t('Order @order_id set to state "@to_state" but is not registered as a valid transition or is blocked through grants.', [
          '@to_state' => $this->configuration['state_id'],
          '@order_id' => $object->id(),
        ]));
      }
    }
    return $return_as_object ? $result : $result->isAllowed();
  }

  private function isValidStateIdForEntity(EntityInterface $entity) {
    $is_valid_state_id = FALSE;
    foreach ($this->getFieldsToUpdate() as $field_name => $state_id) {
      /** @var $item \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface */
      $item = $entity->get($field_name)->first();
      $settable_values = $item->getSettableValues();
      if (in_array($state_id, $settable_values)) {
        $is_valid_state_id = TRUE;
      }
    }
    return $is_valid_state_id;
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $entities) {
    $plugin_definition = $this->getPluginDefinition();
    $entity_type = $plugin_definition['type'];
    $field_name = $this->configuration['field_name'];
    $original_statuses = array_reduce($entities, function (array $carry, EntityInterface $item) use ($field_name) {
      $carry[$item->id()] = $item->{$field_name}->first()->getId();
      return $carry;
    }, []);

    $event = new WorkflowStateActionEvent($entities);
    $event->setOriginalStatuses($original_statuses)
      ->setToState($this->configuration['state_id']);

    $this->eventDispatcher->dispatch($event, "entity.bulk_pre_update");
    $this->eventDispatcher->dispatch($event, "entity.$entity_type.bulk_pre_update");
    parent::executeMultiple($entities);
    $this->eventDispatcher->dispatch($event, "entity.bulk_post_update");
    $this->eventDispatcher->dispatch($event, "entity.$entity_type.bulk_post_update");
  }
}
