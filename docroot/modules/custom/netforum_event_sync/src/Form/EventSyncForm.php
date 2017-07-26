<?php

namespace Drupal\netforum_event_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\netforum_soap\GetClient;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Exception;

/**
 * Class EventSyncForm.
 */
class EventSyncForm extends ConfigFormBase {

  protected $node_storage;

  protected $get_client;

  public function __construct(NodeStorageInterface $nodeStorage, GetClient $getClient) {
    $this->node_storage = $nodeStorage;
    $this->get_client = $getClient;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node'),
      $container->get('netforum_soap.get_client')
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
    $this->syncEvents();
  }

  private function syncEvents() {
    //get stored
    $event_types = explode("\n", str_replace("\r", "", $this->config('netforum_event_sync.eventsync')->get('event_types')));
    $netforum_service = $this->get_client;
    $client = $netforum_service->getClient();
    //store all the customer keys from the GetOrganizationByType calls
    if(!empty($event_types)) {
      foreach ($event_types as $type) {
        $params = array(
          'typeCode' => $type,
        );
        try {
          $response = $client->__soapCall('GetEventListByType', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $netforum_service->getResponseHeaders());
          var_dump($response);
        } catch (Exception $e) {

        }
      }
    }
  }
}
