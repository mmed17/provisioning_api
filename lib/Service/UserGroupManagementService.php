<?php
declare(strict_types=1);

namespace OCA\Provisioning_API\Service;

use Exception;
use OCA\Provisioning_API\Db\OrganizationMapper;
use OCA\Provisioning_API\Db\PlanMapper;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCA\Provisioning_API\Db\UserMapper;
use OCP\AppFramework\OCS\OCSException;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class UserGroupManagementService {

    public function __construct(
        private LoggerInterface $logger,
        private IUserManager $userManager,
        private IGroupManager $groupManager,
        private OrganizationMapper $organizationMapper,
        private UserMapper $userMapper,
        private SubscriptionMapper $subscriptionMapper,
        private PlanMapper $planMapper,
    ) {}

    /**
     * Handles user group change logic.
     */
    public function handleUserGroupChange(IUser $user, IGroup $newGroup): void {
        error_log("------------ [UserGroupManagementService] HANDLING USER GROUP CHANGE ------------");

        $userId = $user->getUID();
        $newGroupId = $newGroup->getGID();

        error_log("[handleUserGroupChange] User ID: {$userId}");
        error_log("[handleUserGroupChange] New Group ID: {$newGroupId}");

        $newOrganization = $this->organizationMapper->findByGroupId($newGroupId);
        error_log($newOrganization ? "[handleUserGroupChange] Found organization ID: {$newOrganization->getId()}" : "[handleUserGroupChange] No organization found for this group");

        if ($newOrganization) {
            $allOrgsGroups = $this->organizationMapper->findOrganizationGroupsForUser($userId);
            error_log("[handleUserGroupChange] User belongs to " . count($allOrgsGroups) . " organization groups");

            $oldOrgsGroups = array_filter(
                $allOrgsGroups,
                function (IGroup $group) use ($newGroupId) {
                    return $group->getGID() !== $newGroupId;
                }
            );

            foreach ($oldOrgsGroups as $oldGroup) {
                if ($oldGroup instanceof IGroup) {
                    error_log("[handleUserGroupChange] Removing user {$userId} from old group {$oldGroup->getGID()}");
                    $oldGroup->removeUser($user);
                }
            }

            $subscription = $this->subscriptionMapper->findByOrganizationId($newOrganization->getId());
            if ($subscription === null) {
                error_log("[handleUserGroupChange] Subscription not found for org ID: {$newOrganization->getId()}");
                throw new OCSException('Subscription not found.', 101);
            }
            error_log("[handleUserGroupChange] Found subscription ID: {$subscription->getId()}");

            $plan = $this->planMapper->find($subscription->getPlanId());
            if ($plan === null) {
                error_log("[handleUserGroupChange] Plan not found for subscription ID: {$subscription->getId()}");
                throw new OCSException('Plan not found.', 101);
            }
            error_log("[handleUserGroupChange] Found plan ID: {$plan->getId()}");

            $orgMembersCount = $this->organizationMapper->getUserCount($newOrganization->getId());
            error_log("[handleUserGroupChange] Organization currently has {$orgMembersCount} members (max allowed: {$plan->getMaxMembers()})");

            if ($orgMembersCount >= $plan->getMaxMembers()) {
                error_log("[handleUserGroupChange] ERROR: Member limit exceeded!");
                throw new OCSException(
                    sprintf(
                        'The organization has %d members, which exceeds the maximum allowed (%d) for the current plan.',
                        $orgMembersCount,
                        $plan->getMaxMembers()
                    )
                );
            }

            error_log("[handleUserGroupChange] Adding user {$userId} to organization {$newOrganization->getId()}");
            $this->userMapper->addOrganizationToUser($userId, $newOrganization->getId());

            error_log("[handleUserGroupChange] Setting user quota: {$plan->getPrivateStoragePerUser()}");
            $user->setQuota($plan->getPrivateStoragePerUser());

            error_log("[handleUserGroupChange] Adding user {$userId} to new group {$newGroupId}");
            $newGroup->addUser($user);

        } else {
            error_log("[handleUserGroupChange] No organization linked — just adding user {$userId} to group {$newGroupId}");
            $newGroup->addUser($user);
        }

        error_log("------------ [UserGroupManagementService] END USER GROUP CHANGE ------------");
    }

    /**
     * Handles user removal from group logic.
     */
    public function handleUserGroupRemove(IUser $user, IGroup $group): void {
        error_log("------------ [UserGroupManagementService] HANDLING USER GROUP REMOVE ------------");

        $userId = $user->getUID();
        $groupId = $group->getGID();

        error_log("[handleUserGroupRemove] User ID: {$userId}");
        error_log("[handleUserGroupRemove] Group ID: {$groupId}");

        $organization = $this->organizationMapper->findByGroupId($groupId);
        error_log($organization ? "[handleUserGroupRemove] Found organization ID: {$organization->getId()}" : "[handleUserGroupRemove] No organization linked to this group");

        if ($organization) {
            error_log("[handleUserGroupRemove] Removing organization link from user {$userId}");
            $this->userMapper->addOrganizationToUser($userId, null);

            error_log("[handleUserGroupRemove] Resetting quota to 0 for user {$userId}");
            $user->setQuota('0');

            error_log("[handleUserGroupRemove] Removing user {$userId} from group {$groupId}");
            $group->removeUser($user);
        } else {
            error_log("[handleUserGroupRemove] Removing user {$userId} from non-organization group {$groupId}");
            $group->removeUser($user);
        }

        error_log("------------ [UserGroupManagementService] END USER GROUP REMOVE ------------");
    }
}