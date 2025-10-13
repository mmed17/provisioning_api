<?php
/**
 * SPDX-FileCopyrightText: 2016 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */
namespace OCA\Provisioning_API\AppInfo;

use OC\Group\Manager as GroupManager;
use OCA\Provisioning_API\Capabilities;
use OCA\Provisioning_API\Db\OrganizationMapper;
use OCA\Provisioning_API\Db\SubscriptionMapper;
use OCA\Provisioning_API\Listener\UserDeletedListener;
use OCA\Provisioning_API\Middleware\ProvisioningApiMiddleware;
use OCA\Provisioning_API\Middleware\SubscriptionMiddleware;
use OCA\Settings\Mailer\NewUserMailHelper;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Utility\IControllerMethodReflector;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Defaults;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\L10N\IFactory;
use OCP\Mail\IMailer;
use OCP\Security\ICrypto;
use OCP\Security\ISecureRandom;
use OCP\User\Events\UserDeletedEvent;
use OCP\Util;
use Psr\Container\ContainerInterface;
use OCP\IRequest;
use OCP\IUserSession;

class Application extends App implements IBootstrap {
	public const APP_ID = "provisioning_api";
	public function __construct(array $urlParams = []) {
		parent::__construct('provisioning_api', $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(UserDeletedEvent::class, UserDeletedListener::class);

		$context->registerService(NewUserMailHelper::class, function (ContainerInterface $c) {
			return new NewUserMailHelper(
				$c->get(Defaults::class),
				$c->get(IURLGenerator::class),
				$c->get(IFactory::class),
				$c->get(IMailer::class),
				$c->get(ISecureRandom::class),
				$c->get(ITimeFactory::class),
				$c->get(IConfig::class),
				$c->get(ICrypto::class),
				Util::getDefaultEmailAddress('no-reply')
			);
		});
		$context->registerService(ProvisioningApiMiddleware::class, function (ContainerInterface $c) {
			$user = $c->get(IUserManager::class)->get($c->get('UserId'));
			$isAdmin = false;
			$isSubAdmin = false;

			if ($user instanceof IUser) {
				$groupManager = $c->get(IGroupManager::class);
				assert($groupManager instanceof GroupManager);
				$isAdmin = $groupManager->isAdmin($user->getUID());
				$isSubAdmin = $groupManager->getSubAdmin()->isSubAdmin($user);
			}

			return new ProvisioningApiMiddleware(
				$c->get(IControllerMethodReflector::class),
				$isAdmin,
				$isSubAdmin
			);
		});
		$context->registerService(SubscriptionMiddleware::class, function (ContainerInterface $c) {
			return new SubscriptionMiddleware(
				$c->get(IControllerMethodReflector::class),
				$c->get(IRequest::class),
				$c->get(IUserSession::class),
				$c->get(IGroupManager::class),
				$c->get(SubscriptionMapper::class),
				$c->get(OrganizationMapper::class),
			);
		});
		$context->registerMiddleware(ProvisioningApiMiddleware::class);
		$context->registerMiddleware(SubscriptionMiddleware::class, true);
		$context->registerCapability(Capabilities::class);
	}

	public function boot(IBootContext $context): void {
	}
}
