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

namespace OCA\OAuth2\Controller;

use OC;
use OCA\OAuth2\Db\AccessToken;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshToken;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCA\OAuth2\Exceptions\UnsupportedPkceTransformException;
use OCA\OAuth2\Utilities;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

class OAuthApiController extends ApiController {
	/**
	 * So lange (Sekunden) nimmt der Token-Endpunkt ein schon ausgetauschtes
	 * Refresh-Token noch an und liefert dann dasselbe neue Paar, und so lange
	 * gilt das Access-Token des Vorgängers weiter. Geht die Antwort auf einen
	 * Austausch verloren (Zeitüberschreitung, Proxy, Verbindungsabbruch,
	 * Ruhezustand), kommt der Client mit seiner Wiederholung so noch an die
	 * neuen Tokens statt an invalid_grant und eine neue Anmeldung.
	 * Einstellbar mit `occ config:app:set oauth2 refresh_token_grace_period
	 * --value <Sekunden>` (0 bis 3600, 0 schaltet sie ab).
	 */
	public const REFRESH_TOKEN_GRACE_PERIOD = 180;

	public function __construct(
		$AppName,
		IRequest $request,
		private readonly ClientMapper $clientMapper,
		private readonly AuthorizationCodeMapper $authorizationCodeMapper,
		private readonly AccessTokenMapper $accessTokenMapper,
		private readonly RefreshTokenMapper $refreshTokenMapper,
		private readonly IUserManager $userManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly ILogger $logger,
		private readonly IDBConnection $db,
		private readonly IConfig $config
	) {
		parent::__construct($AppName, $request);
	}

	/**
	 * Implements the OAuth 2.0 Access Token Response.
	 *
	 * @param string $grant_type The authorization grant type.
	 * @param string $code The authorization code.
	 * @param string $redirect_uri The redirect URI.
	 * @param string $refresh_token The refresh token.
	 * @param string $code_verifier The PKCE code verifier.
	 * @param string $client_id The client id in case of a public client.
	 * @return JSONResponse The Access Token or an empty JSON Object.
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 * @PublicPage
	 * @CORS
	 */
	public function generateToken(
		$grant_type,
		$code = null,
		$redirect_uri = null,
		$refresh_token = null,
		$code_verifier = null,
		$client_id = null
	) {
		if (!\is_string($grant_type)) {
			return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}

		// Resolve the client and any presented secret independently of the grant type
		// and auth style (the secret arrives via HTTP Basic). Whether the secret is
		// *required* is then decided from the client record (confidential vs public),
		// NOT from whether a PKCE code_verifier happens to be present: the old code
		// skipped the secret check for any request carrying client_id + code_verifier,
		// so an attacker holding a stolen refresh_token could bypass a confidential
		// client's secret on the refresh_token grant just by adding a bogus
		// code_verifier. PKCE is an auth-code mechanism and must not gate the secret.
		$clientCredentials = $this->getClientCredentials();
		$presentedSecret = null;
		if ($clientCredentials !== null) {
			[$clientIdentifier, $presentedSecret] = $clientCredentials;
		} elseif (\is_string($client_id)) {
			$clientIdentifier = $client_id;
		} else {
			return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
		}

		try {
			/** @var \OCA\OAuth2\Db\Client $client */
			$client = $this->clientMapper->findByIdentifier($clientIdentifier);
		} catch (DoesNotExistException) {
			return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
		}

		$expectedSecret = (string)$client->getSecret();
		if ($expectedSecret !== '') {
			// Confidential client: the secret is mandatory for every grant type and
			// cannot be skipped by presenting a PKCE code_verifier. Timing-safe compare
			// (strcmp leaks the secret one byte at a time via response time).
			if ($presentedSecret === null || !\hash_equals($expectedSecret, (string)$presentedSecret)) {
				return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
			}
		} else {
			// Public client (no registered secret): the authorization_code grant must
			// use PKCE, i.e. present a code_verifier.
			if ($grant_type === 'authorization_code' && !\is_string($code_verifier)) {
				return new JSONResponse(['error' => 'invalid_request', 'error_description' => 'code_verifier required'], Http::STATUS_BAD_REQUEST);
			}
		}

		switch ($grant_type) {
			case 'authorization_code':
				if (!\is_string($code) || !\is_string($redirect_uri)) {
					return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
				}

				try {
					/** @var \OCA\OAuth2\Db\AuthorizationCode $authorizationCode */
					$authorizationCode = $this->authorizationCodeMapper->findByCode($code);
				} catch (DoesNotExistException $exception) {
					// could be that authorization code has been already cleaned up or client sends wrong authorization code
					$this->logger->debug("authorization code does not exist: {$exception}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'authorization code does not exist'], Http::STATUS_BAD_REQUEST);
				}

				if (\strcmp((string)$authorizationCode->getClientId(), (string)$client->getId()) !== 0) {
					$this->logger->debug("auth grant client ids mismatch: {$authorizationCode->getClientId()} != {$client->getId()}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'auth grant client ids mismatch'], Http::STATUS_BAD_REQUEST);
				}

				if ($authorizationCode->hasExpired()) {
					$this->logger->debug("auth grant expired: {$authorizationCode->getExpires()}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'auth grant expired'], Http::STATUS_BAD_REQUEST);
				}

				if (!Utilities::validateRedirectUri($client->getRedirectUri(), \urldecode($redirect_uri), $client->getAllowSubdomains())) {
					$this->logger->debug("auth grant redirect uri invalid: {$redirect_uri}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'auth grant redirect uri invalid'], Http::STATUS_BAD_REQUEST);
				}

				// RFC 6749 §4.1.3: dieselbe redirect_uri wie bei der Autorisierung,
				// nicht nur eine, die zur Registrierung passt - mit Loopback-Port
				// (RFC 8252 §7.3) oder Subdomains passen viele. Codes von vor dem
				// Update tragen keine URI; für sie gilt bis zum Ablauf nur die
				// Registrierung.
				$authorizedRedirectUri = $authorizationCode->getRedirectUri();
				if ($authorizedRedirectUri !== null && !Utilities::isSameRedirectUri($authorizedRedirectUri, \urldecode($redirect_uri))) {
					$this->logger->debug("auth grant redirect uri mismatch: {$redirect_uri} is not {$authorizedRedirectUri}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'auth grant redirect uri mismatch'], Http::STATUS_BAD_REQUEST);
				}

				try {
					if (!$authorizationCode->isCodeVerifierValid($code_verifier)) {
						// Do not log the PKCE code verifier itself — it is a per-flow secret.
						$this->logger->debug('code verifier invalid', ['app' => __CLASS__]);
						return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'code verifier invalid'], Http::STATUS_BAD_REQUEST);
					}
				} catch (UnsupportedPkceTransformException $e) {
					$this->logger->debug("code challenge method invalid: {$e}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_request', 'error_description' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
				}

				$this->logger->info('An authorization code has been used by the client "' . $client->getName() . '" to request an access token.', ['app' => $this->appName]);

				$userId = $authorizationCode->getUserId();

				// strip off username if it exists
				if (\strstr($userId, ':')) {
					[, $userId] = \explode(':', $userId, 2);
				}

				$this->authorizationCodeMapper->delete($authorizationCode);

				$userObj = $this->userManager->get($userId);
				if ($userObj === null || !$userObj->isEnabled()) {
					$this->logger->debug("the matching user is missing or disabled", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'unauthorized_client', 'error_description' => 'user not enabled'], Http::STATUS_BAD_REQUEST);
				}

				break;
			case 'refresh_token':
				$statusCode = Http::STATUS_BAD_REQUEST;
				// This fixes the infinite loop issue with desktop client 2.4.2
				if (\preg_match('/\bmirall\b.+2\.4\.2/i', (string)$this->request->getHeader('User-Agent'))) {
					$statusCode = Http::STATUS_OK;
				}

				if (!\is_string($refresh_token)) {
					return new JSONResponse(['error' => 'invalid_request'], $statusCode);
				}

				try {
					/** @var RefreshToken $refreshToken */
					$refreshToken = $this->refreshTokenMapper->findByToken($refresh_token);
				} catch (DoesNotExistException $exception) {
					// could be that token has been already cleaned up or client sends wrong token
					$this->logger->debug("refresh token does not exist: {$exception}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'refresh token does not exist'], $statusCode);
				}

				if (\strcmp((string)$refreshToken->getClientId(), (string)$client->getId()) !== 0) {
					$this->logger->debug("refresh grant client ids mismatch: {$refreshToken->getClientId()} != {$client->getId()}", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'refresh grant client ids mismatch'], $statusCode);
				}

				$userId = $refreshToken->getUserId();

				// strip off username if it exists
				if (\strstr($userId, ':')) {
					[, $userId] = \explode(':', $userId, 2);
				}

				$userObj = $this->userManager->get($userId);
				if ($userObj === null || !$userObj->isEnabled()) {
					$this->logger->debug("the matching user is missing or disabled", ['app' => __CLASS__]);
					return new JSONResponse(['error' => 'unauthorized_client', 'error_description' => 'user not enabled'], Http::STATUS_BAD_REQUEST);
				}

				return $this->refreshTokens($refreshToken, $client, $userId, $statusCode);
			default:
				OC::$server->getLogger()->debug("unhandled grant type: {$grant_type}", ['app' => __CLASS__]);
				return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'unhandled grant type'], Http::STATUS_BAD_REQUEST);
		}

		[$accessToken, $refreshToken] = $this->issueTokens($client, $userId);
		return $this->tokenResponse($accessToken, $refreshToken, $userId);
	}

	/**
	 * Tauscht ein Refresh-Token gegen ein neues Paar aus Access- und
	 * Refresh-Token (Rotation).
	 *
	 * Neues Paar anlegen und das alte als verbraucht vermerken geschieht in
	 * einer Transaktion, das Vermerken nur, wenn das alte noch unverbraucht
	 * ist: Von zwei gleichzeitigen Anfragen mit demselben Refresh-Token tauscht
	 * genau eine aus, die andere bekommt deren Ergebnis (replayRotation()).
	 * Bisher löschte der Austausch altes Refresh- und Access-Token sofort und
	 * legte das neue Paar danach ohne Transaktion an: Gleichzeitige Anfragen
	 * ergaben mehrere gültige Nachfolger oder invalid_grant, und ging die
	 * Antwort verloren, scheiterte die Wiederholung des Clients mit
	 * invalid_grant und er musste sich neu anmelden.
	 */
	private function refreshTokens(RefreshToken $refreshToken, Client $client, string $userId, int $statusCode): JSONResponse {
		$now = \time();
		$gracePeriod = $this->refreshTokenGracePeriod();

		if ($refreshToken->getRotatedAt() !== null) {
			return $this->replayRotation($refreshToken, $client, $now, $gracePeriod, $statusCode);
		}

		$this->db->beginTransaction();
		try {
			[$accessToken, $newRefreshToken] = $this->issueTokens($client, $userId);
			$rotated = $this->refreshTokenMapper->markRotated($refreshToken->getId(), $newRefreshToken->getId(), $now) === 1;
			if ($rotated) {
				// Anfragen mit dem bisherigen Access-Token, die noch unterwegs sind, gehen während der Gnadenfrist durch
				// Refresh-Tokens aus 0.1.0 (2017) haben kein Access-Token (access_token_id NULL): nichts zu verlängern
				if ($refreshToken->getAccessTokenId() !== null) {
					$this->accessTokenMapper->limitExpiry($refreshToken->getAccessTokenId(), $now + $gracePeriod);
				}
				$this->db->commit();
			} else {
				$this->db->rollBack();
			}
		} catch (\Throwable $e) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}
			throw $e;
		}

		if ($rotated) {
			// Aufräumen außerhalb der Transaktion: die verbrauchten Vorgänger braucht ab jetzt niemand mehr
			try {
				$this->refreshTokenMapper->deleteBySuccessor($refreshToken->getId());
			} catch (\Throwable $e) {
				$this->logger->logException($e, ['app' => $this->appName, 'message' => 'Could not delete exchanged refresh tokens']);
			}
		} else {
			// eine gleichzeitige Anfrage mit demselben Refresh-Token war schneller: ihr Ergebnis gilt auch für diese
			try {
				/** @var RefreshToken $current */
				$current = $this->refreshTokenMapper->find($refreshToken->getId());
			} catch (DoesNotExistException) {
				return $this->refreshTokenUsed($statusCode);
			}
			return $this->replayRotation($current, $client, $now, $gracePeriod, $statusCode);
		}

		$this->logger->info('A refresh token has been used by the client "' . $client->getName() . '" to request an access token.', ['app' => $this->appName]);
		return $this->tokenResponse($accessToken, $newRefreshToken, $userId);
	}

	/**
	 * Ein schon ausgetauschtes Refresh-Token kommt noch einmal: Liegt der
	 * Austausch weniger als die Gnadenfrist zurück und ist sein Nachfolger noch
	 * unverbraucht, bekommt der Client dasselbe Paar wie beim Austausch – er
	 * hat die Antwort darauf wohl nicht erhalten. Sonst invalid_grant.
	 */
	private function replayRotation(RefreshToken $rotated, Client $client, int $now, int $gracePeriod, int $statusCode): JSONResponse {
		$successorId = $rotated->getSuccessorId();
		if ($successorId === null || $now - (int)$rotated->getRotatedAt() >= $gracePeriod) {
			$this->logger->info('The client "' . $client->getName() . '" used a refresh token that had already been exchanged.', ['app' => $this->appName]);
			return $this->refreshTokenUsed($statusCode);
		}
		try {
			/** @var RefreshToken $successor */
			$successor = $this->refreshTokenMapper->find($successorId);
			/** @var AccessToken $accessToken */
			$accessToken = $this->accessTokenMapper->find($successor->getAccessTokenId());
		} catch (DoesNotExistException) {
			return $this->refreshTokenUsed($statusCode);
		}
		if ($successor->getRotatedAt() !== null || $successor->getClientId() !== $rotated->getClientId() || $successor->getUserId() !== $rotated->getUserId()) {
			// der Nachfolger ist selbst schon ausgetauscht: der Client hat die Antwort also erhalten
			$this->logger->info('The client "' . $client->getName() . '" used a refresh token that had already been exchanged.', ['app' => $this->appName]);
			return $this->refreshTokenUsed($statusCode);
		}

		$userId = $successor->getUserId();
		if (\strstr($userId, ':')) {
			[, $userId] = \explode(':', $userId, 2);
		}
		$this->logger->info('The client "' . $client->getName() . '" repeated a token refresh within the grace period and got the same tokens again.', ['app' => $this->appName]);
		return $this->tokenResponse($accessToken, $successor, $userId, \max(0, $accessToken->getExpires() - $now));
	}

	private function refreshTokenUsed(int $statusCode): JSONResponse {
		return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'refresh token does not exist'], $statusCode);
	}

	/**
	 * Legt ein neues Paar aus Access- und Refresh-Token an.
	 *
	 * @return array{0: AccessToken, 1: RefreshToken}
	 */
	private function issueTokens(Client $client, string $userId): array {
		$accessToken = new AccessToken();
		$accessToken->setToken(Utilities::generateRandom());
		$accessToken->setClientId($client->getId());
		$accessToken->setUserId($userId);
		$accessToken->resetExpires($this->accessTokenLifetime());
		$this->accessTokenMapper->insert($accessToken);

		$refreshToken = new RefreshToken();
		$refreshToken->setToken(Utilities::generateRandom());
		$refreshToken->setClientId($client->getId());
		$refreshToken->setUserId($userId);
		$refreshToken->setAccessTokenId($accessToken->getId());
		$this->refreshTokenMapper->insert($refreshToken);

		return [$accessToken, $refreshToken];
	}

	private function tokenResponse(AccessToken $accessToken, RefreshToken $refreshToken, string $userId, ?int $expiresIn = null): JSONResponse {
		return new JSONResponse(
			[
				'access_token' => $accessToken->getToken(),
				'token_type' => 'Bearer',
				'expires_in' => $expiresIn ?? $this->accessTokenLifetime(),
				'refresh_token' => $refreshToken->getToken(),
				'user_id' => $userId,
				'message_url' => $this->urlGenerator->linkToRouteAbsolute($this->appName . '.page.authorizationSuccessful')
			]
		);
	}

	/**
	 * Lebensdauer neuer Access-Tokens in Sekunden: `occ config:app:set oauth2
	 * access_token_lifetime --value <Sekunden>` (10 bis 86400, Standard 3600).
	 */
	private function accessTokenLifetime(): int {
		$value = $this->config->getAppValue($this->appName, 'access_token_lifetime', '');
		if (!\is_numeric($value)) {
			return AccessToken::EXPIRATION_TIME;
		}
		return \min(86400, \max(10, (int)$value));
	}

	private function refreshTokenGracePeriod(): int {
		$value = $this->config->getAppValue($this->appName, 'refresh_token_grace_period', '');
		if (!\is_numeric($value)) {
			return self::REFRESH_TOKEN_GRACE_PERIOD;
		}
		return \min(3600, \max(0, (int)$value));
	}

	/**
	 * @return array{0: string, 1: string}|null
	 */
	private function getClientCredentials(): ?array {
		$user = $_SERVER['PHP_AUTH_USER'] ?? null;
		$password = $_SERVER['PHP_AUTH_PW'] ?? null;
		if (\array_key_exists('PHP_AUTH_USER', $_SERVER) || \array_key_exists('PHP_AUTH_PW', $_SERVER)) {
			return \is_string($user) && \is_string($password) ? [$user, $password] : null;
		}
		if (\is_string($user) && \is_string($password)) {
			return [$user, $password];
		}

		$authHeader = $this->request->getHeader('Authorization');
		if (!\is_string($authHeader) || \stripos($authHeader, 'Basic ') !== 0) {
			return null;
		}

		$decoded = \base64_decode(\substr($authHeader, 6), true);
		if (!\is_string($decoded) || !\str_contains($decoded, ':')) {
			return null;
		}

		[$clientId, $clientSecret] = \explode(':', $decoded, 2);
		return [$clientId, $clientSecret];
	}
}
