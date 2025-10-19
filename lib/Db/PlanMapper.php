<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;

class PlanMapper extends QBMapper {
    public function __construct(IDBConnection $db, private LoggerInterface $logger) {
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
     * This custom insert method overrides the default QBMapper's insert.
     * It manually builds the query to ensure the 'is_public' boolean is handled correctly.
     *
     * @param Plan $plan The entity to insert.
     * @return Plan The entity with the new ID set.
     */
    public function insert(Entity $plan): Plan {
        // Ensure we are only handling Plan objects.
        if (!$plan instanceof Plan) {
            throw new \InvalidArgumentException('Entity must be an instance of Plan');
        }

        $qb = $this->db->getQueryBuilder();
        
        $qb->insert($this->getTableName())
           ->values([
               'name' => $qb->createNamedParameter($plan->getName()),
               'max_members' => $qb->createNamedParameter($plan->getMaxMembers(), \PDO::PARAM_INT),
               'max_projects' => $qb->createNamedParameter($plan->getMaxProjects(), \PDO::PARAM_INT),
               'shared_storage_per_project' => $qb->createNamedParameter($plan->getSharedStoragePerProject(), \PDO::PARAM_INT),
               'private_storage_per_user' => $qb->createNamedParameter($plan->getPrivateStoragePerUser(), \PDO::PARAM_INT),
               'price' => $qb->createNamedParameter($plan->getPrice()),
               'currency' => $qb->createNamedParameter($plan->getCurrency()),
               'is_public' => $qb->createNamedParameter($plan->getIsPublic(), \PDO::PARAM_BOOL)
           ]);
        
        $qb->executeStatement();

        // Set the ID on the original entity object and return it.
        $plan->setId($qb->getLastInsertId());
        
        return $plan;
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