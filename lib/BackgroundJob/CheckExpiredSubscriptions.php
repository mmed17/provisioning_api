<?php

declare(strict_types=1);

namespace OCA\Provisioning_API\BackgroundJob;

use DateTime;
use DateTimeZone;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCP\AppFramework\IAppContainer;
use OCP\BackgroundJob\Job;
use OCP\IDBConnection;
use OCP\AppFramework\Utility\ITimeFactory;
use Psr\Log\LoggerInterface;

/**
 * UPDATED: This class now correctly calls the parent constructor to ensure
 * all necessary properties like '$time' are initialized.
 */
class CheckExpiredSubscriptions extends Job {

    /**
     * The constructor for a background job MUST accept the TimeFactory and AppContainer
     * and pass them to the parent constructor.
     */
    public function __construct(protected ITimeFactory $time, private SubscriptionMapper $subscriptionMapper) {
        parent::__construct($time);
    }

    /**
     * This is the main function that gets executed by the Nextcloud cron system.
     * The logic to find and expire subscriptions remains the same.
     */
    protected function run($argument): void {
        $this->subscriptionMapper->invalidateExpiredSubscriptions();
    }
}