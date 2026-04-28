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

namespace OCA\OAuth2\Hooks;

use OC\User\Manager;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCP\ILogger;

class UserHooks {
	public function __construct(
		private readonly Manager $userManager,
		private readonly AuthorizationCodeMapper $authorizationCodeMapper,
		private readonly AccessTokenMapper $accessTokenMapper,
		private readonly RefreshTokenMapper $refreshTokenMapper,
		private readonly ILogger $logger,
		private readonly string $AppName
	) {
	}

	/**
	 * Registers a pre-delete hook for users to delete authorization codes,
	 * access tokens and refresh tokens that reference the user.
	 */
	public function register(): void {
		$callback = function ($user): void {
			if ($user->getUID() !== null) {
				$this->logger->info('Deleting authorization codes, access tokens and refresh tokens referencing the user to be deleted "' . $user->getUID() . '".', ['app' => $this->AppName]);

				$this->authorizationCodeMapper->deleteByUser($user->getUID());
				$this->accessTokenMapper->deleteByUser($user->getUID());
				$this->refreshTokenMapper->deleteByUser($user->getUID());
			}
		};
		/** @phan-suppress-next-line PhanUndeclaredMethod */
		$this->userManager->listen('\OC\User', 'preDelete', $callback);
	}
}
