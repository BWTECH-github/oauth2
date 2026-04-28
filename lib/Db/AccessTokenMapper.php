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
use OCP\ILogger;

class AccessTokenMapper extends Mapper {
	public function __construct(
		IDb $db,
		private readonly ILogger $logger,
		private readonly string $AppName
	) {
		parent::__construct($db, 'oauth2_access_tokens');
	}

	/**
	 * Selects an access token by its ID.
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
	 * Selects an access token by its token.
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
	 * Selects all access tokens.
	 */
	public function findAll($limit = null, $offset = null): array {
		$sql = 'SELECT * FROM `' . $this->tableName . '`';
		return $this->findEntities($sql, [], $limit, $offset);
	}

	/**
	 * Deletes all access tokens for a given client_id.
	 * Used for client deletion by the administrator in the admin settings.
	 *
	 * @see \OCA\OAuth2\Controller\SettingsController::deleteClient()
	 */
	public function deleteByClient($clientId): void {
		if (!\is_int($clientId)) {
			throw new InvalidArgumentException('Argument client_id must be an int');
		}

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `client_id` = ?';
		$this->executeStatement($sql, [$clientId], null, null);
	}

	/**
	 * Deletes all access tokens for the given user ID.
	 */
	public function deleteByUser($userId): void {
		if (!\is_string($userId)) {
			throw new InvalidArgumentException('Argument user_id must be a string');
		}

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `user_id` = ?';
		$this->executeStatement($sql, [$userId], null, null);
	}

	/**
	 * Deletes all access tokens for given client and user ID
	 * Used for the token deletion by the UserHooks.
	 */
	public function deleteByClientUser($clientId, $userId): void {
		if (!\is_int($clientId) || !\is_string($userId)) {
			throw new InvalidArgumentException('Argument client_id must be an int and user_id must be a string');
		}

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `client_id` = ? AND `user_id` = ?';
		$this->executeStatement($sql, [$clientId, $userId], null, null);
	}

	/**
	 * Deletes all entities in the table.
	 */
	public function deleteAll(): void {
		$sql = 'DELETE FROM `' . $this->tableName . '`';
		$this->executeStatement($sql, []);
	}

	/**
	 * Deletes all access tokens that expired one week before.
	 */
	public function cleanUp(): void {
		$this->logger->info('Cleaning up expired Access Tokens.', ['app' => $this->AppName]);

		$sql = 'DELETE FROM `' . $this->tableName . '` WHERE `expires` <= ' . (\time() - 60 * 60 * 24 * 7);
		$this->executeStatement($sql);
	}
}
