<?php

namespace Drupal\vendor_search\Plugin\Block;

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
 * Class VendorSearch
 *
 * @package Drupal\vendor_search\Plugin\Block
 *
 * @Block(
 *   id="vendor_search",
 *   admin_label = @Translation("Find a Preferred Vendor"),
 *   category = @Translation("Sidebar")
 * )
 */
class VendorSearch extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  protected $formBuilder;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $build['vendor_search'] = [
      'form' => $this->formBuilder->getForm(\Drupal\vendor_search\Form\SimpleVendorSearch::class),
      '#cache' => [
        'max-age' => \Drupal\Core\Cache\Cache::PERMANENT,
      ],
    ];

    return $build;
  }

}