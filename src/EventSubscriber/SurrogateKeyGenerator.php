<?php

/**
 * @file
 * Contains \Drupal\Fastly\EventSubscriber\CacheTagsHeaderLimitDetector.
 */

namespace Drupal\fastly\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
/**
 * SurrogateKeyGenerator class.
 */
class SurrogateKeyGenerator implements EventSubscriberInterface {

  /**
   * The Fastly logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs a new CacheTagsHeaderLimitDetector object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The Fastly logger channel.
   */
  public function __construct(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * Logs an emergency event when the X-Drupal-Cache-Tags header exceeds 16 KB.
   *
   * @param \Symfony\Component\HttpKernel\Event\FilterResponseEvent $event
   *   The event to process.
   */
  public function onRespond(FilterResponseEvent $event) {
    if (!$event->isMasterRequest()) {
      return;
    }

    $response = $event->getResponse();

    $surrogate_key_header_value = $response->headers->get('X-Drupal-Cache-Tags');
    if (strlen($surrogate_key_header_value) > 16384) {
      $this->logger->notice('X-Drupal-Cache-Tags header size exceeded the 16 KB limit that Fastly supports; replaced the cache tags with hashed equivalents.');
      $cache_tags = explode(' ', $surrogate_key_header_value);
      $hashes = static::cacheTagsToHashes($cache_tags);
      $surrogate_key_header_value = implode(' ', $hashes);
    }

    $response->headers->set('Surrogate-Key', $surrogate_key_header_value);
  }

  /**
   * Maps cache tags to hashes.
   *
   * Used when the Surrogate-Key/X-Drupal-Cache-Tags header size otherwise
   * exceeds 16 KB.
   *
   * @param string[] $cache_tags
   *   The cache tags in the header.
   *
   * @return string[]
   *   The hashes to use instead in the header.
   */
  public static function cacheTagsToHashes(array $cache_tags) {
    $hashes = [];
    foreach ($cache_tags as $cache_tag) {
      $hashes[] = substr(md5($cache_tag), 0, 3);
    }
    return $hashes;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::RESPONSE][] = ['onRespond'];
    return $events;
  }

}
