<?php

/**
 * @file
 * This file contains no working PHP code; it exists to provide additional
 * documentation for doxygen as well as to document hooks in the standard
 * Drupal manner.
 */

/**
 * @defgroup wsclient_rest_hooks Web service client REST's hooks
 * @{
 * Hooks that can be implemented by other modules in order to extend web service
 * client REST.
 */

/**
 * Alter the HTTP request.
 *
 * @param HttpClientRequest $request
 *   The http request to be manipulated before it's handed over to curl.
 * @param WSClientServiceDescription $service
 *   The web service description.
 *
 * @see WSClientRESTEndpoint::alterRequest()
 */
function hook_wsclient_rest_request_alter(&$request, &$service) {
  if ($service->name == 'myservice') {
    // Remove empty parameters.
    $request->parameters = array_filter($request->parameters, 'strlen');
  }
}

/**
 * Provide the formatter for handling requests and parsing responses.
 *
 * @return array
 *   An array whose keys are classes implementing HttpClientFormatter interface
 *       and whose values are arrays containing the keys:
 *   - label: A human readable, translated label for the formatter.
 *   - format: Format of the formatter. Is used to group the formatters.
 *   - settings: An array, containing the settings of the formatter. The key is
 *       the setting name, the value is the default value of the setting.
 */
function hook_wsclient_rest_formatter_info() {
  return array(
    'WslientRestJSONFormatter' => array(
      'label' => t('Basic JSON formatter'),
      'format' => t('JSON'),
      'settings' => array(),
    ),
  );
}

/**
 * @}
 */
