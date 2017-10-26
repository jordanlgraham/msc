<?php

namespace Drupal\facility_search\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class SimpleFacilitySearch extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'facility_search_simple';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['keys'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Facility Search by Name, City, or Zip Code'),
      '#title_display' => 'invisible',
      '#placeholder' => $this->t('Facility Search by Name, City, or Zip Code'),
      '#required' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search'),
      '#prefix' => '<div class="facility-search-submit">',
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
    // Set the query string depending on the user's input
    if (!is_numeric($keys)) {
      $query = ['keys' => $keys];
    }
    else {
      $query = [
        'proximity' => 5,
        'geocode_postal' => $keys,
        'geocode_state' => 1,
      ];
    }
    $form_state->setRedirect('view.facility_search.page_1', [], ['query' => $query]);
  }

}