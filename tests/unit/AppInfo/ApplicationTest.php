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
use OCA\OAuth2\Hooks\UserHooks;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IUserManager;
use Test\TestCase;

/**
 * Auf der Anmeldeseite einer Autorisierung legt die App das Kopfelement
 * <data key="oauth2" …> für js/login.js ab. Ohne Text geben Kerne ohne
 * OC_Template::renderHeaders() es als <data …/> aus; der HTML-Parser lässt
 * <data> offen, es umschließt die Anmeldekarte und schiebt sie nach links
 * oben. Eingaben aus der Adresse, die keine Zeichenketten oder kein gültiges
 * UTF-8 sind, und Fehler der Abfragen von Client und Konto dürfen die
 * Anmeldeseite nicht mit HTTP 500 abbrechen.
 *
 * @group DB
 */
class ApplicationTest extends TestCase {
	private const AUTH_INIT_EVENT = 'OCA\DAV\Connector\Sabre::authInit';

	private array $scripts;
	private array $styles;
	private array $headers;
	private DIContainer $appContainer;
	private array $authInitListeners;

	protected function setUp(): void {
		parent::setUp();
		$this->scripts = \OC_Util::$scripts;
		$this->styles = \OC_Util::$styles;
		$this->headers = \OC_Util::$headers;
		\OC_Util::$headers = [];
		$this->appContainer = \OC::$server->getAppContainer('oauth2');
		$this->authInitListeners = \OC::$server->getEventDispatcher()->getListeners(self::AUTH_INIT_EVENT);
	}

	protected function tearDown(): void {
		\OC_Util::$scripts = $this->scripts;
		\OC_Util::$styles = $this->styles;
		\OC_Util::$headers = $this->headers;
		// new Application() meldet seinen Container beim Server an; den alten
		// zurücklegen, damit der Mapper-Mock in keinem anderen Test auftaucht
		\OC::$server->registerAppContainer('oauth2', $this->appContainer);
		// Der Konstruktor hängt zusätzlich einen authInit-Listener an den
		// globalen Dispatcher; nur die im Test hinzugekommenen wieder abhängen
		$dispatcher = \OC::$server->getEventDispatcher();
		foreach ($dispatcher->getListeners(self::AUTH_INIT_EVENT) as $listener) {
			if (!\in_array($listener, $this->authInitListeners, true)) {
				$dispatcher->removeListener(self::AUTH_INIT_EVENT, $listener);
			}
		}
		$this->restoreService('Request');
		$this->restoreService('UserManager');
		parent::tearDown();
	}

	public function testDataHeaderIsRenderedWithEndTagOnEveryCore(): void {
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop&redirect_uri=http%3A%2F%2Flocalhost%3A57197',
			$this->createMapperFor('desktop')
		);

		$app->boot();

		$headers = $this->dataHeaders();
		$this->assertCount(1, $headers);
		$this->assertSame(['key' => 'oauth2', 'client' => 'Desktop-Client'], $headers[0]['attributes']);
		// null ergäbe auf alten Kernen <data …/>, '' ergibt auf jedem <data …></data>
		$this->assertSame('', $headers[0]['text']);
	}

	public function testRedirectUrlAsArrayIsIgnored(): void {
		$mapper = $this->createMock(ClientMapper::class);
		$mapper->expects($this->never())->method('findByIdentifier');
		// redirect_url[]=… – urldecode() bräche mit einem TypeError ab
		$app = $this->createApp(
			['/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop'],
			$mapper
		);

		$app->boot();

		$this->assertSame([], $this->dataHeaders());
	}

	public function testClientIdAsArrayIsIgnored(): void {
		$mapper = $this->createMock(ClientMapper::class);
		// findByIdentifier() wiese ein Array mit einer InvalidArgumentException ab
		$mapper->expects($this->never())->method('findByIdentifier');
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id%5B%5D=desktop&redirect_uri=http%3A%2F%2Flocalhost%3A57197',
			$mapper
		);

		$app->boot();

		$this->assertSame([], $this->dataHeaders());
	}

	public function testUserAsArrayGivesNoLoginHint(): void {
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop&user%5B%5D=anna',
			$this->createMapperFor('desktop')
		);

		$app->boot();

		$headers = $this->dataHeaders();
		$this->assertCount(1, $headers);
		$this->assertSame(['key' => 'oauth2', 'client' => 'Desktop-Client'], $headers[0]['attributes']);
	}

	public function testUnknownUserIsPassedOnAsLoginHint(): void {
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop&user=niemand-' . $this->getUniqueID(),
			$this->createMapperFor('desktop')
		);

		$app->boot();

		$headers = $this->dataHeaders();
		$this->assertCount(1, $headers);
		$this->assertStringStartsWith('niemand-', $headers[0]['attributes']['login_hint'] ?? '');
	}

	public function testClientIdWithInvalidUtf8NeverReachesTheDatabase(): void {
		$mapper = $this->createMock(ClientMapper::class);
		// PostgreSQL wiese das Byte 0xFF mit SQLSTATE 22021 ab – HTTP 500
		$mapper->expects($this->never())->method('findByIdentifier');
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=%FF&redirect_uri=http%3A%2F%2Flocalhost%3A57197',
			$mapper
		);

		$app->boot();

		$this->assertSame([], $this->dataHeaders());
	}

	public function testUserWithInvalidUtf8NeverReachesTheDatabase(): void {
		$userManager = $this->createMock(IUserManager::class);
		// ebenso das Konto: kein Abfragen, kein Anmeldehinweis aus kaputten Bytes
		$userManager->expects($this->never())->method('get');
		$this->overwriteService('UserManager', $userManager);
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop&user=%FF',
			$this->createMapperFor('desktop')
		);

		$app->boot();

		$headers = $this->dataHeaders();
		$this->assertCount(1, $headers);
		$this->assertSame(['key' => 'oauth2', 'client' => 'Desktop-Client'], $headers[0]['attributes']);
	}

	public function testFailingClientLookupIsLoggedInsteadOfAbortingTheLoginPage(): void {
		// So meldet PostgreSQL ungültige Bytes; jeder andere Datenbankfehler ebenso
		$failure = new \RuntimeException('SQLSTATE[22021]: Character not in repertoire');
		$mapper = $this->createMock(ClientMapper::class);
		$mapper->method('findByIdentifier')->willThrowException($failure);
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop',
			$mapper
		);
		$this->expectLoggedFailure($app, $failure);

		$app->boot();

		$this->assertSame([], $this->dataHeaders());
	}

	public function testFailingUserLookupIsLoggedAndTheGivenUserStaysTheHint(): void {
		$failure = new \RuntimeException('SQLSTATE[08006]: Connection failure');
		$userManager = $this->createMock(IUserManager::class);
		$userManager->method('get')->willThrowException($failure);
		$this->overwriteService('UserManager', $userManager);
		$app = $this->createApp(
			'/index.php/apps/oauth2/authorize?response_type=code&client_id=desktop&user=anna',
			$this->createMapperFor('desktop')
		);
		$this->expectLoggedFailure($app, $failure);

		$app->boot();

		$headers = $this->dataHeaders();
		$this->assertCount(1, $headers);
		// wie bei einem unbekannten Konto bleibt der übergebene Name der Hinweis
		$this->assertSame('anna', $headers[0]['attributes']['login_hint'] ?? null);
	}

	/**
	 * Baut die App für eine GET-Anfrage mit der gegebenen redirect_url. Statt
	 * der echten UserHooks steckt ein Mock im Container: register() hängt
	 * sonst einen preDelete-Listener an den globalen UserManager, der den Test
	 * überdauert und in späteren Tests beim Löschen von Konten mitläuft.
	 */
	private function createApp(string|array $redirectUrl, ClientMapper $mapper): Application {
		$request = $this->createMock(IRequest::class);
		$request->method('getMethod')->willReturn('GET');
		$request->method('getParam')->willReturnCallback(
			static fn ($key) => $key === 'redirect_url' ? $redirectUrl : null
		);
		$this->overwriteService('Request', $request);

		$app = new Application();
		$container = $app->getContainer();
		// \OC::$server fragt bei OCA\…-Klassen zuerst den Container der App
		$container->registerService(ClientMapper::class, static fn () => $mapper);
		$userHooks = $this->createMock(UserHooks::class);
		$userHooks->expects($this->once())->method('register');
		$container->registerService('UserHooks', static fn () => $userHooks);
		return $app;
	}

	/**
	 * Erwartet genau einen Log-Eintrag der App mit der gescheiterten Abfrage;
	 * gefangen werden darf sie, verschwiegen nicht.
	 */
	private function expectLoggedFailure(Application $app, \Throwable $failure): void {
		$logger = $this->createMock(ILogger::class);
		$logger->expects($this->once())->method('logException')->with(
			$failure,
			$this->callback(static fn (array $context) => ($context['app'] ?? null) === 'oauth2')
		);
		$app->getContainer()->registerService('Logger', static fn () => $logger);
	}

	private function createMapperFor(string $identifier): ClientMapper {
		$client = new Client();
		$client->setName('Desktop-Client');
		$mapper = $this->createMock(ClientMapper::class);
		$mapper->expects($this->once())->method('findByIdentifier')->with($identifier)->willReturn($client);
		return $mapper;
	}

	private function dataHeaders(): array {
		return \array_values(\array_filter(
			\OC_Util::$headers,
			static fn (array $header) => $header['tag'] === 'data'
		));
	}
}
