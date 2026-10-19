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

use InvalidArgumentException;
use OCP\AppFramework\Db\Entity;
use OCP\AppFramework\Db\Mapper;
use OCP\IDb;

class RefreshTokenMapper extends Mapper {
	public function __construct(IDb $db) {
		// die Entity ausdrücklich: aus dem Klassennamen abgeleitet ginge es für Unterklassen schief
		parent::__construct($db, 'oauth2_refresh_tokens', RefreshToken::class);
	}

	/**
	 * Selects an refresh code by its ID.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found.
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result.
	 */
	public function find($id): Entity {
		if (!\is_int($id)) {
			throw new InvalidArgumentException('Argument id must be an int');
		}

		$sql = 'SELECT * FROM `' . $this->tableName . '` WHERE `id` = ?';
		return $this->findEntity($sql, [$id], null, null);
	}

	/**
	 * Selects an refresh token by its token.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found.
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result.
	 */
	public function findByToken($token): Entity {
		if (!\is_string($token)) {
			throw new InvalidArgumentException('Argument token must be a string');
		}

		$sql = 'SELECT * FROM `' . $this->tableName . '` WHERE `token` = ?';
		return $this->findEntity($sql, [$token], null, null);
	}

	/**
	 * Selects all refresh codes.
	 */
	public function findAll($limit = null, $offset = null): array {
		$sql = 'SELECT * FROM `' . $this->tableName . '`';
		return $this->findEntities($sql, [], $limit, $offset);
	}

	/**
	 * Deletes all refresh tokens for given client and user ID.
	 */
	public function deleteByClientUser($clientId, $userId): void {
		if (!\is_int($clientId) || !\is_string($userId)) {
			throw new InvalidArgumentException('Argument client_id must be an int and user_id must be a string');
		}

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `client_id` = ? AND `user_id` = ?';
		$this->executeStatement($sql, [$clientId, $userId], null, null);
	}

	/**
	 * Deletes all refresh tokens for a given client_id.
	 * Used for client deletion by the administrator in the admin settings.
	 */
	public function deleteByClient($clientId): void {
		if (!\is_int($clientId)) {
			throw new InvalidArgumentException('Argument client_id must be an int');
		}

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `client_id` = ?';
		$this->executeStatement($sql, [$clientId], null, null);
	}

	/**
	 * Deletes all refresh tokens for the given user ID.
	 * Used for the token deletion by the UserHooks.
	 */
	public function deleteByUser($userId): void {
		if (!\is_string($userId)) {
			throw new InvalidArgumentException('Argument user_id must be a string');
		}

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `user_id` = ?';
		$this->executeStatement($sql, [$userId], null, null);
	}

	/**
	 * Deletes all entities in the table.
	 */
	public function deleteAll(): void {
		$sql = 'DELETE FROM `' . $this->tableName . '`';
		$this->executeStatement($sql, []);
	}

	/**
	 * Vermerkt den Austausch eines Refresh-Tokens, aber nur, wenn es noch
	 * keiner verbraucht hat. Zwei gleichzeitige Anfragen mit demselben Token
	 * kommen so nicht beide durch: Die Datenbank sperrt die Zeile, die zweite
	 * findet sie danach schon verbraucht und ändert nichts.
	 *
	 * @return int 1, wenn dieser Aufruf den Austausch vermerkt hat, sonst 0
	 */
	public function markRotated(int $id, int $successorId, int $rotatedAt): int {
		$sql = 'UPDATE `' . $this->tableName . '` SET `rotated_at` = ?, `successor_id` = ? WHERE `id` = ? AND `rotated_at` IS NULL';
		return $this->executeStatement($sql, [$rotatedAt, $successorId, $id]);
	}

	/**
	 * Löscht die verbrauchten Vorgänger eines Refresh-Tokens: Wird es selbst
	 * ausgetauscht, braucht deren Gnadenfrist niemand mehr.
	 */
	public function deleteBySuccessor(int $successorId): void {
		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `successor_id` = ?';
		$this->executeStatement($sql, [$successorId]);
	}

	/**
	 * Löscht Refresh-Tokens, die vor $rotatedBefore ausgetauscht wurden.
	 */
	public function cleanUp(int $rotatedBefore): void {
		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `rotated_at` IS NOT NULL AND `rotated_at` < ?';
		$this->executeStatement($sql, [$rotatedBefore]);
	}
}
