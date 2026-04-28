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

use OCA\OAuth2\Exceptions\UnsupportedPkceTransformException;
use OCA\OAuth2\Utilities;
use OCP\AppFramework\Db\Entity;

/**
 * @method string getCode()
 * @method void setCode(string $code)
 * @method int getClientId()
 * @method void setClientId(int $clientId)
 * @method string getUserId()
 * @method void setUserId(string $userId)
 * @method int getExpires()
 * @method void setExpires(int $value)
 * @method void setCodeChallenge(string $codeChallenge)
 * @method void setCodeChallengeMethod(string $codeChallengeMethod)
 */
class AuthorizationCode extends Entity {
	public const EXPIRATION_TIME = 600;

	protected ?string $code = null;
	protected ?int $clientId = null;
	protected ?string $userId = null;
	protected ?int $expires = null;
	protected ?string $codeChallenge = null;
	protected ?string $codeChallengeMethod = null;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('code', 'string');
		$this->addType('client_id', 'int');
		$this->addType('user_id', 'string');
		$this->addType('expires', 'int');
		$this->addType('code_challenge', 'string');
		$this->addType('code_challenge_method', 'string');
	}

	/**
	 * Resets the expiry time to EXPIRATION_TIME seconds from now.
	 */
	public function resetExpires(): void {
		$this->setExpires(\time() + self::EXPIRATION_TIME);
	}

	/**
	 * Determines if an authorization code has expired.
	 */
	public function hasExpired(): bool {
		return \time() >= $this->getExpires();
	}

	public function isCodeVerifierValid($codeVerifier): bool {
		return match ($this->codeChallengeMethod) {
			'S256' => Utilities::base64url_encode(\hash('sha256', $codeVerifier, true)) === $this->codeChallenge,
			'plain', '', null => $codeVerifier === $this->codeChallenge,
			default => throw new UnsupportedPkceTransformException("Code challenge method {$this->codeChallengeMethod} not supported"),
		};
	}
}
