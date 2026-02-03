<?php

namespace OCA\Provisioning_API\Group;

use OCA\Provisioning_API\Db\OrganizationMapper;
use OCP\IDBConnection;
use OCP\IGroup;
use OCP\IGroupManager;
use OCP\IUser;
use OC\Group\Manager as GroupManager;

class OrganizationGroupManager implements IGroupManager {

    public function __construct(
        private IGroupManager $originalGroupManager,
        private OrganizationMapper $organizationMapper,
        private GroupManager $statedGroupManager,
    ) {}

    public function search(string $search, ?int $limit = null, ?int $offset = 0) {
        $gids = $this->organizationMapper->findOrganizationGroupIds($search, $limit, $offset);
        $groups = [];
        foreach ($gids as $gid) {
            $group = $this->originalGroupManager->get($gid);
            if ($group !== null) {
                $groups[] = $group;
            }
        }

        return $groups;
    }

    public function isBackendUsed($backendClass) {
        return $this->originalGroupManager->isBackendUsed($backendClass);
    }

    public function addBackend($backend) {
        return $this->originalGroupManager->addBackend($backend);
    }

    public function clearBackends() {
        return $this->originalGroupManager->clearBackends();
    }

    public function getBackends() {
        return $this->originalGroupManager->getBackends();
    }

    public function get($gid): ?IGroup {
        return $this->originalGroupManager->get($gid);
    }

    public function groupExists($gid): bool {
        return $this->originalGroupManager->groupExists($gid);
    }

    public function createGroup($gid): ?IGroup {
        return $this->originalGroupManager->createGroup($gid);
    }

    public function getUserGroups(?IUser $user = null) {
        if ($user === null) {
            return [];
        }

        $gid = $this->organizationMapper->findOrganizationGroupIdForUser($user->getUID());
        if ($gid === null) {
            return [];
        }

        $group = $this->originalGroupManager->get($gid);
        return ($group instanceof IGroup) ? [$group] : [];
    }

    public function getUserGroupIds(IUser $user): array {
        $gid = $this->organizationMapper->findOrganizationGroupIdForUser($user->getUID());
        return ($gid === null) ? [] : [$gid];
    }

    public function displayNamesInGroup($gid, $search = '', $limit = -1, $offset = 0) {
        return $this->originalGroupManager->displayNamesInGroup($gid, $search, $limit, $offset);
    }

    public function isAdmin($userId): bool {
        return $this->originalGroupManager->isAdmin($userId);
    }

    public function isInGroup($userId, $group): bool {
        return $this->originalGroupManager->isInGroup($userId, $group);
    }

    public function getDisplayName(string $groupId): ?string {
        return $this->originalGroupManager->getDisplayName($groupId);
    }

    public function isDelegatedAdmin(string $userId): bool {
        return $this->originalGroupManager->isDelegatedAdmin($userId);
    }

    /**
	 * @return \OC\SubAdmin
	 */
	public function getSubAdmin() {
        return $this->statedGroupManager->getSubAdmin();
	}
}