<?php

namespace Drupal\facility_search\Plugin\Block;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FacilitySearch
 *
 * @package Drupal\facility_search\Plugin\Block
 *
 * @Block(
 *   id="facility_search",
 *   admin_label = @Translation("Find a Facility"),
 *   category = @Translation("Sidebar")
 * )
 */
class FacilitySearch extends BlockBase implements ContainerFactoryPluginInterface {

  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  public function build() {
    $build = [];
    $build['status_messages'] = [
      '#type' => 'status_messages',
    ];
    $build['facility_search'] = [
      'form' => $this->formBuilder->getForm(\Drupal\facility_search\Form\SimpleFacilitySearch::class),
    ];

    return $build;
  }

}