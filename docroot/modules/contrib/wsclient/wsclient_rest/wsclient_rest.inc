<?php

/**
 * @file
 * Web service client REST - include file.
 */

/**
 * A remote endpoint type for invoking REST services.
 */
class WSClientRESTEndpoint extends WSClientEndpoint {

  /**
   * @var HttpClient
   */
  protected $client;

  public function __construct(WSClientServiceDescription $service) {
    $this->service = $service;
    $this->url = $service->url;
  }

  /**
   * {@inheritdoc}
   * @return HttpClient
   */
  public function client() {
    if (!isset($this->client)) {
      $settings = $this->service->settings;

      // Determine which type of authentication to use.
      $authentication = NULL;
      if (isset($settings['authentication'])) {
        // Handle each type of authentication.
        foreach ($settings['authentication'] as $auth => $auth_settings) {
          // @todo Remove this check once multiple authentications are
          // implemented.
          if ($auth == 'type') {
            continue;
          }

          // @todo Remove this check once multiple authentications are
          // implemeted. For now the switch would cause the last configured
          // authentication type to be taken.
          if ($auth == $settings['authentication']['type']) {
            // @todo Allow multiple authentications by implementing and using
            // HttpClientCompositeAuth.
            switch ($auth) {
              // OAuth v2 support using the http_client module.
              case 'oauth2':
                $authentication = new HttpClientOAuth2($auth_settings);
                break;

              // Default authentication options from the http_client module.
              case 'basic':
              case 'wss':
              default:
                $username = $auth_settings['username'];
                $password = $auth_settings['password'];
                $authentication = new HttpClientBasicAuth($username, $password);
                break;
            }
          }
        }
      }

      if (wsclient_rest_has_old_formatter($settings)) {
        $formatter = new $settings['formatter']();
        $this->client = new HttpClient($authentication, $formatter, $this);
      }
      else {
        $formatters = array();
        foreach (array('send_formatter', 'receive_formatter') as $formatter_type) {
          // Settings could be missing.
          $formatter_settings = !empty($settings[$formatter_type]['settings'])
            ? $settings[$formatter_type]['settings']
            : array();
          $formatters[$formatter_type] = new $settings[$formatter_type]['class']($formatter_settings);
        }
        $formatter = new HttpClientCompositeFormatter($formatters['send_formatter'], $formatters['receive_formatter']);
        $this->client = new HttpClient($authentication, $formatter, $this);
      }
      // Pass through additional curl options.
      if (!empty($settings['curl options'])) {
        $this->client->options['curlopts'] = $settings['curl options'];
      }
    }
    return $this->client;
  }

  /**
   * Calls the REST service.
   *
   * @param string $operation_name
   *   The name of the operation to execute.
   * @param array $arguments
   *   Arguments to pass to the service with this operation.
   */
  public function call($operation_name, $arguments) {
    $operation = $this->service->operations[$operation_name];
    $operation_url = isset($operation['url']) ? $operation['url'] : '';
    // Replace argument patterns in the operation URL.
    foreach ($arguments as $key => $value) {
      if (strpos($operation_url, '@' . $key) !== FALSE) {
        $operation_url = str_replace('@' . $key, $value, $operation_url);
        unset($arguments[$key]);
      }
    }
    $client = $this->client();
    $type = isset($operation['type']) ? $operation['type'] : 'GET';
    $data = NULL;
    if (isset($operation['data'])) {
      $data = $arguments[$operation['data']];
      unset($arguments[$operation['data']]);
    }
    // Services module compliance: post fields should be in the $data array
    // instead of $arguments.
    if ($type == 'POST' || $type == 'PUT') {
      $data = array_merge((array) $data, $arguments);
      if (empty($data)) {
        unset($data);
      }
      unset($arguments);
    }
    try {
      $response = $client->execute(new HttpClientRequest($this->service->url . $operation_url, array(
        'method' => $type,
        'parameters' => $arguments,
        'data' => $data,
      )));
      return $response;
    }
    catch (HttpClientException $e) {
      throw new WSClientException('Error invoking the REST service %name, operation %operation: %error', array('%name' => $this->service->label, '%operation' => $operation_name, '%error' => $e->getMessage()));
    }
  }

  /**
   * Adds options to the Web Service config form specific to the REST service.
   *
   * Allows the user to choose which formatter (JSO, PHP, XML) to use for
   * requesting or parsing
   *
   * @see WSClientEndpoint::formAlter($form, $form_state)
   */
  public function formAlter(&$form, &$form_state) {
    $service = $form_state['service'];
    $options = wsclient_rest_formatters_as_options();
    switch ($form_state['form']) {
      case 'main':
        $default_formatter = NULL;
        foreach (array('send_formatter', 'receive_formatter') as $formatter_type) {
          if (wsclient_rest_has_custom_formatter($service->settings, $formatter_type)) {
            $default_formatter = 'custom';
          }
          else {
            $default_formatter = isset($service->settings[$formatter_type]['class'])
              ? $service->settings[$formatter_type]['class']
              : 'WsclientRestJSONFormatter';
          }
          switch ($formatter_type) {
            case 'send_formatter':
              $title = t('Request formatter (send_formatter)');
              $description = t('Choose how to serialize the request.');
              $weight = 50;
              break;
            case 'receive_formatter':
              $title = t('Response formatter (receive_formatter)');
              $description = t('Choose how to parse the response.');
              $weight = 55;
              break;
          }
          $form['settings'][$formatter_type] = array(
            '#type' => 'fieldset',
            '#title' => $title,
            '#tree' => TRUE,
            '#weight' => $weight,
          );
          $form['settings'][$formatter_type]['class'] = array(
            '#type' => 'select',
            '#title' => t('Formatter'),
            '#default_value' => $default_formatter,
            '#description' => $description,
            '#options' => $options,
          );
        }
        break;
      case 'operation':
        $operation = $form_state['operation'];
        $form['type'] = array(
          '#type' => 'select',
          '#title' => t('HTTP Method'),
          '#default_value' => isset($operation['type']) ? $operation['type'] : 'GET',
          '#description' => t('Specify the HTTP request method used for this operation.'),
          '#options' => array(
            'GET' => 'GET',
            'POST' => 'POST',
            'PUT' => 'PUT',
            'DELETE' => 'DELETE',
          ),
          '#weight' => -5,
        );
        $form['url'] = array(
          '#type' => 'textfield',
          '#title' => t('Path'),
          '#default_value' => isset($operation['url']) ? $operation['url'] : '',
          '#description' => t('The path to append to the services base URL. In order to construct the path using parameter values make use of replacements in the form "@parameter-name". E.g. the path "node/@nid" together with a "nid" parameter could be used to construct the path to a Drupal node.'),
          '#weight' => -5,
        );
        break;
    }
    // Allow formatters to alter the form.
    foreach ($options as $options_groupped) {
      foreach (array_keys($options_groupped) as $formatter_class) {
        if ($formatter_class == 'custom') {
          // Skip custom.
          continue;
        }
        $formatter = new $formatter_class();
        if (method_exists($formatter, 'formAlter')) {
          $formatter->formAlter($form, $form_state);
        }
      }
    }
    $form['#submit'][] = 'wsclient_rest_form_submit';
  }

  /**
   * Alters the given request.
   *
   * The public alterRequest method supported by Hugo Wetterberg's HttpClient.
   * The request to be altered is passed to drupal_alter() with the service
   * definition as a context.
   *
   * @param HttpClientRequest $request
   *   The request to be altered.
   *
   * @see HttpClient
   * @see hook_wsclient_rest_request_alter()
   */
  public function alterRequest($request) {
    drupal_alter('wsclient_rest_request', $request, $this->service);
  }
}

class WsclientRestFormFormatter extends HttpClientBaseFormatter {
  public function __construct() {
    parent::__construct(self::FORMAT_FORM);
  }
}

class WsclientRestJSONFormatter extends HttpClientBaseFormatter {
  public function __construct() {
    parent::__construct(self::FORMAT_JSON);
  }
}

class WsclientRestPHPFormatter extends HttpClientBaseFormatter {
  public function __construct() {
    parent::__construct(self::FORMAT_PHP);
  }
}

class WsclientRestXMLFormatter extends HttpClientXMLFormatter {
  public function __construct($settings = array()) {
    parent::__construct(
      !empty($settings['default_root']) ? $settings['default_root'] : NULL,
      !empty($settings['adaptive_root']) ? $settings['adaptive_root'] : NULL
    );
    $this->settings = $settings;
  }

  public function formAlter(&$form, &$form_state) {
    switch ($form_state['form']) {
      case 'main':
        $service = $form_state['service'];
        $info = module_invoke_all('wsclient_rest_formatter_info');
        $default_formatter_settings = $info['WsclientRestXMLFormatter']['settings'];
        foreach (array('send_formatter', 'receive_formatter') as $formatter_type) {
          // Alias for such long nested path.
          $formatter_settings = !empty($service->settings[$formatter_type]['settings'])
            ? $service->settings[$formatter_type]['settings']
            : array();
          // UI for settings.
          $form['settings'][$formatter_type]['settings']['default_root'] = array(
            '#type' => 'textfield',
            '#title' => t('Default root'),
            '#default_value' => isset($formatter_settings['default_root']) ? $formatter_settings['default_root'] : $default_formatter_settings['default_root'],
            '#description' => t('Default root for created XML documents.'),
            '#states' => array(
              'visible' => array(
                'select[name="settings[' . $formatter_type . '][class]"]' => array('value' => 'WsclientRestXMLFormatter'),
              ),
            ),
          );
          $form['settings'][$formatter_type]['settings']['adaptive_root'] = array(
            '#type' => 'checkbox',
            '#title' => t('Adaptive root'),
            '#default_value' => isset($formatter_settings['adaptive_root']) ? $formatter_settings['adaptive_root'] : $default_formatter_settings['adaptive_root'],
            '#description' => t('If it is set to TRUE and the source data has a single root attribute the formatter will use that attribute as root. The object %foo_object would be serialized to %foo_wrapped instead of %foo_wrapped_result.',
              array(
                '%foo_object' => '{"foo":"bar"}',
                '%foo_wrapped' => '<foo>bar</foo>',
                '%foo_wrapped_result' => '<result><foo>bar</foo></result>',
              )
            ),
            '#states' => array(
              'visible' => array(
                'select[name="settings[' . $formatter_type . '][class]"]' => array('value' => 'WsclientRestXMLFormatter'),
              ),
            ),
          );
        }
        break;
    }
  }

  public function formSubmit($form, &$form_state) {
    switch ($form_state['form']) {
      case 'main':
        // Remove XML settings from the formatters that are not XML.
        foreach (array('send_formatter', 'receive_formatter') as $formatter_type) {
          if (empty($form_state['values']['settings'][$formatter_type]['formatter'])) {
            continue;
          }
          if ($form_state['values']['settings'][$formatter_type]['formatter'] != 'WsclientRestXMLFormatter') {
            foreach (array('default_root', 'adaptive_root') as $setting) {
              unset($form_state['values']['settings'][$formatter_type]['settings'][$setting]);
            }
          }
        }
        break;
    }
  }
}
