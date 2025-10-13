<?php

namespace OCA\Provisioning_API\Middleware;

use DateTime;
use DateTimeZone;
use OCP\AppFramework\Middleware;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\IGroupManager;
use OC\Core\Controller\LoginController;
use OCP\AppFramework\OCS\OCSForbiddenException;
use OCP\AppFramework\Http\TemplateResponse;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCA\Provisioning_API\Db\OrganizationMapper;

/**
 * SubscriptionMiddleware
 *
 * This middleware checks if a non-admin user has a valid and active subscription
 * associated with their organization before allowing access to protected routes.
 */
class SubscriptionMiddleware extends Middleware {
    private IControllerMethodReflector $reflector;
    private IRequest $request;
    private IUserSession $userSession;
    private IGroupManager $groupManager;
    private SubscriptionMapper $subscriptionMapper;
    private OrganizationMapper $organizationMapper;

    public function __construct(
        IControllerMethodReflector $reflector,
        IRequest $request,
        IUserSession $userSession,
        IGroupManager $groupManager,
        SubscriptionMapper $subscriptionMapper,
        OrganizationMapper $organizationMapper
    ) {
        $this->reflector = $reflector;
        $this->request = $request;
        $this->userSession = $userSession;
        $this->groupManager = $groupManager;
        $this->subscriptionMapper = $subscriptionMapper;
        $this->organizationMapper = $organizationMapper;
    }
    
    /**
     * This method is executed before the controller action.
     * It performs all the necessary checks to validate a user's subscription.
     */
    public function beforeController($controller, $methodName) {
        // 1. Bypass public routes that don't require login.
        if ($this->isPublicRoute($controller, $methodName)) {
            return;
        }

        // 2. Ensure a user is authenticated.
        $user = $this->userSession->getUser();
        if ($user === null) {
            throw new OCSForbiddenException('Authentication required to access this resource.');
        }

        // 3. Admins have unrestricted access, so we bypass them.
        if ($this->groupManager->isAdmin($user->getUID())) {
            return;
        }

        // 4. Find the user's organization by checking their group memberships.
        $organization = $this->organizationMapper->findByUserId($user->getUID());

        // If the user does not belong to any organization, block access.
        if ($organization === null) {
            throw new OCSForbiddenException('You are not a member of a valid organization.');
        }

        // 5. Find the active subscription for that organization.
        $subscription = $this->subscriptionMapper->findByOrganizationId($organization->getId());
        
        // 6. If no active subscription is found, block access.
        if ($subscription === null) {
            throw new OCSForbiddenException('Your organization does not have an active subscription. Please contact your administrator.');
        }

        // 7. Explicitly check if the subscription's end date has passed.
        // This is the crucial check to see if the plan has expired.
        $endedAtString = $subscription->getEndedAt();
        if ($endedAtString == null) {
            throw new OCSForbiddenException('Your organization subscription has undetermined ending time. Please contact your administrator to renew.');
        }
        
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $endedAt = new DateTime($endedAtString, new DateTimeZone('UTC'));

        if ($endedAt < $now) {
            throw new OCSForbiddenException('Your organization\'s subscription has expired. Please contact your administrator to renew.');
        }

        // At this point, the user has a valid, active, and non-expired subscription. We allow access.
    }

    /**
     * Determines if a route is public and should bypass checks.
     */
    private function isPublicRoute($controller, $methodName): bool {
        if ($this->reflector->hasAnnotation('NoLoginRequired')) {
            return true;
        }
        if ($controller instanceof LoginController && 
            in_array($methodName, ['showLoginForm', 'login', 'tryLogin'])) {
            return true;
        }
        $pathInfo = $this->request->getPathInfo();
        if (in_array($pathInfo, ['/logout', '/index.php/logout'])) {
            return true;
        }
        return false;
    }

    /**
     * Catches the OCSForbiddenException to render a user-friendly error page.
     */
    public function afterException($controller, $methodName, \Exception $exception) {
        if ($exception instanceof OCSForbiddenException) {
            $params = ['message' => $exception->getMessage()];
            return new TemplateResponse('provisioning_api', 'errors/unauthorized', $params, 'guest');
        }
        throw $exception;
    }
}