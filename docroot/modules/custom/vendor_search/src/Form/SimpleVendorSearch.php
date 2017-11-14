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
    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Preferred Vendor Search'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Vendor Name'),
      '#required' => TRUE,
    ];

    // We need to provide this hidden field so the exposed filters on the destination view function properly.
    $form['field_primary_services_target_id'] = [
      '#type' => 'hidden',
      '#title' => $this->t('Primary Service'),
      '#title_display' => 'invisible',
      '#default_value' => $this->t('All'),
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
    $title = $form_state->getValue('title');
    $services = $form_state->getValue('field_primary_services_target_id');

    $query = ['title' => $title, 'field_primary_services_target_id' => $services];

    $form_state->setRedirect('view.preferred_vendors.page_1', [], ['query' => $query]);
  }

}