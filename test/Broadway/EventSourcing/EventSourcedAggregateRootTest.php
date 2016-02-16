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

use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\TestCase;
use Broadway\EventSourcing\EventInterface;

class EventSourcedAggregateRootTest extends TestCase
{

    /**
     * @test
     */
    public function it_calls_apply_for_specific_events()
    {
        $aggregateRoot = new MyTestAggregateRoot();
        $aggregateRoot->initializeState($this->toDomainEventStream(array(new AggregateEvent())));

        $this->assertTrue($aggregateRoot->isCalled);
    }

    private function toDomainEventStream(array $events)
    {
        $messages = array();
        $privateId = -1;
        foreach ($events as $event) {
            $privateId++;
            $messages[] = DomainMessage::recordNow(1, $privateId, 42, new Metadata(array()), $event, DateTime::now());
        }

        return new DomainEventStream($messages);
    }
}

class MyTestAggregateRoot extends EventSourcedAggregateRoot
{
    public $isCalled = false;

    public function getAggregateRootId()
    {
        return 'y0l0';
    }

    public function applyAggregateEvent($event)
    {
        $this->isCalled = true;
    }
}

class AggregateEvent implements EventInterface
{

    public function getEventId()
    {
        return "eventid";
    }

    public function getShopId()
    {
        return "shopid";
    }

    public function getHappenedOn()
    {
        return DateTime::now();
    }
}