<?php

namespace Drupal\forward;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a class for building markup for a Forward link on an entity.
 */
class ForwardLinkBuilder implements ForwardLinkBuilderInterface {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected $tokenService;

  /**
   * The render service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The link generation service.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $linkGenerator;

  /**
   * Constructs a ForwardLinkBuilder object.
   *
   * @param \Drupal\Core\Utility\Token
   *   The token service.
   * @param \Drupal\Core\Render\RendererInterface
   *   The render service.
   * @param \Drupal\Core\Utility\LinkGenerator
   *   The link generation service.
   */
  public function __construct(Token $token_service, RendererInterface $renderer, LinkGenerator $link_generator) {
    $this->tokenService = $token_service;
    $this->renderer = $renderer;
    $this->linkGenerator = $link_generator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('token'),
      $container->get('renderer'),
      $container->get('link_generator')
    );
  }

  /**
   * Build a link.
   */
  private function buildLink(EntityInterface $entity, array $settings) {
    $langcode = $entity->language()->getId();
    $token = ['forward' => ['entity' => $entity]];
    $title = $this->tokenService->replace($settings['forward_interface_title'], $token, array('langcode' => $langcode));
    $title_text = $title;

    $html = FALSE;
    // Output the correct style of link.
    $default_icon = drupal_get_path('module', 'forward') . '/images/forward.gif';
    $custom_icon = $settings['forward_link_icon'];
    $link_style = $settings['forward_link_style'];
    switch ($link_style) {
      // Text only is a "noop" since the title text is already setup above.

      // Image only.
      case 1:
        $img = $custom_icon ? $custom_icon : $default_icon;
        $render_array = array('#theme' => 'image', '#uri' => $img, '#alt' => $title, '#attributes' => array('class' => array('forward-icon')));
        $title = $this->renderer->render($render_array);
        $html = TRUE;
        break;
      // Image and text.
      case 2:
        $img = $custom_icon ? $custom_icon : $default_icon;
        $render_array = array(
          'image' => array('#theme' => 'image', '#uri' => $img, '#alt' => $title, '#attributes' => array('class' => array('forward-icon', 'forward-icon-margin'))),
          'text' => array('#markup' => $title_text),
        );
        $title = $this->renderer->render($render_array);
        $html = TRUE;
        break;
    }
    $attributes = array(
      'title' => $this->tokenService->replace($settings['forward_link_title'], $token, array('langcode' => $langcode)),
      'class' => array('forward-page'),
    );
    if ($settings['forward_link_nofollow']) {
      $attributes['rel'] = 'nofollow';
    }

    $entity_id = $entity->id();
    $entity_type = $entity->getEntityTypeId();
    $url = Url::fromUri("internal:/forward/{$entity_type}/{$entity_id}");
    $url->setOptions(
      array(
        'html' => $html,
        'attributes' => $attributes,
      )
    );
    return array('title' => $title, 'url' => $url, 'attributes' => $attributes);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForwardEntityLink(EntityInterface $entity, array $settings) {
    $link = $this->buildLink($entity, $settings);
    if ($settings['forward_link_inline']) {
      // Render the link inline with other node links.
      $render_array = array(
        '#theme' => 'links',
        '#links' => array(
          'forward' => array(
            'url' => $link['url'],
            'title' => $link['title'],
            'attributes' => $link['attributes'],
          ),
        ),
        '#attributes' => array(
          'class' => array('links', 'inline'),
        ),
        '#attached' => array(
          'library' => array(
            'forward/forward',
          ),
        ),
        '#weight' => $settings['forward_interface_weight'],
      );
    } else {
      // Standard render.
      $render_array = array(
        '#markup' => $this->linkGenerator->generate($link['title'], $link['url']),
        '#attached' => array(
          'library' => array(
            'forward/forward',
          ),
        ),
        '#weight' => $settings['forward_interface_weight'],
      );
    }
    return $render_array;
  }
}
