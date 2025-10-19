<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use DateTime;

class SubscriptionHistoryMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'subscriptions_history', SubscriptionHistory::class);
    }

    /**
     * Creates a detailed history log for a subscription change.
     * This function captures the state before and after the modification.
     *
     * @param Subscription $newSubscription The subscription object AFTER the changes have been applied.
     * @param ?Subscription $previousSubscription The subscription object BEFORE the changes. Can be null for new subscriptions.
     * @param string $changedByUserId The UID of the user performing the action.
     * @param ?string $notes Optional notes for additional context about the change.
     * @return SubscriptionHistory The newly created and inserted history entity.
     */
    public function createLog(
        Subscription $newSubscription,
        ?Subscription $previousSubscription,
        string $changedByUserId,
        ?string $notes = null
    ): SubscriptionHistory {
        $history = new SubscriptionHistory();

        // Set the core details for the history record
        $history->setSubscriptionId($newSubscription->getId());
        $history->setChangedByUserId($changedByUserId);
        $history->setChangeTimestamp((new DateTime())->format('Y-m-d H:i:s'));
        $history->setNotes($notes);

        // Record the state of the subscription AFTER the change
        $history->setNewPlanId($newSubscription->getPlanId());
        $history->setNewStatus($newSubscription->getStatus());
        $history->setNewStartedAt($newSubscription->getStartedAt());
        $history->setNewEndedAt($newSubscription->getEndedAt());
        $history->setNewPausedAt($newSubscription->getPausedAt());
        $history->setNewCancelledAt($newSubscription->getCancelledAt());

        // If a previous subscription state was provided, record it
        if ($previousSubscription !== null) {
            $history->setPreviousPlanId($previousSubscription->getPlanId());
            $history->setPreviousStatus($previousSubscription->getStatus());
            $history->setPreviousStartedAt($previousSubscription->getStartedAt());
            $history->setPreviousEndedAt($previousSubscription->getEndedAt());
            $history->setPreviousPausedAt($previousSubscription->getPausedAt());
            $history->setPreviousCancelledAt($previousSubscription->getCancelledAt());
        }
        
        // Insert the new log into the database and return the entity
        return $this->insert($history);
    }
}
