<?php

namespace Broadway\EventStore;

use Aws\DynamoDb\DynamoDbClient;
use Broadway\Domain\DateTime;
use Broadway\Domain\DomainEventStream;
use Broadway\Domain\DomainEventStreamInterface;
use Broadway\Domain\DomainMessage;
use Broadway\Domain\Metadata;
use Broadway\Serializer\SerializerInterface;

/**
 * Event store using an aws dynamodb as storage.
 */
class DynamoEventStore implements EventStoreInterface
{
    private $dynamoDbClient;
    private $payloadSerializer;
    private $metadataSerializer;
    private $tableName;

    public function __construct(
        DynamoDbClient $dynamoDbClient,
        SerializerInterface $payloadSerializer,
        SerializerInterface $metadataSerializer,
        $tableName
    ) {
        $this->dynamoDbClient = $dynamoDbClient;
        $this->payloadSerializer = $payloadSerializer;
        $this->metadataSerializer = $metadataSerializer;
        $this->tableName = $tableName;
    }

    /**
     * {@inheritdoc}
     */
    public function load($id)
    {
        $iterator = $this->dynamoDbClient->getIterator('Query', [
            'TableName' => $this->tableName,
            'IndexName' => 'RootUUIDIndex',
            'KeyConditions' => [
                'rootUUID' => [
                    'AttributeValueList' => [
                        ['S' => $id],
                    ],
                    'ComparisonOperator' => 'EQ',
                ],
            ],
        ]);

        foreach ($iterator as $row) {
            $events[] = $this->deserializeEvent($row);
        }

        if (empty($events)) {
            throw new EventStreamNotFoundException(sprintf('EventStream not found for aggregate with id %s for table %s', $id, $this->tableName));
        }

        return new DomainEventStream($events);
    }

    /**
     * {@inheritdoc}
     */
    public function append($id, DomainEventStreamInterface $eventStream)
    {
        if (0 !== iterator_count($eventStream)) {
            foreach ($eventStream as $domainMessage) {
                $putRequest = $this->prepareEvent($domainMessage);
                $this->flushToDynamo($putRequest);
            }
        }
    }

    /**
     * @param DomainMessage $domainMessage
     *
     * @return array
     */
    private function prepareEvent(DomainMessage $domainMessage): array
    {
        /** @var EventInterface $event */
        $event = $domainMessage->getPayload();

        return [
            'rootUUID' => ['S' => $event->getRootUUID()],
            'uuid' => ['S' => $event->getEventId()],
            'shopID' => ['N' => (string) $event->getShopId()],
            'deviceID' => empty($event->getDeviceId()) ? ['N' => '-1'] : ['N' => (string) $event->getDeviceId()],
            'deviceUUID' => empty($event->getDeviceUUID()) ? ['NULL' => true] : ['S' => $event->getDeviceUUID()],
            'happenedOn' => ['S' => null !== $event->getHappenedOn() ? $event->getHappenedOn()->toString() : DateTime::now()->toString()],
            'playhead' => ['N' => (string) $domainMessage->getPlayhead()],
            'recordedOn' => ['S' => $domainMessage->getRecordedOn()->toString()],
            'type' => ['S' => (new \ReflectionClass($event))->getShortName()],
            'payload' => ['S' => json_encode($event->serialize())],
        ];
    }

    /**
     * @param array putRequests
     */
    private function flushToDynamo(array $putRequest)
    {
        $this->dynamoDbClient->putItem([
            'TableName' => $this->tableName,
            'Item' => $putRequest,
        ]);
    }

    /**
     * @param array $row
     */
    private function deserializeEvent($row): DomainMessage
    {
        $className = sprintf('AppBundle\Event\%s', ucwords($row['type']['S']));

        $data = array_merge(json_decode($row['payload']['S'], true), [
            'rootUUID' => $row['rootUUID']['S'],
            'eventId' => $row['uuid']['S'],
            'shopId' => $row['shopID']['N'],
            'date' => $row['happenedOn']['S'],
            'deviceUUID' => $row['deviceUUID']['S'],
            'deviceID' => $row['deviceID']['N'],
        ]);

        $data['extraData'] = $data['extraData'] ?? [];
        $data['isTraining'] = $event['isTraining'] ?? true === json_decode($row['source']['S'], true)['is_training'];

        return new DomainMessage(
            $row['rootUUID']['S'],
            $row['playhead']['N'],
            $row['uuid']['S'],
            $row['shopID']['N'],
            new MetaData([]),
            ($className)::deserialize($data),
            DateTime::fromString($row['happenedOn']['S']),
            DateTime::fromString($row['recordedOn']['S'])
        );
    }
}
