<?php

namespace Drupal\apitools\Plugin\migrate_plus\data_fetcher;

use Drupal\apitools\Api\Client\ClientInterface;
use Drupal\apitools\ClientManagerInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\migrate\MigrateException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http as HttpBase;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieve data over an HTTP connection for migration.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: url
 *   data_fetcher_plugin: localist_http
 * @endcode
 *
 * @DataFetcher(
 *   id = "apitools_http",
 *   title = @Translation("APITools HTTP")
 * )
 */
class Http extends HttpBase {

  /**
   * @var \Drupal\apitools\Api\Client\ClientInterface
   */
  protected $apitoolsClient;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): \Drupal\migrate_plus\DataFetcherPluginBase
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.apitools_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ClientManagerInterface $client_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    if (empty($configuration['client_plugin_id'])) {
      throw new InvalidPluginDefinitionException('apitools_http', 'Missing value for "client_plugin_id" in migration definition');
    }
    try {
      $this->apitoolsClient = $client_manager->load($configuration['client_plugin_id']);
    }
    catch (PluginNotFoundException $e) {
      throw new InvalidPluginDefinitionException('apitools_http', 'Invalid ID for "client_plugin_id" in migration definition');
    }
  }

  private function doGetResponse($url, $options) {
    if (strpos($url, ':') !== FALSE) {
      $props = explode(':', $url);
      $method = array_pop($props);
      $executable = array_reduce($props, function($carry, $value) {
        return $carry->{$value};
      }, $this->apitoolsClient);

      if ($executable && $method) {
        if (is_array($this->configuration['client_arguments']))  {
          $options = array_merge($this->configuration['client_arguments'], [$options]);
          return $executable->{$method}(...$options);
        }
        return $executable->{$method}($options);
      }
    }
    return $this->apitoolsClient->get($url, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse($url, $is_count = FALSE): \Psr\Http\Message\ResponseInterface
  {
    try {
      $options = ['headers' => $this->getRequestHeaders()];
      if (!empty($this->configuration['authentication'])) {
        $options = array_merge($options, $this->getAuthenticationPlugin()->getAuthenticationOptions());
      }
      if (!empty($this->configuration['client_options'])) {
        $options = array_merge($options, $this->configuration['client_options']);
      }
      $options['count'] = $is_count;
      $response = $this->doGetResponse($url, $options);
      if (empty($response)) {
        throw new MigrateException('No response at ' . $url . '.');
      }
    }
    catch (RequestException $e) {
      throw new MigrateException('Error message: ' . $e->getMessage() . ' at ' . $url . '.');
    }
    return $response;
  }

  private function isCount() {
    $callers = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 15);
    return array_reduce($callers, function($carry, $item) {
      if ($carry) {
        return $carry;
      }
      $is_source_class = $item['class'] == SourcePluginBase::class;
      $is_count = $item['function'] == 'count';
      if ($is_source_class && $is_count) {
        $carry = TRUE;
      }
      return $carry;
    }, FALSE);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent($url): string
  {
    $is_count = $this->isCount();
    $data = $this->getResponse($url, $is_count);
    //$events = $data['events'];
    //$events = array_map(function($value) { return $value['event']; }, $events);
    return \Drupal::service('serializer')->encode($data, 'json');
  }
}
