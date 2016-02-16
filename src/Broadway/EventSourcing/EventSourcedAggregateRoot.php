<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\EventSourcing;

use Broadway\Domain\AggregateRoot as AggregateRootInterface;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainEventStreamInterface;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventSourcing\EventInterface;

/**
 * Convenience base class for event sourced aggregate roots.
 */
abstract class EventSourcedAggregateRoot implements AggregateRootInterface
{
    /**
     * @var array
     */
    private $uncommittedEvents = array();

    /**
     * Applies an event. The event is added to the AggregateRoot's list of uncommitted events.
     *
     * @param $event
     */
    public function apply(EventInterface $event)
    {
        $this->handleRecursively($event);

        $this->uncommittedEvents[] = DomainMessage::recordNow(
                $this->getAggregateRootId(), 
                $event->getEventId(), 
                $event->getShopId(), 
                new Metadata(array()),
                $event, 
                $event->getHappenedOn()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getUncommittedEvents()
    {
        $stream = new DomainEventStream($this->uncommittedEvents);

        $this->uncommittedEvents = array();

        return $stream;
    }

    /**
     * Initializes the aggregate using the given "history" of events.
     */
    public function initializeState(DomainEventStreamInterface $stream)
    {
        foreach ($stream as $message) {
            $this->handleRecursively($message->getPayload());
        }
    }

    /**
     * Handles event if capable.
     *
     * @param $event
     */
    protected function handle($event)
    {
        $method = $this->getApplyMethod($event);

        if (!method_exists($this, $method)) {
            return;
        }

        $this->$method($event);
    }

    /**
     * {@inheritDoc}
     */
    protected function handleRecursively($event)
    {
        $this->handle($event);

        foreach ($this->getChildEntities() as $entity) {
            $entity->registerAggregateRoot($this);
            $entity->handleRecursively($event);
        }
    }

    /**
     * Returns all child entities
     *
     * Override this method if your aggregate root contains child entities.
     *
     * @return array
     */
    protected function getChildEntities()
    {
        return array();
    }

    private function getApplyMethod($event)
    {
        $classParts = explode('\\', get_class($event));

        return 'apply' . end($classParts);
    }
}