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
 * @method int getAccessTokenId()
 * @method void setAccessTokenId(int $accessTokenId)
 * @method int|null getRotatedAt()
 * @method void setRotatedAt(int $rotatedAt)
 * @method int|null getSuccessorId()
 * @method void setSuccessorId(int $successorId)
 *
 * Nach dem Austausch bleibt ein Refresh-Token als verbraucht stehen: rotated_at
 * nennt den Zeitpunkt, successor_id das Refresh-Token, das es ersetzt hat.
 * Während der Gnadenfrist liefert es das Ergebnis dieses Austauschs noch einmal
 * (siehe OAuthApiController::replayRotation()).
 */
class RefreshToken extends Entity {
	protected ?string $token = null;
	protected ?int $clientId = null;
	protected ?string $userId = null;
	protected ?int $accessTokenId = null;
	protected ?int $rotatedAt = null;
	protected ?int $successorId = null;

	public function __construct() {
		$this->addType('id', 'int');
		$this->addType('token', 'string');
		$this->addType('client_id', 'int');
		$this->addType('user_id', 'string');
		$this->addType('access_token_id', 'int');
		$this->addType('rotated_at', 'int');
		$this->addType('successor_id', 'int');
	}
}
