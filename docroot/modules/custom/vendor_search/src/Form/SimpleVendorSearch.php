<?php

namespace Drupal\vendor_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SimpleVendorSearch extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'vendor_search_simple';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['keys'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preferred Vendor Search'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Vendor Name'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#prefix' => '<div class="vendor-search-submit">',
      '#suffix' => '</div>',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Redirect the user to the view with the exposed filter set.
    $keys = $form_state->getValue('keys');
    $query = ['keys' => $keys];

    $form_state->setRedirect('view.preferred_vendors.page_1', [], ['query' => $query]);
  }

}