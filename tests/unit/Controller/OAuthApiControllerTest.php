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

use OCA\OAuth2\AppInfo\Application;
use OCA\OAuth2\Controller\OAuthApiController;
use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCode;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshToken;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCA\OAuth2\Utilities;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use OCP\IUser;
use PHPUnit\Framework\TestCase;

class OAuthApiControllerTest extends TestCase {
	/** @var OAuthApiController $controller */
	private $controller;

	/** @var IRequest | \PHPUnit\Framework\MockObject\MockObject */
	private $request;

	/** @var ClientMapper $clientMapper */
	private $clientMapper;

	/** @var AuthorizationCodeMapper $authorizationCodeMapper */
	private $authorizationCodeMapper;

	/** @var AccessTokenMapper */
	private $accessTokenMapper;

	/** @var RefreshTokenMapper */
	private $refreshTokenMapper;

	/** @var IUserManager */
	private $userManager;

	/** @var string $userId */
	private $userId = 'john';

	/** @var string $clientIdentifier1 */
	private $clientIdentifier1 = 'NXCy3M3a6FM9pecVyUZuGF62AJVJaCfmkYz7us4yr4QZqVzMIkVZUf1v2IzvsFZa';

	/** @var string $clientIdentifier2 */
	private $clientIdentifier2 = 'JaCfmkYz7MIkXCy3M3a6FM9pecVyUZVZUf1v2IzvsFZaNuGF62AJVus4yr4QZqVz';

	/** @var string $clientSecret */
	private $clientSecret = '9yUZuGF6pecVaCfmIzvsFZakYNXCyr4QZqVzMIky3M3a6FMz7us4VZUf2AJVJ1v2';

	/** @var string $redirectUri */
	private $redirectUri = 'https://owncloud.org';

	/** @var Client $client1 */
	private $client1;

	/** @var Client $client2 */
	private $client2;

	/** @var AuthorizationCode $authorizationCode */
	private $authorizationCode;

	/** @var AccessToken $accessToken */
	private $accessToken;

	/** @var RefreshToken $refreshToken */
	private $refreshToken;

	/** @var String $refreshToken */
	private $authorizationSuccessfulMessageUrl;

	/** @var Client|null Desktop-Client aus addDesktopClientWithCode() */
	private $desktopClient;

	public function setUp(): void {
		parent::setUp();

		$app = new Application();
		$container = $app->getContainer();

		$this->clientMapper = $container->query('OCA\OAuth2\Db\ClientMapper');
		$this->clientMapper->deleteAll();
		$this->authorizationCodeMapper = $container->query('OCA\OAuth2\Db\AuthorizationCodeMapper');
		$this->authorizationCodeMapper->deleteAll();
		$this->accessTokenMapper = $container->query('OCA\OAuth2\Db\AccessTokenMapper');
		$this->accessTokenMapper->deleteAll();
		$this->refreshTokenMapper = $container->query('OCA\OAuth2\Db\RefreshTokenMapper');
		$this->refreshTokenMapper->deleteAll();

		/** @var Client $client */
		$client = new Client();
		$client->setIdentifier($this->clientIdentifier1);
		$client->setSecret($this->clientSecret);
		$client->setRedirectUri($this->redirectUri);
		$client->setName('ownCloud');
		$this->client1 = $this->clientMapper->insert($client);

		$client = new Client();
		$client->setIdentifier($this->clientIdentifier2);
		$client->setSecret($this->clientSecret);
		$client->setRedirectUri('https://www.google.de');
		$client->setName('Google');
		$this->client2 = $this->clientMapper->insert($client);

		/** @var AuthorizationCode $authorizationCode */
		$authorizationCode = new AuthorizationCode();
		$authorizationCode->setCode('kYz7us4yr4QZyUZuMIkVZUf1v2IzvsFZaNXCy3M3amqVGF62AJVJaCfz6FM9pecV');
		$authorizationCode->setClientId($this->client1->getId());
		$authorizationCode->setUserId($this->userId);
		$authorizationCode->resetExpires();
		$this->authorizationCode = $this->authorizationCodeMapper->insert($authorizationCode);

		/** @var AccessToken $accessToken */
		$accessToken = new AccessToken();
		$accessToken->setToken('sFz6FM9pecGF62kYz7us43M3amqVZaNQZyUZuMIkAJVJaCfVyr4Uf1v2IzvVZXCy');
		$accessToken->setClientId($this->client1->getId());
		$accessToken->setUserId($this->userId);
		$accessToken->resetExpires();
		$this->accessToken = $this->accessTokenMapper->insert($accessToken);

		/** @var RefreshToken $refreshToken */
		$refreshToken = new RefreshToken();
		$refreshToken->setToken('GF62kYz7us4yr4Uf1v2IzvsFZaNQZyUZuMIkAJVJaCfz6FM9pecVZXCy3M3amqVV');
		$refreshToken->setClientId($this->client1->getId());
		$refreshToken->setUserId($this->userId);
		$refreshToken->setAccessTokenId($accessToken->getId());
		$this->refreshToken = $this->refreshTokenMapper->insert($refreshToken);

		/** @var IURLGenerator $urlGenerator */
		$urlGenerator = $container->query('ServerContainer')->getURLGenerator();
		$this->authorizationSuccessfulMessageUrl = $urlGenerator->linkToRouteAbsolute(
			$container->query('AppName') . '.page.authorizationSuccessful'
		);

		$this->request = $this->createMock(IRequest::class);
		$this->userManager = $this->createMock(IUserManager::class);

		$this->controller = $this->createController($this->refreshTokenMapper);
	}

	/**
	 * Controller mit den Mappern des Tests; ein eigener RefreshTokenMapper
	 * spielt gleichzeitige Anfragen nach.
	 */
	private function createController(RefreshTokenMapper $refreshTokenMapper): OAuthApiController {
		$container = (new Application())->getContainer();
		return new OAuthApiController(
			$container->query('AppName'),
			$this->request,
			$this->clientMapper,
			$this->authorizationCodeMapper,
			$this->accessTokenMapper,
			$refreshTokenMapper,
			$this->userManager,
			$container->query('ServerContainer')->getURLGenerator(),
			$container->query('Logger'),
			\OC::$server->getDatabaseConnection(),
			\OC::$server->getConfig()
		);
	}

	public function tearDown(): void {
		parent::tearDown();

		$this->clientMapper->delete($this->client1);
		$this->clientMapper->delete($this->client2);
		$this->authorizationCodeMapper->delete($this->authorizationCode);
		$this->refreshTokenMapper->delete($this->refreshToken);
		if ($this->desktopClient !== null) {
			$this->authorizationCodeMapper->deleteByClient($this->desktopClient->getId());
			$this->clientMapper->delete($this->desktopClient);
			$this->desktopClient = null;
		}
		// Tokens nicht liegen lassen: andere Tests legen dieselben Zeichenketten an (eindeutiger Index auf access_tokens.token)
		$this->accessTokenMapper->deleteAll();
		$this->refreshTokenMapper->deleteAll();
		$config = \OC::$server->getConfig();
		$config->deleteAppValue('oauth2', 'access_token_lifetime');
		$config->deleteAppValue('oauth2', 'refresh_token_grace_period');
		unset($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
	}

	/**
	 * Ein gültiger Austausch: Client-Kennung und Geheimnis von client1, Konto aktiv.
	 */
	private function prepareRefresh(): void {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;
		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);
	}

	private function refresh(string $refreshToken, ?OAuthApiController $controller = null): \stdClass {
		$result = ($controller ?? $this->controller)->generateToken('refresh_token', null, null, $refreshToken);
		$this->assertInstanceOf(JSONResponse::class, $result);
		$json = \json_decode($result->render());
		$json->status = $result->getStatus();
		return $json;
	}

	private function setRotatedAt(string $refreshToken, int $rotatedAt): void {
		\OC::$server->getDatabaseConnection()->executeUpdate(
			'UPDATE `*PREFIX*oauth2_refresh_tokens` SET `rotated_at` = ? WHERE `token` = ?',
			[$rotatedAt, $refreshToken]
		);
	}

	public function testGenerateTokenWithUnknownGrantType() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('unknown');
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());
	}

	public function testGenerateTokenWithAuthorizationCode() {
		$_SERVER['PHP_AUTH_USER'] = null;
		$_SERVER['PHP_AUTH_PW'] = null;

		$result = $this->controller->generateToken(null, $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = 'test';
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_client', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = 'test';

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_client', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('authorization_code', null);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier2;

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;

		$result = $this->controller->generateToken('authorization_code', 'test', $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), 'http://www.example.org');
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$this->authorizationCode->setExpires(\time() - 1);
		$this->authorizationCodeMapper->update($this->authorizationCode);
		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		$this->authorizationCode->resetExpires();
		$this->authorizationCodeMapper->update($this->authorizationCode);
		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->access_token);
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertNotEmpty($json->token_type);
		$this->assertEquals('Bearer', $json->token_type);
		$this->assertNotEmpty($json->expires_in);
		$this->assertEquals(AccessToken::EXPIRATION_TIME, $json->expires_in);
		$this->assertNotEmpty($json->refresh_token);
		$this->assertEquals(64, \strlen($json->refresh_token));
		$this->assertNotEmpty($json->user_id);
		$this->assertEquals($this->userId, $json->user_id);
		$this->assertNotEmpty($json->message_url);
		$this->assertEquals($this->authorizationSuccessfulMessageUrl, $json->message_url);
		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals(0, \count($this->authorizationCodeMapper->findAll()));
		$this->assertEquals(2, \count($this->accessTokenMapper->findAll()));
		$this->assertEquals(2, \count($this->refreshTokenMapper->findAll()));
	}

	public function testGenerateTokenWithRefreshToken() {
		$_SERVER['PHP_AUTH_USER'] = null;
		$_SERVER['PHP_AUTH_PW'] = null;

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = 'test';
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_client', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = 'test';

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_client', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('refresh_token', null, null, null);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier2;

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;

		$result = $this->controller->generateToken('refresh_token', null, null, 'test');
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->access_token);
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertNotEmpty($json->token_type);
		$this->assertEquals('Bearer', $json->token_type);
		$this->assertNotEmpty($json->expires_in);
		$this->assertEquals(AccessToken::EXPIRATION_TIME, $json->expires_in);
		$this->assertNotEmpty($json->refresh_token);
		$this->assertEquals(64, \strlen($json->refresh_token));
		$this->assertNotEmpty($json->user_id);
		$this->assertEquals($this->userId, $json->user_id);
		$this->assertNotEmpty($json->message_url);
		$this->assertEquals($this->authorizationSuccessfulMessageUrl, $json->message_url);
		$this->assertEquals(200, $result->getStatus());
		// das alte Paar bleibt für die Gnadenfrist stehen: das Refresh-Token als verbraucht, das Access-Token mit kurzem Ablauf
		$this->assertEquals(2, \count($this->accessTokenMapper->findAll()));
		$this->assertEquals(2, \count($this->refreshTokenMapper->findAll()));
		/** @var RefreshToken $old */
		$old = $this->refreshTokenMapper->find($this->refreshToken->getId());
		$this->assertNotNull($old->getRotatedAt());
		/** @var RefreshToken $successor */
		$successor = $this->refreshTokenMapper->findByToken($json->refresh_token);
		$this->assertSame($successor->getId(), $old->getSuccessorId());
		$this->assertNull($successor->getRotatedAt());
		/** @var AccessToken $oldAccessToken */
		$oldAccessToken = $this->accessTokenMapper->find($this->accessToken->getId());
		$this->assertLessThanOrEqual(\time() + OAuthApiController::REFRESH_TOKEN_GRACE_PERIOD, $oldAccessToken->getExpires());
		$this->assertFalse($oldAccessToken->hasExpired());
	}

	/**
	 * Die Antwort auf einen Austausch ging verloren, der Client wiederholt ihn mit dem alten Refresh-Token: Innerhalb der
	 * Gnadenfrist bekommt er dasselbe Paar, nicht invalid_grant. Bisher musste er sich neu anmelden.
	 */
	public function testRefreshRepeatedWithinTheGracePeriodGetsTheSameTokens(): void {
		$this->prepareRefresh();
		$first = $this->refresh($this->refreshToken->getToken());
		$this->assertSame(200, $first->status);

		$second = $this->refresh($this->refreshToken->getToken());

		$this->assertSame(200, $second->status);
		$this->assertSame($first->access_token, $second->access_token);
		$this->assertSame($first->refresh_token, $second->refresh_token);
		$this->assertSame('Bearer', $second->token_type);
		$this->assertGreaterThan(AccessToken::EXPIRATION_TIME - 10, $second->expires_in);
		$this->assertLessThanOrEqual(AccessToken::EXPIRATION_TIME, $second->expires_in);
		// kein drittes Paar
		$this->assertCount(2, $this->accessTokenMapper->findAll());
		$this->assertCount(2, $this->refreshTokenMapper->findAll());

		// das neue Refresh-Token tauscht ganz normal weiter
		$third = $this->refresh($first->refresh_token);
		$this->assertSame(200, $third->status);
		$this->assertNotSame($first->refresh_token, $third->refresh_token);
	}

	public function testRefreshRepeatedAfterTheGracePeriodIsRejected(): void {
		$this->prepareRefresh();
		$first = $this->refresh($this->refreshToken->getToken());
		$this->assertSame(200, $first->status);
		$this->setRotatedAt($this->refreshToken->getToken(), \time() - OAuthApiController::REFRESH_TOKEN_GRACE_PERIOD - 1);

		$second = $this->refresh($this->refreshToken->getToken());

		$this->assertSame(400, $second->status);
		$this->assertSame('invalid_grant', $second->error);
		// das neue Paar gilt weiter
		$this->assertSame(200, $this->refresh($first->refresh_token)->status);
	}

	/**
	 * Hat der Client das neue Refresh-Token schon benutzt, hat er die Antwort erhalten: das alte bleibt abgelehnt, und es
	 * verschwindet beim nächsten Austausch.
	 */
	public function testRefreshRepeatedAfterTheSuccessorWasUsedIsRejected(): void {
		$this->prepareRefresh();
		$first = $this->refresh($this->refreshToken->getToken());
		$second = $this->refresh($first->refresh_token);
		$this->assertSame(200, $second->status);

		$replay = $this->refresh($this->refreshToken->getToken());

		$this->assertSame(400, $replay->status);
		$this->assertSame('invalid_grant', $replay->error);
		// das zweite Refresh-Token liefert innerhalb der Frist weiter das Paar seines Austauschs, denn dessen Nachfolger ist
		// noch unbenutzt
		$this->assertSame($second->refresh_token, $this->refresh($first->refresh_token)->refresh_token);
		// beim zweiten Austausch fiel das erste Refresh-Token weg
		$tokens = \array_map(static fn (RefreshToken $t) => $t->getToken(), $this->refreshTokenMapper->findAll());
		$this->assertNotContains($this->refreshToken->getToken(), $tokens);
		$this->assertContains($first->refresh_token, $tokens);
		$this->assertContains($second->refresh_token, $tokens);
	}

	public function testGracePeriodCanBeTurnedOff(): void {
		\OC::$server->getConfig()->setAppValue('oauth2', 'refresh_token_grace_period', '0');
		$this->prepareRefresh();
		$first = $this->refresh($this->refreshToken->getToken());
		$this->assertSame(200, $first->status);

		$replay = $this->refresh($this->refreshToken->getToken());

		$this->assertSame(400, $replay->status);
		$this->assertSame('invalid_grant', $replay->error);
		/** @var AccessToken $oldAccessToken */
		$oldAccessToken = $this->accessTokenMapper->find($this->accessToken->getId());
		$this->assertTrue($oldAccessToken->hasExpired());
	}

	/**
	 * Zwei gleichzeitige Austauschanfragen mit demselben Refresh-Token: Die zweite las das Token, bevor die erste es als
	 * verbraucht vermerkte. Sie tauscht nicht noch einmal aus, sondern bekommt das Paar der ersten; kein zweiter
	 * Nachfolger, kein invalid_grant. Bisher ergaben solche Anfragen mehrere gültige Paare und/oder invalid_grant.
	 */
	public function testConcurrentRefreshesYieldOnePair(): void {
		$this->prepareRefresh();
		$db = (new Application())->getContainer()->query(\OCP\IDb::class);
		$racingMapper = new class($db) extends RefreshTokenMapper {
			public ?\Closure $afterLookup = null;

			public function findByToken($token): \OCP\AppFramework\Db\Entity {
				$entity = parent::findByToken($token);
				if ($this->afterLookup !== null) {
					$afterLookup = $this->afterLookup;
					$this->afterLookup = null;
					$afterLookup();
				}
				return $entity;
			}
		};
		$winner = null;
		$racingMapper->afterLookup = function () use (&$winner) {
			// die andere Anfrage tauscht aus, während diese das Token schon gelesen hat
			$winner = $this->refresh($this->refreshToken->getToken());
		};

		$loser = $this->refresh($this->refreshToken->getToken(), $this->createController($racingMapper));

		$this->assertNotNull($winner);
		$this->assertSame(200, $winner->status);
		$this->assertSame(200, $loser->status);
		$this->assertSame($winner->access_token, $loser->access_token);
		$this->assertSame($winner->refresh_token, $loser->refresh_token);
		// genau ein Nachfolger, die zurückgerollte Anfrage hinterlässt nichts
		$this->assertCount(2, $this->accessTokenMapper->findAll());
		$this->assertCount(2, $this->refreshTokenMapper->findAll());
	}

	public function testAccessTokenLifetimeIsConfigurable(): void {
		$config = \OC::$server->getConfig();
		$config->setAppValue('oauth2', 'access_token_lifetime', '30');
		$this->prepareRefresh();

		$json = $this->refresh($this->refreshToken->getToken());

		$this->assertSame(200, $json->status);
		$this->assertSame(30, $json->expires_in);
		/** @var AccessToken $accessToken */
		$accessToken = $this->accessTokenMapper->findByToken($json->access_token);
		$this->assertLessThanOrEqual(\time() + 30, $accessToken->getExpires());
		$this->assertGreaterThanOrEqual(\time() + 29, $accessToken->getExpires());

		// Grenzen: mindestens 10 s, höchstens ein Tag; Unsinn ergibt den Standard
		$config->setAppValue('oauth2', 'access_token_lifetime', '1');
		$json = $this->refresh($json->refresh_token);
		$this->assertSame(10, $json->expires_in);
		$config->setAppValue('oauth2', 'access_token_lifetime', '9999999');
		$json = $this->refresh($json->refresh_token);
		$this->assertSame(86400, $json->expires_in);
		$config->setAppValue('oauth2', 'access_token_lifetime', 'eine Stunde');
		$json = $this->refresh($json->refresh_token);
		$this->assertSame(AccessToken::EXPIRATION_TIME, $json->expires_in);
	}

	public function testGenerateTokenWithRefreshTokenClient242() {
		$this->request
			->method('getHeader')
			->with('User-Agent')
			->willReturn('Mozilla\/5.0 (Macintosh) mirall\/2.4.2 (build 10200)');
		$_SERVER['PHP_AUTH_USER'] = null;
		$_SERVER['PHP_AUTH_PW'] = null;

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = 'test';
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_client', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = 'test';

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_client', $json->error);
		$this->assertEquals(400, $result->getStatus());

		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$result = $this->controller->generateToken('refresh_token', null, null, null);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals(200, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier2;

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(200, $result->getStatus());

		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;

		$result = $this->controller->generateToken('refresh_token', null, null, 'test');
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(200, $result->getStatus());

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->access_token);
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertNotEmpty($json->token_type);
		$this->assertEquals('Bearer', $json->token_type);
		$this->assertNotEmpty($json->expires_in);
		$this->assertEquals(AccessToken::EXPIRATION_TIME, $json->expires_in);
		$this->assertNotEmpty($json->refresh_token);
		$this->assertEquals(64, \strlen($json->refresh_token));
		$this->assertNotEmpty($json->user_id);
		$this->assertEquals($this->userId, $json->user_id);
		$this->assertNotEmpty($json->message_url);
		$this->assertEquals($this->authorizationSuccessfulMessageUrl, $json->message_url);
		$this->assertEquals(200, $result->getStatus());
		// das alte Paar bleibt für die Gnadenfrist stehen
		$this->assertEquals(2, \count($this->accessTokenMapper->findAll()));
		$this->assertEquals(2, \count($this->refreshTokenMapper->findAll()));
	}

	public function testGenerateTokenWithAuthorizationCodeDisabledUser() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(false);
		$this->userManager->method('get')->willReturn($userMock);

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('unauthorized_client', $json->error);
		$this->assertEquals(400, $result->getStatus());
	}

	public function testGenerateTokenWithAuthorizationCodeMissingdUser() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$this->userManager->method('get')->willReturn(null);

		$result = $this->controller->generateToken('authorization_code', $this->authorizationCode->getCode(), $this->redirectUri);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('unauthorized_client', $json->error);
		$this->assertEquals(400, $result->getStatus());
	}

	public function testGenerateTokenWithRefreshTokenDisabledUser() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(false);
		$this->userManager->method('get')->willReturn($userMock);

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('unauthorized_client', $json->error);
		$this->assertEquals(400, $result->getStatus());
	}

	public function testGenerateTokenWithRefreshTokenMissingUser() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$this->userManager->method('get')->willReturn(null);

		$result = $this->controller->generateToken('refresh_token', null, null, $this->refreshToken->getToken());
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('unauthorized_client', $json->error);
		$this->assertEquals(400, $result->getStatus());
	}

	public function testGenerateTokenWithAuthorizationCodeAndPKCE() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		// PKCE with plain code challenge and without providing the challenge method
		$this->authorizationCode->resetExpires();
		$this->authorizationCode->setCodeChallenge('challenge');
		$this->authorizationCodeMapper->update($this->authorizationCode);
		$result = $this->controller->generateToken(
			'authorization_code',
			$this->authorizationCode->getCode(),
			$this->redirectUri,
			null,
			'challenge'
		);
		$this->assertTrue($result instanceof JSONResponse);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->access_token);
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertNotEmpty($json->token_type);
		$this->assertEquals('Bearer', $json->token_type);
		$this->assertNotEmpty($json->expires_in);
		$this->assertEquals(AccessToken::EXPIRATION_TIME, $json->expires_in);
		$this->assertNotEmpty($json->refresh_token);
		$this->assertEquals(64, \strlen($json->refresh_token));
		$this->assertNotEmpty($json->user_id);
		$this->assertEquals($this->userId, $json->user_id);
		$this->assertNotEmpty($json->message_url);
		$this->assertEquals($this->authorizationSuccessfulMessageUrl, $json->message_url);
		$this->assertEquals(200, $result->getStatus());
		$this->assertEquals(0, \count($this->authorizationCodeMapper->findAll()));
		$this->assertEquals(2, \count($this->accessTokenMapper->findAll()));
		$this->assertEquals(2, \count($this->refreshTokenMapper->findAll()));

		// PKCE with plain code challenge and with providing the challenge method
		$this->authorizationCode->resetExpires();
		$this->authorizationCode->setCodeChallenge('challenge');
		$this->authorizationCode->setCodeChallengeMethod('plain');
		$this->authorizationCodeMapper->insert($this->authorizationCode);
		$result = $this->controller->generateToken(
			'authorization_code',
			$this->authorizationCode->getCode(),
			$this->redirectUri,
			null,
			'challenge'
		);
		$this->assertEquals(200, $result->getStatus());
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->access_token);
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertNotEmpty($json->token_type);
		$this->assertEquals('Bearer', $json->token_type);

		// PKCE with plain code challenge and incorrect code verifier
		$this->authorizationCode->resetExpires();
		$this->authorizationCode->setCodeChallenge('challenge');
		$this->authorizationCode->setCodeChallengeMethod('plain');
		$this->authorizationCodeMapper->insert($this->authorizationCode);
		$result = $this->controller->generateToken(
			'authorization_code',
			$this->authorizationCode->getCode(),
			$this->redirectUri,
			null,
			'false verifier'
		);
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals(400, $result->getStatus());
	}

	public function testGenerateTokenWithAuthorizationCodeAndPKCES256() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		// PKCE with S256 code challenge
		$codeVerifier = Utilities::base64url_encode(\random_bytes(32));
		$codeChallenge = Utilities::base64url_encode(\hash('sha256', $codeVerifier, true));
		$this->authorizationCode->resetExpires();
		$this->authorizationCode->setCodeChallenge($codeChallenge);
		$this->authorizationCode->setCodeChallengeMethod('S256');
		$this->authorizationCodeMapper->update($this->authorizationCode);
		$result = $this->controller->generateToken(
			'authorization_code',
			$this->authorizationCode->getCode(),
			$this->redirectUri,
			null,
			$codeVerifier
		);
		$this->assertEquals(200, $result->getStatus());
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->access_token);
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertNotEmpty($json->token_type);
		$this->assertEquals('Bearer', $json->token_type);
	}
	public function testGenerateTokenWithAuthorizationCodeAndPKCESInvalidChallengeMethod() {
		$_SERVER['PHP_AUTH_USER'] = $this->clientIdentifier1;
		$_SERVER['PHP_AUTH_PW'] = $this->clientSecret;

		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		// PKCE with incorrect code challenge method
		$this->authorizationCode->resetExpires();
		$this->authorizationCode->setCodeChallenge('challenge');
		$this->authorizationCode->setCodeChallengeMethod('invalid');
		$this->authorizationCodeMapper->update($this->authorizationCode);
		$result = $this->controller->generateToken(
			'authorization_code',
			$this->authorizationCode->getCode(),
			$this->redirectUri,
			null,
			'challenge'
		);
		$this->assertEquals(400, $result->getStatus());
		$json = \json_decode($result->render());
		$this->assertNotEmpty($json->error);
		$this->assertEquals('invalid_request', $json->error);
		$this->assertEquals('Code challenge method invalid not supported', $json->error_description);
	}

	/**
	 * Desktop-Client, wie ihn Version20170329194544 einträgt (http://localhost:*),
	 * mit einem Code für die angegebene redirect_uri (null: Code von vor dem
	 * Update, ohne gespeicherte URI). Meldet sich als dieser Client an.
	 */
	private function addDesktopClientWithCode(?string $redirectUri): AuthorizationCode {
		$client = new Client();
		$client->setIdentifier('xdXOt13JKxym1B1QcEncf2XDkLAexMBFwiT9j6EfhhHFJhs2KM9jbjTmf8JBXE69');
		$client->setSecret('UBntmLjC2yYCeHwsyj73Uwo9TAaecAetRwMw0xYcvNL9yRdLSUi0hUAHfvCHFeFh');
		$client->setRedirectUri('http://localhost:*');
		$client->setName('Desktop Client');
		$client = $this->clientMapper->insert($client);
		$this->desktopClient = $client;

		$authorizationCode = new AuthorizationCode();
		$authorizationCode->setCode(\str_pad('desktop-loopback-code', 64, 'x'));
		$authorizationCode->setClientId($client->getId());
		$authorizationCode->setUserId($this->userId);
		$authorizationCode->resetExpires();
		if ($redirectUri !== null) {
			$authorizationCode->setRedirectUri($redirectUri);
		}
		$authorizationCode = $this->authorizationCodeMapper->insert($authorizationCode);

		$_SERVER['PHP_AUTH_USER'] = $client->getIdentifier();
		$_SERVER['PHP_AUTH_PW'] = $client->getSecret();
		$userMock = $this->createMock(IUser::class);
		$userMock->method('isEnabled')->willReturn(true);
		$this->userManager->method('get')->willReturn($userMock);

		return $authorizationCode;
	}

	/**
	 * Passt zur Registrierung, ist aber nicht die URI der Autorisierung.
	 */
	public function providesRedirectUrisOtherThanTheAuthorization(): array {
		return [
			'anderer Port' => ['http://127.0.0.1:49152', 'http://127.0.0.1:49153'],
			'andere Loopback-Adresse' => ['http://127.0.0.1:49152', 'http://[::1]:49152'],
			'localhost statt IP' => ['http://127.0.0.1:49152', 'http://localhost:49152'],
			'IP statt localhost' => ['http://localhost:49152', 'http://127.0.0.1:49152'],
			'localhost anderer Port' => ['http://localhost:49152', 'http://localhost:49153'],
		];
	}

	/**
	 * RFC 6749 §4.1.3: Der Token-Endpunkt verlangt dieselbe redirect_uri wie
	 * die Autorisierung, nicht nur eine, die zur Registrierung passt.
	 *
	 * @dataProvider providesRedirectUrisOtherThanTheAuthorization
	 */
	public function testGenerateTokenRequiresTheRedirectUriOfTheAuthorization(string $authorizedFor, string $presented): void {
		$authorizationCode = $this->addDesktopClientWithCode($authorizedFor);

		$result = $this->controller->generateToken('authorization_code', $authorizationCode->getCode(), $presented);

		$json = \json_decode($result->render());
		$this->assertEquals(400, $result->getStatus());
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals('auth grant redirect uri mismatch', $json->error_description);
		$this->assertCount(1, $this->accessTokenMapper->findAll(), 'kein neues Zugriffstoken');
	}

	public function providesTheRedirectUriOfTheAuthorization(): array {
		return [
			'127.0.0.1' => ['http://127.0.0.1:49152', 'http://127.0.0.1:49152'],
			'127.0.0.1, gleichwertig geschrieben' => ['http://127.0.0.1:49152', 'http://127.0.0.1:49152/'],
			'[::1]' => ['http://[::1]:49152', 'http://[::1]:49152'],
			'localhost wie bisher' => ['http://localhost:49152', 'http://localhost:49152'],
		];
	}

	/**
	 * @dataProvider providesTheRedirectUriOfTheAuthorization
	 */
	public function testGenerateTokenWithTheRedirectUriOfTheAuthorization(string $authorizedFor, string $presented): void {
		$authorizationCode = $this->addDesktopClientWithCode($authorizedFor);

		$result = $this->controller->generateToken('authorization_code', $authorizationCode->getCode(), $presented);

		$json = \json_decode($result->render());
		$this->assertEquals(200, $result->getStatus(), \json_encode($json));
		$this->assertEquals(64, \strlen($json->access_token));
		$this->assertEquals(64, \strlen($json->refresh_token));
		$remaining = \array_map(static function (AuthorizationCode $code) {
			return $code->getCode();
		}, $this->authorizationCodeMapper->findAll());
		$this->assertNotContains($authorizationCode->getCode(), $remaining, 'Code ist verbraucht');
	}

	/**
	 * Codes von vor dem Update tragen keine redirect_uri; für sie gilt bis zum
	 * Ablauf (zehn Minuten) nur die Prüfung gegen die Registrierung - jetzt
	 * auch mit Loopback-Adresse.
	 */
	public function testGenerateTokenWithCodeFromBeforeTheUpdate(): void {
		$authorizationCode = $this->addDesktopClientWithCode(null);

		$result = $this->controller->generateToken('authorization_code', $authorizationCode->getCode(), 'http://127.0.0.1:49152');

		$json = \json_decode($result->render());
		$this->assertEquals(200, $result->getStatus(), \json_encode($json));
		$this->assertEquals(64, \strlen($json->access_token));
	}

	public function testGenerateTokenWithCodeFromBeforeTheUpdateStillChecksTheRegistration(): void {
		$authorizationCode = $this->addDesktopClientWithCode(null);

		$result = $this->controller->generateToken('authorization_code', $authorizationCode->getCode(), 'http://evil.com:49152');

		$json = \json_decode($result->render());
		$this->assertEquals(400, $result->getStatus());
		$this->assertEquals('invalid_grant', $json->error);
		$this->assertEquals('auth grant redirect uri invalid', $json->error_description);
	}
}
