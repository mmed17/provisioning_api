<?php

namespace OCA\Provisioning_API\Event;

use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\IEventListener;


class SubscriptionCreatedEventListener implements IEventListener {

    public function __construct() {}

    public function handle(Event $event): void {
        if (!($event instanceOf SubscriptionCreatedEvent)) {
            return;
        }

        $subscription = $event->getSubscription();
    }
}