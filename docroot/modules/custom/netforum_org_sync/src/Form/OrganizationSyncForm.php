<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\netforum_soap\GetClient;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Exception;

/**
 * Class OrganizationSyncForm.
 *
 * @package Drupal\netforum_org_sync\Form
 */

class OrganizationSyncForm extends ConfigFormBase {
  protected $node_storage;

  protected $get_client;

  public function __construct(NodeStorageInterface $nodeStorage, GetClient $getClient) {
    $this->node_storage = $nodeStorage;
    $this->get_client = $getClient;
  }
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node'),
      $container->get('netforum_soap.get_client')->getClient()
    );
  }
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'netforum_org_sync.organizationsync',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'org_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('netforum_org_sync.organizationsync');
    $form['org_types'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Organization Type(s)'),
      '#description' => $this->t('A list of Organization Types to search for in the GetOrganizationByType API call (i.e. Assisted Living)'),
      '#default_value' => $config->get('org_types'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('netforum_org_sync.organizationsync')
      ->set('org_types', $form_state->getValue('org_types'))
      ->save();
    $this->syncOrganizations();
  }
  
  private function loadOrgNode($cst_key = false, $type = false) {
    if(!empty($cst_key)) {
      $query = $this->node_storage->getQuery();
      $query->condition('status', 1);
      $query->condition('type', $type);
      $query->condition('field_customer_key', $cst_key);
      $entity_ids = $query->execute();
      if(!empty(array_values($entity_ids)[0])) {
        return array_values($entity_ids)[0];
      } else {
        return false;
      }
    } else {
      return false;
    }
  }
  
  public function syncOrganizations() {
    $organizations = $this->getOrganizations();
    if($organizations) {
      foreach ($organizations as $cst_key => $organization) {
        //If the API returns an organization as an "associate,"
        //the organization should be in the vendor content type,
        //not the facility content type.
        if($this->cleanSoapField($organization['OrgCode']) == 'Associate') {
          $org_type = 'vendor';
        } else {
          $org_type = 'facility';
        }
        $existing_node = $this->loadOrgNode($cst_key, $org_type);
        if(empty($existing_node)) {
          $node = $this->node_storage->create(['type' => $org_type]);
        } else {
          $node = $this->node_storage->load($existing_node);
        }

        if($org_type == 'vendor' && !empty($organization['org_name'])) {
          try {
            $node->set('title', $this->cleanSoapField($organization['org_name']));

            //todo: test address fields
            $node->field_address->country_code = 'US';
            $node->field_address->administrative_area = $this->cleanSoapField($organization['adr_state']);
            $node->field_address->locality = $this->cleanSoapField($organization['adr_city']);
            $node->field_address->postal_code = $this->cleanSoapField($organization['adr_post_code']);
            $node->field_address->address_line1 = $this->cleanSoapField($organization['adr_line_1']);
            $node->field_address->address_line2 = $this->cleanSoapField($organization['adr_line_2']);

            //todo: lookup saving taxonomy
            //todo: parse primary services field
            //todo: check for existing taxonomy, if it doesn't exist, create it

            //$node->set('field_primary_services');
            //$node->set('field_additional_services');

            //todo: Need an example of an org that has a Twitter and Facebook filled out
            //todo: Load these ancillary contact info fields
            //$node->set('field_contact');
            //$node->set('field_contact_title');
            //$node->set('field_email');
            //$node->set('field_phone');
            //$node->set('field_web_address');
            //$node->set('field_facebook');
            //$node->set('field_twitter');
            //$node->set('field_web_address', $this->cleanSoapField($organization['CstWebSite']));
            $node->set('field_email', $this->cleanSoapField($organization['EmailAddress']));
            $node->validate();
            $node->save();
//          $node->set('field_')
            //todo: handle vendors
          } catch (Exception $e) {

          }
        }

        //We definitely need a title or this function will cause a fatal error,
        //thus we check for $organization['org_name'].

        elseif(!empty($organization['org_name'])) {
          $node->set('title', $this->cleanSoapField($organization['org_name']));
          $node->set('field_customer_key', $this->cleanSoapField($organization['cst_key']));
          $node->field_address->country_code = 'US';
          $node->field_address->administrative_area = $this->cleanSoapField($organization['AddressState']);
          $node->field_address->locality = $this->cleanSoapField($organization['AddressCity']);
          $node->field_address->postal_code = $this->cleanSoapField($organization['AddressZip']);
          $node->field_address->address_line1 = $this->cleanSoapField($organization['AddressLine1']);
          $node->field_address->address_line2 = $this->cleanSoapField($organization['AddressLine2']);
          $node->set('field_customer_type', $this->cleanSoapField($organization['CustomerType']));
          $node->set('field_member_flag', $this->cleanSoapField($organization['MemberFlag']));
          $node->set('field_customer_id', $this->cleanSoapField($organization['CustomerID']));
          $node->set('field_customer_web_site', $this->cleanSoapField($organization['CstWebSite']));
          $node->set('field_customer_phone_number', $this->cleanSoapField($organization['PhoneNumber']));
          $node->set('field_customer_fax_number', $this->cleanSoapField($organization['FaxNumber']));
          $node->save();
        } else {
          $message = $this->t('Facility with customer key @cst_key returned from API with no facility name (SortName).', array('@cst_key' => $organization['cst_key']));
          \Drupal::logger('NetForum Organization Sync')->error($message);
        }
      }
    }
  }

  private function getOrganizations() {
    $responseHeaders = '';
    $facility_types = explode("\n", str_replace("\r", "", $this->config('netforum_org_sync.organizationsync')->get('org_types')));
    $netforum_service = $this->get_client;
    $client = $netforum_service->getClient();
    //todo: handle more than 300 records
    
    //store all the customer keys from the GetOrganizationByType calls
    $facility_cst_keys = array();
    foreach($facility_types as $type) {
      $params = array(
        'typeCode' => $type,
        'bMembersOnly' => '0',
      );
      try {
        $response = $client->__soapCall('GetOrganizationByType', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $responseHeaders);
        if(!empty($response->GetOrganizationByTypeResult->Result)) {
          foreach ($response->GetOrganizationByTypeResult->Result as $result) {
            $facility_cst_keys[] = $result->org_cst_key;
          }
        }
        else {
          continue;
        }
      } catch(Exception $e) {
        $message = t('GetCustomerByName API function failed.');
        \Drupal::logger('msc_netforum_auth')->error($message);
        return false;
      }
    }
    //todo: break this out into a separate function
    //an associative, multi-level array that will include all the
    $orgs = array();
    foreach($facility_cst_keys as $cst_key) {
      $params = array(
        'szObjectKey' => $cst_key,
        'ns:szObjectName' => 'organization',
      );

      try {
        $response = $client->__soapCall('GetFacadeObject', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $responseHeaders);
        if(!empty($response->GetFacadeObjectResult->OrganizationObject)) {
          $xml = simplexml_load_string($response->GetCustomerByKeyResult->any);
          $json = json_encode($xml);
          $array = json_decode($json, TRUE);
          if(!empty($array['Result'])) {
            $orgs[(string) $cst_key] = $array['Result'];
          }
        }

      } catch(Exception $e) {
        //todo: add exception, although a message this granular may not be helpful.
      }
    }

    return $orgs;
  }
  /*
   * A helper function that cleans up the output of fields returned from SoapClient calls.
   * If an XML field returns a value, it simply returns back the value. If the value is empty,
   * it returns an empty string.
   */
  private function cleanSoapField($field) {
    if(!empty($field)) {
      return $field;
    } else {
      return '';
    }
  }
}
