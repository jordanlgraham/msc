<?php

namespace Drupal\forward\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the Forward widget.
 *
 * @FieldWidget(
 *   id = "forward",
 *   label = @Translation("Forward"),
 *   field_types = {
 *     "forward",
 *   }
 * )
 */
class ForwardWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $display = $items[$delta]->get('display')->getValue();
    $options = [
      TRUE => $this->t('Display the Forward link'),
      FALSE => $this->t('Hide the Forward link'),
    ];
    $element['display'] = [
      '#title' => $this->t('Display'),
      '#type' => 'radios',
      '#options' => $options,
      '#default_value' => $display ?? TRUE,
    ];
    $element['#type'] = 'details';
    $element['#group'] = 'advanced';

    return $element;
  }

}
