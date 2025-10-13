<?php
declare(strict_types=1);
namespace OCA\Provisioning_API\Service;

use DateTime;
use OCA\Provisioning_API\Db\OrganizationMapper;
use OCA\Provisioning_API\Db\Plan;
use OCP\IDBConnection;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Db\DoesNotExistException;
use OCA\Provisioning_API\Db\Subscription;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCA\Provisioning_API\Db\PlanMapper;
use OCP\AppFramework\OCS\OCSNotFoundException;

class SubscriptionService {
    private IDBConnection $db;
    private SubscriptionMapper $subscriptionMapper;
    private PlanMapper $planMapper;
    private OrganizationMapper $organizationMapper;
    private PlanService $planService;

    public function __construct(
        IDBConnection $db,
        SubscriptionMapper $subscriptionMapper, 
        PlanMapper $planMapper,
        OrganizationMapper $organizationMapper,
        PlanService $planService,
    ) {
        $this->db = $db;
        $this->subscriptionMapper = $subscriptionMapper;
        $this->planMapper = $planMapper;
        $this->organizationMapper = $organizationMapper;
        $this->planService = $planService;
    }

    /**
     * Retrieves the active subscription for a given organization ID.
     * @param int $organizationId
     * @return DataResponse
     */
    public function createSubscription(
        int $organizationId,
        string $validity,
        ?int $planId,
        ?int $memberLimit,
        ?int $projectsLimit,
        ?int $sharedStoragePerProject,
        ?int $privateStorage,
        ?float $price,
        ?string $currency
    ): Subscription {

        $subscription = new Subscription();

        $now = new \DateTime('now', new \DateTimeZone('UTC'));
        $validityDuration = \DateInterval::createFromDateString($validity);
        $endedAt = (clone $now)->add($validityDuration);

        $subscription->setOrganizationId($organizationId);
        $subscription->setStatus('active');
        $subscription->setStartedAt($now->format('Y-m-d H:i:s'));
        $subscription->setEndedAt($endedAt->format('Y-m-d H:i:s'));

        if ($planId === null) {
            $plan = $this->planMapper->create(
                'Custom Plan for Org ' . $organizationId,
                $memberLimit,
                $projectsLimit,
                $sharedStoragePerProject,
                $privateStorage,
                $price,
                $currency,
                false
            );
            
            $planId = $plan->getId();
        }

        $subscription->setPlanId($planId);

        $newSubscription = $this->subscriptionMapper->insert($subscription);

        return $newSubscription;
    }

    /**
     * The main orchestrator for updating a subscription.
     */
    public function updateSubscription(
        string $groupId,
        string $displayName,
        int $newPlanId, // The plan ID selected in the frontend
        int $maxMembers,
        int $maxProjects,
        int $sharedStoragePerProject,
        int $privateStoragePerUser,
        string $status,
        ?string $extendDuration,
        ?float $price,
        ?string $currency,
        string $changedByUserId
    ) {
        // 1. Find the organization and its current subscription.
        $organization = $this->organizationMapper->findOrganizationByGroupId($groupId);
        if ($organization === null) {
            throw new OCSNotFoundException('Organization does not exist');
        }

        $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());
        if ($subscription === null) {
            throw new OCSNotFoundException('Active subscription for this organization does not exist');
        }

        // Keep a copy of the original state for the history log.
        $originalSubscription = clone $subscription;

        // 2. Update the organization's display name if it has changed.
        if ($organization->getName() !== $displayName) {
            $organization->setName($displayName);
            $this->organizationMapper->update($organization);
        }

        // 3. Handle the plan logic: this now correctly handles all update scenarios.
        // UPDATED: Pass both the new and original plan IDs for smarter logic.
        $finalPlanId = $this->planService->handlePlanUpdate(
            $newPlanId,
            $originalSubscription->getPlanId(),
            $maxMembers,
            $maxProjects,
            $sharedStoragePerProject,
            $privateStoragePerUser,
            $price,
            $currency,
            $organization->getId()
        );
        $subscription->setPlanId($finalPlanId);

        // 4. Update the subscription's status and end date.
        $subscription->setStatus($status);
        if ($extendDuration !== null) {
            $currentEndedAt = $subscription->getEndedAt() ? new DateTime($subscription->getEndedAt()) : new DateTime();
            $newEndedAt = (clone $currentEndedAt)->modify('+' . $extendDuration);
            $subscription->setEndedAt($newEndedAt->format('Y-m-d H:i:s'));
        }

        // 5. Save the changes to the subscription.
        $updatedSubscription = $this->subscriptionMapper->update($subscription);

        // 6. Create a detailed history log of the changes.
        // $this->historyService->createLog(
        //     $originalSubscription,
        //     $updatedSubscription,
        //     $changedByUserId,
        //     'Subscription details updated.'
        // );

        return $updatedSubscription;
    }
}