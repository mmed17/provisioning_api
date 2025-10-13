<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SubscriptionHistoryMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'subscriptions_history', SubscriptionHistory::class);
    }

    /**
     * Creates a history record (snapshot) from a Subscription entity.
     * This should be called BEFORE the subscription is saved to the database.
     *
     * @param Subscription $originalSubscription The subscription object before modification.
     * @param string $changedBy The UID of the user making the change.
     * @param string $description A human-readable description of the change.
     * @return SubscriptionHistory
     */
    public function createSnapshot(
        Subscription $originalSubscription,
        string $changedBy,
        string $description
    ): SubscriptionHistory {
        $history = new SubscriptionHistory();

        $history->setSubscriptionId($originalSubscription->getId());
        $history->setChangedBy($changedBy);
        $history->setChangeDescription($description);
        $history->setOrganizationId($originalSubscription->getOrganizationId());
        $history->setPlanId($originalSubscription->getPlanId());
        $history->setOverrideMaxProjects($originalSubscription->getOverrideMaxProjects());
        $history->setOverrideMaxMembers($originalSubscription->getOverrideMaxMembers());
        $history->setOverrideQuotaPerProject($originalSubscription->getOverrideQuotaPerProject());
        $history->setStatus($originalSubscription->getStatus());
        $history->setOriginalStartedAt($originalSubscription->getStartedAt());
        $history->setOriginalExpiresAt($originalSubscription->getExpiresAt());

        return $this->insert($history);
    }
}