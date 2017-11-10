<?php

namespace Drupal\msca_donate\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class DonateForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'msca_donate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $success_content = '') {
    $id = Html::getUniqueId('paypal-donate');

    $form['#attributes']['data-donate'] = $id;
    $form['#attributes']['class'][] = 'donate-form';

    $form['amount'] = [
      '#type' => 'number',
      '#title' => $this->t('Donation amount'),
      '#attributes' => [
        'step' => '0.01',
        'class' => ['amount'],
      ],
      '#field_prefix' => '$',
      '#suffix' => '<div class="alert invalid-amount">' . $this->t('Please enter a valid amount.') . '</div>',
    ];

    $form['button_wrapper'] = [
      '#type' => '#markup',
      '#markup' => "<div id='$id'></div>",
    ];

    $form['success_message'] = [
      '#type' => 'html_tag',
      '#tag' => 'script',
      '#attributes' => [
        'type' => 'text/template',
        'class' => 'success-template',
      ],
      '#value' => $success_content,
    ];

    $form['success_content'] = [
      '#type' => 'markup',
      '#prefix' => '<div class="success-message">',
      '#suffix' => '</div>',
    ];

    $cf = $this->config(DonateConfigForm::CONFIG_KEY);
    $form['#attached']['drupalSettings']['mscaDonate']['credentials']['sandbox'] = $cf->get('sandbox_key');
    $form['#attached']['drupalSettings']['mscaDonate']['credentials']['production'] = $cf->get('production_key');
    $form['#attached']['drupalSettings']['mscaDonate']['mode'] = $cf->get('mode');

    $form['#attached']['library'][] = 'msca_donate/donate';

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // This form won't be submitted.
  }

}
