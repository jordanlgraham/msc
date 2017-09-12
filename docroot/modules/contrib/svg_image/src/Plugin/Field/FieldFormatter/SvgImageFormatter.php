<?php

namespace Drupal\svg_image\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;
use Drupal\Core\Cache\Cache;

/**
 * Plugin implementation of the 'image' formatter.
 *
 * We have to fully override standard field formatter, so we will keep original
 * label and formatter ID.
 *
 * @FieldFormatter(
 *   id = "image",
 *   label = @Translation("Image"),
 *   field_types = {
 *     "image"
 *   },
 *   quickedit = {
 *     "editor" = "image"
 *   }
 * )
 */
class SvgImageFormatter extends ImageFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    /** @var \Drupal\file\Entity\File[] $files */
    $files = $this->getEntitiesToView($items, $langcode);

    // Early opt-out if the field is empty.
    if (empty($files)) {
      return $elements;
    }

    $url = NULL;
    $imageLinkSetting = $this->getSetting('image_link');
    // Check if the formatter involves a link.
    if ($imageLinkSetting === 'content') {
      $entity = $items->getEntity();
      if (!$entity->isNew()) {
        $url = $entity->toUrl();
      }
    }
    elseif ($imageLinkSetting === 'file') {
      $linkFile = TRUE;
    }

    $imageStyleSetting = $this->getSetting('image_style');

    // Collect cache tags to be added for each item in the field.
    $cacheTags = [];
    if (!empty($imageStyleSetting)) {
      $imageStyle = $this->imageStyleStorage->load($imageStyleSetting);
      $cacheTags = $imageStyle->getCacheTags();
    }

    foreach ($files as $delta => $file) {
      $cacheContexts = [];
      if (isset($linkFile)) {
        $imageUri = $file->getFileUri();
        $url = Url::fromUri(file_create_url($imageUri));
        $cacheContexts[] = 'url.site';
      }
      $cacheTags = Cache::mergeTags($cacheTags, $file->getCacheTags());

      // Extract field item attributes for the theme function, and unset them
      // from the $item so that the field template does not re-render them.
      $item = $file->_referringItem;
      $attributes = $item->_attributes;
      unset($item->_attributes);

      $elements[$delta] = [
        '#theme' => 'image_formatter',
        '#item' => $item,
        '#item_attributes' => $attributes,
        '#image_style' => svg_image_is_file_svg($file) ? NULL : $imageStyleSetting,
        '#url' => $url,
        '#cache' => [
          'tags' => $cacheTags,
          'contexts' => $cacheContexts,
        ],
      ];
    }

    return $elements;
  }

}
