<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

class PlanMapper extends QBMapper {
    public function __construct(IDBConnection $db) {
        parent::__construct($db, 'plans', Plan::class);
    }

    /**
     * Inserts a new plan entity.
     * @return Plan
     */
    public function create(
        string $name,
        int $maxMembers,
        int $maxProjects,
        int $sharedStoragePerProject,
        int $privateStoragePerUser,
        ?float $price,
        ?string $currency = 'EUR',
        ?bool $isPublic = false
    ): Plan {
        $plan = new Plan();

        $plan->setName($name);
        $plan->setMaxMembers($maxMembers);
        $plan->setMaxProjects($maxProjects);
        $plan->setSharedStoragePerProject($sharedStoragePerProject);
        $plan->setPrivateStoragePerUser($privateStoragePerUser);
        $plan->setPrice($price);
        $plan->setCurrency($currency);
        $plan->setIsPublic($isPublic);

        return $this->insert($plan);
    }

    /**
     * Finds all available plans.
     * @return Plan[]
     */
    public function findAll(): array {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')->from($this->getTableName())->where(
            $qb->expr()->eq('is_public',$qb->createNamedParameter(true, \PDO::PARAM_BOOL))
        );
        return $this->findEntities($qb);
    }

    /**
     * ADDED: Explicitly defines the find method to ensure it's always available.
     * This is the standard way to find a single record by its primary key.
     *
     * @param int $id The ID of the plan to find.
     * @return Plan|null
     */
    public function find(int $id): ?Plan {
        $qb = $this->db->getQueryBuilder();
        $qb->select('*')
           ->from($this->getTableName())
           ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));

        try {
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }
}