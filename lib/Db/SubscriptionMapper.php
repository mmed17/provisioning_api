<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class SubscriptionMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'subscriptions', Subscription::class);
    }

    /**
     * Finds the active subscription for a given organization ID.
     * @param int $organizationId
     * @return Subscription|null
     */
    public function findByOrganizationId(int $organizationId): ?Subscription {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq(
                'organization_id', 
                $qb->createNamedParameter($organizationId))
            );

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
    
    public function findActiveSubscriptionForOrganization(int $organizationId): ?Subscription {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('organization_id', $qb->createNamedParameter($organizationId)))
           ->andWhere(
                $qb->expr()->gt('ended_at', $qb->createNamedParameter(date('Y-m-d H:i:s')))
           )
           ->andWhere($qb->expr()->eq('status', $qb->createNamedParameter('active')));

        try {
            // Use findEntity as we expect only one active subscription.
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}