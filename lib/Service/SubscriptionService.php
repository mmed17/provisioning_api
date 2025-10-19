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
use OCA\Provisioning_API\Db\SubscriptionHistory;
use OCA\Provisioning_API\Db\SubscriptionHistoryMapper;
use OCP\AppFramework\OCS\OCSNotFoundException;

class SubscriptionService {
    private IDBConnection $db;
    private SubscriptionMapper $subscriptionMapper;
    private PlanMapper $planMapper;
    private OrganizationMapper $organizationMapper;
    private PlanService $planService;
    private SubscriptionHistoryMapper $SubscriptionHistoryMapper;

    public function __construct(
        IDBConnection $db,
        SubscriptionMapper $subscriptionMapper, 
        PlanMapper $planMapper,
        OrganizationMapper $organizationMapper,
        PlanService $planService,
        SubscriptionHistoryMapper $SubscriptionHistoryMapper
    ) {
        $this->db = $db;
        $this->subscriptionMapper = $subscriptionMapper;
        $this->planMapper = $planMapper;
        $this->organizationMapper = $organizationMapper;
        $this->planService = $planService;
        $this->SubscriptionHistoryMapper = $SubscriptionHistoryMapper;
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
        int $newPlanId,
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
        $organization = $this->organizationMapper->findByGroupId($groupId);
        if ($organization === null) {
            throw new OCSNotFoundException('Organization does not exist');
        }

        $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());
        if ($subscription === null) {
            throw new OCSNotFoundException('Active subscription for this organization does not exist');
        }

        // Keep a copy of the original state for the history log.
        $previousSubscription = clone $subscription;

        // 2. Update the organization's display name if it has changed.
        if ($organization->getName() !== $displayName) {
            $organization->setName($displayName);
            $this->organizationMapper->update($organization);
        }

        // 3. Handle the plan logic to get the final plan ID.
        $finalPlanId = $this->planService->handlePlanUpdate(
            $newPlanId,
            $previousSubscription->getPlanId(),
            $maxMembers,
            $maxProjects,
            $sharedStoragePerProject,
            $privateStoragePerUser,
            $price,
            $currency,
            $organization->getId()
        );
        $subscription->setPlanId($finalPlanId);

        // 4. Handle changes in the subscription's status.
        $now = new DateTime();
        $originalStatus = $previousSubscription->getStatus();
        $newStatus = $status;

        // Only perform actions if the status has actually changed.
        if ($originalStatus !== $newStatus) {
            switch ($newStatus) {
                case 'paused':
                    $subscription->setStatus('paused');
                    $subscription->setPausedAt($now->format('Y-m-d H:i:s'));
                    $subscription->setCancelledAt(null); // Reset cancel date
                    break;

                case 'cancelled':
                    $subscription->setStatus('cancelled');
                    $subscription->setCancelledAt($now->format('Y-m-d H:i:s'));
                    $subscription->setPausedAt(null); // Reset paused date
                    break;

                case 'active':
                    $subscription->setStatus('active');
                    $subscription->setPausedAt(null); // Reset paused date
                    $subscription->setCancelledAt(null); // Reset cancellation date
                    break;
            }
        }

        // 5. Handle extending the subscription's duration.
        if ($extendDuration !== null) {
            $currentEndedAt = 
                $subscription->getEndedAt() ? new DateTime($subscription->getEndedAt()) : new DateTime();
            
            $newEndedAt = (clone $currentEndedAt)->modify('+' . $extendDuration);
            $subscription->setEndedAt($newEndedAt->format('Y-m-d H:i:s'));
        }

        // 6. Save the final changes to the subscription in the database.
        $updatedSubscription = $this->subscriptionMapper->update($subscription);

        // 7. Create a detailed history log of the changes using the new mapper function.
        $this->SubscriptionHistoryMapper->createLog(
            $updatedSubscription,
            $previousSubscription,
            $changedByUserId,
        );

        // 8. CHECK if we need to delete the old plan.
        $originalPlan = $this->planMapper->find($previousSubscription->getPlanId());
        if ($originalPlan !== null && !$originalPlan->getIsPublic() && $originalPlan->getId() !== $finalPlanId) {
            $this->planMapper->delete($originalPlan);
        }

        return $updatedSubscription;
    }
}