<?php

declare(strict_types=1);

namespace OCA\Provisioning_API\Controller;

use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\OCSController;
use OCP\IRequest;
use OCP\IUserSession;
use OCA\Provisioning_API\Db\PlanMapper;
use OCP\IGroupManager;

class PlansController extends OCSController {
	private PlanMapper $planMapper;
	private IUserSession $userSession;
	private IGroupManager $groupManager;
	
	public function __construct(
		string $appName,
		IRequest $request,
		PlanMapper $planMapper,
		IUserSession $userSession,
		IGroupManager $groupManager
	) {
		parent::__construct($appName, $request);
		$this->planMapper = $planMapper;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	/**
	 * @return DataResponse
	 * @throws OCSForbiddenException
     * 
     * @AdminRequired
	 */
    public function getPlans(): DataResponse {
        $plans = $this->planMapper->findAll();
        return new DataResponse(['plans' => $plans]);
    }
}