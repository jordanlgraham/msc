<?php
namespace Drupal\wsclient_soap;

/**
 * A remote endpoint type for invoking SOAP services.
 */
class WSClientSOAPEndpoint extends WSClientEndpoint {

  public function client() {
    if (!isset($this->client)) {
      $options['exceptions'] = TRUE;
      // Handle Basic HTTP authentication.
      if (!empty($this->service->settings['authentication']['basic'])) {
        $this->service->settings['options']['login'] = $this->service->settings['authentication']['basic']['username'];
        $this->service->settings['options']['password'] = $this->service->settings['authentication']['basic']['password'];
      }
      if (!empty($this->service->settings['options'])) {
        $options += $this->service->settings['options'];
      }
      try {
        $this->client = new SOAPClient($this->url, $options);
      }
      catch (SoapFault $e) {
        throw new WSClientException('Error initializing SOAP client for service %name', array('%name' => $this->service->name));
      }

      // Handle WSS style secured webservice.
      // https://www.drupal.org/node/2420779
      if (!empty($this->service->settings['authentication']['wss'])) {
        $this->client->__setSoapHeaders(new WSSESecurityHeader(
          $this->service->settings['authentication']['wss']['username'],
          $this->service->settings['authentication']['wss']['password']
        ));
      }
      elseif (!empty($this->service->global_header_parameters)) {
        $header_parameters = $this->service->global_header_parameters;
        $data_types = $this->service->datatypes;

        $headers = array();
        foreach ($header_parameters as $type => $parameter) {
          $name_space = $parameter['name space url'];
          $data_type = $data_types[$type];
          $soap_vars = array();

          foreach ($data_type['property info'] as $name => $property) {
            $soap_vars[] = new SoapVar($property['default value'], XSD_STRING, NULL, NULL, $name, $name_space);
          }

          $header_data = new SoapVar($soap_vars, SOAP_ENC_OBJECT, NULL, NULL, $type, $name_space);

          $headers[] = new SoapHeader($name_space, $type, $header_data, FALSE);
        }

        $this->client->__setSoapHeaders($headers);
      }

    }
    return $this->client;
  }

  /**
   * Retrieve metadata from the WSDL about available data types and operations.
   *
   * @param boolean $reset
   *   If TRUE, existing data types and operations will be overwritten.
   */
  public function initializeMetadata($reset = TRUE) {
    $client = $this->client();
    $data_types = wsclient_soap_parse_types($client->__getTypes());
    $operations = wsclient_soap_parse_operations($client->__getFunctions());
    if ($reset) {
      $this->service->datatypes = $data_types;
      $this->service->operations = $operations;
    }
    else {
      $this->service->datatypes += $data_types;
      $this->service->operations += $operations;
    }
    $this->service->clearCache();
  }

  /**
   * Calls the SOAP service.
   *
   * @param string $operation
   *   The name of the operation to execute.
   * @param array $arguments
   *   Arguments to pass to the service with this operation.
   */
  public function call($operation, $arguments) {
    $client = $this->client();
    try {
      $response = $client->__soapCall($operation, $arguments);
      return $response;
    }
    catch (SoapFault $e) {
      throw new WSClientException('Error invoking the SOAP service %name, operation %operation: %error', array('%name' => $this->service->label, '%operation' => $operation, '%error' => $e->getMessage()));
    }
  }
}
