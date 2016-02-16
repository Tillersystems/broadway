<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\EventStore;

use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainEventStreamInterface;
use Broadway\EventStore\Management\Criteria;
use Broadway\EventStore\Management\EventStoreManagementInterface;

/**
 * In-memory implementation of an event store.
 *
 * Useful for testing code that uses an event store.
 */
class InMemoryEventStore implements EventStoreInterface, EventStoreManagementInterface
{
    private $events = array();

    /**
     * {@inheritDoc}
     */
    public function load($id)
    {
        $id = (string) $id;

        if (isset($this->events[$id])) {
            return new DomainEventStream($this->events[$id]);
        }

        throw new EventStreamNotFoundException(sprintf('EventStream not found for aggregate with id %s', $id));
    }

    /**
     * {@inheritDoc}
     */
    public function append($id, DomainEventStreamInterface $eventStream)
    {
        $id = (string) $id;

        if (! isset($this->events[$id])) {
            $this->events[$id] = array();
        }

        foreach ($eventStream as $event) {
            $privateId = $event->getPrivateId();
            $this->assertPrivateId($this->events[$id], $privateId);

            $this->events[$id][$privateId] = $event;
        }
    }

    private function assertPrivateId($events, $privateId)
    {
        if (isset($events[$privateId])) {
            throw new InMemoryEventStoreException(
                sprintf("An event with privateId '%d' is already committed.", $privateId)
            );
        }
    }

    public function visitEvents(Criteria $criteria, EventVisitorInterface $eventVisitor)
    {
        foreach ($this->events as $id => $events) {
            foreach ($events as $event) {
                if (! $criteria->isMatchedBy($event)) {
                    continue;
                }

                $eventVisitor->doWithEvent($event);
            }
        }
    }
}
