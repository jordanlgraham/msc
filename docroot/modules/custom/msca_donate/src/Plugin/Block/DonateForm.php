<?php

namespace Drupal\msca_donate\Plugin\Block;

use Drupal\Core\Annotation\Translation;
use Drupal\Core\Block\Annotation\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DonateForm
 *
 * @package Drupal\msca_donate\Plugin\Block
 *
 * @Block(
 *   id="donate_form",
 *   category=@Translation("Forms"),
 *   admin_label=@Translation("Donate form"),
 * )
 */
class DonateForm extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
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

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Confirmation message'),
      '#description' => $this->t('Message to display after the donation has been made. Use "@first" and "@last"
        for the user\'s first and last name'),
      '#required' => TRUE,
      '#default_value' => !empty($this->configuration['message']) ? $this->configuration['message'] : '',
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $this->configuration['message'] = $form_state->getValue('message');
    parent::submitConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $msg = $this->configuration['message'];
    $build['form'] = $this->formBuilder->getForm(\Drupal\msca_donate\Form\DonateForm::class, $msg);
    $build['#cache'] = [
      'max-age' => Cache::PERMANENT,
    ];
    return $build;
  }

}
