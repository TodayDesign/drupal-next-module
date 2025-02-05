<?php

namespace Today\next\EventSubscriber;

use Today\next\Event\EntityActionEvent;
use Today\next\Event\EntityEvents;
use Today\next\Event\EntityRevalidatedEvent;

/**
 * Defines an event subscriber for revalidating entity.
 *
 * @see \Today\next\Event\EntityActionEvent
 * @see \Today\next\EntityEventDispatcher
 */
class EntityActionEventRevalidateSubscriber extends EntityActionEventSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[EntityEvents::ENTITY_ACTION] = ['onAction'];
    return $events;
  }

  /**
   * Revalidates the entity.
   *
   * @param \Today\next\Event\EntityActionEvent $event
   *   The event.
   */
  public function onAction(EntityActionEvent $event) {
    if ($revalidator = $this->nextEntityTypeManager->getRevalidator($event->getEntity())) {
      $revalidated = $revalidator->revalidate($event);

      // Dispatch post revalidation event.
      $revalidated_event = EntityRevalidatedEvent::createFromEntityActionEvent($event);
      $revalidated_event->setRevalidated($revalidated);
      $this->eventDispatcher->dispatch($revalidated_event, EntityEvents::ENTITY_REVALIDATED);
    }

    return NULL;
  }

}
