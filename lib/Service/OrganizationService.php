<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Service;

use OCP\IDBConnection;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\AppFramework\Http\DataResponse;
use OCA\Provisioning_API\Db\Organization;
use OCA\Provisioning_API\Db\OrganizationMapper;

class OrganizationService {
    private OrganizationMapper $organizationMapper;
    private ITimeFactory $timeFactory;
    private IDBConnection $db;

    public function __construct(
        OrganizationMapper $organizationMapper,
        ITimeFactory $timeFactory,
        IDBConnection $db
    ) {
        $this->organizationMapper = $organizationMapper;
        $this->timeFactory = $timeFactory;
        $this->db = $db;
    }

    /**
     * Creates a new organization with the given Nextcloud group ID.
     * @param string $nextcloudGroupId
     * @return DataResponse
     */
    public function createOrganization(string $nextcloudGroupId, string $name): Organization {
        $organization = new Organization();
        $organization->setNextcloudGroupId($nextcloudGroupId);
        $organization->setName($name);

        $this->organizationMapper->insert($organization);
        return $organization;
    }
}