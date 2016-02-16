<?php

namespace Broadway\EventSourcing;

/**
 *
 * @author Vincent Oliveira <vincent@tillersystems.com>
 */
interface EventInterface
{

    /**
     * Get Order Identifier
     * 
     * @return string
     */
    public function getEventId();

    /**
     * Get Happened On
     * 
     * @return DateTime
     */
    public function getHappenedOn();

    /**
     * Get shop id
     * 
     * @return string
     */
    public function getShopId();
}