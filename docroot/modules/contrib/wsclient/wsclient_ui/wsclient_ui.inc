<?php

/**
 * @file
 * WSClient UI - implements service description management and configuration
 * screens.
 */

use Drupal\wsclient_ui\WSClientUIController;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Provides a form to add, edit and clone web service descriptions.
 */
function wsclient_service_form($form, &$form_state, $service, $op = 'edit') {
  if ($op == 'clone') {
    $service->label .= ' (cloned)';
    $service->name .= '_clone';
  }
  $type_info = wsclient_get_types();
  if (empty($type_info)) {
    drupal_set_message(t('No service types were found, please enable a module that provides a service type.'), 'warning');
  }
  $types = array();
  foreach ($type_info as $type => $info) {
    $types[$type] = $info['label'];
  }

  $form['label'] = array(
    '#type' => 'textfield',
    '#title' => t('Label'),
    '#default_value' => $service->label,
    '#required' => TRUE,
    '#description' => t('The human-readable name.'),
    '#weight' => 10,
  );
  $form['name'] = array(
    '#type' => 'machine_name',
    '#default_value' => isset($service->name) ? $service->name : '',
    '#maxlength' => 32,
    '#machine_name' => array(
      'exists' => 'wsclient_service_load',
      'source' => array('label'),
    ),
    '#required' => TRUE,
    '#description' => t('The machine-readable name of this service is used internally to identify the service. This name must contain only lowercase letters, numbers, and underscores and must be unique.'),
    '#element_validate' => array('form_validate_machine_name', 'entity_ui_validate_machine_name'),
    '#weight' => 20,
  );
  $form['url'] = array(
    '#type' => 'textfield',
    '#title' => t('URL'),
    '#default_value' => $service->url,
    '#required' => TRUE,
    '#description' => t('The URL of the web service.'),
    '#element_validate' => array('wsclient_ui_element_url_validate'),
    '#weight' => 30,
  );
  $form['type'] = array(
    '#type' => 'select',
    '#title' => t('Type'),
    '#default_value' => $service->type,
    '#options' => $types,
    '#required' => TRUE,
    '#description' => t('The type of the web service.'),
    '#weight' => 40,
  );
  if ($op == 'edit') {
    // Operations of the web service in a table
    $rows = array();
    $operations = wsclient_ui_label_sort($service->operations);
    foreach ($operations as $name => $operation) {
      $row = array();
      $row[] = array('data' => array(
        '#theme' => 'entity_ui_overview_item',
        '#label' => $operation['label'],
        '#name' => $name,
        '#url' => array(
          'path' => WSCLIENT_UI_PATH . '/manage/' . $service->name . '/operation/' . $name,
          'options' => array(),
        ),
      ));
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = l(t('Edit'), WSCLIENT_UI_PATH . '/manage/' . $service->name . '/operation/' . $name);

        $row[] = Link::fromTextAndUrl('Edit', Url::fromUri('WSCLIENT_UI_PATH . \'/manage/\' . $service->name . \'/operation/\' . $name)'));
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = l(t('Delete'), WSCLIENT_UI_PATH . '/manage/' . $service->name . '/operation/' . $name . '/delete');
        $row[] = Link::fromTextAndUrl('Delete', Url::fromUri('WSCLIENT_UI_PATH . \'/manage/\' . $service->name . \'/operation/\' . $name. \'/delete)'));


        $rows[] = $row;
    }
    $header = array(t('Label'), array('data' => t('Operations'), 'colspan' => 3));
    $add_operation = array(
      '#theme' => 'links__wsclient',
      '#links' => array(
        'add_op' => array(
          'title' => t('Add operation'),
          'href' => WSCLIENT_UI_PATH . '/manage/' . $service->name . '/add/operation',
        ),
      ),
    );
    $add_operation['#attributes']['class'][] = 'rules-operations-add';
    $add_operation['#attributes']['class'][] = 'action-links';
    $row = array();
    $row[] = array('data' => $add_operation, 'colspan' => 3);
    $rows[] = array('data' => $row, 'class' => array('rules-elements-add'));
    // @todo description help text for operations, data types
    $form['operations'] = array(
      '#access' => TRUE,
      '#tree' => TRUE,
      '#theme' => 'table',
      '#empty' => t('None'),
      '#caption' => t('Operations'),
      '#rows' => $rows,
      '#header' => $header,
      '#weight' => 65,
    );
    // Add some table styling from Rules.
    $form['operations']['#attributes']['class'][] = 'rules-elements-table';
    $form['operations']['#attached']['css'][] = drupal_get_path('module', 'rules') . '/ui/rules.ui.css';

    // Data types of the web service in a table
    $rows = array();
    $datatypes = wsclient_ui_label_sort($service->datatypes);
    foreach ($datatypes as $name => $datatype) {
      $row = array();
      $row[] = array('data' => array(
        '#theme' => 'entity_ui_overview_item',
        '#label' => $datatype['label'],
        '#name' => $name,
        '#url' => array(
          'path' => WSCLIENT_UI_PATH . '/manage/' . $service->name . '/type/' . $name,
          'options' => array(),
        ),
      ));
      // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = l(t('Edit'), WSCLIENT_UI_PATH . '/manage/' . $service->name . '/type/' . $name);
        $row[] = Link::fromTextAndUrl('Edit', Url::fromUri('WSCLIENT_UI_PATH . \'/manage/\' . $service->name . \'/operation/\' . $name)'));


        // @FIXME
// l() expects a Url object, created from a route name or external URI.
// $row[] = l(t('Delete'), WSCLIENT_UI_PATH . '/manage/' . $service->name . '/type/' . $name . '/delete');
        $row[] = Link::fromTextAndUrl('Delete', Url::fromUri('WSCLIENT_UI_PATH . \'/manage/\' . $service->name . \'/operation/\' . $name. \'/delete)'));


        $rows[] = $row;
    }
    $header = array(t('Label'), array('data' => t('Operations'), 'colspan' => 3));
    $add_type = array(
      '#theme' => 'links__wsclient',
      '#links' => array(
        'add_op' => array(
          'title' => t('Add data type'),
          'href' => WSCLIENT_UI_PATH . '/manage/' . $service->name . '/add/type',
        ),
      ),
    );
    $add_type['#attributes']['class'][] = 'rules-operations-add';
    $add_type['#attributes']['class'][] = 'action-links';
    $row = array();
    $row[] = array('data' => $add_type, 'colspan' => 3);
    $rows[] = array('data' => $row, 'class' => array('rules-elements-add'));
    $form['datatypes'] = array(
      '#access' => TRUE,
      '#tree' => TRUE,
      '#theme' => 'table',
      '#empty' => t('None'),
      '#caption' => t('Data types'),
      '#rows' => $rows,
      '#header' => $header,
      '#weight' => 70,
    );

    $settings = $service->settings;
    // Simply naming the settings here to reflect the internal entity
    // structure is enough to mean we don't need to worry about serializing
    // it ourself.
    $form['settings'] = array(
      '#tree' => TRUE,
      '#weight' => 45,
    );
    // Input for Authentication options.
    $form['settings']['authentication'] = array(
      '#type' => 'fieldset',
      '#title' => t('Authentication'),
      '#weight' => 60,
    );

    // @todo Remove authentication type once multiple authentications is implemented
    // @see WSClientRESTEndpoint::client() => Allow multiple authentications by implementing and using HttpClientCompositeAuth.
    $form['settings']['authentication']['type'] = array(
      '#type' => 'select',
      '#options' => array(
        'none' => t('None'),
        'basic' => t('Basic (htauth)'),
        'wss' => t('WSS (Web Service Security)'),
        'oauth2' => t('OAuth 2.0'),
      ),
      '#default_value' => isset($settings['authentication']['type']) ? $settings['authentication']['type'] : 'none',
    );

    $xpath = ':input[name="settings[authentication][type]"]';

    $basic_states = array(
      '#states' => array(
        'visible' => array(
          $xpath => array('value' => 'basic'),
        ),
      ),
    );

    $basic_settings = isset($settings['authentication']['basic']) ? $settings['authentication']['basic'] : array();

    $form['settings']['authentication']['basic'] = array(
      '#type' => 'fieldset',
      '#title' => t('Basic HTAuth'),
      '#description' => t('HTAuth requires http_client.module > 7.x-2.4'),
    ) + $basic_states;
    $form['settings']['authentication']['basic']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => isset($basic_settings['username'])? $basic_settings['username'] : '',
    );
    $form['settings']['authentication']['basic']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => isset($basic_settings['password'])? $basic_settings['password'] : '',
    );

    $wss_settings = isset($settings['authentication']['wss']) ? $settings['authentication']['wss'] : array();

    $form['settings']['authentication']['wss'] = array(
      '#type' => 'fieldset',
      '#title' => t('WSS (Web Services Security)'),
      '#states' => array(
        'visible' => array(
          $xpath => array('value' => 'wss'),
        ),
      ),
    );
    $form['settings']['authentication']['wss']['username'] = array(
      '#type' => 'textfield',
      '#title' => ('Username'),
      '#default_value' => isset($wss_settings['username'])? $wss_settings['username'] : '',
    );
    $form['settings']['authentication']['wss']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => isset($wss_settings['password'])? $wss_settings['password'] : '',
    );

    $oauth2_states = array(
      '#states' => array(
        'visible' => array(
          $xpath => array('value' => 'oauth2'),
        ),
      ),
    );

    $xpath_auth_flow = ':input[name="settings[authentication][oauth2][auth_flow]"]';

    $oauth2_states_server_side = array(
      '#states' => array(
        'visible' => array(
          $xpath => array('value' => 'oauth2'),
          $xpath_auth_flow => array('value' => 'server-side'),
        ),
      ),
    );

    $oauth2_states_user_password = array(
      '#states' => array(
        'visible' => array(
          $xpath => array('value' => 'oauth2'),
          $xpath_auth_flow => array('value' => 'user-password'),
        ),
      ),
    );

    $form['settings']['authentication']['oauth2'] = array(
      '#type' => 'fieldset',
      '#title' => t('OAuth 2.0'),
    ) + $oauth2_states;

    $oauth_settings = isset($settings['authentication']['oauth2']) ? $settings['authentication']['oauth2'] : array();

    $form['settings']['authentication']['oauth2']['auth_flow'] = array(
      '#type' => 'select',
      '#title' => t('Auth flow'),
      '#options' => array(
        'client-credentials' => t('Client credentials'),
        'server-side' => t('Server side'),
        'user-password' => t('User password'),
      ),
      '#default_value' => isset($oauth_settings['auth_flow']) ? $oauth_settings['auth_flow'] : '',
    );
    $form['settings']['authentication']['oauth2']['token_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('Token endpoint'),
      '#default_value' => isset($oauth_settings['token_endpoint']) ? $oauth_settings['token_endpoint'] : '',
      '#description' => t('E.g. https://server.org/oauth2/token.'),
      // '#element_validate' => array('wsclient_ui_element_url_validate'),
    );
    $form['settings']['authentication']['oauth2']['client_id'] = array(
      '#type' => 'textfield',
      '#title' => t('Client ID'),
      '#default_value' => isset($oauth_settings['client_id']) ? $oauth_settings['client_id'] : '',
      '#description' => t('The client ID as registered on the OAuth 2.0 server.'),
    );
    $form['settings']['authentication']['oauth2']['client_secret'] = array(
      '#type' => 'textfield',
      '#title' => t('Client secret'),
      '#default_value' => isset($oauth_settings['client_secret']) ? $oauth_settings['client_secret'] : '',
      '#description' => t('The client secret as registered on the OAuth 2.0 server.'),
    );
    $form['settings']['authentication']['oauth2']['scope'] = array(
      '#type' => 'textfield',
      '#title' => t('Scope'),
      '#default_value' => isset($oauth_settings['scope']) ? $oauth_settings['scope'] : '',
      '#description' => t('Space separated list of scopes.'),
    );
    $form['settings']['authentication']['oauth2']['query_auth'] = array(
      '#type' => 'select',
      '#title' => t('Query auth'),
      '#options' => array(0 => t('No'), 1 => t('Yes')),
      '#default_value' => isset($oauth_settings['query_auth']) ? $oauth_settings['query_auth'] : 0,
      '#description' => t("Yes will make the access token be set on the query, otherwise it'll be set on the headers."),
    );
    $form['settings']['authentication']['oauth2']['redirect_uri'] = array(
      '#type' => 'textfield',
      '#title' => t('Redirect URI'),
      '#default_value' => isset($oauth_settings['redirect_uri']) ? $oauth_settings['redirect_uri'] : '',
      '#description' => t('E.g. https://client.org/oauth2/authorized. Only needed for server-side auth flow.'),
    ) + $oauth2_states_server_side;
    $form['settings']['authentication']['oauth2']['authorization_endpoint'] = array(
      '#type' => 'textfield',
      '#title' => t('Authorization endpoint'),
      '#default_value' => isset($oauth_settings['authorization_endpoint']) ? $oauth_settings['authorization_endpoint'] : '',
      '#description' => t('E.g. https://server.org/oauth2/authorize. Only needed for server-side auth flow.'),
    ) + $oauth2_states_server_side;
    $form['settings']['authentication']['oauth2']['username'] = array(
      '#type' => 'textfield',
      '#title' => t('Username'),
      '#default_value' => isset($oauth_settings['username']) ? $oauth_settings['username'] : '',
      '#description' => t('Username of resource owner on the OAuth 2.0 server. Only needed for user-password auth flow.'),
    ) + $oauth2_states_user_password;
    $form['settings']['authentication']['oauth2']['password'] = array(
      '#type' => 'textfield',
      '#title' => t('Password'),
      '#default_value' => isset($oauth_settings['password']) ? $oauth_settings['password'] : '',
      '#description' => t('Password of resource owner on the OAuth 2.0 server. Only needed for user-password auth flow.'),
    ) + $oauth2_states_user_password;

    // Input for global service parameters.
    $form['global_parameters'] = array(
      '#tree' => TRUE,
      '#element_validate' => array('wsclient_ui_validate_global_parameters'),
      '#theme' => 'wsclient_ui_global_parameter_form',
      '#title' => t('Input for global service parameters'),
      '#description' => t('Specify the global parameters for the service. Global parameters will be used if the value of an operation parameter with the same name is empty.'),
      '#weight' => 70,
    );

    // Input for global SOAP service header parameters.
    $form['global_header_parameters'] = array(
      '#tree' => TRUE,
      '#theme' => 'wsclient_ui_global_header_parameter_form',
      '#title' => t('Input for global SOAP header parameters'),
      '#description' => t('Specify the global header parameters for the SOAP header. Global header parameters will be added to the SOAP header for all SOAP service operations.'),
      '#weight' => 71,
    );

    $weight = 0;
    foreach ($service->global_parameters as $name => $info) {
      $form['global_parameters']['items'][$name] = _wsclient_ui_global_parameter_row($service, $datatypes, $name, $info);
      $form['global_parameters']['items'][$name]['weight']['#default_value'] = $weight++;
    }

    // Always add three empty lines for global parameters input.
    $form_state['more'] = isset($form_state['more']) ? $form_state['more'] : 3;
    for ($i = 0; $i < $form_state['more']; $i++) {
      if (!isset($form['global_parameters']['items'][$i])) {
        $form['global_parameters']['items'][$i] = _wsclient_ui_global_parameter_row($service, $datatypes);
      }
    }
    $form['global_parameters']['more'] = array(
      '#type' => 'submit',
      '#value' => t('Add more'),
      '#limit_validation_errors' => array(array('properties')),
      '#submit' => array('wsclient_ui_more_submit'),
    );

    $types = array();
    foreach ($service->datatypes as $type_name => $item) {
      $types[$type_name] = $item['label'];
    }

    // Add rows to global header parameter rows.
    foreach ($service->global_header_parameters as $name => $info) {
      $form['global_header_parameters']['items'][$name] = _wsclient_ui_global_header_parameter_row($service, $types, $name, $info);
      $form['global_header_parameters']['items'][$name]['weight']['#default_value'] = $weight++;
    }

    // Always add three empty lines for global header parameters input.
    for ($i = 0; $i < $form_state['more']; $i++) {
      if (!isset($form['global_header_parameters']['items'][$i])) {
        $form['global_header_parameters']['items'][$i] = _wsclient_ui_global_header_parameter_row($service, $types);
      }
    }
    $form['global_header_parameters']['more'] = array(
      '#type' => 'submit',
      '#value' => t('Add more'),
      '#limit_validation_errors' => array(array('properties')),
      '#submit' => array('wsclient_ui_more_submit'),
    );

    // Add some table styling from Rules.
    $form['datatypes']['#attributes']['class'][] = 'rules-elements-table';
    $form['datatypes']['#attached']['css'][] = drupal_get_path('module', 'rules') . '/ui/rules.ui.css';
  }
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
    '#weight' => 80,
  );
  $form_state['service'] = $service;
  // Allow the endpoint to make alterations to the form.
  $form_state['form'] = 'main';
  if ($service->type) {
    $service->endpoint()->formAlter($form, $form_state);
  }

  $form['#submit'][] = 'wsclient_service_form_submit';

  return $form;
}

/**
 * Submit callback of the web service description form.
 */
function wsclient_service_form_submit($form, &$form_state) {
  $service = entity_ui_form_submit_build_entity($form, $form_state);

  // Save global paramters.
  if (isset($form_state['values']['global_parameters'])) {
    $service->global_parameters = array();
    foreach ($form_state['values']['global_parameters']['items'] as $key => $item) {
      if (!empty($item['name'])) {
        $service->global_parameters[$item['name']] = array(
          'default value' => $item['default_value']
        );
      }
    }
  }

  // Save global header paramters.
  if (isset($form_state['values']['global_header_parameters'])) {
    $service->global_header_parameters = array();

    foreach ($form_state['values']['global_header_parameters']['items'] as $key => $item) {
      if (!empty($item['data_type'])) {
        $service->global_header_parameters[$item['data_type']] = array(
          'name space url' => $item['name_space'],
        );
      }
    }
  }

  $service->save();
  drupal_set_message(t('Web service description %service has been saved.', array('%service' => $service->label)));
  if ($form_state['op'] == 'add') {
    $form_state['redirect'] = WSCLIENT_UI_PATH . '/manage/' . $service->name;
  }
  else {
    $form_state['redirect'] = WSCLIENT_UI_PATH;
  }
}

/**
 * FAPI callback to validate a URL.
 */
function wsclient_ui_element_url_validate($element, &$form_state) {
  if (!valid_url($element['#value'], TRUE)) {
    form_error($element, t('Please enter a valid URL.'));
  }
}

/**
 * Operation form.
 */
function wsclient_ui_operation($form, &$form_state, $service, $operation, $op = 'edit') {
  $form['label'] = array(
    '#type' => 'textfield',
    '#title' => t('Label'),
    '#default_value' => isset($operation['label']) ? $operation['label'] : '',
    '#required' => TRUE,
    '#description' => t('The human-readable name of the operation.'),
    '#weight' => -10,
  );
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Name'),
    '#default_value' => isset($operation['name']) ? $operation['name'] : '',
    '#required' => TRUE,
    '#description' => t('The machine-readable name of this operation is used internally to identify the operation.'),
    '#element_validate' => array('wsclient_ui_operation_name_validate'),
    '#weight' => -10,
  );
  $form['parameters'] = array(
    '#tree' => TRUE,
    '#element_validate' => array('wsclient_ui_validate_parameters'),
    '#theme' => 'wsclient_ui_parameter_form',
    '#title' => t('Parameters'),
    '#description' => t('Specify the parameters for the operation. For each parameter you have to specify a certain data type and a unique name containing only alphanumeric characters and underscores. You can also specify a default value for the parameter, if the parameter is required, and if null values are allowed.'),
  );
  $weight = 0;
  $types = wsclient_ui_types(TRUE);
  if (isset($operation['parameter'])) {
    foreach ($operation['parameter'] as $name => $info) {
      $form['parameters']['items'][$name] = _wsclient_ui_parameter_row($service, $types, $name, $info);
      $form['parameters']['items'][$name]['weight']['#default_value'] = $weight++;
    }
  }
  // Always add three empty lines.
  $form_state['more'] = isset($form_state['more']) ? $form_state['more'] : 3;
  for ($i = 0; $i < $form_state['more']; $i++) {
    if (!isset($form['parameters']['items'][$i])) {
      $form['parameters']['items'][$i] = _wsclient_ui_parameter_row($service, $types);
      $form['parameters']['items'][$i]['weight']['#default_value'] = $weight++;
    }
  }
  $form['parameters']['more'] = array(
    '#type' => 'submit',
    '#value' => t('Add more'),
    '#limit_validation_errors' => array(array('parameters')),
    '#submit' => array('wsclient_ui_more_submit'),
  );
  // Exclude the hidden data type for result types.
  unset($types['hidden']);

  $result_type = 0;
  $multiple = FALSE;
  if (isset($operation['result']['type'])) {
    $result_type = wsclient_map_type($service->name, $service->dataTypes(), $operation['result']['type']);
    if (strpos($result_type, 'list<') === 0) {
      $multiple = TRUE;
      // Cut off the 'list<>' indicator.
      $result_type = substr($result_type, 5, -1);
    }
  }
  $form['result_type'] = array(
    '#type' => 'select',
    '#title' => t('Result type'),
    '#options' => array(0 => '--') + $types,
    '#default_value' => $result_type,
    '#description' => t('The result data type returned by the service'),
  );
  $form['result_multiple'] = array(
    '#type' => 'checkbox',
    '#title' => t('Multiple result'),
    '#default_value' => $multiple,
    '#description' => t('If checked, the result variable is a list containing multiple elements of the result type.'),
  );
  $form['result_label'] = array(
    '#type' => 'textfield',
    '#title' => t('Result label'),
    '#default_value' => isset($operation['result']['label']) ? $operation['result']['label'] : '',
    '#description' => t('The human-readable name of the result variable returned by the service.'),
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  $form_state['service'] = $service;
  $form_state['operation'] = $operation;

  // Allow the endpoint to make alterations to the form.
  $form_state['form'] = 'operation';
  $service->endpoint()->formAlter($form, $form_state);

  $form['#submit'][] = 'wsclient_ui_operation_submit';
  return $form;
}

/**
 * Generates a row in the global parameter table.
 */
function _wsclient_ui_global_parameter_row($service, $types, $name = '', $info = array()) {
  $param_type = 0;
  $multiple = FALSE;
  $parameter['name'] = array(
    '#type' => 'textfield',
    '#size' => 40,
    '#default_value' => $name,
    '#element_validate' => array('wsclient_ui_name_validate'),
  );
  $parameter['default_value'] = array(
    '#type' => 'textfield',
    '#size' => 30,
    '#default_value' => isset($info['default value']) ? $info['default value'] : '',
  );
  return $parameter;
}

/**
 * Generates a row in the global parameter table.
 */
function _wsclient_ui_global_header_parameter_row($service, $types, $name = '', $info = array()) {
  if (isset($name)) {
    $parameter['data_type'] = array(
      '#type' => 'select',
      '#options' => array(0 => '--') + $types,
      '#default_value' => $name,
    );

    $parameter['name_space'] = array(
      '#type' => 'textfield',
      '#size' => 255,
      '#default_value' => isset($info['name space url']) ? $info['name space url'] : '',
    );

    return $parameter;
  }
}

/**
 * Generates a row in the parameter table.
 */
function _wsclient_ui_parameter_row($service, $types, $name = '', $info = array()) {
  $param_type = 0;
  $multiple = FALSE;
  if (isset($info['type'])) {
    $param_type = wsclient_map_type($service->name, $service->dataTypes(), $info['type']);
    if (strpos($param_type, 'list<') === 0) {
      $multiple = TRUE;
      // Cut off the 'list<>' indicator.
      $param_type = substr($param_type, 5, -1);
    }
  }
  $parameter['type'] = array(
    '#type' => 'select',
    '#options' => array(0 => '--') + $types,
    '#default_value' => $param_type,
  );
  $parameter['multiple'] = array(
    '#type' => 'checkbox',
    '#default_value' => $multiple,
  );
  $parameter['name'] = array(
    '#type' => 'textfield',
    '#size' => 40,
    '#default_value' => $name,
    '#element_validate' => array('wsclient_ui_name_validate'),
  );
  $parameter['default_value'] = array(
    '#type' => 'textfield',
    '#size' => 30,
    '#default_value' => isset($info['default value']) ? $info['default value'] : '',
  );
  $parameter['required'] = array(
    '#type' => 'checkbox',
    '#default_value' => isset($info['optional']) ? !$info['optional'] : TRUE,
  );
  $parameter['allow_null'] = array(
    '#type' => 'checkbox',
    '#default_value' => isset($info['allow null']) ? $info['allow null'] : FALSE,
  );
  $parameter['weight'] = array(
    '#type' => 'weight',
  );
  return $parameter;
}

/**
 * Validation callback for machine names of parameters or properties.
 */
function wsclient_ui_name_validate($element, &$form_state) {
  if ($element['#value'] && !preg_match('!^[A-Za-z0-9_]+$!', $element['#value'])) {
    form_error($element, t('Machine names must contain only letters, numbers, and underscores.'));
  }
}

/**
 * Submit callback for adding more parameter rows.
 */
function wsclient_ui_more_submit($form, &$form_state) {
  $form_state['more']++;
  $form_state['rebuild'] = TRUE;
}

/**
 * Submit callback of operation form.
 */
function wsclient_ui_operation_submit($form, &$form_state) {
  $service = $form_state['service'];
  $operation = $form_state['operation'];

  $operation['label'] = $form_state['values']['label'];
  $operation['parameter'] = array();
  foreach ($form_state['values']['parameters']['items'] as $key => $item) {
    if (!empty($item['name'])) {
      // Unmap the data type if it is local to this service.
      $unmapped_type = _wsclient_ui_unmap_type($item['type'], $service);
      if ($item['multiple']) {
        $operation['parameter'][$item['name']] = array('type' => 'list<' . $unmapped_type . '>');
      }
      else {
        $operation['parameter'][$item['name']] = array('type' => $unmapped_type);
      }
      if ($item['default_value'] !== '') {
        $operation['parameter'][$item['name']]['default value'] = $item['default_value'];
      }
      if (!$item['required']) {
        $operation['parameter'][$item['name']]['optional'] = TRUE;
      }
      $operation['parameter'][$item['name']]['allow null'] = $item['allow_null'];
    }
  }
  if (!empty($form_state['values']['result_type'])) {
    $unmapped_type = _wsclient_ui_unmap_type($form_state['values']['result_type'], $service);
    $operation['result'] = array(
      'type' => $unmapped_type,
      'label' => isset($form_state['values']['result_label']) ? $form_state['values']['result_label'] : 'result',
    );
    if ($form_state['values']['result_multiple']) {
      $operation['result']['type'] = 'list<' . $unmapped_type . '>';
    }
  }
  unset($service->operations[$form_state['operation']['name']]);
  $service->operations[$form_state['values']['name']] = $operation;
  $service->save();
  drupal_set_message(t('Operation %operation has been saved.', array('%operation' => $operation['label'])));
  $form_state['redirect'] = WSCLIENT_UI_PATH . '/manage/' . $service->name;
  rules_clear_cache();
}

/**
 * Validation callback for operation names.
 */
function wsclient_ui_operation_name_validate($element, &$form_state) {
  if ($element['#value'] && !preg_match('!^[A-Za-z0-9_]+$!', $element['#value'])) {
    form_error($element, t('Operation names must contain only letters, numbers, and underscores.'));
  }
  if ($element['#value'] != $form_state['operation']['name'] && isset($form_state['service']->operations[$element['#value']])) {
    form_error($element, t('An operation with that name already exists'));
  }
}

/**
 * Themes the operation form for editing the used parameters.
 *
 * @ingroup themeable
 */
function theme_wsclient_ui_parameter_form($variables) {
  $elements = $variables['element'];

  $table['#theme'] = 'table';
  $table['#header'] = array(t('Data type'), t('Multiple'), t('Name'), t('Default value'), t('Required'), t('Allow Null'), array('data' => t('Weight'), 'class' => array('tabledrag-hide')));
  $table['#attributes']['id'] = 'rules-' . \Drupal\Component\Utility\Html::getId($elements['#title']) . '-id';

  foreach (\Drupal\Core\Render\Element::children($elements['items']) as $key) {
    $element = &$elements['items'][$key];
    // Add special classes to be used for tabledrag.js.
    $element['weight']['#attributes']['class'] = array('rules-element-weight');

    $row = array();
    $row[] = array('data' => $element['type']);
    $row[] = array('data' => $element['multiple']);
    $row[] = array('data' => $element['name']);
    $row[] = array('data' => $element['default_value']);
    $row[] = array('data' => $element['required']);
    $row[] = array('data' => $element['allow_null']);
    $row[] = array('data' => $element['weight']);
    $row = array('data' => $row) + $element['#attributes'];
    $row['class'][] = 'draggable';
    $table['#rows'][] = $row;
  }
  $elements['items']['#printed'] = TRUE;
  //if (!empty($table['#rows'])) {
    // @FIXME
// TableDrag is now attached with the #tabledrag property of certain render
// arrays. drupal_add_tabledrag() is now internal and should never be called directly.
// 
// 
// @see https://www.drupal.org/node/2160571
// drupal_add_tabledrag($table['#attributes']['id'], 'order', 'sibling', 'rules-element-weight');

 // }

  // Theme it like a form item, but with the description above the content.
  $attributes['class'][] = 'form-item';
  $attributes['class'][] = 'rules-variables-form';

  $output = '<div' . drupal_attributes($attributes) . '>' . "\n";
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output .= theme('form_element_label', $variables);

  if (!empty($elements['#description'])) {
    $output .= ' <div class="description">' . $elements['#description'] . "</div>\n";
  }
  $output .= ' ' . \Drupal::service("renderer")->render($table) . "\n";
  // Add in any further children elements.
  foreach (\Drupal\Core\Render\Element::children($elements, TRUE) as $key) {
    $output .= \Drupal::service("renderer")->render($elements[$key]);
  }
  $output .= "</div>\n";
  return $output;
}

/**
 * Themes the global parameters form for editing the used parameters.
 *
 * @ingroup themeable
 */
function theme_wsclient_ui_global_parameter_form($variables) {
  $elements = $variables['element'];

  $table['#theme'] = 'table';
  $table['#header'] = array(t('Name'), t('Default value'));
  $table['#attributes']['id'] = 'rules-' . \Drupal\Component\Utility\Html::getId($elements['#title']) . '-id';

  foreach (\Drupal\Core\Render\Element::children($elements['items']) as $key) {
    $element = &$elements['items'][$key];
    // Add special classes to be used for tabledrag.js.
    $element['weight']['#attributes']['class'] = array('rules-element-weight');

    $row = array();
    $row[] = array('data' => $element['name']);
    $row[] = array('data' => $element['default_value']);
    $row = array('data' => $row) + $element['#attributes'];
    $row['class'][] = 'draggable';
    $table['#rows'][] = $row;
  }
  $elements['items']['#printed'] = TRUE;

  // Theme it like a form item, but with the description above the content.
  $attributes['class'][] = 'form-item';
  $attributes['class'][] = 'rules-variables-form';

  $output = '<div' . drupal_attributes($attributes) . '>' . "\n";
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output .= theme('form_element_label', $variables);

  if (!empty($elements['#description'])) {
    $output .= ' <div class="description">' . $elements['#description'] . "</div>\n";
  }
  $output .= ' ' . \Drupal::service("renderer")->render($table) . "\n";
  // Add in any further children elements.
  foreach (\Drupal\Core\Render\Element::children($elements, TRUE) as $key) {
    $output .= \Drupal::service("renderer")->render($elements[$key]);
  }
  $output .= "</div>\n";
  return $output;
}

/**
 * Themes the global header parameters form for editing the used parameters.
 *
 * @ingroup themeable
 */
function theme_wsclient_ui_global_header_parameter_form($variables) {
  $elements = $variables['element'];

  $table['#theme'] = 'table';
  $table['#header'] = array(t('Data Type'), t('Name Space URL'));
  $table['#attributes']['id'] = 'rules-' . \Drupal\Component\Utility\Html::getId($elements['#title']) . '-id';

  foreach (\Drupal\Core\Render\Element::children($elements['items']) as $key) {
    $element = &$elements['items'][$key];
    // Add special classes to be used for tabledrag.js.
    $element['weight']['#attributes']['class'] = array('rules-element-weight');

    $row = array();
    $row[] = array('data' => $element['data_type']);
    $row[] = array('data' => $element['name_space']);
    $row = array('data' => $row) + $element['#attributes'];
    $row['class'][] = 'draggable';
    $table['#rows'][] = $row;
  }
  $elements['items']['#printed'] = TRUE;

  // Theme it like a form item, but with the description above the content.
  $attributes['class'][] = 'form-item';
  $attributes['class'][] = 'rules-variables-form';

  $output = '<div' . drupal_attributes($attributes) . '>' . "\n";
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output .= theme('form_element_label', $variables);

  if (!empty($elements['#description'])) {
    $output .= ' <div class="description">' . $elements['#description'] . "</div>\n";
  }
  $output .= ' ' . \Drupal::service("renderer")->render($table) . "\n";
  // Add in any further children elements.
  foreach (\Drupal\Core\Render\Element::children($elements, TRUE) as $key) {
    $output .= \Drupal::service("renderer")->render($elements[$key]);
  }
  $output .= "</div>\n";
  return $output;
}

/**
 * Returns a list of available data types for service parameters, return
 * values or data type properties. Entities are excluded.
 *
 * @param $hidden
 * 	 Flag to indicate whether the special 'hidden' data type should be added to
 *   the list as well.
 */
function wsclient_ui_types($hidden = FALSE) {
  $cache = rules_get_cache();
  $data_info = $cache['data_info'];
  $entity_info = \Drupal::entityManager()->getDefinitions();
  // Remove entities.
  $data_info = array_diff_key($data_info, $entity_info);
  unset($data_info['entity']);
  // Remove the generic list type.
  unset($data_info['list']);
  $options = array();
  foreach ($data_info as $type => $properties) {
    // Do not add lists as this handled by the "multiple" checkbox.
    if (strpos($type, 'list<') !== 0) {
      $options[$type] = $properties['label'];
    }
  }
  if ($hidden) {
    // Add special 'hidden' data type
    $options['hidden'] = t('hidden');
  }
  natcasesort($options);
  return $options;
}

/**
 * FAPI callback to validate the form for editing parameter info.
 */
function wsclient_ui_validate_parameters($elements, &$form_state) {
  $names = array();
  foreach (\Drupal\Core\Render\Element::children($elements['items']) as $item_key) {
    $element = &$elements['items'][$item_key];
    if ($element['name']['#value'] || $element['type']['#value']) {
      foreach (array('name' => t('Name'), 'type' => t('Data type')) as $key => $title) {
        if (!$element[$key]['#value']) {
          form_error($element[$key], t('!name field is required.', array('!name' => $title)));
        }
      }
      if (isset($names[$element['name']['#value']])) {
        form_error($element['name'], t('The name %name is already taken.', array('%name' => $element['name']['#value'])));
      }
      $names[$element['name']['#value']] = TRUE;
    }
    if ($element['type']['#value'] == 'hidden' && $element['default_value']['#value'] === '') {
      form_error($element['default_value'], t('The "hidden" data type requires a default value.'));
    }
  }
}

/**
 * FAPI callback to validate the form for editing global parameter info.
 */
function wsclient_ui_validate_global_parameters($elements, &$form_state) {
  $names = array();
  foreach (\Drupal\Core\Render\Element::children($elements['items']) as $item_key) {
    $element = &$elements['items'][$item_key];
    if ($element['name']['#value']) {
      if (isset($names[$element['name']['#value']])) {
        form_error($element['name'], t('The name %name is already taken.', array('%name' => $element['name']['#value'])));
      }
      $names[$element['name']['#value']] = TRUE;
    }
  }
}

/**
 * Operation delete confirmation form.
 */
function wsclient_ui_operation_delete($form, &$form_state, $service, $operation) {
  $confirm_question = t('Are you sure you want to delete the operation %operation?', array('%operation' => $operation['label']));
  $form_state['service'] = $service;
  $form_state['operation'] = $operation;
  return confirm_form($form, $confirm_question, WSCLIENT_UI_PATH . '/manage/' . $service->name);
}

/**
 * Submit callback of operation delete form.
 */
function wsclient_ui_operation_delete_submit($form, &$form_state) {
  $service = $form_state['service'];
  $operation = $form_state['operation'];
  unset($service->operations[$operation['name']]);
  $service->save();
  drupal_set_message(t('Deleted operation %operation.', array('%operation' => $operation['label'])));
  $form_state['redirect'] = WSCLIENT_UI_PATH . '/manage/' . $service->name;
}

/**
 * Data type form.
 */
function wsclient_ui_type($form, &$form_state, $service, $type, $op = 'edit') {
  $form['label'] = array(
    '#type' => 'textfield',
    '#title' => t('Label'),
    '#default_value' => isset($type['label']) ? $type['label'] : '',
    '#required' => TRUE,
    '#description' => t('The human-readable name of the data type.'),
  );
  $form['name'] = array(
    '#type' => 'textfield',
    '#title' => t('Name'),
    '#default_value' => isset($type['name']) ? $type['name'] : '',
    '#required' => TRUE,
    '#description' => t('The machine-readable name of this data type is used internally to identify the data type.'),
    '#element_validate' => array('wsclient_ui_type_name_validate'),
  );
  $form['properties'] = array(
    '#tree' => TRUE,
    '#element_validate' => array('wsclient_ui_validate_parameters'),
    '#theme' => 'wsclient_ui_property_form',
    '#title' => t('Properties'),
    '#description' => t('Specify the properties for the data type. For each property you have to specify a certain data type and a unique name containing only alphanumeric characters and underscores. You can also specify if null values are allowed.'),
  );
  $types = wsclient_ui_types();
  if (isset($type['property info'])) {
    foreach ($type['property info'] as $name => $info) {
      $form['properties']['items'][$name] = _wsclient_ui_property_row($service, $types, $name, $info);
    }
  }
  // Always add three empty lines.
  $form_state['more'] = isset($form_state['more']) ? $form_state['more'] : 3;
  for ($i = 0; $i < $form_state['more']; $i++) {
    if (!isset($form['properties']['items'][$i])) {
      $form['properties']['items'][$i] = _wsclient_ui_property_row($service, $types);
    }
  }
  $form['properties']['more'] = array(
    '#type' => 'submit',
    '#value' => t('Add more'),
    '#limit_validation_errors' => array(array('properties')),
    '#submit' => array('wsclient_ui_more_submit'),
  );
  $form['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Save'),
  );
  $form_state['service'] = $service;
  $form_state['type'] = $type;

  // Allow the endpoint to make alterations to the form.
  $form_state['form'] = 'type';
  $service->endpoint()->formAlter($form, $form_state);

  $form['#submit'][] = 'wsclient_ui_type_submit';

  return $form;
}

/**
 * Submit callback of data type form.
 */
function wsclient_ui_type_submit($form, &$form_state) {
  $service = $form_state['service'];
  $type = $form_state['type'];
  $type['label'] = $form_state['values']['label'];

  $type['property info'] = array();
  foreach ($form_state['values']['properties']['items'] as $key => $item) {
    if (!empty($item['name'])) {
      $unmapped_type = _wsclient_ui_unmap_type($item['type'], $service);
      $type['property info'][$item['name']] = array(
        'type' => $unmapped_type,
        'label' => $item['label'],
        'allow null' => $item['allow_null'],
        'default value' => $item['default_value'],
      );
      if ($item['multiple']) {
        $type['property info'][$item['name']] = array('type' => 'list<' . $unmapped_type . '>');
      }
    }
  }
  unset($service->datatypes[$form_state['type']['name']]);
  $service->datatypes[$form_state['values']['name']] = $type;
  $service->save();
  drupal_set_message(t('Data type %type has been saved.', array('%type' => $type['label'])));
  $form_state['redirect'] = WSCLIENT_UI_PATH . '/manage/' . $service->name;
  // Clear caches so that the new type is made available immediately.
  rules_clear_cache();
  $service->clearCache();
}

/**
 * Validation callback for data type names.
 */
function wsclient_ui_type_name_validate($element, &$form_state) {
  if ($element['#value'] && !preg_match('!^[A-Za-z0-9_]+$!', $element['#value'])) {
    form_error($element, t('Data type names must contain only letters, numbers, and underscores.'));
  }
  if ($element['#value'] != $form_state['type']['name'] && isset($form_state['service']->datatypes) && in_array($element['#value'], array_keys($form_state['service']->datatypes))) {
    form_error($element, t('A data type with that name already exists'));
  }
}

/**
 * Helper function to sort a nested data information array based on the label
 * of the items.
 */
function wsclient_ui_label_sort($data_info, $label_key = 'label') {
  $sort_info = array();
  foreach ($data_info as $key => $info) {
    $sort_info[$key] = $info[$label_key];
  }
  natcasesort($sort_info);
  foreach ($sort_info as $key => $label) {
    $sort_info[$key] = $data_info[$key];
  }
  return $sort_info;
}

/**
 * Data type delete confirmation form.
 */
function wsclient_ui_type_delete($form, &$form_state, $service, $type) {
  $confirm_question = t('Are you sure you want to delete the data type %type?', array('%type' => $type['label']));
  $form_state['service'] = $service;
  $form_state['type'] = $type;
  return confirm_form($form, $confirm_question, WSCLIENT_UI_PATH . '/manage/' . $service->name);
}

/**
 * Submit callback of data type delete form.
 */
function wsclient_ui_type_delete_submit($form, &$form_state) {
  $service = $form_state['service'];
  $type = $form_state['type'];
  unset($service->datatypes[$type['name']]);
  $service->save();
  drupal_set_message(t('Deleted data type %type.', array('%type' => $type['label'])));
  $form_state['redirect'] = WSCLIENT_UI_PATH . '/manage/' . $service->name;
}

/**
 * Generates a row in the properties table.
 */
function _wsclient_ui_property_row($service, $types, $name = '', $info = array()) {
  $property_type = 0;
  $multiple = FALSE;
  if (isset($info['type'])) {
    $property_type = wsclient_map_type($service->name, $service->dataTypes(), $info['type']);
    if (strpos($property_type, 'list<') === 0) {
      $multiple = TRUE;
      // Cut off the 'list<>' indicator.
      $property_type = substr($property_type, 5, -1);
    }
  }
  $property['type'] = array(
    '#type' => 'select',
    '#options' => array(0 => '--') + $types,
    '#default_value' => $property_type,
  );
  $property['multiple'] = array(
    '#type' => 'checkbox',
    '#default_value' => $multiple,
  );
  $property['name'] = array(
    '#type' => 'textfield',
    '#size' => 40,
    '#default_value' => $name,
    '#element_validate' => array('wsclient_ui_name_validate'),
  );
  $property['default_value'] = array(
    '#type' => 'textfield',
    '#size' => 30,
    '#default_value' => isset($info['default value']) ? $info['default value'] : '',
  );
  $property['label'] = array(
    '#type' => 'textfield',
    '#size' => 40,
    '#default_value' => isset($info['label']) ? $info['label'] : $name,
  );
  $property['allow_null'] = array(
    '#type' => 'checkbox',
    '#default_value' => isset($info['allow null']) ? $info['allow null'] : FALSE,
  );
  return $property;
}

/**
 * Themes the data type form for editing the properties.
 *
 * @ingroup themeable
 */
function theme_wsclient_ui_property_form($variables) {
  $elements = $variables['element'];

  $table['#theme'] = 'table';
  $table['#header'] = array(
    t('Data type'),
    t('Multiple'),
    t('Name'),
    t('Default value'),
    t('Label'),
    t('Allow Null'),
  );
  $table['#attributes']['id'] = 'rules-' . \Drupal\Component\Utility\Html::getId($elements['#title']) . '-id';

  foreach (\Drupal\Core\Render\Element::children($elements['items']) as $key) {
    $element = &$elements['items'][$key];

    $row = array();
    $row[] = array('data' => $element['type']);
    $row[] = array('data' => $element['multiple']);
    $row[] = array('data' => $element['name']);
    $row[] = array('data' => $element['default_value']);
    $row[] = array('data' => $element['label']);
    $row[] = array('data' => $element['allow_null']);
    $row = array('data' => $row) + $element['#attributes'];
    $table['#rows'][] = $row;
  }
  $elements['items']['#printed'] = TRUE;

  // Theme it like a form item, but with the description above the content.
  $attributes['class'][] = 'form-item';
  $attributes['class'][] = 'rules-variables-form';

  $output = '<div' . drupal_attributes($attributes) . '>' . "\n";
  // @FIXME
// theme() has been renamed to _theme() and should NEVER be called directly.
// Calling _theme() directly can alter the expected output and potentially
// introduce security issues (see https://www.drupal.org/node/2195739). You
// should use renderable arrays instead.
// 
// 
// @see https://www.drupal.org/node/2195739
// $output .= theme('form_element_label', $variables);

  if (!empty($elements['#description'])) {
    $output .= ' <div class="description">' . $elements['#description'] . "</div>\n";
  }
  $output .= ' ' . \Drupal::service("renderer")->render($table) . "\n";
  // Add in any further children elements.
  foreach (\Drupal\Core\Render\Element::children($elements, TRUE) as $key) {
    $output .= \Drupal::service("renderer")->render($elements[$key]);
  }
  $output .= "</div>\n";
  return $output;
}

/**
 * Maps the global name of a data type back to the local name in the service,
 * if the type actually stems from the service.
 */
function _wsclient_ui_unmap_type($type, $service) {
  if (strpos($type, 'wsclient_' . $service->name . '_') === 0) {
    $unmaped_type = substr($type, strlen('wsclient_' . $service->name . '_'));
    if (array_key_exists($unmaped_type, $service->dataTypes())) {
      return $unmaped_type;
    }
  }
  return $type;
}
