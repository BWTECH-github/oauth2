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

namespace OCA\OAuth2\Tests\Unit\Command;

use OCA\OAuth2\Commands\AddClient;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Component\Console\Tester\CommandTester;
use Test\TestCase;

/**
 * Loopback-Clients bekommen den Code an jeden Port des Rechners (RFC 8252
 * §7.3); vertrauenswürdig - also ohne Zustimmung - nur mit force-trust.
 *
 * Client::setTrusted() fragt die Datenbankplattform ab, daher die Gruppe DB.
 *
 * @group DB
 */
class AddClientTest extends TestCase {
	private const ID = 'GO23t7SEhcrffzPrpm5gLfvPbJkKUA3dcMLvNen5IDr2ORQnzFcN8x0MytWPWaAd';
	private const SECRET = 'xHY8hBLHwJzOh8MOjGIjdju9RxWscyjKw2f8k74XkzgKXXnqdDQ0FMG7sU3O2rUl';

	/** @var ClientMapper|MockObject */
	private $clientMapper;
	/** @var CommandTester */
	private $commandTester;

	protected function setUp(): void {
		parent::setUp();

		$this->clientMapper = $this->createMock(ClientMapper::class);
		$this->clientMapper->method('findByName')->willThrowException(new DoesNotExistException('unknown'));
		$this->clientMapper->method('findByIdentifier')->willThrowException(new DoesNotExistException('unknown'));
		$this->commandTester = new CommandTester(new AddClient($this->clientMapper));
	}

	public function providesLoopbackRedirectUris(): array {
		return [
			['http://localhost:*'],
			['http://127.0.0.1/cb'],
			['http://127.0.0.1:*'],
			['http://[::1]/cb'],
			['http://[::1]:*'],
		];
	}

	/**
	 * @dataProvider providesLoopbackRedirectUris
	 */
	public function testTrustedLoopbackClientIsRefused(string $redirectUri): void {
		$this->clientMapper->expects($this->never())->method('insert');

		$status = $this->commandTester->execute([
			'name' => 'Loopback',
			'client-id' => self::ID,
			'client-secret' => self::SECRET,
			'redirect-url' => $redirectUri,
			'allow-sub-domains' => 'false',
			'trusted' => 'true',
		]);

		self::assertSame(1, $status);
		self::assertStringContainsString('Cannot set localhost as trusted.', $this->commandTester->getDisplay());
	}

	/**
	 * @dataProvider providesLoopbackRedirectUris
	 */
	public function testUntrustedLoopbackClientIsAdded(string $redirectUri): void {
		$this->clientMapper->expects($this->once())->method('insert')->with(
			$this->callback(static function (Client $client) use ($redirectUri) {
				return $client->getRedirectUri() === $redirectUri && $client->getTrusted() === false;
			})
		);

		$status = $this->commandTester->execute([
			'name' => 'Loopback',
			'client-id' => self::ID,
			'client-secret' => self::SECRET,
			'redirect-url' => $redirectUri,
		]);

		self::assertSame(0, $status);
	}

	public function testTrustedLoopbackClientWithForceTrust(): void {
		$this->clientMapper->expects($this->once())->method('insert');

		$status = $this->commandTester->execute([
			'name' => 'Loopback',
			'client-id' => self::ID,
			'client-secret' => self::SECRET,
			'redirect-url' => 'http://[::1]:*',
			'allow-sub-domains' => 'false',
			'trusted' => 'true',
			'force-trust' => 'true',
		]);

		self::assertSame(0, $status);
	}
}
