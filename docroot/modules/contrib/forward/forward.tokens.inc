<?php

/**
 * @file
 * forward.tokens.inc
 * Provide tokens related to the forward module.
 */

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Xss;

/**
 * Implements hook_token_info().
 */
function forward_token_info() {
  $data['types'] = array(
    'forward' => array(
      'name' => t('Forward'),
      'description' => t('Tokens related to forward module.'),
    ),
  );
  $data['tokens'] = array(
    'forward' => array(
      'recipients' => array(
        'name' => t("Email recipient(s)"),
        'description' => t("Recipient email address or email addresses for forwarded content."),
      ),
      'sender-name' => array(
        'name' => t("Sender's name"),
        'description' => t("Name of sender as entered in Forwarding form."),
      ),
      'sender-email' => array(
        'name' => t("Sender's email address"),
        'description' => t("Email address of sender as entered in Forwarding form."),
      ),
      'entity-type' => array(
        'name' => t("Entity type"),
        'description' => t('The type of entity being forwarded. For nodes, uses the bundle name, e.g. "article" or "page", instead of the type.'),
      ),
      'entity-title' => array(
        'name' => t("Entity title"),
        'description' => t('The title of the entity being forwarded.'),
      ),
    ),
  );
  return $data;
}

/**
 * Implements hook_tokens().
 */
function forward_tokens($type, $tokens, array $data, array $options, BubbleableMetadata $bubbleable_metadata) {
  $replacements = array();
  if (($type == 'forward') && !empty($data['forward'])) {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'entity-type':
          if (isset($data['forward']['entity'])) {
            $entity = $data['forward']['entity'];
            $entity_type = (string)$entity->getEntityType()->getLabel();
            if ($entity->getEntityType()->hasKey('bundle')) {
              // For entity types with bundles, use the bundle label instead of the entity type label.
              $bundles = \Drupal::service('entity_type.bundle.info')->getBundleInfo($entity->getEntityTypeId());
              $entity_type = $bundles[$entity->bundle()]['label'];
            }
            // Use the translated label, then lowercase it.
            $entity_type = strtolower(t($entity_type, array(), $options));
          }
          $replacements[$original] = isset($data['forward']['entity']) ? Markup::create(Xss::filter($entity_type)) : t('page');
          break;
        case 'entity-title':
          if (isset($data['forward']['entity'])) {
            $entity = $data['forward']['entity'];
            $entity_title = $entity->access('view') ? $entity->label() : t('a page');
          }
          $replacements[$original] = isset($data['forward']['entity']) ? Markup::create(Xss::filter($entity_title)) : t('a page');
          break;
        case 'recipients':
          $replacements[$original] = isset($data['forward']['recipients']) ? Markup::create(Xss::filter(implode($data['forward']['recipients'], ', '))) : t('(Recipient list)');
          break;
        case 'sender-name':
          $replacements[$original] = isset($data['forward']['sender_name']) ? Markup::create(Xss::filter($data['forward']['sender_name'])) : t('(Your name)');
          break;
        case 'sender-email':
          $replacements[$original] = isset($data['forward']['sender_email']) ? Markup::create(Xss::filter($data['forward']['sender_email'])) : t('(Your email address)');
          break;
      }
    }
  }
  return $replacements;
}
