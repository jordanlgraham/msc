<?php

namespace Drupal\facility_search\Plugin\views\area;

use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Utility\Xss;
use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\area\Result;
use Drupal\views\Plugin\views\style\DefaultSummary;

/**
 * Views area handler to display some configurable result summary. Extended
 * to show exposed filters that are set.
 *
 * @ingroup views_area_handlers
 *
 * @ViewsArea("result_plus_proximity")
 */
class ResultPlusProximity extends Result {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['content'] = [
      'default' => $this->t('Displaying @start - @end of @total'),
    ];

    $options['term_filter_indentifiers'] = [
      'default' => $this->t('term_identifer'),
    ];

    $options['text_filter_indentifiers'] = [
      'default' => $this->t('text_identifer'),
    ];

    $options['item_type_singular'] = [
      'default' => 'item',
    ];

    $options['item_type_plural'] = [
      'default' => 'items',
    ];

    $options['exposed_summary_prefix'] = [
      'default' => 'for',
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $item_list = [
      '#theme' => 'item_list',
      '#items' => [
        '@start -- the initial record number in the set',
        '@end -- the last record number in the set',
        '@total -- the total records in the set',
        '@label -- the human-readable name of the view',
        '@per_page -- the number of items per page',
        '@current_page -- the current page number',
        '@current_record_count -- the current page record count',
        '@page_count -- the total page count',
        '@exposed_filter_summary -- summary of exposed filters',
        '@proximity_string -- e.g. \'within [x] miles of [location]\'',
      ],
    ];
    $list = \Drupal::service('renderer')->render($item_list);
    $form['content'] = [
      '#title' => $this->t('Display'),
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $this->options['content'],
      '#description' => $this->t('You may use HTML code in this field. The following tokens are supported:') . $list,
    ];
    $form['term_filter_indentifiers'] = [
      '#title' => $this->t('Exposed Taxonomy Term Filter Identifiers'),
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $this->options['term_filter_indentifiers'],
      '#description' => $this->t('Enter the machine name identifiers of each exposed Taxonomy Term fields you care to include in the summary. New line for each.'),
    ];
    $form['text_filter_indentifiers'] = [
      '#title' => $this->t('Exposed Text Filter Identifiers'),
      '#type' => 'textarea',
      '#rows' => 3,
      '#default_value' => $this->options['text_filter_indentifiers'],
      '#description' => $this->t('Enter the machine name identifiers of each exposed text fields you care to include in the summary. New line for each.'),
    ];
    $form['item_type_singular'] = [
      '#title' => $this->t('Type of thing shown - singular'),
      '#type' => 'textfield',
      '#default_value' => $this->options['item_type_singular'],
      '#description' => $this->t('Enter the singular type of thing being shown.'),
    ];
    $form['item_type_plural'] = [
      '#title' => $this->t('Type of thing shown - plural'),
      '#type' => 'textfield',
      '#default_value' => $this->options['item_type_plural'],
      '#description' => $this->t('Enter the plural type of thing being shown.'),
    ];
    $form['exposed_summary_prefix'] = [
      '#title' => $this->t('Prefix to show before the exposed summary'),
      '#type' => 'textfield',
      '#default_value' => $this->options['exposed_summary_prefix'],
      '#description' => $this->t('Example "for".'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (strpos($this->options['content'], '@total') !== FALSE) {
      $this->view->get_total_rows = TRUE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render($empty = FALSE) {
    $merp = 'derp';
    // Must have options and does not work on summaries.
    if (!isset($this->options['content']) || $this->view->style_plugin instanceof DefaultSummary) {
      return [];
    }
    $output = '';
    $format = $this->options['content'];
    $exposed = $this->view->exposed_data;
    $selected = [];
    $term_filters = explode(PHP_EOL, $this->options['term_filter_indentifiers']);
    $text_filters = explode(PHP_EOL, $this->options['text_filter_indentifiers']);

    // Handle Text filters.
    if (!empty($text_filters)) {
      foreach ($text_filters as $text_filter) {
        $text_filter = trim($text_filter);
        if (isset($exposed[$text_filter]) && !empty($exposed[$text_filter])) {
          $selected[] = $exposed[$text_filter];
        }
      }
    }

    // Handle taxonomy term filters.
    if (!empty($term_filters)) {
      foreach ($term_filters as $term_filter) {
        $term_filter = trim($term_filter);
        if (isset($exposed[$term_filter]) && $exposed[$term_filter] != 'All') {
          if ($term = Term::load($exposed[$term_filter])) {
            $selected[] = $term->getName();
          }
        }
      }
    }

    $exposed_filter_summary = '';
    if (!empty($selected)) {
      // TODO: Allow altering html?
      // Starts to get a bit crazy.
      $exposed_filter_summary = $this->options['exposed_summary_prefix'] . '<strong>' . implode(', ', $selected) . '</strong>';
    }

    // Calculate the page totals.
    $current_page = (int) $this->view->getCurrentPage() + 1;
    $per_page = (int) $this->view->getItemsPerPage();
    // @TODO: Maybe use a possible is views empty functionality.
    // Not every view has total_rows set, use view->result instead.
    $total = isset($this->view->total_rows) ? $this->view->total_rows : count($this->view->result);
    $label = Html::escape($this->view->storage->label());
    // If there is no result the "start" and "current_record_count" should be
    // equal to 0. To have the same calculation logic, we use a "start offset"
    // to handle all the cases.
    $start_offset = empty($total) ? 0 : 1;
    if ($per_page === 0) {
      $page_count = 1;
      $start = $start_offset;
      $end = $total;
    }
    else {
      $page_count = (int) ceil($total / $per_page);
      $total_count = $current_page * $per_page;
      if ($total_count > $total) {
        $total_count = $total;
      }
      $start = ($current_page - 1) * $per_page + $start_offset;
      $end = $total_count;
    }
    $current_record_count = ($end - $start) + $start_offset;

    // Get proximity and location info.
    $proximity = 'within ' . $exposed['proximity'] . ' miles of ';
    $location = $exposed['center']['geocoder']['geolocation_geocoder_address'];
    
    // Get the search information.
    $replacements = [];
    $replacements['@exposed_filter_summary'] = $exposed_filter_summary;
    $replacements['@start'] = $start;
    $replacements['@end'] = $end;
    $replacements['@total'] = \Drupal::translation()->formatPlural($total, '1 @singular', '@count @plural', ['@singular' => $this->options['item_type_singular'], '@plural' => $this->options['item_type_plural']]);
    $replacements['@label'] = $label;
    $replacements['@per_page'] = $per_page;
    $replacements['@current_page'] = $current_page;
    $replacements['@current_record_count'] = $current_record_count;
    $replacements['@page_count'] = $page_count;
    // Add proximity and location info if user has specified a location.
    $replacements['@proximity_string'] = '';
    $addressParts = explode(',', $exposed['center']['geocoder']['geolocation_geocoder_address']); 
    if ($addressParts !== [0 => '']) {
      $replacements['@proximity_string'] = $proximity . $location;
    }

    // Send the output.
    if (!empty($total) || !empty($this->options['empty'])) {
      $output .= Xss::filterAdmin(str_replace(array_keys($replacements), array_values($replacements), $format));
      // Return as render array.
      return [
        '#markup' => $output,
      ];
    }

    return [];
  }

}