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
		/** @var AccessTokenMapper $accessTokenMapper */
		$accessTokenMapper = $container->query(AccessTokenMapper::class);
		/** @var IURLGenerator | MockObject $urlGenerator */
		$urlGenerator = $this->createMock(IURLGenerator::class);
		/** @var IUserSession | MockObject $userSession */
		$userSession = $this->createMock(IUserSession::class);
		/** @var IRequest | MockObject $request */
		$request = $this->createMock(IRequest::class);
		/** @var IUser | MockObject $user */
		$user = $this->createMock(IUser::class);
		/** @var IUserManager | MockObject $userManager */
		$userManager = $this->createMock(IUserManager::class);
		$userSession->method('getUser')->willReturn($user);
		$userManager->method('get')->willReturn($user);
		$user->method('getUID')->willReturn('Alice');

		$this->controller = new PageController(
			$container->query('AppName'),
			$request,
			$this->clientMapper,
			$this->authorizationCodeMapper,
			$accessTokenMapper,
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

	public function testTrustedClient(): void {
		$identifier = 'trusted-client';
		// add trusted client
		$client = new Client();
		$client->setIdentifier($identifier);
		$client->setSecret($this->secret);
		$client->setRedirectUri($this->redirectUri);
		$client->setName('trusted client for testing');
		$client->setAllowSubdomains(false);
		$client->setTrusted(true);
		$this->client = $this->clientMapper->insert($client);

		/** @var RedirectResponse $result */
		$result = $this->controller->authorize('code', $identifier, \urldecode($this->redirectUri));
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertStringStartsWith($this->redirectUri . '?code=', $result->getRedirectURL());
	}

	/**
	 * Der Desktop-Client, wie ihn Version20170329194544 einträgt.
	 */
	private function addDesktopClient(): Client {
		$client = new Client();
		$client->setIdentifier('xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69');
		$client->setSecret('UBntmLjC2yYCeHwsyj73Uwo9TAaecAetRwMw0xYcvNL9yRdLSUi0hUAHfvCHFeFh');
		$client->setRedirectUri('http://localhost:*');
		$client->setName('Desktop Client');
		$client->setAllowSubdomains(false);
		return $this->clientMapper->insert($client);
	}

	/**
	 * RFC 8252 §7.3: Rückleitung auf die Loopback-IP mit beliebigem Port,
	 * localhost wie bisher.
	 */
	public function providesLoopbackRedirectsOfTheDesktopClient(): array {
		return [
			'127.0.0.1' => ['http://127.0.0.1:49152'],
			'127.0.0.1 mit Schrägstrich' => ['http://127.0.0.1:50123/'],
			'[::1]' => ['http://[::1]:49152'],
			'localhost wie bisher' => ['http://localhost:49152'],
		];
	}

	public function providesRejectedRedirectsOfTheDesktopClient(): array {
		return [
			'anderer Pfad' => ['http://127.0.0.1:49152/evil'],
			'https' => ['https://127.0.0.1:49152'],
			'Benutzer-Info' => ['http://user@127.0.0.1:49152'],
			'fremder Host hinter Benutzer-Info' => ['http://127.0.0.1:49152@evil.com/'],
			'fremder Host' => ['http://evil.com:49152'],
		];
	}

	/**
	 * @dataProvider providesLoopbackRedirectsOfTheDesktopClient
	 */
	public function testAuthorizeAsksForConsentOnLoopbackRedirectOfTheDesktopClient(string $redirectUri): void {
		$desktop = $this->addDesktopClient();

		$result = $this->controller->authorize('code', $desktop->getIdentifier(), $redirectUri, 'state');

		$this->clientMapper->delete($desktop);
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize', $result->getTemplateName());
	}

	/**
	 * @dataProvider providesRejectedRedirectsOfTheDesktopClient
	 */
	public function testAuthorizeRejectsOtherRedirectsOfTheDesktopClient(string $redirectUri): void {
		$desktop = $this->addDesktopClient();

		$result = $this->controller->authorize('code', $desktop->getIdentifier(), $redirectUri, 'state');

		$this->clientMapper->delete($desktop);
		self::assertInstanceOf(TemplateResponse::class, $result);
		self::assertEquals('authorize-error', $result->getTemplateName());
		self::assertEquals(['client_name' => 'Desktop Client'], $result->getParams());
	}

	/**
	 * Der Code merkt sich die redirect_uri, damit der Token-Endpunkt dieselbe
	 * verlangen kann (RFC 6749 §4.1.3).
	 *
	 * @dataProvider providesLoopbackRedirectsOfTheDesktopClient
	 */
	public function testGenerateAuthorizationCodeRedirectsToTheLoopbackAndRemembersTheUri(string $redirectUri): void {
		$desktop = $this->addDesktopClient();

		$result = $this->controller->generateAuthorizationCode('code', $desktop->getIdentifier(), $redirectUri, 'state');

		self::assertInstanceOf(RedirectResponse::class, $result);
		[$url, $query] = \explode('?', $result->getRedirectURL(), 2);
		self::assertSame($redirectUri, $url);
		\parse_str($query, $parameters);
		self::assertSame('state', $parameters['state']);
		/** @var AuthorizationCode $authorizationCode */
		$authorizationCode = $this->authorizationCodeMapper->findByCode($parameters['code']);
		$this->authorizationCodeMapper->deleteByClient($desktop->getId());
		$this->clientMapper->delete($desktop);
		self::assertSame($redirectUri, $authorizationCode->getRedirectUri());
	}

	/**
	 * @dataProvider providesRejectedRedirectsOfTheDesktopClient
	 */
	public function testGenerateAuthorizationCodeIssuesNoCodeForOtherRedirectsOfTheDesktopClient(string $redirectUri): void {
		$desktop = $this->addDesktopClient();
		$before = \count($this->authorizationCodeMapper->findAll());

		$result = $this->controller->generateAuthorizationCode('code', $desktop->getIdentifier(), $redirectUri, 'state');

		$after = \count($this->authorizationCodeMapper->findAll());
		$this->authorizationCodeMapper->deleteByClient($desktop->getId());
		$this->clientMapper->delete($desktop);
		self::assertInstanceOf(RedirectResponse::class, $result);
		self::assertEquals(OC_Util::getDefaultPageUrl(), $result->getRedirectURL());
		self::assertSame($before, $after);
	}
}
