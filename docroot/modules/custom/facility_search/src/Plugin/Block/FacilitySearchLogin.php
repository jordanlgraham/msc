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
 * Class FacilitySearchLogin
 *
 * @package Drupal\facility_search\Plugin\Block
 *
 * @Block(
 *   id="facility_search_login",
 *   admin_label = @Translation("Find a Facility/User login"),
 *   category = @Translation("Homepage")
 * )
 */
class FacilitySearchLogin extends BlockBase implements ContainerFactoryPluginInterface {

  protected $formBuilder;

  protected $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $formBuilder,
                              AccountProxyInterface $user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $formBuilder;
    $this->currentUser = $user;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder'),
      $container->get('current_user')
    );
  }

  public function build() {
    $build = [];
    $build['#prefix'] = '<div class="facility-and-login container"><div class="row">';
    $build['#suffix'] = '</div></div>';
    $build['facility_search'] = [
      '#prefix' => '<div class="facility-search search-login-section col-12 col-md-6">',
      'header' => [
        '#type' => 'markup',
        '#markup' => '<h3>' . $this->t('Find A Member Facility Nearby')  . '</h3>',
      ],
      'form' => $this->formBuilder->getForm(\Drupal\facility_search\Form\SimpleFacilitySearch::class),
      'link' => [
        '#prefix' => '<div class="bottom-link">',
        '#url' => Url::fromRoute('view.d9_facility_search.page_1'),
        '#title' => $this->t('Advanced Search'),
        '#type' => 'link',
        '#suffix' => '</div>',
      ],
      '#suffix' => '</div>',
    ];

    $build['login'] = [
      '#prefix' => '<div class="login search-login-section col-12 col-md-6">',
      '#suffix' => '</div>',
    ];

    if ($this->currentUser->isAuthenticated()) {
      $build['login']['content'] = [
        '#type' => 'markup',
        '#markup' => '<h3>' . $this->t('Member Login') . '</h3><p>' . t('You are currently logged in.') . '</p>',
      ];
    }
    else {
      $build['login']['content'] = [
        'header' => [
          '#type' => 'markup',
          '#markup' => '<h3>' . $this->t('Member Login') . '</h3>',
        ],
        'form' => $this->formBuilder->getForm(\Drupal\user\Form\UserLoginForm::class),
        // 'links' => [
        //   '#prefix' => '<div class="bottom-link">',
        //   '#suffix' => '</div>',
        //   '#theme' => 'links',
        //   '#links' => [
        //     'register' => [
        //       'title' => $this->t('Create Profile'),
        //       'url' => Url::fromRoute('user.register'),
        //     ],
        //     'reset' => [
        //       'title' => $this->t('Forgot Password'),
        //       'url' => Url::fromRoute('user.pass'),
        //     ],
        //  ],
        // ],
        'messages' => [
          '#type' => 'status_messages',
        ],
      ];
    }

    $build['#cache'] = [
      'contexts' => [
        'user.roles:authenticated',
      ],
    ];

    return $build;
  }

}
