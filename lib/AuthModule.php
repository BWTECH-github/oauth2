<?php
/**
 * @author Project Seminar "sciebo@Learnweb" of the University of Muenster
 * @copyright Copyright (c) 2017, University of Muenster, ownCloud GmbH
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 */

namespace OCA\OAuth2;

use OC;
use OC\User\LoginException;
use OCA\OAuth2\AppInfo\Application;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\Authentication\IAuthModule;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;

class AuthModule implements IAuthModule {
	private bool $tokenUnknown = false;

	/**
	 * @throws \Exception
	 */
	#[\Override]
	public function auth(IRequest $request): ?IUser {
		$authHeader = $request->getHeader('Authorization');

		if (!\is_string($authHeader) || \stripos($authHeader, 'Bearer ') !== 0) {
			return null;
		}

		$bearerToken = \substr($authHeader, 7);

		$user = $this->authToken($bearerToken);
		if ($user === null) {
			// In case the token is not known to the oauth2 app and
			// openidconnect is enabled we do not throw an exception.
			// This allows the openidconnect app to handle the token.
			// The openidconnect app will then finally throw the exception
			// and cause the request to die.
			if ($this->tokenCanBeHandledByOpenIDConnect()) {
				return null;
			}
			throw new LoginException('Invalid token');
		}
		return $user;
	}

	/**
	 * Returns null because the user's password is not handled in the app.
	 * Triggers a \OC\Authentication\Exceptions\PasswordlessTokenException when
	 * verifying the session, @see \OC\User\Session::checkTokenCredentials().
	 *
	 * Note: This means that only master key encryption is working with the app.
	 */
	#[\Override]
	public function getUserPassword(IRequest $request): ?string {
		return null;
	}

	public function authToken(string $bearerToken): ?IUser {
		$app = new Application();
		$container = $app->getContainer();
		$logger = $container->getServer()->getLogger();

		/** @var AccessTokenMapper $accessTokenMapper */
		$accessTokenMapper = $container->query(AccessTokenMapper::class);

		try {
			/** @var \OCA\OAuth2\Db\AccessToken $accessToken */
			$accessToken = $accessTokenMapper->findByToken($bearerToken);

			if ($accessToken->hasExpired()) {
				$logger->debug("token expired $bearerToken", ['app' => __CLASS__]);
				return null;
			}
		} catch (DoesNotExistException) {
			// we don't know the token - openid connect can handle it
			$this->tokenUnknown = true;
			$logger->debug("token does not exist $bearerToken", ['app' => __CLASS__]);
			return null;
		} catch (MultipleObjectsReturnedException) {
			$logger->debug("multiple tokens exist for $bearerToken", ['app' => __CLASS__]);
			return null;
		}

		/** @var IUserManager $userManager */
		$userManager = $container->query('UserManager');
		$userId = $accessToken->getUserId();
		if (\strstr($userId, ':')) {
			[1 => $userId] = \explode(':', $userId, 2);
		}
		return $userManager->get($userId);
	}

	protected function tokenCanBeHandledByOpenIDConnect(): bool {
		if (!$this->tokenUnknown) {
			return false;
		}

		return OC::$server->getAppManager()->isEnabledForUser('openidconnect');
	}
}
