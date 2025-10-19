<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class OrganizationMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'organizations', Organization::class);
    }

    /**
     * Finds an organization by its corresponding Nextcloud group ID.
     * UPDATED: Renamed from findByNextcloudGroupId for consistency.
     *
     * @param string $groupId The Nextcloud group ID.
     * @return Organization|null
     */
    public function findByGroupId(string $groupId): ?Organization {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq(
               'nextcloud_group_id', 
               $qb->createNamedParameter($groupId)
           ));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Finds an organization by one of its user's ID.
     * This works by joining through the standard Nextcloud group membership table.
     *
     * @param string $userId The user's ID.
     * @return Organization|null
     */
    public function findByUserId(string $userId): ?Organization {
        $qb = $this->db->getQueryBuilder();
        $qb->select('o.*')
           ->from($this->getTableName(), 'o')
           ->innerJoin('o', 'group_user', 'gu', $qb->expr()->eq('o.nextcloud_group_id', 'gu.gid'))
           ->where($qb->expr()->eq('gu.uid', $qb->createNamedParameter($userId)));
           
        try {
            // A user might be in multiple groups, but should only be in one organization.
            // This will return the first one it finds.
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}