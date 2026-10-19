<?php
/**
 * @author Project Seminar "sciebo@Learnweb" of the University of Muenster
 * @copyright Copyright (c) 2017, University of Muenster
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

namespace OCA\OAuth2\Tests\Unit\BackgroundJob;

use OCA\OAuth2\AppInfo\Application;
use OCA\OAuth2\BackgroundJob\CleanUp;
use OCA\OAuth2\Db\RefreshToken;
use OCA\OAuth2\Db\RefreshTokenMapper;
use PHPUnit\Framework\TestCase;

class CleanUpTest extends TestCase {
	public function testRun() {
		$c = \OC::$server->query(CleanUp::class);
		$this->assertNull($c->run(''));
	}

	/**
	 * Vor mehr als einem Tag ausgetauschte Refresh-Tokens verschwinden, unverbrauchte und eben ausgetauschte bleiben.
	 */
	public function testRunDeletesLongExchangedRefreshTokens(): void {
		/** @var RefreshTokenMapper $mapper */
		$mapper = (new Application())->getContainer()->query(RefreshTokenMapper::class);
		$mapper->deleteAll();
		$tokens = [];
		foreach (['old', 'recent', 'current'] as $name) {
			$token = new RefreshToken();
			$token->setToken(\str_pad($name, 64, 'x'));
			$token->setClientId(1);
			$token->setUserId('john');
			$token->setAccessTokenId(1);
			$tokens[$name] = $mapper->insert($token);
		}
		$mapper->markRotated($tokens['old']->getId(), $tokens['recent']->getId(), \time() - CleanUp::EXCHANGED_REFRESH_TOKEN_RETENTION - 10);
		$mapper->markRotated($tokens['recent']->getId(), $tokens['current']->getId(), \time() - 60);

		\OC::$server->query(CleanUp::class)->run('');

		$left = \array_map(static fn (RefreshToken $t) => $t->getToken(), $mapper->findAll());
		\sort($left);
		$this->assertSame([\str_pad('current', 64, 'x'), \str_pad('recent', 64, 'x')], $left);
		$mapper->deleteAll();
	}
}
