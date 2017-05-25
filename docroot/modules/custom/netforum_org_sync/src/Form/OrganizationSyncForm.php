<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use SoapClient;
use Exception;

/**
 * Class OrganizationSyncForm.
 *
 * @package Drupal\netforum_org_sync\Form
 */
class OrganizationSyncForm extends ConfigFormBase {

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
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('netforum_org_sync.organizationsync')
      ->set('org_types', $form_state->getValue('org_types'))
      ->save();
    $syncOrganizations = $this->syncOrganizations();
  }
  
  private function loadOrgNode($cst_key = false, $type = false) {
    if(!empty($cst_key)) {
      $query = \Drupal::entityQuery('node');
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
        if($this->cleanField($organization['OrgCode']) == 'associate') {
          $org_type = 'vendor';
        } else {
          $org_type = 'facility';
        }
        $existing_node = $this->loadOrgNode($cst_key, $org_type);
        if(empty($existing_node)) {
          $node = Node::create(['type' => $org_type]);
        } else {
          $node = Node::load($existing_node);
        }

        if($org_type == 'vendor') {
          //field_primary_services
          //field_additional_services
          //field_contact
          //field_contact_title
          //field_email
          //field_facebook
          //field_phone
          //field_twitter
          $node->field_web_address = $this->cleanField($organization['CstWebSite']);
          $node->field_address->country_code = 'US';
          $node->field_address->administrative_area = $this->cleanField($organization['AddressState']);
          $node->field_address->locality = $this->cleanField($organization['AddressCity']);
          $node->field_address->postal_code = $this->cleanField($organization['AddressZip']);
          $node->field_address->address_line1 = $this->cleanField($organization['AddressLine1']);
          $node->field_address->address_line2 = $this->cleanField($organization['AddressLine2']);

          //todo: handle vendors
        }

        //We definitely need a title or this function will cause a fatal error,
        //thus we check for $organization['SortName'].

        if(!empty($organization['SortName'])) {
          $node->set('title', $this->cleanField($organization['SortName']));
          $node->set('field_customer_key', $this->cleanField($organization['cst_key']));
          $node->field_address->country_code = 'US';
          $node->field_address->administrative_area = $this->cleanField($organization['AddressState']);
          $node->field_address->locality = $this->cleanField($organization['AddressCity']);
          $node->field_address->postal_code = $this->cleanField($organization['AddressZip']);
          $node->field_address->address_line1 = $this->cleanField($organization['AddressLine1']);
          $node->field_address->address_line2 = $this->cleanField($organization['AddressLine2']);
          $node->set('field_customer_type', $this->cleanField($organization['CustomerType']));
          $node->set('field_member_flag', $this->cleanField($organization['MemberFlag']));
          $node->set('field_customer_id', $this->cleanField($organization['CustomerID']));
          $node->set('field_customer_web_site', $this->cleanField($organization['CstWebSite']));
          $node->set('field_customer_phone_number', $this->cleanField($organization['PhoneNumber']));
          $node->set('field_customer_fax_number', $this->cleanField($organization['FaxNumber']));
          $node->save();
        } else {
          $message = t('Facility with customer key @cst_key returned from API with no facility name (SortName).', array('@cst_key' => $organization['cst_key']));
          \Drupal::logger('NetForum Organization Sync')->error($message);
        }
      }
    }
  }

  private function getOrganizations() {
    $responseHeaders = '';
    $facility_types= explode("\n", str_replace("\r", "", \Drupal::config('netforum_org_sync.organizationsync')->get('org_types')));
    drupal_set_message(print_r($facility_types,true));
    $netforum_service = \Drupal::service('netforum_soap.get_token');
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
      $params = array('szCstKey' => $cst_key);

      try {
        $response = $client->__soapCall('GetCustomerByKey', array('parameters' => $params), NULL, $netforum_service->getAuthHeaders(), $responseHeaders);
        if(!empty($response->GetCustomerByKeyResult->any)) {
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
  private function cleanField($field) {
    if(!empty($field)) {
      return $field;
    } else {
      return '';
    }
  }
}
