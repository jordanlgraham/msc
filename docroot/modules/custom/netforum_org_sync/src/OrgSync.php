<?php

namespace Drupal\netforum_org_sync;

use \Exception;
use Psr\Log\LoggerInterface;
use Drupal\node\NodeInterface;
use Drupal\netforum_soap\GetClient;
use Drupal\netforum_soap\SoapHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

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
  const DATE_FORMAT = 'm/d/Y H:i:s A';
  /**
   * @var \Drupal\netforum_soap\SoapHelper
   */
  private $helper;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, ConfigFactoryInterface $configFactory,
                              GetClient $getClient, LoggerInterface $logger, DateFormatterInterface $dateFormatter,
                              SoapHelper $helper) {
    $this->node_storage = $entityTypeManager->getStorage('node');
    $this->term_storage = $entityTypeManager->getStorage('taxonomy_term');
    $this->config = $configFactory->get('netforum_org_sync.organizationsync');
    $this->logger = $logger;
    $this->get_client = $getClient;
    $this->dateFormatter = $dateFormatter;
    $this->helper = $helper;
  }

  /**
   * Load or create a node based on the organization array retrieved from Netforum.
   *
   * @param array $organization
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  private function loadOrCreateOrgNode(array $organization) {
    if (!$node = $this->loadOrgNode($organization)) {
      $type = $this->getOrganizationType($organization);
      $node = $this->node_storage->create(['type' => $type]);
    }
    return $node;
  }

  private function loadOrCreateTermsByName($terms, $vocabulary) {
    $tids = array();
    foreach ($terms as $term_name) {
      $term = $this->term_storage->loadByProperties(['vid' => $vocabulary, 'name' => $term_name]);
      if(!empty($term)) {
        $tids[] = array_pop($term);
      } else {
        $new_term = $this->term_storage->create(['name' => $term_name, 'vid' => $vocabulary]);
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
   * @param array $organization
   *
   * @return bool|NodeInterface
   */
  private function loadOrgNode(array $organization) {
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
      return $this->node_storage->load($nid);
    }
    return FALSE;
  }

  /**
   * Unpublish a sync org node.
   *
   * @param array $organization
   *
   * @return bool|\Drupal\node\NodeInterface
   */
  private function unpublishOrgNode(array $organization) {
    $node = $this->loadOrgNode($organization);
    if ($node) {
      $node->set('status', NodeInterface::NOT_PUBLISHED);
      $node->save();
      return $node;
    }
    return FALSE;
  }

  /**
   * @param $org
   * @param \Drupal\node\NodeInterface $node
   *
   * @return \Drupal\node\NodeInterface
   */
  private function saveOrgNode(array $org, NodeInterface $node) {

    $individual = $this->getIndividual($org['con__cst_key']);
    //first handle fields that exist in both the Facility and Vendor content types
    $node->set('title', $this->helper->cleanSoapField($org['org_name']));
    $node->field_address->country_code = 'US';
    $node->field_address->administrative_area = $this->helper->cleanSoapField($org['adr_state']);
    $node->field_address->locality = $this->helper->cleanSoapField($org['adr_city']);
    $node->field_address->postal_code = $this->helper->cleanSoapField($org['adr_post_code'], 'postal');
    $node->field_address->address_line1 = $this->helper->cleanSoapField($org['adr_line1']);
    $node->field_address->address_line2 = $this->helper->cleanSoapField($org['adr_line2']);
    $node->field_contact = isset($individual['name']) ? $this->helper->cleanSoapField($individual['name']) : '';
    $node->field_contact_title = isset($individual['title']) ? $this->helper->cleanSoapField($individual['title']) : '';
    $node->field_email = isset($individual['email']) ? $this->helper->cleanSoapField($individual['email']) : '';
    $node->field_phone = $this->helper->cleanSoapField($org['phn_number_complete']);; //not in GetFacadeObject
    $node->field_web_address = $this->helper->cleanSoapField($org['cst_web_site'], 'url');
    $node->field_facebook = $this->helper->URLfromSocialHandle($org['cel_facebook_name'], 'facebook'); //Link
    $node->field_linkedin = $this->helper->URLfromSocialHandle($org['cel_linkedin_name'], 'linkedin'); //Link
    $node->field_twitter = $this->helper->URLfromSocialHandle($org['cel_twitter_name'], 'twitter'); //Link
    $node->field_customer_key = $this->helper->cleanSoapField($org['org_cst_key']); //Text (plain)
    //fields specific to facility nodes
    if($node->getType() == 'facility') {
      $node->field_administrator = $this->helper->cleanSoapField($org['con__cst_ind_full_name_dn']);// Text (plain)
      $node->field_customer_fax_number = $this->helper->cleanSoapField($org['fax_number']);// Text (plain)
      $node->field_customer_phone_number = $this->helper->cleanSoapField($org['phn_number_complete']);// Text (plain)
      $node->field_customer_type = $this->helper->cleanSoapField($org['cst_type'], 'array');// List (text)
      $node->field_customer_web_site = $this->helper->checkURLValidity($org['cst_web_site']);// Text (plain)
      $node->field_languages_spoken = $this->helper->cleanSoapField($org['org_custom_text_08'], 'array');//  List (text)
      $node->field_licensed_nursing_facility_ = $this->helper->cleanSoapField($org['org_custom_integer_10']);//  Number (integer)
      $node->field_medicaid = $this->helper->cleanSoapField($org['org_custom_flag_05'], 'boolean');//  Boolean
      $node->field_medicare = $this->helper->cleanSoapField($org['org_custom_flag_09'], 'boolean');//  Boolean
      $node->field_member_flag = $this->helper->cleanSoapField($org['cst_member_flag'], 'boolean');// Boolean
      $node->field_pace_program = $this->helper->cleanSoapField($org['org_custom_flag_02'], 'boolean');//  Boolean
      $node->field_service_type = $this->helper->cleanSoapField($org['org_custom_text_09'], 'array');//  List (text)
      $node->field_populations_served = $this->helper->cleanSoapField($org['org_custom_text_11'], 'array');//  List (text)
      $node->field_specialized_unit = $this->helper->cleanSoapField($org['org_custom_text_10'], 'array');//  List (text)
      $node->field_va_contract = $this->helper->cleanSoapField($org['org_custom_flag_01'], 'boolean');// Boolean
      $facility_type = [$this->helper->cleanSoapField([$org['org_ogt_code']])];
      $node->field_facility_type = $this->loadOrCreateTermsByName($facility_type, 'facility_type');
      $node->field_acronym = $this->helper->cleanSoapField($org['org_acronym']);
      $node->field_state_id = $this->helper->cleanSoapField($org['org_custom_string_03']);
      $node->field_congressional_district = $this->helper->cleanSoapField($org['org_custom_string_10']);
      $pref_acos = $this->helper->cleanSoapField($org['org_custom_text_12'], 'array');
      $node->field_preferred_provider_acos = $this->loadOrCreateTermsByName($pref_acos, 'preferred_provider_acos');
      $contract_acos = $this->helper->cleanSoapField($org['org_custom_text_13'], 'array');
      $node->field_contracted_acos = $this->loadOrCreateTermsByName($contract_acos, 'contracted_acos');
      $node->field_county = $this->helper->cleanSoapField($org['org_custom_text_14'], 'array');
      $node->field_owner = $this->helper->cleanSoapField($org['org_custom_string_05']);
      $node->field_number_of_employees = $this->helper->cleanSoapField($org['org_num_employee']);
      $node->field_chapter_affiliate = $this->helper->cleanSoapField($org['org_chapter_affiliate'], 'array');
      $node->field_social_worker = $this->helper->cleanSoapField($org['org_custom_flag_03'], 'boolean');
      $node->field_massmap_member = $this->helper->cleanSoapField($org['org_custom_flag_04'], 'boolean');
      $node->field_for_profit = $this->helper->cleanSoapField($org['org_custom_flag_06'], 'boolean');
      $node->field_income_subsidies = $this->helper->cleanSoapField($org['org_custom_flag_07'], 'boolean');
      $node->field_ahca_member = $this->helper->cleanSoapField($org['org_custom_flag_08'], 'boolean');
      $node->field_preferred_provider_of_aco = $this->helper->cleanSoapField($org['org_custom_flag_10'], 'boolean');
      $node->field_contract_with_aco = $this->helper->cleanSoapField($org['org_custom_flag_11'], 'boolean');
      $node->field_cna_training_site = $this->helper->cleanSoapField($org['org_custom_flag_12'], 'boolean');
      $node->field_administrator_in_training = $this->helper->cleanSoapField($org['org_custom_flag_13'], 'boolean');
      $node->field_assisted_living_on_campus = $this->helper->cleanSoapField($org['org_custom_flag_14'], 'boolean');
      $node->field_ncal_member = $this->helper->cleanSoapField($org['org_custom_flag_15'], 'boolean');
      $node->field_facility_price_range_min = $this->helper->cleanSoapField($org['org_custom_currency_01'], 'currency');
      $node->field_facility_price_range_max = $this->helper->cleanSoapField($org['org_custom_currency_02'], 'currency');

      $hmo = $this->helper->cleanSoapField($org['org_custom_text_05'], 'array');
      $node->field_hmo_accepted = $this->loadOrCreateTermsByName($hmo, 'hmo_accepted');
      $sco = $this->helper->cleanSoapField($org['org_custom_text_06'], 'array');
      $node->field_sco_accepted = $this->loadOrCreateTermsByName($sco, 'sco_accepted');
      $ltc = $this->helper->cleanSoapField($org['org_custom_text_07'], 'array');
      $node->field_private_ltc_insurance = $this->loadOrCreateTermsByName($ltc, 'private_ltc_insurance_accepted');
      $oasis = $this->helper->cleanSoapField($org['org_custom_text_15'], 'array');
      $node->field_oasis_participation = $this->loadOrCreateTermsByName($oasis, 'oasis_participation');

      $node->field_assisted_living_beds = $this->helper->cleanSoapField($org['org_custom_integer_07']);
      $node->field_companion_units = $this->helper->cleanSoapField($org['org_custom_integer_04']);
      $node->field_dementia_care_beds = $this->helper->cleanSoapField($org['org_custom_integer_12']);
      $node->field_dph_region = $this->helper->cleanSoapField($org['org_custom_integer_13']);
      $node->field_ep_region = $this->helper->cleanSoapField($org['org_custom_string_04']);
      $node->field_hospital_affiliation = $this->helper->cleanSoapField($org['org_custom_string_01']);
      $node->field_hospital_based_nf_tcu_beds = $this->helper->cleanSoapField($org['org_custom_integer_08']);
      $node->field_independent_living_beds = $this->helper->cleanSoapField($org['org_custom_integer_09']);
      $node->field_licensed_rest_home_beds = $this->helper->cleanSoapField($org['org_custom_integer_11']);
      $node->field_manager = $this->helper->cleanSoapField($org['org_custom_string_12']);
      $node->field_medicaid_occupancy = $this->helper->cleanSoapField($org['org_custom_integer_02']);
      $node->field_medicare_occupancy_percent = $this->helper->cleanSoapField($org['org_custom_integer_14']);
      $node->field_name_of_assisted_living = $this->helper->cleanSoapField($org['org_custom_string_11']);
      $node->field_network = $this->helper->cleanSoapField($org['org_custom_string_09']);
      $node->field_number_of_beds_oos = $this->helper->cleanSoapField($org['org_custom_string_08']);
      $node->field_number_of_residents = $this->helper->cleanSoapField($org['org_custom_integer_01']);
      $node->field_one_bedroom = $this->helper->cleanSoapField($org['org_custom_integer_05']);
      $node->field_previous_manager = $this->helper->cleanSoapField($org['org_custom_string_13']);
      $node->field_representative_district = $this->helper->cleanSoapField($org['org_custom_string_06']);
      $node->field_retirement_community_aff = $this->helper->cleanSoapField($org['org_custom_string_02']);
      $node->field_senate_district = $this->helper->cleanSoapField($org['org_custom_string_07']);
      $node->field_studio = $this->helper->cleanSoapField($org['org_custom_integer_03']);
      $node->field_total_annual_admissions = $this->helper->cleanSoapField($org['org_custom_integer_15']);
      $node->field_two_bedroom = $this->helper->cleanSoapField($org['org_custom_integer_06']);
      $node->field_wib_region = $this->helper->cleanSoapField($org['org_custom_text_01']);
      $memberFlag = $this->helper->cleanSoapField($org['cst_member_flag']);
      if ($memberFlag == '0') {
        $node->setUnpublished();
      } elseif($memberFlag == '1') {
        $node->setPublished();
      }
    }
    // Fields specific to vendor nodes.
    $primary_services = $this->helper->cleanSoapField($org['org_custom_text_03'], 'array');
    $additional_services = $this->helper->cleanSoapField($org['org_custom_text_04'], 'array');

    // Merge all services for use in the preferred vendors view.
    $all_services = array_merge($primary_services, $additional_services);

    if (!empty($primary_services)) {
      $node->field_primary_services = $this->loadOrCreateTermsByName($primary_services, 'vendor_services_offered');
    }
    if (!empty($additional_services)) {
      $node->field_additional_services = $this->loadOrCreateTermsByName($additional_services, 'vendor_services_offered');
    }
    if (!empty($all_services)) {
      $node->field_all_services = $this->loadOrCreateTermsByName($all_services, 'vendor_services_offered');
    }


    try {
      $node->save();
    } catch (EntityStorageException $e) {
      $this->logger->error($e->getMessage());
    }
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
    if($org_code === 'Associate') {
      return 'vendor';
    } else {
      return 'facility';
    }
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
    $changes = $this->getOrganizationChanges($start_date, $end_date);
    $count = 0;
    if (empty($changes)) {
      return $count;
    }
    $types = $this->typesToSync();
    foreach ($changes as $change_org) {
      $node = $this->syncOrganization($change_org, $types);
      if ($node) {
        $count++;
      }
    }
    return $count;
  }

  private function getIndividual($cst_key) {
    if (empty($cst_key)) {
      return '';
    }
    $record = $this->getObject($cst_key, 'individual');

    if ($record) {
      $individual = array();
      if(!empty($record['ind_full_name_cp'])) {
        $individual['name'] = $record['ind_full_name_cp'];
      }
      if(!empty($record['ind_title'])) {
        $individual['title'] = $record['ind_title'];
      }
      if(!empty($record['eml_address'])) {
        $individual['email'] = $record['eml_address'];
      }
      return $individual;
    }
    else {
      //return an empty string so that
      return '';
    }
  }

  /**
   * Get a full facade object from Netforum.
   *
   * @param string $cst_key
   * @param string $object_type
   *
   * @return bool|array
   */
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
  public function typesToSync() {
    return explode("\n", str_replace("\r", "", $this->config->get('org_types')));
  }

  public function getOrganizationChanges($startDate, $endDate) {
    $format = 'm/d/Y H:i:s A';
    $client = $this->get_client->getClient();
    $responseHeaders = $this->get_client->getResponseHeaders();
    $params = [
      'szStartDate' => date($format, $startDate),
      'szEndDate' => date($format, $endDate),
    ];
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
        // If one result is returned, it won't be an array of results.
        // Change to an array of results so return is consistent.
        if (isset($orgs['Result']['org_cst_key'])) {
          return [$orgs['Result']];
        }

        // Use usort to sort the $org array by 'cst_name_cp'.
        // $orgs['Result'] = usort($orgs['Result'], [self::class, 'compareByCstNameCp']);
        return $orgs['Result'];
      }
    throw new Exception('Empty organizations response.');
  }

  /**
   * Sync an organization.
   *
   * @param $org
   *  The organization item array from Netforum
   * @param $facility_types
   *  Facility/vendor types to include in the sync
   *
   * @return bool|\Drupal\Core\Entity\EntityInterface|null
   */
  public function syncOrganization(array $org, array $facility_types) {
    // This API method doesn't allow filtering by facility type, so do it here.
    if (empty($org['org_ogt_code']) || !in_array($org['org_ogt_code'], $facility_types)) {
      return FALSE;
    }

    // Make sure the organization is a member.
      if ($this->helper->cleanSoapField($org['cst_member_flag']) !== '1') {
      // A synced node may no longer be a member.
      // Check for any nodes with this key and unpublish them.
      $node = $this->unpublishOrgNode($org);
      if ($node) {
        return $node;
      }
      return FALSE;
    }

    // Don't create any new facilities for 'Multi-Facility Corporate' orgs.
    if ($org['org_ogt_code'] === 'Multi-Facility Corporate') {
      return FALSE;
    }

    //We need to get the GetFacadeObject version of this, which returns
    //more fields than GetOrganizationChangesByDate. Silly, but necessary.
    $organization = $this->getObject($org['org_cst_key']);

    $node = $this->loadOrCreateOrgNode($organization);
    $this->saveOrgNode($organization, $node);
    return $node;
  }

  /**
   * Custom comparison function to sort by 'cst_name_cp'.
   *
   * @param array $a
   * @param array $b
   * @return int
   */
  public static function compareByCstNameCp($a, $b) {
    return strcmp($a['cst_name_cp'], $b['cst_name_cp']);
  }

}
