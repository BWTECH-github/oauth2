<?php
/**
 * @copyright Copyright (c) 2026, BW-Tech GmbH
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
 *
 */

namespace OCA\OAuth2\Tests\Unit\Migrations;

use OCA\oauth2\Migrations\Version20261126120000;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * Gespeicherte Weiterleitungs-URIs verlieren Leerraum am Anfang und Ende -
 * sonst ändert sich nichts, auch nicht Leerraum mitten in der Adresse.
 *
 * @group DB
 */
class Version20261126120000Test extends TestCase {
	private const PREFIX = 'oauth2-trim-test-';

	/** @var IDBConnection */
	private $db;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__ . '/../../../appinfo/Migrations/Version20261126120000.php';
	}

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->removeTestClients();
	}

	protected function tearDown(): void {
		$this->removeTestClients();
		parent::tearDown();
	}

	private function removeTestClients(): void {
		$qb = $this->db->getQueryBuilder();
		$qb->delete('oauth2_clients')
			->where($qb->expr()->like('name', $qb->createNamedParameter($this->db->escapeLikeParameter(self::PREFIX) . '%')))
			->execute();
	}

	private function addClient(string $suffix, string $redirectUri): void {
		$qb = $this->db->getQueryBuilder();
		$qb->insert('oauth2_clients')
			->values([
				'identifier' => $qb->createNamedParameter(\str_pad($suffix, 64, 'i')),
				'secret' => $qb->createNamedParameter(\str_repeat('s', 64)),
				'redirect_uri' => $qb->createNamedParameter($redirectUri),
				'name' => $qb->createNamedParameter(self::PREFIX . $suffix),
				'allow_subdomains' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
				'trusted' => $qb->createNamedParameter(false, IQueryBuilder::PARAM_BOOL),
			])
			->execute();
	}

	/**
	 * @return array[] Zeilen der Testclients, nach Name
	 */
	private function clients(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id', 'identifier', 'secret', 'redirect_uri', 'name', 'allow_subdomains', 'trusted')
			->from('oauth2_clients')
			->where($qb->expr()->like('name', $qb->createNamedParameter($this->db->escapeLikeParameter(self::PREFIX) . '%')))
			->execute();
		$rows = [];
		foreach ($result->fetchAllAssociative() as $row) {
			$rows[$row['name']] = $row;
		}
		$result->free();
		\ksort($rows);
		return $rows;
	}

	public function testRedirectUrisLoseWhitespaceAtStartAndEndOnly(): void {
		// so stand der Desktop Client beim Kunden in der Datenbank
		$this->addClient('a', 'http://localhost:* ');
		$this->addClient('b', "\thttp://127.0.0.1:*\r\n");
		$this->addClient('c', 'http://local host:*');
		$this->addClient('d', 'oc://android.owncloud.com');
		$before = $this->clients();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->exactly(2))->method('info');
		(new Version20261126120000())->run($out);

		$expected = $before;
		$expected[self::PREFIX . 'a']['redirect_uri'] = 'http://localhost:*';
		$expected[self::PREFIX . 'b']['redirect_uri'] = 'http://127.0.0.1:*';
		self::assertEquals($expected, $this->clients());
	}

	public function testSecondRunIsANoop(): void {
		$this->addClient('a', 'http://localhost:* ');
		(new Version20261126120000())->run($this->createMock(IOutput::class));
		$afterFirst = $this->clients();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->never())->method('info');
		(new Version20261126120000())->run($out);

		self::assertEquals($afterFirst, $this->clients());
	}

	public function testRedirectUriOfOnlyWhitespaceStaysAsItIs(): void {
		$this->addClient('a', ' ');
		$before = $this->clients();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->never())->method('info');
		(new Version20261126120000())->run($out);

		self::assertEquals($before, $this->clients());
	}
}
