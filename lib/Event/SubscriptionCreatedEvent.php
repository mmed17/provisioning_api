<?php

namespace OCA\Provisioning_API\Event;

use OCA\Provisioning_API\Db\Subscription;
use OCP\EventDispatcher\Event;

class SubscriptionCreatedEvent extends Event {

    public function __construct(private readonly Subscription $subscription) {
        parent::__construct();
    }

    public function getSubscription(): Subscription {
        return $this->subscription;
    }
}