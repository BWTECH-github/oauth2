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
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshToken;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCA\OAuth2\Exceptions\UnsupportedPkceTransformException;
use OCA\OAuth2\Utilities;
use OCP\AppFramework\ApiController;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;

class OAuthApiController extends ApiController {
	public function __construct(
		$AppName,
		IRequest $request,
		private readonly ClientMapper $clientMapper,
		private readonly AuthorizationCodeMapper $authorizationCodeMapper,
		private readonly AccessTokenMapper $accessTokenMapper,
		private readonly RefreshTokenMapper $refreshTokenMapper,
		private readonly IUserManager $userManager,
		private readonly IURLGenerator $urlGenerator,
		private readonly ILogger $logger
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

		if (\is_string($client_id) && \is_string($code_verifier)) {
			// The authorization code flow doesn't require a client secret in case of a public client.
			// Instead, the client needs to use the PKCE extension and send a code challenge / code verifier.
			// That is why we don't compare the client secret when the client id and code verifier are set in the
			// query parameters.
			try {
				/** @var \OCA\OAuth2\Db\Client $client */
				$client = $this->clientMapper->findByIdentifier($client_id);
			} catch (DoesNotExistException) {
				return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
			}
		} else {
			$clientCredentials = $this->getClientCredentials();
			if ($clientCredentials === null) {
				return new JSONResponse(['error' => 'invalid_request'], Http::STATUS_BAD_REQUEST);
			}
			[$clientIdentifier, $clientSecret] = $clientCredentials;

			try {
				/** @var \OCA\OAuth2\Db\Client $client */
				$client = $this->clientMapper->findByIdentifier($clientIdentifier);
			} catch (DoesNotExistException) {
				return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
			}

			if (\strcmp($client->getSecret(), $clientSecret) !== 0) {
				return new JSONResponse(['error' => 'invalid_client'], Http::STATUS_BAD_REQUEST);
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

				try {
					if (!$authorizationCode->isCodeVerifierValid($code_verifier)) {
						$this->logger->debug("code verifier invalid: {$code_verifier}", ['app' => __CLASS__]);
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

				$this->logger->info('A refresh token has been used by the client "' . $client->getName() . '" to request an access token.', ['app' => $this->appName]);

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

				$relatedAccessToken = new AccessToken();
				$relatedAccessToken->setId($refreshToken->getAccessTokenId());
				$this->accessTokenMapper->delete($relatedAccessToken);
				$this->refreshTokenMapper->delete($refreshToken);

				break;
			default:
				OC::$server->getLogger()->debug("unhandled grant type: {$grant_type}", ['app' => __CLASS__]);
				return new JSONResponse(['error' => 'invalid_grant', 'error_description' => 'unhandled grant type'], Http::STATUS_BAD_REQUEST);
		}

		$token = Utilities::generateRandom();
		$accessToken = new AccessToken();
		$accessToken->setToken($token);
		$accessToken->setClientId($client->getId());
		$accessToken->setUserId($userId);
		$accessToken->resetExpires();
		$this->accessTokenMapper->insert($accessToken);

		$token = Utilities::generateRandom();
		$refreshToken = new RefreshToken();
		$refreshToken->setToken($token);
		$refreshToken->setClientId($client->getId());
		$refreshToken->setUserId($userId);
		$refreshToken->setAccessTokenId($accessToken->getId());
		$this->refreshTokenMapper->insert($refreshToken);

		return new JSONResponse(
			[
				'access_token' => $accessToken->getToken(),
				'token_type' => 'Bearer',
				'expires_in' => AccessToken::EXPIRATION_TIME,
				'refresh_token' => $refreshToken->getToken(),
				'user_id' => $userId,
				'message_url' => $this->urlGenerator->linkToRouteAbsolute($this->appName . '.page.authorizationSuccessful')
			]
		);
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
