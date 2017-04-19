<?php
namespace Drupal\wsclient;

/**
 * Default parent class for service endpoints.
 */
abstract class WSClientEndpoint implements WSClientEndpointInterface {

  /**
   * @var WSClientServiceDescription
   */
  protected $service;

  protected $url;

  protected $client;

  public function __construct(WSClientServiceDescription $service) {
    $this->service = $service;
    $this->url = $service->url;
  }

  public function call($operation, $arguments) {}

  public function dataTypes() {}

  public function formAlter(&$form, &$form_state) {}

  public function clearCache() {
    unset($this->client);
  }
}
