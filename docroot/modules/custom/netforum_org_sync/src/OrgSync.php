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

  public function syncOrganizations($start_date = false, $end_date = false) {
    if($start_date && $end_date) {
      $organizations = $this->syncOrganizationChanges($start_date, $end_date);
    }
    else {
      $organizations = $this->getOrganizations();
    }
    if ($organizations && is_array($organizations)) {
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
   * @param $org
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\node\NodeInterface
   */
  private function saveOrgNode(array $org, NodeInterface $node) {
    //for non-static functions from the SoapHelper class.
    $soaphelper = new SoapHelper;
    $individual = $this->getIndividual($org['con__cst_key']);
    //first handle fields that exist in both the Facility and Vendor content types
    $node->set('title', SoapHelper::cleanSoapField($org['org_name']));
    $node->field_address->country_code = 'US';
    $node->field_address->administrative_area = SoapHelper::cleanSoapField($org['adr_state']);
    $node->field_address->locality = SoapHelper::cleanSoapField($org['adr_city']);
    $node->field_address->postal_code = SoapHelper::cleanSoapField($org['adr_post_code']);
    $node->field_address->address_line1 = SoapHelper::cleanSoapField($org['adr_line1']);
    $node->field_address->address_line2 = SoapHelper::cleanSoapField($org['adr_line2']);
    $node->field_contact = isset($individual['name']) ? SoapHelper::cleanSoapField($individual['name']) : '';
    $node->field_contact_title = isset($individual['title']) ? SoapHelper::cleanSoapField($individual['title']) : '';
    $node->field_email = isset($individual['email']) ? SoapHelper::cleanSoapField($individual['email']) : '';
    $node->field_phone = SoapHelper::cleanSoapField($org['phn_number_complete']);; //not in GetFacadeObject
    $node->field_web_address = SoapHelper::cleanSoapField($org['cst_web_site']);
    $node->field_facebook = $soaphelper->URLfromSocialHandle($org['cel_facebook_name'], 'facebook'); //Link
    $node->field_linkedin = $soaphelper->URLfromSocialHandle($org['cel_linkedin_name'], 'linkedin'); //Link
    $node->field_twitter = $soaphelper->URLfromSocialHandle($org['cel_twitter_name'], 'twitter'); //Link
    $node->field_customer_key = SoapHelper::cleanSoapField($org['org_cst_key']); //Text (plain)

    //fields specific to facility nodes
    if($node->getType() == 'facility') {
      $node->field_administrator = SoapHelper::cleanSoapField($org['con__cst_ind_full_name_dn']);// Text (plain)
      $node->field_assisted_living_beds = SoapHelper::cleanSoapField($org['org_custom_integer_07']); // Number (Integer)
      $node->field_customer_fax_number = SoapHelper::cleanSoapField($org['fax_number']);// Text (plain)
      $node->field_customer_phone_number = SoapHelper::cleanSoapField($org['phn_number_complete']);// Text (plain)
      $node->field_customer_type = SoapHelper::cleanSoapField($org['cst_type'], 'array');// List (text)
      $node->field_customer_web_site = SoapHelper::checkURLValidity($org['cst_web_site']);// Text (plain)
      $node->field_languages_spoken = SoapHelper::cleanSoapField($org['org_custom_text_08'], 'array');//  List (text)
      $node->field_licensed_nursing_facility_ = SoapHelper::cleanSoapField($org['org_custom_integer_10']);//  Number (integer)
      $node->field_medicaid = SoapHelper::cleanSoapField($org['org_custom_flag_05'], 'boolean');//  Boolean
      $node->field_medicare = SoapHelper::cleanSoapField($org['org_custom_flag_09'], 'boolean');//  Boolean
      $node->field_member_flag = SoapHelper::cleanSoapField($org['cst_member_flag'], 'boolean');// Boolean
      $node->field_pace_program = SoapHelper::cleanSoapField($org['org_custom_flag_02'], 'boolean');//  Boolean
      $node->field_service_type = SoapHelper::cleanSoapField($org['org_custom_text_09'], 'array');//  List (text)
      $node->field_populations_served = SoapHelper::cleanSoapField($org['org_custom_text_11'], 'array');//  List (text)
      $node->field_specialized_unit = SoapHelper::cleanSoapField($org['org_custom_text_10'], 'array');//  List (text)
      $node->field_va_contract = SoapHelper::cleanSoapField($org['org_custom_flag_01'], 'boolean');// Boolean

      $node->field_assisted_living_beds = SoapHelper::cleanSoapField($org['org_custom_integer_07']);
      $node->field_companion_units = SoapHelper::cleanSoapField($org['org_custom_integer_04']);
      $node->field_dementia_care_beds = SoapHelper::cleanSoapField($org['org_custom_integer_12']);
      $node->field_dph_region = SoapHelper::cleanSoapField($org['org_custom_integer_13']);
      $node->field_ep_region = SoapHelper::cleanSoapField($org['org_custom_string_04']);
      $node->field_hospital_affiliation = SoapHelper::cleanSoapField($org['org_custom_string_01']);
      $node->field_hospital_based_nf_tcu_beds = SoapHelper::cleanSoapField($org['org_custom_integer_08']);
      $node->field_independent_living_beds = SoapHelper::cleanSoapField($org['org_custom_integer_09']);
      $node->field_licensed_rest_home_beds = SoapHelper::cleanSoapField($org['org_custom_integer_11']);
      $node->field_manager = SoapHelper::cleanSoapField($org['org_custom_string_12']);
      $node->field_medicaid_occupancy = SoapHelper::cleanSoapField($org['org_custom_integer_02']);
      $node->field_medicare_occupancy_percent = SoapHelper::cleanSoapField($org['org_custom_integer_14']);
      $node->field_name_of_assisted_living = SoapHelper::cleanSoapField($org['org_custom_string_11']);
      $node->field_network = SoapHelper::cleanSoapField($org['org_custom_string_09']);
      $node->field_number_of_beds_oos = SoapHelper::cleanSoapField($org['org_custom_string_08']);
      $node->field_number_of_residents = SoapHelper::cleanSoapField($org['org_custom_integer_01']);
      $node->field_one_bedroom = SoapHelper::cleanSoapField($org['org_custom_integer_05']);
      $node->field_previous_manager = SoapHelper::cleanSoapField($org['org_custom_string_13']);
      $node->field_representative_district = SoapHelper::cleanSoapField($org['org_custom_string_06']);
      $node->field_retirement_community_aff = SoapHelper::cleanSoapField($org['org_custom_string_02']);
      $node->field_senate_district = SoapHelper::cleanSoapField($org['org_custom_string_07']);
      $node->field_studio = SoapHelper::cleanSoapField($org['org_custom_integer_03']);
      $node->field_total_annual_admissions = SoapHelper::cleanSoapField($org['org_custom_integer_15']);
      $node->field_two_bedroom = SoapHelper::cleanSoapField($org['org_custom_integer_06']);
      $node->field_wib_region = SoapHelper::cleanSoapField($org['org_custom_text_01']);
    }
    //fields specific to vendor nodes
    $primary_services = SoapHelper::cleanSoapField($org['org_custom_text_03'], 'array');
    $additional_services = SoapHelper::cleanSoapField($org['org_custom_text_04'], 'array');

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
      if ($org = $this->getObject($cst_key)) {
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
        $facility_types = $this->typesToSync();
        foreach ($orgs['Result'] as $key => $org) {

          // This API method doesn't allow filtering by facility type, so do it here.
          if (!empty($org['org_ogt_code']) && !in_array($org['org_ogt_code'], $facility_types)) {
            continue;
          }

          //We need to get the GetFacadeObject version of this, which returns
          //more fields than GetOrganizationChangesByDate. Silly, but necessary.
          $organization = $this->getObject($org['org_cst_key']);

          //If it's a facility, make sure it's a member facility, or move on.
          if($org['org_ogt_code'] != 'associate' &&
            (empty($organization['cst_member_flag'])
              || $organization['cst_member_flag'] != '1')) {
            continue;
          }
          if(!empty($organization['cst_member_flag']) && $organization['cst_member_flag'] == '1') {
            $node = $this->loadOrCreateOrgNode($organization);
            $this->saveOrgNode($organization, $node);
            // Save some memory.
            unset($node);
            unset($orgs['Result'][$key]);
          }

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

  private function getIndividual($cst_key) {
    if (empty($cst_key)) {
      return '';
    }
    $record = $this->getObject($cst_key, 'individual');
    $individual = '';
    if ($record) {
      if(!empty($record['ind_full_name_cp'])) {
        $individual['name'] = $record['ind_full_name_cp'];
      }
      if(!empty($record['ind_title'])) {
        $individual['title'] = $record['ind_title'];
      }
      if(!empty($record['eml_address'])) {
        $individual['email'] = $record['eml_address'];
      }
    }


    return $individual;
  }

  public function getObject($cst_key, $object_type = 'organization') {
    $params = array(
      'szObjectKey' => $cst_key,
      'szObjectName' => $object_type,
    );
    $schemas = [
      'organization' => 'http://www.avectra.com/OnDemand/2005/ Organization.xsd',
      'individual' => 'http://www.avectra.com/OnDemand/2005/ Individual.xsd'
    ];
    if (!isset($schemas[$object_type])) {
      return FALSE;
    }
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
          $xmlstring = str_replace(' xsi:schemaLocation="' . $schemas[$object_type] . '"', '', $response->GetFacadeObjectResult->any);
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
      $this->logger->error('Unable to retrieve @type @key from Netforum. Error: @err',
        ['@type' => $object_type, '@key' => $cst_key, '@err' => $e->getMessage()]);
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
