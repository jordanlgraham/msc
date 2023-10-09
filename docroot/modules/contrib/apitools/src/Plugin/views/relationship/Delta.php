<?php

namespace Drupal\apitools\Plugin\views\relationship;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\relationship\Standard;

/**
 * A relationship handler with a specified delta
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("delta")
 */
class Delta extends Standard {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['delta'] = [
      'default' => 0
    ];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $form['delta'] = [
      '#type' => 'number',
      '#title' => $this->t('Delta'),
      '#description' => $this->t('Field delta value'),
      '#default_value' => $this->options['delta'],
    ];
  }

  public function ensureMyTable() {
    if (!isset($this->tableAlias)) {
      $join = $this->query->getJoinData($this->table, $this->query->relationships[$this->relationship]['base']);
      $join->extra[] = [
        'field' => 'delta',
        'value' => $this->options['delta'],
        'numeric' => TRUE
      ];
      $this->tableAlias = $this->query->ensureTable($this->table, $this->relationship, $join);
    }
    return $this->tableAlias;
  }
}
