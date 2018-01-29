<?php

namespace Drupal\msca_tweet_block\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Abraham\TwitterOAuth\TwitterOAuth;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\State\StateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'TweetBlock' block.
 *
 * @Block(
 *  id = "tweet_block",
 *  admin_label = @Translation("Tweet block"),
 * )
 */
class TweetBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  public function __construct(array $configuration, $plugin_id, $plugin_definition,
                              StateInterface $state, Config $config, DateFormatterInterface $dateFormatter) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->state = $state;
    $this->config = $config;
    $this->dateFormatter = $dateFormatter;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state'),
      $container->get('config.factory')->get('msca_tweet_block.settings'),
      $container->get('date.formatter')
    );
  }


  /**
   * Get the latest tweet from the account specified in settings
   *
   * @return mixed
   */
  private function getTweet() {
    return $this->state->get('msca_tweet_block_tweet');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $tweet = $this->getTweet();
    $build = [
      '#theme' => 'msca_tweet_block',
      '#cache' => [
        'max-age' => Cache::PERMANENT,
        'tags' => ['msca_tweet_block'],
        'keys' => [],
      ],
    ];
    if ($tweet) {
      $date = strtotime($tweet->created_at);

      $time_ago = $this->dateFormatter->formatDiff($date, REQUEST_TIME, [
        'granularity' => 1,
        'return_as_object' => TRUE,
      ]);


      // Set the variables used in twig template
      $build['#time_ago'] = $time_ago->getString();
      $build['#text'] = $tweet->text;
      $build['#retweets'] = $tweet->retweet_count;
      $build['#favs'] = $tweet->favorite_count;
      $build['#twitter_url'] = $this->config->get('twitter_url');
      $build['#facebook_url'] = $this->config->get('facebook_url');
    }
    else {
      $build['#text'] = $this->t('No tweets currently available');
    }

    return $build;
  }

}
