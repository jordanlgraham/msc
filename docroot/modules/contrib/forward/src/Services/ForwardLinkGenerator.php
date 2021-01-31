<?php

namespace Drupal\forward\Services;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for generating a Forward link on an entity.
 */
class ForwardLinkGenerator implements ForwardLinkGeneratorInterface {

  /**
   * The link generation service.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $linkGenerator;

  /**
   * The render service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * Constructs a ForwardLinkBuilder object.
   *
   * @param \Drupal\Core\Utility\LinkGenerator $link_generator
   *   The core link generation service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The render service.
   * @param \Drupal\Core\Utility\Token $token_service
   *   The token service.
   */
  public function __construct(LinkGenerator $link_generator, RendererInterface $renderer, Token $token_service) {
    $this->linkGenerator = $link_generator;
    $this->renderer = $renderer;
    $this->tokenService = $token_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('link_generator'),
      $container->get('token'),
      $container->get('renderer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function generate(EntityInterface $entity, array $settings) {
    $link = $this->generateLink($entity, $settings);

    $render_array = [
      '#theme' => 'forward_link_formatter',
      '#link' => $link,
      '#attached' => [
        'library' => [
          'forward/forward',
        ],
      ],
    ];

    return $render_array;
  }

  /**
   * Generate a link to forward the entity using the settings.
   */
  protected function generateLink(EntityInterface $entity, array $settings) {
    $langcode = $entity->language()->getId();
    $token = ['forward' => ['entity' => $entity]];
    $title = $this->tokenService->replace(
      $settings['title'],
      $token,
      ['langcode' => $langcode]
    );
    $title_text = $title;

    $html = FALSE;
    // Output the correct style of link.
    $default_icon = drupal_get_path('module', 'forward') . '/images/forward.gif';
    $custom_icon = $settings['icon'];
    $link_style = $settings['style'];
    switch ($link_style) {
      // Text only is a "noop" since the title text is already setup above.
      // Image only.
      case 1:
        $img = $custom_icon ? $custom_icon : $default_icon;
        $render_array = [
          '#theme' => 'image',
          '#uri' => $img,
          '#alt' => $title,
          '#attributes' => ['class' => ['forward-icon']],
        ];
        $title = $this->renderer->render($render_array);
        $html = TRUE;
        break;

      // Image and text.
      case 2:
        $img = $custom_icon ? $custom_icon : $default_icon;
        $render_array = [
          'image' => [
            '#theme' => 'image',
            '#uri' => $img,
            '#alt' => $title,
            '#attributes' => [
              'class' => [
                'forward-icon',
                'forward-icon-margin',
              ],
            ],
          ],
          'text' => ['#markup' => $title_text],
        ];
        $title = $this->renderer->render($render_array);
        $html = TRUE;
        break;
    }
    $attributes = [
      'title' => $title_text,
      'class' => ['forward-page'],
    ];
    if ($settings['nofollow']) {
      $attributes['rel'] = 'nofollow';
    }

    $entity_id = $entity->id();
    $entity_type = $entity->getEntityTypeId();
    $url = Url::fromUri("internal:/forward/{$entity_type}/{$entity_id}");
    $url->setOptions([
      'html' => $html,
      'attributes' => $attributes,
    ]);

    return $this->linkGenerator->generate($title, $url);
  }

}
