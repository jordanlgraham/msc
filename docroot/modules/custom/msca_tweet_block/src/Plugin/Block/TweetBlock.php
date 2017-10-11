<?php

namespace Drupal\msca_tweet_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Abraham\TwitterOAuth\TwitterOAuth;

/**
 * Provides a 'TweetBlock' block.
 *
 * @Block(
 *  id = "tweet_block",
 *  admin_label = @Translation("Tweet block"),
 * )
 */
class TweetBlock extends BlockBase  {


  /**
   * Get the latest tweet from the account specified in settings
   * @return mixed
   */
  private function getTweet() {

    // Yes, it would be better to do all of this with dependency injection for config.
    $config = \Drupal::config('msca_tweet_block.settings');

    $access_token = $config->get('access_token');
    $access_token_secret = $config->get('access_token_secret');
    $consumer_key = $config->get('consumer_key');
    $consumer_secret = $config->get('consumer_secret');


    // Connect to twitter API with our account info
    $connection = new TwitterOAuth($consumer_key, $consumer_secret, $access_token, $access_token_secret);

    $tweet_markup = [];
    // Set the theme here because I'm lazy and don't want to join the array in build()
    $tweet_markup['#theme'] = 'msca_tweet_block';

    $content = $connection->get("account/verify_credentials");

    // The API claims you can check the most recent HTTP status to see if connection
    // was successful, but it always returns '0' for me. So we're doing this
    if (isset($content->status)) {

      // Get the actual tweet
      $tweet = $content->status;

      // Get the time ago
      $date = strtotime($tweet->created_at);

      /** @var \Drupal\Core\Datetime\DateFormatterInterface $formatter */
      $date_formatter = \Drupal::service('date.formatter');

      $time_ago = $date_formatter->formatDiff($date, REQUEST_TIME, [
        'granularity' => 1,
        'return_as_object' => TRUE,
      ]);


      // Set the variables used in twig template
      $tweet_markup['#time_ago'] = $time_ago->getString();
      $tweet_markup['#text'] = $tweet->text;
      $tweet_markup['#retweets'] = $tweet->retweet_count;
      $tweet_markup['#favs'] = $tweet->favorite_count;
      $tweet_markup['#twitter_url'] = $config->get('twitter_url');
      $tweet_markup['#facebook_url'] = $config->get('facebook_url');
    } else {
      // Use this as error message
      $tweet_markup['#text'] = t('No tweets currently available')->render();
    }

    return $tweet_markup;
  }

  /**
   * {@inheritdoc}
   */
  public function build() {

    return [
      $this->getTweet()
    ];
  }

}
