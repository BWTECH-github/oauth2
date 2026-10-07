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

use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\oauth2\Migrations\Version20170329194544;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * Die Standard-Clients dürfen einen schon vorhandenen Eintrag (z. B. von Hand
 * in oauth2 0.1.0 angelegt, wo es noch keine eindeutigen Indizes gab) weder
 * doppeln noch verändern.
 *
 * @group DB
 */
class Version20170329194544Test extends TestCase {
	private const DESKTOP_ID = 'xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69';
	private const ANDROID_ID = 'e4rAsNUSIUs0lF4nbv9FmCeUkTlV9GdgTLDH1b5uie7syb90SzEVrbN7HIpmWJeD';

	/** @var ClientMapper */
	private $mapper;
	/** @var IOutput */
	private $out;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__ . '/../../../appinfo/Migrations/Version20170329194544.php';
	}

	protected function setUp(): void {
		parent::setUp();
		$this->mapper = \OC::$server->query(ClientMapper::class);
		$this->mapper->deleteAll();
		$this->out = $this->createMock(IOutput::class);
	}

	protected function tearDown(): void {
		$this->mapper->deleteAll();
		parent::tearDown();
	}

	private function addClient(string $name, string $identifier, string $secret, string $redirect): Client {
		$client = new Client();
		$client->setName($name);
		$client->setIdentifier($identifier);
		$client->setSecret($secret);
		$client->setRedirectUri($redirect);
		$client->setAllowSubdomains(false);
		return $this->mapper->insert($client);
	}

	/**
	 * @return Client[]
	 */
	private function clientsWithIdentifier(string $identifier): array {
		return \array_values(\array_filter($this->mapper->findAll(), static function (Client $c) use ($identifier) {
			return $c->getIdentifier() === $identifier;
		}));
	}

	private function names(): array {
		$names = \array_map(static function (Client $c) {
			return $c->getName();
		}, $this->mapper->findAll());
		\sort($names);
		return $names;
	}

	public function testFreshInstallGetsTheThreeDefaultClients(): void {
		(new Version20170329194544())->run($this->out);

		self::assertSame(['Android', 'Desktop Client', 'iOS'], $this->names());
	}

	public function testHandMadeDesktopClientIsNeitherDuplicatedNorChanged(): void {
		$existing = $this->addClient('Mein Desktop', self::DESKTOP_ID, 'eigenes-geheimnis', 'http://localhost:*');

		(new Version20170329194544())->run($this->out);

		$desktop = $this->clientsWithIdentifier(self::DESKTOP_ID);
		self::assertCount(1, $desktop);
		self::assertSame($existing->getId(), $desktop[0]->getId());
		self::assertSame('Mein Desktop', $desktop[0]->getName());
		self::assertSame('eigenes-geheimnis', $desktop[0]->getSecret());
		// die anderen beiden fehlten und kommen dazu
		self::assertSame(['Android', 'Mein Desktop', 'iOS'], $this->names());
	}

	public function testClientWithTheSameNameIsKeptAndReported(): void {
		$existing = $this->addClient('Android', 'eine-andere-kennung', 'geheim', 'oc://android.example.org');

		// die offizielle Android-App kann sich ohne ihre Kennung nicht anmelden -
		// das muss die Verwaltung sehen, samt dem Weg, es zu beheben
		$this->out->expects($this->once())->method('warning')
			->with($this->logicalAnd(
				$this->stringContains('<Android>'),
				$this->stringContains('eine-andere-kennung'),
				$this->stringContains('occ oauth2:modify-client'),
				$this->stringContains(self::ANDROID_ID)
			));

		(new Version20170329194544())->run($this->out);

		self::assertSame(['Android', 'Desktop Client', 'iOS'], $this->names());
		self::assertSame([], $this->clientsWithIdentifier(self::ANDROID_ID));
		self::assertSame($existing->getId(), $this->mapper->findByName('Android')->getId());
		self::assertSame('eine-andere-kennung', $this->mapper->findByName('Android')->getIdentifier());
	}

	public function testWithoutConflictNothingIsReported(): void {
		$this->addClient('Mein Desktop', self::DESKTOP_ID, 'eigenes-geheimnis', 'http://localhost:*');
		$this->out->expects($this->never())->method('warning');

		(new Version20170329194544())->run($this->out);
	}

	public function testSecondRunIsANoop(): void {
		(new Version20170329194544())->run($this->out);
		$first = $this->mapper->findAll();

		(new Version20170329194544())->run($this->out);

		self::assertEquals($first, $this->mapper->findAll());
	}
}
