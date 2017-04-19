<?php
namespace Drupal\wsclient_feeds;

/**
 * A slightly extended result of FeedsHTTPFetcher::fetch().
 *
 * Most Feeds processors expect the ->raw data returned by
 * fetchers to be strings.
 * When using wsclient, it's already parsed object structures once
 * we've fetched it using wsclient invoke().
 *
 * For conformity, the $result->raw will be the raw string, but we'll
 * internally keep a handle on the $result->data also.
 *
 * This will allow you to swap out the Parser with XPathParser or others if
 * you so desired.
 */
class FeedsWSClientFetcherResult extends FeedsFetcherResult {
  protected $data;

  /**
   * Set the data array.
   */
  public function setData($data) {
    $this->data = $data;
  }
  /**
   * Return the data array.
   */
  public function getData() {
    return $this->data;
  }
}
