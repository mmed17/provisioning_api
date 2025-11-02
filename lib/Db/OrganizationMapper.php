<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;
use OCP\IGroupManager;
use Psr\Log\LoggerInterface;

class OrganizationMapper extends QBMapper {
    public function __construct(
        IDBConnection $db,
        private IGroupManager $groupManager,
        private LoggerInterface $logger
    ) {
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
        ->from($this->getTableName(), 'o') // Gets 'organizations' (without prefix)
        ->innerJoin(
            'o',                // Alias of the FROM table ('organizations')
            'users',            // Table to join with (Nextcloud adds prefix automatically)
            'u',                // Alias for the 'users' table
            $qb->expr()->eq('o.id', 'u.organization_id') // Join condition: organizations.id = users.organization_id
        )
        ->where(
            $qb->expr()->eq('u.uid', $qb->createNamedParameter($userId)) // Filter by users.uid
        );

        try {
            // findEntity executes the query and maps the first result row
            // to an Organization entity. If no row is found, it throws DoesNotExistException.
            return $this->findEntity($qb);
        } catch (DoesNotExistException $e) {
            return null;
        }
    }

    /**
     * Récupère tous les groupes d'un utilisateur qui sont liés à une organisation.
     *
     * @param string $userId L'UID de l'utilisateur.
     * @return IGroup[] Un tableau d'objets IGroup.
     */
    public function findOrganizationGroupsForUser(string $userId): array {
        $qb = $this->db->getQueryBuilder();

        // 1. Construire la requête pour trouver les *ID* des groupes correspondants
        $qb->select('g.gid')
           ->from('groups', 'g') // Table 1: oc_groups (alias 'g')
           ->innerJoin(
               'g',                      // Jointure depuis 'g'
               'group_user',             // Table 2: oc_group_user (alias 'gu')
               'gu',                     // Alias
               $qb->expr()->eq('g.gid', 'gu.gid') // Condition: g.gid = gu.gid
           )
           ->innerJoin(
               'g',                      // Jointure depuis 'g'
               'organizations',          // Table 3: oc_organizations (alias 'o')
               'o',                      // Alias
               // Condition: g.gid = o.nextcloud_group_id
               $qb->expr()->eq('g.gid', 'o.nextcloud_group_id')
           )
           ->where(
               // Où l'utilisateur correspond à l'ID fourni
               $qb->expr()->eq('gu.uid', $qb->createNamedParameter($userId))
           );

        // 2. Exécuter la requête et récupérer les ID de groupe
        $groupIds = [];
        try {
            $result = $qb->executeQuery();
            // Récupère tous les 'gid' dans un tableau plat (ex: ['org1', 'org2'])
            $groupIds = $result->fetchAll(\PDO::FETCH_COLUMN, 0);
            $result->closeCursor();
        } catch (\Exception $e) {
            $this->logger->error(
                'Impossible de récupérer les groupes d\'organisation pour l\'utilisateur : ' . $e->getMessage(), [
                'app' => 'provisioning_api', // Mettez votre appid
                'exception' => $e
            ]);
            return []; // Retourner un tableau vide en cas d'erreur
        }

        // 3. Convertir les ID de groupe en objets IGroup complets
        $groupObjects = [];
        foreach ($groupIds as $gid) {
            $group = $this->groupManager->get((string) $gid); // Utiliser IGroupManager
            if ($group !== null) {
                $groupObjects[] = $group;
            }
        }

        return $groupObjects;
    }

    /**
     * Calcule le nombre total d'utilisateurs pour UNE SEULE organisation spécifique,
     * en se basant sur la colonne oc_users.organization_id.
     *
     * @param int $organizationId L'ID de l'organisation pour laquelle compter les utilisateurs.
     * @return int Le nombre d'utilisateurs trouvés.
     */
    public function getUserCount(int $organizationId): int {
        $qb = $this->db->getQueryBuilder();

        $qb->selectAlias($qb->createFunction('COUNT(u.uid)'), 'user_count')
        ->from('users', 'u')
        ->where(
            $qb->expr()->eq('u.organization_id', $qb->createNamedParameter($organizationId, \PDO::PARAM_INT))
        );

        $result = $qb->executeQuery();
        $count = $result->fetchOne();
        $result->closeCursor();

        return (int)$count;
    }
}