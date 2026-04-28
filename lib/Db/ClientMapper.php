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

class ClientMapper extends Mapper {
	public function __construct(IDb $db) {
		parent::__construct($db, 'oauth2_clients');
	}

	/**
	 * Selects a client by its ID.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found.
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result.
	 */
	public function find($id): Entity {
		if (!\is_int($id)) {
			throw new InvalidArgumentException('id must not be null');
		}

		$sql = 'SELECT * FROM `' . $this->tableName . '` WHERE `id` = ?';
		return $this->findEntity($sql, [$id], null, null);
	}

	/**
	 * Selects a client by its identifier.
	 *
	 * @throws \OCP\AppFramework\Db\DoesNotExistException if not found.
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException if more than one result.
	 */
	public function findByIdentifier($identifier): Entity {
		if (!\is_string($identifier)) {
			throw new InvalidArgumentException('identifier must not be null');
		}

		$sql = 'SELECT * FROM `' . $this->tableName . '` WHERE `identifier` = ?';
		return $this->findEntity($sql, [$identifier], null, null);
	}

	public function findByName($name): Entity {
		if (!\is_string($name)) {
			throw new InvalidArgumentException('name must not be null');
		}
		$sql = 'SELECT * FROM `' . $this->tableName . '` WHERE `name` = ?';
		return $this->findEntity($sql, [$name], null, null);
	}

	/**
	 * Selects all clients.
	 */
	public function findAll($limit = null, $offset = null): array {
		$sql = 'SELECT * FROM `' . $this->tableName . '`';
		return $this->findEntities($sql, [], $limit, $offset);
	}

	/**
	 * Selects clients by the given user ID.
	 */
	public function findByUser($userId): array {
		if (!\is_string($userId)) {
			throw new InvalidArgumentException('userId must not be null');
		}

		$sql = 'SELECT * FROM `' . $this->tableName . '` '
			. 'WHERE `id` IN ( '
			. 'SELECT `client_id` FROM `*PREFIX*oauth2_auth_codes` WHERE `user_id` = ? '
			. 'UNION '
			. 'SELECT `client_id` FROM `*PREFIX*oauth2_access_tokens` WHERE `user_id` = ? '
			. ')';
		return $this->findEntities($sql, [$userId, $userId], null, null);
	}

	/**
	 * Deletes all entities in the table.
	 */
	public function deleteAll(): void {
		$sql = 'DELETE FROM `' . $this->tableName . '`';
		$this->executeStatement($sql, []);
	}
}
