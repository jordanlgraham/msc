<?php

namespace Drupal\msc_your_membership;

use \Exception;
use Drupal\Core\Link;
use Psr\Log\LoggerInterface;
use Drupal\node\NodeInterface;
use Drupal\netforum_soap\SoapHelper;
use Drupal\Core\State\StateInterface;
use Drupal\ymapi\Plugin\ApiTools\Client;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class OrgSync {

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * @var \Drupal\ymapi\Plugin\ApiTools\Client
   */
  protected $client;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $termStorage;

  /**
   * @var \Drupal\msc_your_membership\YMApiUtils
   */
  protected $ymApiUtils;

  const CRON_STATE_KEY = 'msc_your_membership.org_sync';
  const DATE_FORMAT = 'm/d/Y H:i:s A';

  public function __construct(ConfigFactoryInterface $configFactory, Client $client, DateFormatterInterface $dateFormatter, LoggerInterface $logger, EntityTypeManagerInterface $entityTypeManager, StateInterface $state, YMApiUtils $ymapi_utils) {
    $this->config = $configFactory->get('msc_your_membership.organizationsync');
    $this->client = $client;
    $this->dateFormatter = $dateFormatter;
    $this->logger = $logger;
    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->state = $state;
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->ymApiUtils = $ymapi_utils;
  }

  /**
   * Load or create a node based on the organization array retrieved from Netforum.
   *
   * @param array $profileanization
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   */
  private function loadOrCreateOrgNode(array $organization) {
    if (!$node = $this->loadOrgNode($organization)) {
      $type = $this->getOrganizationType($organization);
      $node = $this->nodeStorage->create(['type' => $type]);
    }
    return $node;
  }

  private function loadOrCreateTermsByName($terms, $vocabulary) {
    $tids = array();
    foreach ($terms as $term_name) {
      $term = $this->termStorage->loadByProperties(['vid' => $vocabulary, 'name' => $term_name]);
      if(!empty($term)) {
        $tids[] = array_pop($term);
      } else {
        $new_term = $this->termStorage->create(['name' => $term_name, 'vid' => $vocabulary]);
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
    $query = $this->nodeStorage->getQuery();
    $query->condition('status', 1);
    $query->condition('field_website_member_id', $organization['ProfileID']);
    $query->accessCheck(FALSE);
    $entity_ids = $query->execute();

    //This function simply returns the first node found.
    //Couple notes:
    // 1) This function should only ever return one node, since it's checking
    //    using the $cst_key UUID
    // 2) This function uses array_values to get the first nid, since the nid
    //    is used as the array index, so it could be anything.

    if (!empty(array_values($entity_ids)[0])) {
      $nid = array_values($entity_ids)[0];
      return $this->nodeStorage->load($nid);
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
    // Set the values of the node's fields that are standard in YM.
    $result = $this->setStandardFieldValues($org, $node);
    if (!$result) {
      // Add an entry in the database log.
      $this->logger->error('Unable to set standard field values for @type @key',
        ['@type' => $node->getType(), '@key' => $org['ProfileID']]);
    } else {
      // Use $result as our $node for the next step.
      $node = $result;
    }

    // Set the values of the node's fields that are custom in YM.
    $result = $this->setCustomFieldValues($org, $node);
    if (!$result) {
      // Add an entry in the database log.
      $this->logger->error(
        'Unable to set facility custom field values for @type @key',
        ['@type' => $node->getType(), '@key' => $org['ProfileID']]
      );
    } else {
      // Use $result as our $node for the next step.
      $node = $result;
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
    $org_code = $organization['MemberAccountInfo']['MemberTypeCode'];
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
    $responseHeaders = $this->client->getResponseHeaders();

    try {
      $authHeaders = $this->client->getAuthHeaders();
      if($authHeaders) {
        // Open a new client.
        $client = $this->client->getClient();
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

  public function getProfileIdsSince($startDate) {
    return $this->ymApiUtils->getMembersInfo();
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
  public function syncOrganization($profile) {
    // Make sure the organization is a member.
    if (!$profile['IsMember']) {
      // A synced node may no longer be a member.
      // Check for any nodes with this key and unpublish them.
      $node = $this->unpublishOrgNode($profile);
      if ($node) {
        return $node;
      }
      return FALSE;
    }

    // Don't create any new facilities for 'Multi-Facility Corporate' orgs.
    if ($profile['MemberAccountInfo']['MemberTypeCode'] === 'Multi-Facility Corporate') {
      return FALSE;
    }

    $node = $this->loadOrCreateOrgNode($profile);

    $this->saveOrgNode($profile, $node);
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

  public function getMemberTypes() {
    $memberTypes = [];

    return $memberTypes;
  }

  /**
   * Set the value of a node's fields that are custom fields in YM.
   *
   * @param object $org
   * @param Node $node
   */
  private function setStandardFieldValues($org, &$node) {
    // Get the states array.
    $usStates = include __DIR__ . '/../inc/states.inc';
    // First handle fields that exist in both the Facility and Vendor content types.
    $node->set('title', $org['MemberProfessionalInfo']['EmployerName']);
    $node->field_address->country_code = 'US';
    // Get the index from $usStates for the element with value $org['MemberProfessionalInfo']['WorkAddressLocation'].
    $abbreviation = array_search($org['MemberProfessionalInfo']['WorkAddressLocation'], $usStates);
    $node->field_address->administrative_area = $abbreviation;
    // $node->field_address->administrative_area = $org['MemberProfessionalInfo']['WorkAddressLocation'];
    $node->field_address->locality = $org['MemberProfessionalInfo']['WorkAddressCity'];
    $node->field_address->postal_code = $org['MemberProfessionalInfo']['WorkAddressPostalCode'];
    $node->field_address->address_line1 = $org['MemberProfessionalInfo']['WorkAddressLine1'];
    $node->field_address->address_line2 = $org['MemberProfessionalInfo']['WorkAddressLine2'];
    $node->field_contact = $org['MemberPersonalInfo']['FirstName'] . ' ' . $org['MemberPersonalInfo']['LastName'];
    $node->field_contact_title = $org['MemberProfessionalInfo']['WorkTitle'];
    $node->field_email = $org['MemberPersonalInfo']['Email'];
    $node->field_phone = $org['MemberPersonalInfo']['HomePhoneNumber'];
    $node->field_web_address = $org['MemberProfessionalInfo']['WorkUrl'];
    $node->field_website_member_id = $org['ProfileID'];

    // Set $node->status based on $org['IsMember'].
    $node->set('status', $org['IsMember'] ? NodeInterface::PUBLISHED : NodeInterface::NOT_PUBLISHED);
    return $node;
  }

  /**
   * Set the value of a node's fields that are custom fields in YM.
   *
   * @param object $org
   * @param Node $node
   */
  private function setCustomFieldValues($org, &$node) {
    // Get the indices for this organization.
    $fields = $this->ymApiUtils->getYmIndeces($org);
    // Iterate over $fields and store the values in $node.
    foreach ($fields as $key => $field) {
      // Skip fields with index == false.
      if ($field['index'] === FALSE) {
        // Add an entry in the database log.
        $this->logger->error('Cannot find YM custom fields index for field @field for @type @key',
          ['@field' => $key, '@type' => $node->getType(), '@key' => $org['ProfileID']]);
        continue;
      }
      // Skip fields that do not include $node->getType() in $field['bundles'].
      if (!in_array($node->getType(), $field['bundles'])) {
        continue;
      }
      // Set the field value based on the field type.
      switch ($field['type']) {
        case 'string':
        case 'integer':
        case 'boolean':
          $node->set($key, $org['MemberCustomFieldResponses'][$field['index']]['Values'][0]['Value']);
          break;
        case 'list_string':
          $node->set($key, array_map(function ($item) {
            return ['value' => $item['Value']];
          }, $org['MemberCustomFieldResponses'][$field['index']]['Values']));
          break;
        case 'link':
          // Create an external link for $org['MemberCustomFieldResponses'][$field['index']]['Values'])).
          $link = Link::fromTextAndUrl($org['MemberCustomFieldResponses'][$field['index']]['Values'][0]['Value'], Url::fromUri($org['MemberCustomFieldResponses'][$field['index']]['Values'][0]['Value']));
          $node->set($key, $link);
          break;
      }
    }
    // Facility nodes also need a couple of standard fields.
    $node->field_customer_fax_number = $org['MemberProfessionalInfo']['WorkFaxNumber'];
    $node->field_customer_phone_number = $org['MemberProfessionalInfo']['WorkPhoneNumber'];

    return $node;
  }

}
