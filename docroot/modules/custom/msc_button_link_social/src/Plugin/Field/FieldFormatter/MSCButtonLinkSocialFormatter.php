<?php

namespace Drupal\msc_button_link_social\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\link\Plugin\Field\FieldFormatter\LinkFormatter;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'link_separate' formatter.
 *
 * @todo https://www.drupal.org/node/1829202 Merge into 'link' formatter once
 *   there is a #type like 'item' that can render a compound label and content
 *   outside of a form context.
 *
 * @FieldFormatter(
 *   id = "msc_button_link_social",
 *   label = @Translation("Link as Social Button"),
 *   field_types = {
 *     "link"
 *   }
 * )
 */
class MSCButtonLinkSocialFormatter extends LinkFormatter {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'trim_length' => 80,
      'rel' => '',
      'target' => '',
      'link_text' => '',
      'icon_only' => 0,
      'btn_type' => 'btn-default',
    ) + parent::defaultSettings();
  }

  public function settingsForm(array $parentForm, FormStateInterface $form_state) {
    $parentForm = parent::settingsForm($parentForm, $form_state);
    $settings = $this->getSettings();

    $form['link_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link text, leave empty for default'),
      '#default_value' => $settings['link_text'],
    ];
	
    $form['btn_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Button type'),
      '#default_value' => $settings['btn_type'],
      '#options' => [
        'btn-default' => $this->t('Default'),
        'btn-twitter' => $this->t('Twitter'),
        'btn-facebook' => $this->t('Facebook'),
      ],
      '#required' => TRUE,
    ];

    $form['btn_size'] = [
      '#type' => 'select',
      '#title' => $this->t('Button size'),
      '#default_value' => $settings['btn_size'],
      '#empty_option' => $this->t('Default'),
      '#options' => [
        'btn-lg' => $this->t('Large'),
        'btn-sm' => $this->t('Small'),
        'btn-xs' => $this->t('Extra Small'),
      ],
    ];

    $form['icon_only'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Icon Only'),
      '#default_value' => $settings['icon_only'],
    ];

    return $form + $parentForm;
  }

  public function settingsSummary() {
    $settings = $this->getSettings();

    $summary[] = $this->t('Button type: @text', ['@text' => $settings['btn_type']]);
    if (!empty($settings['btn_size'])) {
      $summary[] = $this->t('Button size: @text', ['@text' => $settings['btn_size']]);
    }
    if (!empty($settings['link_text'])) {
      $summary[] = $this->t('Link text: @text', ['@text' => $settings['link_text']]);
    }
    if (!empty($settings['icon_only'])) {
      $summary[] = $this->t('Icon Only: @text', ['@text' => $settings['icon_only']]);
    }
    if (!empty($settings['rel'])) {
      $summary[] = $this->t('Add rel="@rel"', ['@rel' => $settings['rel']]);
    }
    if (!empty($settings['target'])) {
      $summary[] = $this->t('Open link in new window');
    }

    return $summary;
  }
  
  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();
    $entity = $items->getEntity();
    $settings = $this->getSettings();

    foreach ($items as $delta => $item) {
      // By default use the full URL as the link text.
      $url = $this->buildUrl($item);
      $link_title = $url->toString();

      // If the link text field value is available, use it for the text.
      if (empty($settings['url_only']) && !empty($item->title)) {
        // Unsanitized token replacement here because the entire link title
        // gets auto-escaped during link generation in
        // \Drupal\Core\Utility\LinkGenerator::generate().
        $link_title = \Drupal::token()->replace($item->title, [$entity->getEntityTypeId() => $entity], ['clear' => TRUE]);
      }

      if (!empty($settings['link_text'])) {
        $link_title = $settings['link_text'];
      }

      // If Icon Only is checked, nuke the text.
      if (!empty($settings['icon_only'] && $settings['icon_only'] == 1)) {
        $link_title = '';
      }

      // The link_separate formatter has two titles; the link text (as in the
      // field values) and the URL itself. If there is no link text value,
      // $link_title defaults to the URL, so it needs to be unset.
      // The URL version may need to be trimmed as well.
      if (empty($item->title) && empty($settings['link_text'])) {
        $link_title = NULL;
      }
      $url_title = $url->toString();
      if (!empty($settings['trim_length'])) {
        $link_title = Unicode::truncate($link_title, $settings['trim_length'], FALSE, TRUE);
        $url_title = Unicode::truncate($url_title, $settings['trim_length'], FALSE, TRUE);
      }

      $element[$delta] = array(
        '#theme' => 'link_formatter_msc_button_link_social',
        '#title' => $link_title,
        '#url_title' => $url_title,
        '#url' => $url,
        '#type' => $settings['btn_type'],
        '#size' => $settings['btn_size'],
      );

      if (!empty($item->_attributes)) {
        // Set our RDFa attributes on the <a> element that is being built.
        $url->setOption('attributes', $item->_attributes);

        // Unset field item attributes since they have been included in the
        // formatter output and should not be rendered in the field template.
        unset($item->_attributes);
      }
    }
    return $element;
  }

}
