<?php
declare(strict_types=1);
namespace OCA\Provisioning_API\Service;

use OCA\Provisioning_API\Db\PlanMapper;

class PlanService {
    private PlanMapper $planMapper;

    public function __construct(
        PlanMapper $planMapper
    ) {
        $this->planMapper = $planMapper;
    }

    /**
     * This is the most important part of the logic. It decides whether to update
     * the existing plan or create a new custom one.
     */
    public function handlePlanUpdate(
        int $newPlanId,
        int $originalPlanId,
        int $maxMembers,
        int $maxProjects,
        int $sharedStorage,
        int $privateStorage,
        ?float $price,
        ?string $currency,
        int $organizationId
    ): int {
        // Find both the original plan and the newly selected plan.
        $originalPlan = $this->planMapper->find($originalPlanId);
        $newPlan = $this->planMapper->find($newPlanId);

        // SCENARIO 1: Switching from one public plan to another (e.g., Free -> Gold).
        // If the newly selected plan is a public one, we just use it and ignore any custom values.
        if ($newPlan !== null && $newPlan->getIsPublic()) {
            return $newPlan->getId();
        }

        // SCENARIO 2: Editing an existing custom ("Enterprise") plan.
        // If the original plan was already a private/custom one, we update its values.
        if (!$originalPlan->getIsPublic()) {
            $originalPlan->setMaxMembers($maxMembers);
            $originalPlan->setMaxProjects($maxProjects);
            $originalPlan->setSharedStoragePerProject($sharedStorage);
            $originalPlan->setPrivateStoragePerUser($privateStorage);
            $originalPlan->setPrice($price);
            $originalPlan->setCurrency($currency);
            $this->planMapper->update($originalPlan);
            return $originalPlan->getId();
        }

        // SCENARIO 3: Upgrading from a public plan to a new custom ("Enterprise") plan.
        // This is the case where the original plan was public, but the new selection
        // is to create a new, editable custom plan.
        $newCustomPlan = $this->planMapper->create(
            'Custom Plan for Org ' . $organizationId,
            $maxMembers,
            $maxProjects,
            $sharedStorage,
            $privateStorage,
            $price,
            $currency,
            false // This is crucial: the new plan is NOT public.
        );

        return $newCustomPlan->getId();
    }
}