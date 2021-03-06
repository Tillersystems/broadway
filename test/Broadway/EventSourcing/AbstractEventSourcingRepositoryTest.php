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

use Broadway\Domain\AggregateRoot;
use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainEventStreamInterface;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\EventHandling\SimpleEventBus;
use Broadway\EventHandling\TraceableEventBus;
use Broadway\EventSourcing\AggregateFactory\PublicConstructorAggregateFactory;
use Broadway\EventSourcing\MetadataEnrichment\MetadataEnricherInterface;
use Broadway\EventSourcing\MetadataEnrichment\MetadataEnrichingEventStreamDecorator;
use Broadway\EventStore\EventStoreInterface;
use Broadway\EventStore\InMemoryEventStore;
use Broadway\EventStore\TraceableEventStore;
use Broadway\ReadModel\Projector;
use Broadway\TestCase;
use RuntimeException;

abstract class AbstractEventSourcingRepositoryTest extends TestCase
{
    /** @var TraceableEventBus */
    protected $eventBus;

    /** @var TraceableEventStoreDecorator */
    protected $eventStreamDecorator;

    /** @var EventStoreInterface */
    protected $eventStore;

    /** @var EventSourcingRepository */
    protected $repository;

    public function setUp()
    {
        $this->eventStore = new TraceableEventStore(new InMemoryEventStore());
        $this->eventStore->trace();

        $this->eventBus = new TraceableEventBus(new SimpleEventBus());
        $this->eventBus->trace();

        $this->eventStreamDecorator = new TraceableEventStoreDecorator();
        $this->eventStreamDecorator->trace();

        $this->repository = $this->createEventSourcingRepository($this->eventStore, $this->eventBus, [$this->eventStreamDecorator]);
    }

    /**
     * @test
     * @expectedException \Assert\InvalidArgumentException
     * @dataProvider objectsNotOfConfiguredClass
     */
    public function it_throws_an_exception_when_adding_an_aggregate_that_is_not_of_the_configured_class($aggregate)
    {
        $this->repository->save($aggregate);
    }

    public function objectsNotOfConfiguredClass()
    {
        return [
            [new TestAggregate()],
            [new AnotherTestEventSourcedAggregate()],
        ];
    }

    /**
     * @test
     */
    public function it_adds_an_aggregate_root()
    {
        $aggregate = $this->createAggregate();
        $aggregate->apply(new DidNumberEvent(42));
        $aggregate->apply(new DidNumberEvent(1337));

        $this->repository->save($aggregate);

        $expected = [new DidNumberEvent(42), new DidNumberEvent(1337)];
        $this->assertEquals($expected, $this->eventStore->getEvents());
        $this->assertEquals($expected, $this->eventBus->getEvents());
    }

    /**
     * @test
     */
    public function it_loads_an_aggregate()
    {
        $this->eventStore->append(42, new DomainEventStream(array(
            DomainMessage::recordNow(42, 0, 33, 0, new Metadata([]), new DidNumberEvent(1337), DateTime::now())
        )));

        $aggregate = $this->repository->load(42);

        $expectedAggregate = $this->createAggregate();
        $expectedAggregate->apply(new DidNumberEvent(1337));
        $expectedAggregate->getUncommittedEvents();

        $this->assertEquals($expectedAggregate, $aggregate);
    }

    /**
     * @test
     * @expectedException \Broadway\Repository\AggregateNotFoundException
     */
    public function it_throws_an_exception_if_aggregate_was_not_found()
    {
        $this->repository->load('does-not-exist');
    }

    /**
     * @test
     */
    public function it_calls_the_event_stream_decorators()
    {
        $aggregate = $this->createAggregate();
        $aggregate->apply(new DidNumberEvent(42));

        $this->repository->save($aggregate);

        $this->assertTrue($this->eventStreamDecorator->isCalled());
    }

    /**
     * @test
     */
    public function it_calls_the_event_stream_decorators_with_the_correct_arguments()
    {
        $event = new DidNumberEvent(42);

        $aggregate = $this->createAggregate();
        $aggregate->apply($event);

        $this->repository->save($aggregate);

        $lastCall = $this->eventStreamDecorator->getLastCall();

        $this->assertEquals($aggregate->getAggregateRootId(), $lastCall['aggregateIdentifier']);
        $this->assertEquals('\\' . get_class($aggregate), $lastCall['aggregateType']);

        $events = iterator_to_array($lastCall['eventStream']);
        $this->assertCount(1, $events);

        $this->assertSame($event, $events[0]->getPayload());
    }

    /**
     * @test
     */
    public function it_publishes_decorated_events()
    {
        $projector = new TestMetadataPublishedProjector();
        $this->eventBus->subscribe($projector);

        $repository = new EventSourcingRepository(
            $this->eventStore,
            $this->eventBus,
            get_class($this->createAggregate()),
            new PublicConstructorAggregateFactory(),
            [new MetadataEnrichingEventStreamDecorator([new TestDecorationMetadataEnricher()])]
        );

        $aggregate = $this->createAggregate();
        $aggregate->apply(new DidNumberEvent(42));
        $repository->save($aggregate);

        $metadata = $projector->getMetadata();
        $data     = $metadata->serialize();

        $this->assertArrayHasKey('decoration_test', $data);
        $this->assertEquals('I am a decorated test', $data['decoration_test']);
    }

    /**
     * @return EventSourcingRepository
     */
    abstract protected function createEventSourcingRepository(TraceableEventStore $eventStore, TraceableEventBus $eventBus, array $eventStreamDecorators);

    /**
     * @return EventSourcedAggregateRoot
     */
    abstract protected function createAggregate();
}

class DidNumberEvent implements EventInterface
{
    public $number;

    public function __construct($number)
    {
        $this->number = $number;
    }

    public function getEventId()
    {
        return "eventid-" . $this->number;
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

class AnotherTestEventSourcedAggregate extends EventSourcedAggregateRoot
{
    public function getAggregateRootId()
    {
        return 1337;
    }
}

class TestAggregate implements AggregateRoot
{
    public function getAggregateRootId()
    {
        return 42;
    }

    public function getUncommittedEvents()
    {
    }
}

class TraceableEventstoreDecorator implements EventStreamDecoratorInterface
{
    private $tracing = false;
    private $calls;

    public function decorateForWrite($aggregateType, $aggregateIdentifier, DomainEventStreamInterface $eventStream)
    {
        if ($this->tracing) {
            $this->calls[] = ['aggregateType' => $aggregateType, 'aggregateIdentifier' => $aggregateIdentifier, 'eventStream' => $eventStream];
        }

        return $eventStream;
    }

    public function trace()
    {
        $this->tracing = true;
    }

    public function isCalled()
    {
        return count($this->calls) > 0;
    }

    public function getLastCall()
    {
        if (! $this->isCalled()) {
            throw new RuntimeException('was never called');
        }

        return $this->calls[count($this->calls) - 1];
    }
}

class TestDecorationMetadataEnricher implements MetadataEnricherInterface
{
    public function enrich(Metadata $metadata)
    {
        return new Metadata(['decoration_test' => 'I am a decorated test']);
    }
}

class TestMetadataPublishedProjector extends Projector
{
    private $metadata;

    public function applyDidNumberEvent(DidNumberEvent $event, DomainMessage $domainMessage)
    {
        $this->metadata = $domainMessage->getMetadata();
    }

    /**
     * @return Metadata
     */
    public function getMetadata()
    {
        return $this->metadata;
    }
}
