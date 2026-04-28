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

namespace OCA\OAuth2\BackgroundJob;

use OC\BackgroundJob\TimedJob;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCodeMapper;

class CleanUp extends TimedJob {
	/**
	 * Cron interval in seconds
	 */
	protected $interval = 86400;

	public function __construct(
		protected AuthorizationCodeMapper $authorizationCodeMapper,
		protected AccessTokenMapper $accessTokenMapper
	) {
	}

	/**
	 * Cleans up expired authorization codes and access tokens.
	 *
	 * @param string $argument
	 */
	#[\Override]
	public function run($argument): void {
		$this->authorizationCodeMapper->cleanUp();
		$this->accessTokenMapper->cleanUp();
	}
}
