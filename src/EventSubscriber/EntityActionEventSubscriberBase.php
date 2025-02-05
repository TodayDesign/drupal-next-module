<?php

namespace Today\next\EventSubscriber;

use Drupal\Core\Logger\LoggerChannelInterface;
use Today\next\NextEntityTypeManagerInterface;
use Today\next\NextSettingsManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Defines an entity action event subscriber.
 */
abstract class EntityActionEventSubscriberBase implements EventSubscriberInterface {

  /**
   * The next entity type manager.
   *
   * @var \Today\next\NextEntityTypeManagerInterface
   */
  protected NextEntityTypeManagerInterface $nextEntityTypeManager;

  /**
   * The next settings manager.
   *
   * @var \Today\next\NextSettingsManagerInterface
   */
  protected NextSettingsManagerInterface $nextSettingsManager;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected LoggerChannelInterface $logger;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * EntityActionEventSubscriber constructor.
   *
   * @param \Today\next\NextEntityTypeManagerInterface $next_entity_type_manager
   *   The next entity type manager.
   * @param \Today\next\NextSettingsManagerInterface $next_settings_manager
   *   The next settings manager.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The logger channel.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   */
  public function __construct(NextEntityTypeManagerInterface $next_entity_type_manager, NextSettingsManagerInterface $next_settings_manager, LoggerChannelInterface $logger, EventDispatcherInterface $event_dispatcher) {
    $this->nextEntityTypeManager = $next_entity_type_manager;
    $this->nextSettingsManager = $next_settings_manager;
    $this->logger = $logger;
    $this->eventDispatcher = $event_dispatcher;
  }

}
