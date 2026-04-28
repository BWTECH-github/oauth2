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

namespace OCA\OAuth2\Db;

use OCP\AppFramework\Db\Entity;

/**
 * @method string getToken()
 * @method void setToken(string $token)
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getExpires()
 * @method void setExpires(int $value)
 */
class AccessToken extends Entity {
	public const EXPIRATION_TIME = 3600;

	protected ?string $token = null;
	protected ?int $clientId = null;
	protected ?string $userId = null;
	protected ?int $expires = null;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('token', 'string');
		$this->addType('client_id', 'int');
		$this->addType('user_id', 'string');
		$this->addType('expires', 'int');
	}

	/**
	 * Resets the expiry time to EXPIRATION_TIME seconds from now.
	 */
	public function resetExpires(): void {
		$this->setExpires(\time() + self::EXPIRATION_TIME);
	}

	/**
	 * Determines if an access token has expired.
	 */
	public function hasExpired(): bool {
		return \time() >= $this->getExpires();
	}
}
