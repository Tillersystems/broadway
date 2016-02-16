<?php

/*
 * This file is part of the broadway/broadway package.
 *
 * (c) Qandidate.com <opensource@qandidate.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Broadway\Domain;

use Broadway\TestCase;

class DomainMessageTest extends TestCase
{
    /**
     * @test
     */
    public function it_has_getters()
    {
        $id         = 'Hi thur';
        $privateId  = 'Hi thor';
        $shopId     = 'Hi thar';
        $payload    = new SomeEvent();
        $metadata   = new Metadata(array('meta'));
        $type       = 'Broadway.Domain.SomeEvent';
        $happenedOn = DateTime::now();

        $domainMessage = DomainMessage::recordNow($id, $privateId, $shopId, $metadata, $payload, $happenedOn);

        $this->assertEquals($id,         $domainMessage->getId());
        $this->assertEquals($privateId,  $domainMessage->getPrivateId());
        $this->assertEquals($shopId,     $domainMessage->getShopId());
        $this->assertEquals($payload,    $domainMessage->getPayload());
        $this->assertEquals($metadata,   $domainMessage->getMetadata());
        $this->assertEquals($type,       $domainMessage->getType());
        $this->assertEquals($happenedOn, $domainMessage->getHappenedOn());
    }

    /**
     * @test
     */
    public function it_returns_a_new_instance_with_more_metadata_on_andMetadata()
    {
        $domainMessage = DomainMessage::recordNow('id', 'sid', 42, new Metadata(), 'payload', DateTime::now());

        $this->assertNotSame($domainMessage, $domainMessage->andMetadata(Metadata::kv('foo', 42)));
    }

    /**
     * @test
     */
    public function it_keeps_all_data_the_same_expect_metadata_on_andMetadata()
    {
        $domainMessage = DomainMessage::recordNow('id', 'sid', 42, new Metadata(), 'payload', DateTime::now());

        $newMessage = $domainMessage->andMetadata(Metadata::kv('foo', 42));

        $this->assertSame($domainMessage->getId(), $newMessage->getId());
        $this->assertSame($domainMessage->getPrivateId(), $newMessage->getPrivateId());
        $this->assertSame($domainMessage->getShopId(), $newMessage->getShopId());
        $this->assertSame($domainMessage->getPayload(), $newMessage->getPayload());
        $this->assertSame($domainMessage->getRecordedOn(), $newMessage->getRecordedOn());
        $this->assertSame($domainMessage->getHappenedOn(), $newMessage->getHappenedOn());

        $this->assertNotSame($domainMessage->getMetadata(), $newMessage->getMetadata());
    }

    /**
     * @test
     */
    public function it_merges_the_metadata_instances_on_andMetadata()
    {
        $domainMessage = DomainMessage::recordNow('id', 'sid', 42, Metadata::kv('bar', 1337), 'payload', DateTime::now());

        $newMessage = $domainMessage->andMetadata(Metadata::kv('foo', 42));

        $expected = new Metadata(array('bar' => 1337, 'foo' => 42));
        $this->assertEquals($expected, $newMessage->getMetadata());
    }
}

class SomeEvent
{
}
