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

namespace OCA\OAuth2\Tests\Unit\Controller;

use OC_Util;
use OCA\OAuth2\AppInfo\Application;
use OCA\OAuth2\Controller\PageController;
use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCode;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Template;
use PHPUnit\Framework\MockObject\MockObject;
use Test\TestCase;

/**
 * Class PageControllerTest
 *
 * @package OCA\OAuth2\Tests\Unit\Controller
 * @group DB
 */
class PageControllerTest extends TestCase {
	/** @var PageController $controller */
	private $controller;

	/** @var ClientMapper $clientMapper */
	private $clientMapper;

	/** @var AuthorizationCodeMapper $authorizationCodeMapper */
	private $authorizationCodeMapper;

	/** @var string $identifier */
	private $identifier = 'NXCy3M3a6FM9pecVyUZuGF62AJVJaCfmkYz7us4yr4QZqVzMIkVZUf1v2IzvsFZa';

	/** @var string $secret */
	private $secret = '9yUZuGF6pecVaCfmIzvsFZakYNXCyr4QZqVzMIky3M3a6FMz7us4VZUf2AJVJ1v2';

	/** @var string $redirectUri */
	private $redirectUri = 'https://owncloud.org';

	/** @var string $name */
	private $name = 'ownCloud';

	/** @var Client $client */
	private $client;

	/** @var IRequest | MockObject $request */
	private $request;

	/** @var AccessTokenMapper $accessTokenMapper */
	private $accessTokenMapper;

	public function setUp(): void {
		parent::setUp();

		$app = new Application();
		$container = $app->getContainer();

		$this->clientMapper = $container->query(ClientMapper::class);
		$this->clientMapper->deleteAll();

		$client = new Client();
		$client->setIdentifier($this->identifier);
		$client->setSecret($this->secret);
		$client->setRedirectUri($this->redirectUri);
		$client->setName($this->name);
		$client->setAllowSubdomains(false);
		$this->client = $this->clientMapper->insert($client);

		$this->authorizationCodeMapper = $container->query(AuthorizationCodeMapper::class);
		$this->accessTokenMapper = $container->query(AccessTokenMapper::class);
		/** @var IURLGenerator | MockObject $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var IUserSession | MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		$this->request = $this->createMock(IRequest::class);
		/** @var IUser | MockObject $user */
		$user = $this->createMock(IUser::class);
		/** @var IUserManager | MockObject $userManager */
		$userManager = $this->createMock(IUserManager::class);
		$userSession->method('getUser')->willReturn($user);
		$userManager->method('get')->willReturn($user);
		$user->method('getUID')->willReturn('Alice');

		$this->controller = new PageController(
			$container->query('AppName'),
			$this->request,
			$this->clientMapper,
			$this->authorizationCodeMapper,
			$this->accessTokenMapper,
			$container->query('Logger'),
			$urlGenerator,
			$userSession,
			$userManager
		);
	}

	public function tearDown(): void {
		parent::tearDown();

		$this->clientMapper->delete($this->client);
	}

	public function testAuthorize(): void {
		// Wrong types
		$result = $this->controller->authorize(1, 'qwertz', 'abcd', 'state');
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(
			['client_name' => null],
			$result->getParams()
		);

		$result = $this->controller->authorize('code', 2, 'abcd', 'state');
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(
			['client_name' => null],
			$result->getParams()
		);

		$result = $this->controller->authorize('code', 'qwertz', 3, 'state');
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(
			['client_name' => null],
			$result->getParams()
		);

		$result = $this->controller->authorize('code', $this->identifier, \urldecode($this->redirectUri), 4);
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(
			['client_name' => null],
			$result->getParams()
		);

		// Wrong parameters
		$result = $this->controller->authorize('code', 'qwertz', 'abcd', 'state');
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(
			['client_name' => null],
			$result->getParams()
		);

		$result = $this->controller->authorize('qwertz', $this->identifier, \urldecode($this->redirectUri));
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals('https://owncloud.org?error=unsupported_response_type', $result->getRedirectURL());

		$result = $this->controller->authorize('code', $this->identifier, \urldecode('https://www.example.org'));
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(
			['client_name' => $this->name],
			$result->getParams()
		);

		$result = $this->controller->authorize('code', $this->identifier, \urldecode($this->redirectUri));
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize', $result->getTemplateName());
		self::assertEquals(['client_name' => $this->name, 'logout_url' => null,
			'current_user' => '<strong>Alice</strong>'], $result->getParams());
	}

	public function testGenerateAuthorizationCode(): void {
		// Wrong types
		$result = $this->controller->generateAuthorizationCode(1, 'qwertz', 'abcd', 'state');
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		$result = $this->controller->generateAuthorizationCode('code', 2, 'abcd', 'state');
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		$result = $this->controller->generateAuthorizationCode('code', 'qwertz', 3, 'state');
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		$result = $this->controller->generateAuthorizationCode('code', $this->identifier, \urldecode($this->redirectUri), 4);
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		// Wrong parameters
		$result = $this->controller->generateAuthorizationCode('code', 'qwertz', 'abcd', 'state');
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		$result = $this->controller->generateAuthorizationCode('qwertz', $this->identifier, \urldecode($this->redirectUri));
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		$result = $this->controller->generateAuthorizationCode('code', $this->identifier, \urldecode('https://www.example.org'));
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());

		self::assertCount(0, $this->authorizationCodeMapper->findAll());
		$result = $this->controller->generateAuthorizationCode('code', $this->identifier, \urldecode($this->redirectUri));
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertCount(1, $this->authorizationCodeMapper->findAll());
		[$url, $query] = \explode('?', $result->getRedirectURL());
		self::assertEquals($url, $this->redirectUri);
		\parse_str($query, $parameters);
		self::assertTrue(\array_key_exists('code', $parameters));
		$expected = \time() + AuthorizationCode::EXPIRATION_TIME;
		/** @var AuthorizationCode $authorizationCode */
		$authorizationCode = $this->authorizationCodeMapper->findByCode($parameters['code']);
		self::assertEqualsWithDelta($expected, $authorizationCode->getExpires(), 1);
		self::assertEquals('Alice', $authorizationCode->getUserId());
		self::assertEquals($this->client->getId(), $authorizationCode->getClientId());
		$this->authorizationCodeMapper->delete($this->authorizationCodeMapper->findByCode($parameters['code']));

		self::assertCount(0, $this->authorizationCodeMapper->findAll());
		$result = $this->controller->generateAuthorizationCode('code', $this->identifier, \urldecode($this->redirectUri), 'testingState');
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertCount(1, $this->authorizationCodeMapper->findAll());
		[$url, $query] = \explode('?', $result->getRedirectURL());
		self::assertEquals($url, $this->redirectUri);
		\parse_str($query, $parameters);
		self::assertTrue(\array_key_exists('state', $parameters));
		self::assertEquals('testingState', $parameters['state']);
		self::assertTrue(\array_key_exists('code', $parameters));
		$expected = \time() + 600;
		/** @var AuthorizationCode $authorizationCode */
		$authorizationCode = $this->authorizationCodeMapper->findByCode($parameters['code']);
		self::assertEqualsWithDelta($expected, $authorizationCode->getExpires(), 1);
		self::assertEquals('Alice', $authorizationCode->getUserId());
		self::assertEquals($this->client->getId(), $authorizationCode->getClientId());
		$this->authorizationCodeMapper->delete($this->authorizationCodeMapper->findByCode($parameters['code']));
	}

	public function testAuthorizationSuccessful(): void {
		$result = $this->controller->authorizationSuccessful();
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorization-successful', $result->getTemplateName());
	}

	/**
	 * Legt einen vertrauenswürdigen Client an; tearDown löscht ihn wieder.
	 */
	private function addTrustedClient(string $identifier): void {
		$client = new Client();
		$client->setIdentifier($identifier);
		$client->setSecret($this->secret);
		$client->setRedirectUri($this->redirectUri);
		$client->setName('trusted client for testing');
		$client->setAllowSubdomains(false);
		$client->setTrusted(true);
		$this->client = $this->clientMapper->insert($client);
	}

	/**
	 * Simuliert den Fetch-Metadata-Kopf Sec-Fetch-Site des Browsers (null = fehlt).
	 */
	private function withSecFetchSite(?string $value): void {
		$this->request->method('getHeader')->willReturnCallback(
			static fn (string $name) => \strcasecmp($name, 'Sec-Fetch-Site') === 0 ? $value : null
		);
	}

	/**
	 * Direkt geöffnete Adresse bei bestehender Sitzung (Sec-Fetch-Site: none,
	 * etwa vom Desktop-Client im Browser gestartet): keine Formularnavigation
	 * davor, die HTTP-Weiterleitung bleibt.
	 */
	public function testTrustedClient(): void {
		$identifier = 'trusted-client';
		$this->addTrustedClient($identifier);
		$this->withSecFetchSite('none');

		/** @var RedirectResponse $result */
		$result = $this->controller->authorize('code', $identifier, \urldecode($this->redirectUri));
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertStringStartsWith($this->redirectUri . '?code=', $result->getRedirectURL());
	}

	public static function providesNavigationsThatMayFollowAFormPost(): array {
		return [
			'after login or 2FA form (same origin)' => ['same-origin'],
			'from a same-site page' => ['same-site'],
			'from another site, e.g. IdP POST binding' => ['cross-site'],
			'browser without fetch metadata' => [null],
		];
	}

	/**
	 * Chromium prüft form-action der Seite, deren Formular die Navigation
	 * ausgelöst hat (Anmeldung, 2FA), auch gegen jede Weiterleitung danach.
	 * Die Kette muss deshalb auf einer Seite der eigenen Herkunft enden.
	 *
	 * @dataProvider providesNavigationsThatMayFollowAFormPost
	 */
	public function testTrustedClientRendersRedirectPageWhenNavigationMayFollowAFormPost(?string $secFetchSite): void {
		$identifier = 'trusted-client';
		$this->addTrustedClient($identifier);
		$this->withSecFetchSite($secFetchSite);

		$result = $this->controller->authorize('code', $identifier, \urldecode($this->redirectUri), 'testingState');

		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('redirect', $result->getTemplateName());
		self::assertEquals('guest', $result->getRenderAs());
		$params = $result->getParams();
		self::assertEquals('trusted client for testing', $params['client_name']);
		[$url, $query] = \explode('?', $params['redirect_url'], 2);
		self::assertEquals($this->redirectUri, $url);
		\parse_str($query, $parameters);
		self::assertEquals('testingState', $parameters['state']);
		// Der Code auf der Seite ist der ausgestellte Code dieses Clients und Nutzers
		/** @var AuthorizationCode $authorizationCode */
		$authorizationCode = $this->authorizationCodeMapper->findByCode($parameters['code']);
		self::assertEquals('Alice', $authorizationCode->getUserId());
		self::assertEquals($this->client->getId(), $authorizationCode->getClientId());
		$this->authorizationCodeMapper->delete($authorizationCode);
	}

	public function testTrustedClientImplicitFlowRendersRedirectPageWithToken(): void {
		$identifier = 'trusted-client';
		$this->addTrustedClient($identifier);
		$this->withSecFetchSite('same-origin');

		$result = $this->controller->authorize('token', $identifier, \urldecode($this->redirectUri));

		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('redirect', $result->getTemplateName());
		[, $query] = \explode('?', $result->getParams()['redirect_url'], 2);
		\parse_str($query, $parameters);
		/** @var AccessToken $accessToken */
		$accessToken = $this->accessTokenMapper->findByToken($parameters['access_token']);
		self::assertEquals($this->client->getId(), $accessToken->getClientId());
		$this->accessTokenMapper->delete($accessToken);
	}

	public function testRedirectTemplateEscapesTargetAndOffersVisibleLink(): void {
		$template = new Template('oauth2', 'redirect', '');
		$template->assign('client_name', 'Evil "<b>client</b>');
		$template->assign('redirect_url', 'http://localhost:43124?code=a&state="><script>x</script>');

		$html = $template->fetchPage();

		self::assertStringContainsString('id="oauth2-redirect-link"', $html);
		self::assertStringContainsString(
			'href="http://localhost:43124?code=a&amp;state=&quot;&gt;&lt;script&gt;x&lt;/script&gt;"',
			$html
		);
		self::assertStringNotContainsString('<script>x', $html);
		self::assertStringNotContainsString('<b>client</b>', $html);
	}
}
