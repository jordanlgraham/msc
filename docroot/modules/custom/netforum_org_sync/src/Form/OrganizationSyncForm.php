<?php

namespace Drupal\netforum_org_sync\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\netforum_soap\GetClient;
use Drupal\node\Entity\Node;
use Drupal\node\NodeStorageInterface;
use Drupal\taxonomy\TermStorageInterface;
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

  protected $term_storage;

  public function __construct(NodeStorageInterface $nodeStorage, TermStorageInterface $termStorage, GetClient $getClient) {
    $this->node_storage = $nodeStorage;
    $this->term_storage = $termStorage;
    $this->get_client = $getClient;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity.manager')->getStorage('node'),
      $container->get('entity.manager')->getStorage('taxonomy_term'),
      $container->get('netforum_soap.get_client')
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

  public function syncOrganizations() {
    $organizations = $this->getOrganizations();
    if ($organizations) {
      foreach ($organizations as $cst_key => $organization) {

        //If the API returns an organization as an "associate,"
        //the organization should be in the vendor content type,
        //not the facility content type.

        $org_type = $this->getOrganizationType($organization['org_ogt_code']);

        $node = $this->loadOrCreateOrgNode($cst_key, $org_type);
        $saved_node = $this->saveOrgNode($organization, $node);
        if(!$saved_node) {
          $message = $this->t('Unable to save node in NetForum Organization sync');
          \Drupal::logger('NetForum Organization Sync')->error($message);
        }
      }
    }
  }

  /**
   * @param $cst_key string UUID NetForum gives for Organizations
   * @param $type string content type to be loaded or saved (either facility or vendor)
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  private function loadOrCreateOrgNode($cst_key, $type) {
    //search for a node with the $cst_key so we can perform an update action.
    $query = $this->node_storage->getQuery();
    $query->condition('status', 1);
    $query->condition('type', $type);
    $query->condition('field_customer_key', $cst_key);
    $entity_ids = $query->execute();

    //This function simply returns the first node found.
    //Couple notes:
    // 1) This function should only ever return one node, since it's checking
    //    using the $cst_key UUID
    // 2) This function uses array_values to get the first nid, since the nid
    //    is used as the array index, so it could be anything.

    if (!empty(array_values($entity_ids)[0])) {
      $nid = array_values($entity_ids)[0];
    }
    if(!empty($nid)) {
      $node = $this->node_storage->load($nid);
    } else {
      $node = $this->node_storage->create(['type' => $type]);
    }
    return $node;
  }
  private function loadOrCreateTermsByName($terms) {
    $tids = array();
    foreach ($terms as $term) {
      $term = $this->term_storage->loadByProperties(['name' => $term]);
      if(!empty($term)) {
        $tids[] = array_pop($term);
      } else {
        $new_term = $this->term_storage->create(['name' => $term, 'vid' => 'vendor_services_offered']);
        try {
          $new_term->save();
          $tids[] = $new_term->entityKeys['id'];
        } catch(EntityStorageException $e) {
          $message = $this->t('Entity Storage Exception: ' ) . $e;
          \Drupal::logger('msc_netforum_auth')->error($message);
        }
      }
    }
    return $tids;
  }
  private function saveOrgNode($organization, $node) {

    //first handle fields that exist in both the Facility and Vendor content types
    $node->set('title', $this->cleanSoapField($organization['org_name']));
    $node->field_address->country_code = 'US';
    $node->field_address->administrative_area = $this->cleanSoapField($organization['adr_state']);
    $node->field_address->locality = $this->cleanSoapField($organization['adr_city']);
    $node->field_address->postal_code = $this->cleanSoapField($organization['adr_post_code']);
    $node->field_address->address_line1 = $this->cleanSoapField($organization['adr_line1']);
    $node->field_address->address_line2 = $this->cleanSoapField($organization['adr_line2']);

    //todo: find these in API
    $node->field_contact = '';
    $node->email = ''; //not in GetFacadeObject
    $node->field_phone = $this->cleanSoapField($organization['phn_number_complete']);; //not in GetFacadeObject
    $node->field_web_address = $this->cleanSoapField($organization['cst_web_site']);
    $node->field_facebook = $this->cleanSoapField($organization['cel_facebook_name']);//  Link
    $node->field_linkedin = $this->cleanSoapField($organization['cel_linkedin_name']);//  Link
    $node->field_twitter = $this->cleanSoapField($organization['cel_twitter_name']);//  Link

    //fields specific to facility nodes
    if($node->type == 'facility') {
      $node->field_administrator = $this->cleanSoapField($organization['con__cst_ind_full_name_dn']);// Text (plain)
      $node->field_customer_fax_number = $this->cleanSoapField($organization['fax_number']);// Text (plain)
      $node->field_customer_id = $this->cleanSoapField($organization['cst_id']);// Number (integer)
      $node->field_customer_key = $this->cleanSoapField($organization['org_cst_key']);//  Text (plain)
      $node->field_customer_phone_number = $this->cleanSoapField($organization['phn_number_complete']);// Text (plain)
      $node->field_customer_type = $this->cleanSoapField($organization['cst_type'], 'array');// List (text)
      $node->field_customer_web_site = $this->cleanSoapField($organization['cst_web_site']);// Text (plain)
      $node->field_languages_spoken = $this->cleanSoapField($organization['org_custom_text_08'], 'array');//  List (text)
      $node->field_licensed_nursing_facility_ = $this->cleanSoapField($organization['org_custom_integer_10']);//  Number (integer)
      $node->field_medicaid = $this->cleanSoapField($organization['org_custom_flag_05'], 'boolean');//  Boolean
      $node->field_medicare = $this->cleanSoapField($organization['org_custom_flag_09'], 'boolean');//  Boolean
      $node->field_member_flag = $this->cleanSoapField($organization['cst_member_flag'], 'boolean');// Boolean
      $node->field_pace_program = $this->cleanSoapField($organization['org_custom_flag_02'], 'boolean');//  Boolean
      $node->field_service_type = $this->cleanSoapField($organization['org_custom_text_09'], 'array');//  List (text)
      $node->field_populations_served = $this->cleanSoapField($organization['org_custom_text_11'], 'array');//  List (text)
      $node->field_specialized_unit = $this->cleanSoapField($organization['org_custom_text_10'], 'array');//  List (text)
      $node->field_va_contract = $this->cleanSoapField($organization['org_custom_flag_01'], 'boolean');// Boolean
    }
    //fields specific to vendor nodes
    $primary_services = $this->cleanSoapField($organization['org_custom_text_03'], 'array');
    $additional_services = $this->cleanSoapField($organization['org_custom_text_04'], 'array');

    $node->field_primary_services = $this->loadOrCreateTermsByName($primary_services);
    $node->field_additional_services = $this->loadOrCreateTermsByName($additional_services);

    return $node->save();
  }

  private function getOrganizationType($org_code) {
    if($org_code == 'Associate') {
      return 'vendor';
    } else {
      return 'facility';
    }
  }



  private function getOrganizations() {
    $facility_types = explode("\n", str_replace("\r", "", $this->config('netforum_org_sync.organizationsync')
      ->get('org_types')));
    $client = $this->get_client->getClient();
    $responseHeaders = $this->get_client->getResponseHeaders();

    //store all the customer keys from the GetOrganizationByType calls
    $facility_cst_keys = array();
    foreach ($facility_types as $type) {
      $params = array(
        'typeCode' => $type,
        'bMembersOnly' => '0',
      );

      try {
        if(!empty($responseHeaders['AuthorizationToken']->Token)) {
          $authHeaders = $this->get_client->getAuthHeaders($responseHeaders['AuthorizationToken']->Token);
        } else {
          $authHeaders = $this->get_client->getAuthHeaders();
        }
        $response = $client->__soapCall('GetOrganizationByType', array('parameters' => $params), NULL, $authHeaders, $responseHeaders);
        if (!empty($response->GetOrganizationByTypeResult->Result)) {
          foreach ($response->GetOrganizationByTypeResult->Result as $result) {
            $facility_cst_keys[] = $result->org_cst_key;
          }
        }
        else {
          continue;
        }
      } catch (Exception $e) {
        $message = t('GetCustomerByName API function failed.');
        \Drupal::logger('msc_netforum_auth')->error($message);
        return FALSE;
      }
    }
    //todo: break this out into a separate function
    //an associative, multi-level array that will include all the
    $client = $this->get_client->getClient();
    $orgs = array();
    foreach ($facility_cst_keys as $cst_key) {
      $params = array(
        'szObjectKey' => $cst_key,
        'szObjectName' => 'organization',
      );

      try {
        $authHeaders = $this->get_client->getAuthHeaders();
        if($authHeaders) {
          $response = $client->__soapCall('GetFacadeObject', array('parameters' => $params), NULL, $authHeaders, $responseHeaders);
          if (!empty($response->GetFacadeObjectResult->any)) {
            //this is silly code that fixes an issue where the xsi namespace is incorrectly set to an invalid URL.
            //for simplicity's sake, we're simply removing all references to the namespace since the xml is still valid
            //without it.
            $xmlstring = str_replace(' xsi:schemaLocation="http://www.avectra.com/OnDemand/2005/ Organization.xsd"', '', $response->GetFacadeObjectResult->any);
            $xmlstring = str_replace('xsi:nil="true"', '', $xmlstring);

            $xml = simplexml_load_string($xmlstring);
            $json = json_encode($xml);
            $array = json_decode($json, TRUE);

            if (!empty($array)) {
              $orgs[(string) $cst_key] = $array;
            }
          }
        }
        else {
          $message = t('Invalid SOAP Header');
          \Drupal::logger('msc_netforum_auth')->error($message);
        }

      } catch (Exception $e) {
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
  private function cleanSoapField($field, $type = 'string') {
    //if it's a boolean field, we need to return a 1 or a 0
    if ($type == 'boolean') {
      if (!empty($field)) {
        return '1';
      }
      else {
        return '0';
      }
    }
    elseif ($type == 'array') {
      if (!empty($field)) {
        if(stristr($field,',')) {
          return explode(',', $field);
        } else {
          return array($field);
        }
      } else {
        return array();
      }
    }
    else {
      if (!empty($field)) {
        return $field;
      }
      else {
        return '';
      }
    }
  }
}