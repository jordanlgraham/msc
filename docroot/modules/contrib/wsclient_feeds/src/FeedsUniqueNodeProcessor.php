<?php
namespace Drupal\wsclient_feeds;

/**
 * Creates nodes from feed items.
 */
class FeedsUniqueNodeProcessor extends FeedsNodeProcessor {

  /**
   * Retrieve the target entity's existing id if available. Otherwise return 0.
   *
   * Cloned from FeedsProcessor::existingEntityId
   * fractionally changed the query.
   *
   * @ingroup mappingapi
   *
   * @param FeedsSource $source
   *   The source information about this import.
   * @param FeedsParserResult $result
   *   A FeedsParserResult object.
   *
   * @return string
   *   The serial id of an entity if found, 0 otherwise.
   */
  protected function existingEntityId(FeedsSource $source, FeedsParserResult $result) {
    $query = db_select('feeds_item')
      ->fields('feeds_item', array('entity_id'))
      ->condition('feed_nid', $source->feed_nid)
      ->condition('entity_type', $this->entityType());

    // Iterate through all unique targets and test whether they do already
    // exist in the database.
    foreach ($this->uniqueTargets($source, $result) as $target => $value) {
      switch ($target) {
        case 'url':
          $entity_id = $query->condition('url', $value)->execute()->fetchField();
          break;

        case 'guid':
          $entity_id = $query->condition('guid', $value)->execute()->fetchField();
          break;

      }
      if (isset($entity_id)) {
        // Return with the content id found.
        return $entity_id;
      }
    }
    return 0;
  }

}
