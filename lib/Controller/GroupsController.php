<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OCA\Provisioning_API\Controller;

use Exception;
use OCA\Settings\Settings\Admin\Sharing;
use OCA\Settings\Settings\Admin\Users;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\Attribute\AuthorizedAdminSetting;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\PasswordConfirmationRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSException;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCS\OCSNotFoundException;
use OCP\AppFramework\OCSController;
use OCP\Files\IRootFolder;
use OCP\Group\ISubAdmin;
use OCP\IConfig;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\L10N\IFactory;
use Psr\Log\LoggerInterface;
use OCA\GroupFolders\Folder\FolderManager;
use OCA\Provisioning_API\Db\OrganizationMapper;
use OCA\Provisioning_API\Db\PlanMapper;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCP\IDBConnection;
use OCA\Provisioning_API\Service\SubscriptionService;
use OCA\Provisioning_API\Service\OrganizationService;
use OCA\Provisioning_API\Service\PlanService;
use OCP\EventDispatcher\IEventDispatcher;

/**
 * @psalm-import-type Provisioning_APIGroupDetails from ResponseDefinitions
 * @psalm-import-type Provisioning_APIUserDetails from ResponseDefinitions
 */
class GroupsController extends AUserDataOCSController {

	public function __construct(
		string $appName,
		IRequest $request,
		IUserManager $userManager,
		IConfig $config,
		IGroupManager $groupManager,
		IUserSession $userSession,
		IAccountManager $accountManager,
		ISubAdmin $subAdminManager,
		IFactory $l10nFactory,
		IRootFolder $rootFolder,
		private LoggerInterface $logger,
		private SubscriptionService $subscriptionService,
		private OrganizationService $organizationService,
		private PlanService $planService,
		private SubscriptionMapper $subscriptionMapper,
		private OrganizationMapper $organizationMapper,
		private PlanMapper $planMapper,
		private FolderManager $folderManager,
		private IDBConnection $db,
		private IEventDispatcher $eventDispatcher
	) {
		parent::__construct($appName,
			$request,
			$userManager,
			$config,
			$groupManager,
			$userSession,
			$accountManager,
			$subAdminManager,
			$l10nFactory,
			$rootFolder,
		);
	}

	/**
	 * Get a list of groups
	 *
	 * @param string $search Text to search for
	 * @param ?int $limit Limit the amount of groups returned
	 * @param int $offset Offset for searching for groups
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<string>}, array{}>
	 *
	 * 200: Groups returned
	 */
	#[NoAdminRequired]
	public function getGroups(string $search = '', ?int $limit = null, int $offset = 0): DataResponse {
		$groups = $this->groupManager->search($search, $limit, $offset);
		$groups = array_values(array_map(function ($group) {
			/** @var IGroup $group */
			return $group->getGID();
		}, $groups));

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * Get a list of groups details
	 *
	 * @param string $search Text to search for
	 * @param ?int $limit Limit the amount of groups returned
	 * @param int $offset Offset for searching for groups
	 * @return DataResponse<Http::STATUS_OK, array{groups: list<Provisioning_APIGroupDetails>}, array{}>
	 *
	 * 200: Groups details returned
	 */
	#[NoAdminRequired]
	#[AuthorizedAdminSetting(settings: Sharing::class)]
	#[AuthorizedAdminSetting(settings: Users::class)]
	public function getGroupsDetails(string $search = '', ?int $limit = null, int $offset = 0): DataResponse {
		$groups = $this->groupManager->search($search, $limit, $offset);
		$groups = array_values(array_map(function ($group) {
			/** @var IGroup $group */
			return [
				'id' => $group->getGID(),
				'displayname' => $group->getDisplayName(),
				'usercount' => $group->count(),
				'disabled' => $group->countDisabled(),
				'canAdd' => $group->canAddUser(),
				'canRemove' => $group->canRemoveUser(),
			];
		}, $groups));

		return new DataResponse(['groups' => $groups]);
	}

	/**
	 * Get a list of users in the specified group
	 *
	 * @param string $groupId ID of the group
	 * @return DataResponse<Http::STATUS_OK, array{users: list<string>}, array{}>
	 * @throws OCSException
	 *
	 * @deprecated 14 Use getGroupUsers
	 *
	 * 200: Group users returned
	 */
	#[NoAdminRequired]
	public function getGroup(string $groupId): DataResponse {
		return $this->getGroupUsers($groupId);
	}

	/**
	 * Get a list of users in the specified group
	 *
	 * @param string $groupId ID of the group
	 * @return DataResponse<Http::STATUS_OK, array{users: list<string>}, array{}>
	 * @throws OCSException
	 * @throws OCSNotFoundException Group not found
	 * @throws OCSForbiddenException Missing permissions to get users in the group
	 *
	 * 200: User IDs returned
	 */
	#[NoAdminRequired]
	public function getGroupUsers(string $groupId): DataResponse {
		$groupId = urldecode($groupId);

		$user = $this->userSession->getUser();
		$isSubadminOfGroup = false;

		// Check the group exists
		$group = $this->groupManager->get($groupId);
		if ($group !== null) {
			$isSubadminOfGroup = $this->groupManager->getSubAdmin()->isSubAdminOfGroup($user, $group);
		} else {
			throw new OCSNotFoundException('The requested group could not be found');
		}

		// Check subadmin has access to this group
		$isAdmin = $this->groupManager->isAdmin($user->getUID());
		$isDelegatedAdmin = $this->groupManager->isDelegatedAdmin($user->getUID());
		if ($isAdmin || $isDelegatedAdmin || $isSubadminOfGroup) {
			$users = $this->groupManager->get($groupId)->getUsers();
			$users = array_map(function ($user) {
				/** @var IUser $user */
				return $user->getUID();
			}, $users);
			/** @var list<string> $users */
			$users = array_values($users);
			return new DataResponse(['users' => $users]);
		}

		throw new OCSForbiddenException();
	}

	/**
	 * Get a list of users details in the specified group
	 *
	 * @param string $groupId ID of the group
	 * @param string $search Text to search for
	 * @param int|null $limit Limit the amount of groups returned
	 * @param int $offset Offset for searching for groups
	 *
	 * @return DataResponse<Http::STATUS_OK, array{users: array<string, Provisioning_APIUserDetails|array{id: string}>}, array{}>
	 * @throws OCSException
	 *
	 * 200: Group users details returned
	 */
	#[NoAdminRequired]
	public function getGroupUsersDetails(string $groupId, string $search = '', ?int $limit = null, int $offset = 0): DataResponse {
		$groupId = urldecode($groupId);
		$currentUser = $this->userSession->getUser();

		// Check the group exists
		$group = $this->groupManager->get($groupId);
		if ($group !== null) {
			$isSubadminOfGroup = $this->groupManager->getSubAdmin()->isSubAdminOfGroup($currentUser, $group);
		} else {
			throw new OCSException('The requested group could not be found', OCSController::RESPOND_NOT_FOUND);
		}

		// Check subadmin has access to this group
		$isAdmin = $this->groupManager->isAdmin($currentUser->getUID());
		$isDelegatedAdmin = $this->groupManager->isDelegatedAdmin($currentUser->getUID());
		if ($isAdmin || $isDelegatedAdmin || $isSubadminOfGroup) {
			$users = $group->searchUsers($search, $limit, $offset);

			// Extract required number
			$usersDetails = [];
			foreach ($users as $user) {
				try {
					/** @var IUser $user */
					$userId = (string)$user->getUID();
					$userData = $this->getUserData($userId);
					// Do not insert empty entry
					if ($userData !== null) {
						$usersDetails[$userId] = $userData;
					} else {
						// Logged user does not have permissions to see this user
						// only showing its id
						$usersDetails[$userId] = ['id' => $userId];
					}
				} catch (OCSNotFoundException $e) {
					// continue if a users ceased to exist.
				}
			}
			return new DataResponse(['users' => $usersDetails]);
		}

		throw new OCSException('The requested group could not be found', OCSController::RESPOND_NOT_FOUND);
	}
	
	/**
	 * Get organization details
	 *
	 * @param string $groupId ID of the group
	 * @return DataResponse
	 * @throws OCSNotFoundException
	 */
	#[NoAdminRequired]
    public function getOrganization(string $groupId): DataResponse {
        $groupId = urldecode($groupId);

        // 1. Find the organization using the group ID.
        $organization = $this->organizationMapper->findByGroupId($groupId);
        if ($organization === null) {
            throw new OCSNotFoundException('Organization does not exist for the given group');
        }

        // 2. Find the active subscription using the organization's numeric ID.
        $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());
        if ($subscription === null) {
            throw new OCSNotFoundException('No active subscription found for this organization');
        }
        
        // 3. Find the details of the plan associated with the subscription.
        $plan = $this->planMapper->find($subscription->getPlanId());
        if ($plan === null) {
            // This case should ideally not happen if data is consistent.
            throw new OCSNotFoundException('The plan associated with this subscription could not be found');
        }

        // 4. Return all the relevant data in a structured response.
        return new DataResponse([
            'organization' => [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'nextcloud_group_id' => $organization->getNextcloudGroupId(),
            ],
            'subscription' => $subscription,
            'plan' => $plan,
        ]);
    }

	/**
	 * Create a new group with its subscription details
	 *
	 * @param string $groupid ID of the group
	 * @param string $displayname Display name of the group
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSException
	 *
	 * 200: Group created successfully
	 */
	#[AuthorizedAdminSetting(settings: Users::class)]
	#[PasswordConfirmationRequired]
	public function addGroup(
		string $groupid,
		string $validity,
		?int $memberLimit,
		?int $projectsLimit,
		?int $sharedStoragePerProject,
		?int $privateStorage,
		?int $planId,
		?float $price,
		?string $currency = 'EUR',
		?string $displayname = '',
	): DataResponse {

		if (empty($groupid)) {
			$this->logger->error(
				'Group name not supplied',
				['app' => 'provisioning_api']
			);
			throw new OCSException('Invalid group name', 101);
		}

		if ($this->groupManager->groupExists($groupid)) {
			throw new OCSException('group exists', 102);
		}

		try {
			$this->db->beginTransaction();
			$group = $this->groupManager->createGroup($groupid);
			if ($group === null) {
				throw new OCSException('Not supported by backend', 103);
			}

			$organization = $this->organizationService->createOrganization(
				$group->getGID(),
				$displayname
			);
			
			if ($organization === null) {
				throw new OCSException('Failed to create organization', 104);
			}
			
			$subscription = $this->subscriptionService->createSubscription(
				$organization->getId(),
				$validity,
				$planId,
				$memberLimit,
				$projectsLimit,
				$sharedStoragePerProject,
				$privateStorage,
				$price,
				$currency
			);
			
			$this->db->commit();
		} catch (\Exception $e) {
			$this->db->rollBack();
			throw new OCSException('Failed to create organization: ' . $e->getMessage(), 104);
		}

		return new DataResponse($subscription);
	}

	/**
	 * Update a group
	 *
	 * @param string $groupId ID of the group
	 * @param string $key Key to update, only 'displayname'
	 * @param string $value New value for the key
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSException
	 *
	 * 200: Group updated successfully
	 */
	#[AuthorizedAdminSetting(settings:Users::class)]
	#[PasswordConfirmationRequired]
	public function updateGroup(string $groupId, string $key, string $value): DataResponse {
		$groupId = urldecode($groupId);

		if ($key === 'displayname') {
			$group = $this->groupManager->get($groupId);
			if ($group === null) {
				throw new OCSException('Group does not exist', OCSController::RESPOND_NOT_FOUND);
			}
			if ($group->setDisplayName($value)) {
				return new DataResponse();
			}

			throw new OCSException('Not supported by backend', 101);
		} else {
			throw new OCSException('', OCSController::RESPOND_UNKNOWN_ERROR);
		}
	}
	
	/**
     * Updates an organization's details and its subscription plan.
     * This is the single entry point for all edits.
     *
     * @param string $groupId The group ID of the organization to update.
     * @param string $displayName The new display name for the organization.
     * @param int $planId The ID of the plan (can be an existing public plan or a custom one).
     * @param int $maxMembers The new limit for members.
     * @param int $maxProjects The new limit for projects.
     * @param int $sharedStoragePerProject The new shared storage limit in bytes.
     * @param int $privateStoragePerUser The new private storage limit in bytes.
     * @param string $status The new status for the subscription (e.g., 'active', 'paused').
     * @param string|null $extendDuration A string to extend the subscription (e.g., "1 month").
     * @param float|null $price The price for a custom plan.
     * @param string|null $currency The currency for a custom plan.
     *
     * @return DataResponse
     * @throws OCSException
     */
    #[AuthorizedAdminSetting(settings:Users::class)]
    #[PasswordConfirmationRequired]
    public function updateSubscription(
        string $groupId,
        string $displayName,
        int $planId,
        int $maxMembers,
        int $maxProjects,
        int $sharedStoragePerProject,
        int $privateStoragePerUser,
        string $status,
        ?string $extendDuration = null,
        ?float $price = null,
        ?string $currency = null
    ): DataResponse {
        $this->db->beginTransaction();
        try {
            $updatedSubscription = $this->subscriptionService->updateSubscription(
                $groupId,
                $displayName,
                $planId,
                $maxMembers,
                $maxProjects,
                $sharedStoragePerProject,
                $privateStoragePerUser,
                $status,
                $extendDuration,
                $price,
                $currency,
                $this->userSession->getUser()->getUID() // Pass the current user for the history log
            );

            $this->db->commit();
            return new DataResponse(['subscription' => $updatedSubscription]);

        } catch (Exception $e) {
            $this->db->rollBack();
            $this->logger->error('Failed to update organization: ' . $e->getMessage(), ['exception' => $e]);
            // Re-throw specific exceptions for better client-side error handling
            if ($e instanceof OCSNotFoundException) {
                throw $e;
            }
            throw new OCSException('Failed to update organization: ' . $e->getMessage());
        }
    }

	/**
	 * Delete a group
	 *
	 * @param string $groupId ID of the group
	 * @return DataResponse<Http::STATUS_OK, list<empty>, array{}>
	 * @throws OCSException
	 *
	 * 200: Group deleted successfully
	 */
	#[AuthorizedAdminSetting(settings:Users::class)]
	#[PasswordConfirmationRequired]
	public function deleteGroup(string $groupId): DataResponse {
		$groupId = urldecode($groupId);

		try {
			$this->db->beginTransaction();
			
			// 1. Find the organization linked to this Nextcloud group.
            $organization = $this->organizationMapper->findByGroupId($groupId);

            // Only proceed if an organization is found.
            if ($organization !== null) {
                // 2. Find subscription for this organization.
                $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());

				// 3. For subscription, get its plan.
				$plan = $this->planMapper->find($subscription->getPlanId());
            }

			// Check it exists
			if (!$this->groupManager->groupExists($groupId)) {
				throw new OCSException('', 101);
			} elseif ($groupId === 'admin' || !$this->groupManager->get($groupId)->delete()) {
				// Cannot delete admin group
				throw new OCSException('', 102);
			}

			// 4. If the plan exists and is NOT public, it's a custom plan that must be deleted.
			if ($plan !== null && !$plan->getIsPublic()) {
				$this->planMapper->delete($plan);
			}

			$this->db->commit();
			return new DataResponse();
		} catch (Exception $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Get the list of user IDs that are a subadmin of the group
	 *
	 * @param string $groupId ID of the group
	 * @return DataResponse<Http::STATUS_OK, list<string>, array{}>
	 * @throws OCSException
	 *
	 * 200: Sub admins returned
	 */
	#[AuthorizedAdminSetting(settings:Users::class)]
	public function getSubAdminsOfGroup(string $groupId): DataResponse {
		// Check group exists
		$targetGroup = $this->groupManager->get($groupId);
		if ($targetGroup === null) {
			throw new OCSException('Group does not exist', 101);
		}

		/** @var IUser[] $subadmins */
		$subadmins = $this->groupManager->getSubAdmin()->getGroupsSubAdmins($targetGroup);
		// New class returns IUser[] so convert back
		/** @var list<string> $uids */
		$uids = [];
		foreach ($subadmins as $user) {
			$uids[] = $user->getUID();
		}

		return new DataResponse($uids);
	}
}
