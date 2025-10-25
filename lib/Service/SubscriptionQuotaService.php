<?php
namespace OCA\Provisioning_API\Service;

use OCA\Provisioning_API\Db\OrganizationMapper;
use OCA\Provisioning_API\Db\PlanMapper;
use OCP\IUserManager;
use OCP\IConfig;
use OCP\IDBConnection;

class SubscriptionQuotaService {

    public function __construct(
        private IUserManager $userManager, 
        private IConfig $config, 
        private IDBConnection $db,
        private OrganizationMapper $organizationMapper, 
        private PlanMapper $planMapper
    ) {}
}