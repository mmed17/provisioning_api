<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class UserMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'users', Subscription::class);
    }

    function addOrganizationToUser(string $userid, int|null $organizationid) {
        $qb = $this->db->getQueryBuilder();
        $qb->update('users')
            ->set('organization_id', $qb->createNamedParameter($organizationid))
            ->where($qb->expr()->eq('uid', $qb->createNamedParameter($userid)));
        $qb->executeStatement();
    }
}