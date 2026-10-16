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

namespace OCA\OAuth2\Tests\Unit\AppInfo;

use OC\AppFramework\DependencyInjection\DIContainer;
use OCA\OAuth2\AppInfo\Application;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\IRequest;
use Test\TestCase;

/**
 * Auf der Anmeldeseite einer Autorisierung legt die App das Kopfelement
 * <data key="oauth2" …> für js/login.js ab. Ohne Text geben Kerne ohne
 * OC_Template::renderHeaders() es als <data …/> aus; der HTML-Parser lässt
 * <data> offen, es umschließt die Anmeldekarte und schiebt sie nach links
 * oben.
 *
 * @group DB
 */
class ApplicationTest extends TestCase {
	private array $scripts;
	private array $styles;
	private array $headers;
	private DIContainer $appContainer;

	protected function setUp(): void {
		parent::setUp();
		$this->scripts = \OC_Util::$scripts;
		$this->styles = \OC_Util::$styles;
		$this->headers = \OC_Util::$headers;
		\OC_Util::$headers = [];
		$this->appContainer = \OC::$server->getAppContainer('oauth2');
	}

	protected function tearDown(): void {
		\OC_Util::$scripts = $this->scripts;
		\OC_Util::$styles = $this->styles;
		\OC_Util::$headers = $this->headers;
		// new Application() meldet seinen Container beim Server an; den alten
		// zurücklegen, damit der Mapper-Mock in keinem anderen Test auftaucht
		\OC::$server->registerAppContainer('oauth2', $this->appContainer);
		$this->restoreService('Request');
		parent::tearDown();
	}

	public function testDataHeaderIsRenderedWithEndTagOnEveryCore(): void {
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('GET');
		$request->method('getParam')->willReturnCallback(
			static fn ($key) => $key === 'redirect_url'
				? '/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop&redirect_uri=http%3A%2F%2Flocalhost%3A57197'
				: null
		);
		$this->overwriteService('Request', $request);

		$client = new Client();
		$client->setName('Desktop-Client');
		$mapper = $this->createMock(ClientMapper::class);
		$mapper->method('findByIdentifier')->with('desktop')->willReturn($client);
		$app = new Application();
		// \OC::$server fragt bei OCA\…-Klassen zuerst den Container der App
		$app->getContainer()->registerService(ClientMapper::class, static fn () => $mapper);

		$app->boot();

		$headers = \array_values(\array_filter(
			\OC_Util::$headers,
			static fn (array $header) => $header['tag'] === 'data'
		));
		$this->assertCount(1, $headers);
		$this->assertSame(['key' => 'oauth2', 'client' => 'Desktop-Client'], $headers[0]['attributes']);
		// null ergäbe auf alten Kernen <data …/>, '' ergibt auf jedem <data …></data>
		$this->assertSame('', $headers[0]['text']);
	}
}
