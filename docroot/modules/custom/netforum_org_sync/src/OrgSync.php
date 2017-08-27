<?php

namespace Drupal\netforum_org_sync;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\netforum_soap\GetClient;
use \Exception;
use Drupal\node\NodeInterface;
use Drupal\netforum_soap\SoapHelper;

class OrgSync {

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $node_storage;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $term_storage;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\netforum_soap\GetClient
   */
  protected $get_client;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  const CRON_STATE_KEY = 'netforum_org_sync.org_sync';

  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory,
                              GetClient $getClient, LoggerInterface $logger, DateFormatterInterface $dateFormatter) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->term_storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->config = $configFactory->get('netforum_org_sync.organizationsync');
    $this->logger = $logger;
    $this->get_client = $getClient;
    $this->dateFormatter = $dateFormatter;
  }

  public function syncOrganizations() {
    $organizations = $this->getOrganizations();
    if ($organizations) {
      foreach ($organizations as $cst_key => $organization) {
        $node = $this->loadOrCreateOrgNode($organization);
        $saved_node = $this->saveOrgNode($organization, $node);
        if(!$saved_node) {
          $this->logger->error('Unable to save node in NetForum Organization sync: Node: <pre>@node</pre> Organization: <pre>@org</pre>',
            ['@node' => print_r($node, TRUE), '@org' => print_r($organization, TRUE)]);
        }
      }
    }
  }


  /**
   * Load or create a node based on the organization array retrieved from Netforum.
   *
   * @param array $organization
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  private function loadOrCreateOrgNode(array $organization) {
    //search for a node with the $cst_key so we can perform an update action.
    $type = $this->getOrganizationType($organization);
    $query = $this->node_storage->getQuery();
    $query->condition('status', 1);
    $query->condition('type', $type);
    $query->condition('field_customer_key', $organization['org_cst_key']);
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
    foreach ($terms as $term_name) {
      $term = $this->term_storage->loadByProperties(['vid' => 'vendor_services_offered', 'name' => $term_name]);
      if(!empty($term)) {
        $tids[] = array_pop($term);
      } else {
        $new_term = $this->term_storage->create(['name' => $term_name, 'vid' => 'vendor_services_offered']);
        try {
          $new_term->save();
          $tids[] = $new_term->id();
        } catch(EntityStorageException $e) {
          $this->logger->error('Error saving term. Term: <pre>@term</pre> Error: @error',
            ['@term' => print_r($term, TRUE), '@error' => $e->getMessage()]);
        }
      }
    }
    return $tids;
  }

  /**
   * @param $organization
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\node\NodeInterface
   */
  private function saveOrgNode(array $organization, NodeInterface $node) {

    //first handle fields that exist in both the Facility and Vendor content types
    $node->set('title', SoapHelper::cleanSoapField($organization['org_name']));
    $node->field_address->country_code = 'US';
    $node->field_address->administrative_area = SoapHelper::cleanSoapField($organization['adr_state']);
    $node->field_address->locality = SoapHelper::cleanSoapField($organization['adr_city']);
    $node->field_address->postal_code = SoapHelper::cleanSoapField($organization['adr_post_code']);
    $node->field_address->address_line1 = SoapHelper::cleanSoapField($organization['adr_line1']);
    $node->field_address->address_line2 = SoapHelper::cleanSoapField($organization['adr_line2']);

    //todo: find these in API
    $node->field_contact = '';
    $node->email = ''; //not in GetFacadeObject
    $node->field_phone = SoapHelper::cleanSoapField($organization['phn_number_complete']);; //not in GetFacadeObject
    $node->field_web_address = SoapHelper::cleanSoapField($organization['cst_web_site']);
    $node->field_facebook = SoapHelper::cleanSoapField($organization['cel_facebook_name']);//  Link
    $node->field_linkedin = SoapHelper::cleanSoapField($organization['cel_linkedin_name']);//  Link
    $node->field_twitter = SoapHelper::cleanSoapField($organization['cel_twitter_name']);//  Link

    //fields specific to facility nodes
    if($node->getType() == 'facility') {
      $node->field_administrator = SoapHelper::cleanSoapField($organization['con__cst_ind_full_name_dn']);// Text (plain)
      $node->field_customer_fax_number = SoapHelper::cleanSoapField($organization['fax_number']);// Text (plain)
      $node->field_customer_key = SoapHelper::cleanSoapField($organization['org_cst_key']);//  Text (plain)
      $node->field_customer_phone_number = SoapHelper::cleanSoapField($organization['phn_number_complete']);// Text (plain)
      $node->field_customer_type = SoapHelper::cleanSoapField($organization['cst_type'], 'array');// List (text)
      $node->field_customer_web_site = SoapHelper::cleanSoapField($organization['cst_web_site']);// Text (plain)
      $node->field_languages_spoken = SoapHelper::cleanSoapField($organization['org_custom_text_08'], 'array');//  List (text)
      $node->field_licensed_nursing_facility_ = SoapHelper::cleanSoapField($organization['org_custom_integer_10']);//  Number (integer)
      $node->field_medicaid = SoapHelper::cleanSoapField($organization['org_custom_flag_05'], 'boolean');//  Boolean
      $node->field_medicare = SoapHelper::cleanSoapField($organization['org_custom_flag_09'], 'boolean');//  Boolean
      $node->field_member_flag = SoapHelper::cleanSoapField($organization['cst_member_flag'], 'boolean');// Boolean
      $node->field_pace_program = SoapHelper::cleanSoapField($organization['org_custom_flag_02'], 'boolean');//  Boolean
      $node->field_service_type = SoapHelper::cleanSoapField($organization['org_custom_text_09'], 'array');//  List (text)
      $node->field_populations_served = SoapHelper::cleanSoapField($organization['org_custom_text_11'], 'array');//  List (text)
      $node->field_specialized_unit = SoapHelper::cleanSoapField($organization['org_custom_text_10'], 'array');//  List (text)
      $node->field_va_contract = SoapHelper::cleanSoapField($organization['org_custom_flag_01'], 'boolean');// Boolean
    }
    //fields specific to vendor nodes
    $primary_services = SoapHelper::cleanSoapField($organization['org_custom_text_03'], 'array');
    $additional_services = SoapHelper::cleanSoapField($organization['org_custom_text_04'], 'array');

    if (!empty($primary_services)) {
      $node->field_primary_services = $this->loadOrCreateTermsByName($primary_services);
    }
    if (!empty($additional_services)) {
      $node->field_additional_services = $this->loadOrCreateTermsByName($additional_services);
    }

    $node->save();
    return $node;
  }

  /**
   * Get the content type for the organization.
   *
   * @param $organization
   *
   * @return string
   */
  private function getOrganizationType($organization) {
    //If the API returns an organization as an "associate,"
    //the organization should be in the vendor content type,
    //not the facility content type.
    $org_code = $organization['org_ogt_code'];
    if($org_code == 'Associate') {
      return 'vendor';
    } else {
      return 'facility';
    }
  }

  private function getOrganizations() {
    $facility_types = $this->typesToSync();
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
        $this->logger->error('GetOrganizationByType API function failed: @err', ['@err' => $e->getMessage()]);
        return FALSE;
      }
    }

    $orgs = array();
    foreach ($facility_cst_keys as $cst_key) {
      if ($org = $this->getOrganization($cst_key)) {
        $orgs[$cst_key] = $org;
      }
    }
    return $orgs;
  }

  /**
   * Sync organizations that have changed within a certain date.
   *
   * @param int $start_date
   *  Start date unix timestamp
   * @param int $end_date
   *  End date unix timestamp
   *
   * @return int
   *  Number of organizations synced.
   */
  public function syncOrganizationChanges($start_date, $end_date) {
    $format = 'm/d/Y H:i:s A';
    $client = $this->get_client->getClient();
    $responseHeaders = $this->get_client->getResponseHeaders();
    $params = [
      'szStartDate' => $this->dateFormatter->format($start_date, 'custom', $format),
      'szEndDate' => $this->dateFormatter->format($end_date, 'custom', $format),
    ];
    try {
      if(!empty($responseHeaders['AuthorizationToken']->Token)) {
        $authHeaders = $this->get_client->getAuthHeaders($responseHeaders['AuthorizationToken']->Token);
      } else {
        $authHeaders = $this->get_client->getAuthHeaders();
      }
      $response = $client->__soapCall('GetOrganizationChangesByDate', array('parameters' => $params), NULL, $authHeaders, $responseHeaders);
      if (!empty($response->GetOrganizationChangesByDateResult->any)) {
        $xmlstring = str_replace(' xsi:schemaLocation="http://www.avectra.com/OnDemand/2005/ Organization.xsd"', '', $response->GetOrganizationChangesByDateResult->any);
        $xmlstring = str_replace('xsi:nil="true"', '', $xmlstring);
        $xml = simplexml_load_string($xmlstring);
        $json = json_encode($xml);
        $orgs = json_decode($json, TRUE);
        if (empty($orgs['Result'])) {
          return 0;
        }
        $i = 0;
        $facility_types = $this->typesToSync();
        foreach ($orgs['Result'] as $key => $organization) {
          // This API method doesn't allow filtering by facility type, so do it here.
          if (!in_array($organization['org_ogt_code'], $facility_types)) {
            continue;
          }
          $node = $this->loadOrCreateOrgNode($organization);
          $this->saveOrgNode($organization, $node);
          // Save some memory.
          unset($node);
          unset($orgs['Result'][$key]);
        }
        return count($orgs['Result']);
      }
      else {
        return FALSE;
      }
    }
    catch (Exception $e) {
      $this->logger->error('Unable to retrieve organization changes by date: @err',
        ['@err' => $e->getMessage()]);
    }
  }

  public function getOrganization($cst_key) {
    $params = array(
      'szObjectKey' => $cst_key,
      'szObjectName' => 'organization',
    );
    $responseHeaders = $this->get_client->getResponseHeaders();

    try {
      $authHeaders = $this->get_client->getAuthHeaders();
      if($authHeaders) {
        // Open a new client.
        $client = $this->get_client->getClient();
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
            return $array;
          }
          return FALSE;
        }
      }
      else {
        $this->logger->error('Invalid SOAP Header');
      }

    } catch (Exception $e) {
      $this->logger->error('Unable to retrieve organization @key from Netforum. Error: @err',
        ['@key' => $cst_key, '@err' => $e->getMessage()]);
    }
  }

  /**
   * A list of organization types to sync.
   *
   * @return array
   */
  private function typesToSync() {
    return explode("\n", str_replace("\r", "", $this->config->get('org_types')));
  }

}
