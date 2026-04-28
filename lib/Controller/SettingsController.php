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

use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCA\OAuth2\Utilities;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IL10N;
use OCP\ILogger;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\Template;
use Rowbot\URL\URL;

class SettingsController extends Controller {
	public function __construct(
		$AppName,
		IRequest $request,
		private readonly ClientMapper $clientMapper,
		private readonly AuthorizationCodeMapper $authorizationCodeMapper,
		private readonly AccessTokenMapper $accessTokenMapper,
		private readonly RefreshTokenMapper $refreshTokenMapper,
		private readonly string $UserId,
		private readonly IL10N $l10n,
		private readonly ILogger $logger,
		private readonly IURLGenerator $urlGenerator
	) {
		parent::__construct($AppName, $request);
	}

	public function addClient(): JSONResponse {
		$redirectUri = \trim($this->request->getParam('redirect_uri', ''));
		$name = \trim($this->request->getParam('name', ''));
		if ($name === '') {
			return $this->sendErrorResponse($this->l10n->t('Name must not be empty'));
		}
		if ($redirectUri === '') {
			return $this->sendErrorResponse($this->l10n->t('Redirect URI must not be empty'));
		}
		if (!Utilities::isValidUrl($redirectUri)) {
			return $this->sendErrorResponse($this->l10n->t('Redirect URI must be a valid URL'));
		}

		try {
			// The name should be unique
			$this->clientMapper->findByName($name);
			return $this->sendErrorResponse($this->l10n->t('Name %s already exists', [$name]));
		} catch (DoesNotExistException) {
			// expected when the client name is not a duplicate
		}

		$client = new Client();
		$client->setIdentifier(Utilities::generateRandom());
		$client->setSecret(Utilities::generateRandom());
		$client->setRedirectUri($redirectUri);
		$client->setName($name);

		$allowSubdomains = $this->request->getParam('allow_subdomains', null) !== null;
		$client->setAllowSubdomains($allowSubdomains);
		$trusted = $this->request->getParam('trusted', null) !== null;

		$rURI = new URL(Utilities::removeWildcardPort($redirectUri));
		if (($rURI->hostname === 'localhost' || $rURI->hostname === '127.0.0.1') && $trusted) {
			return $this->sendErrorResponse($this->l10n->t('Cannot set localhost as trusted.'));
		}
		$client->setTrusted($trusted);

		$this->clientMapper->insert($client);
		$this->logger->info('The client "' . $client->getName() . '" has been added.', ['app' => $this->appName]);

		$template = new Template('oauth2', 'client.part', '');
		/** @phan-suppress-next-line PhanTypeMismatchArgument */
		$template->assign('client', $this->clientMapper->findByIdentifier($client->getIdentifier()));
		return new JSONResponse(
			[
				'status' => 'success',
				'rowHtml' => $template->fetchPage(),
				'data' => [] // OC.msg needs this
			]
		);
	}

	/**
	 * Deletes a client.
	 *
	 * @throws DoesNotExistException
	 * @throws MultipleObjectsReturnedException
	 */
	public function deleteClient($id): JSONResponse {
		if (!\is_int($id)) {
			return $this->sendErrorResponse($this->l10n->t('Client id must be a number'));
		}
		/** @var Client $client */
		$client = $this->clientMapper->find($id);
		$clientName = $client->getName();
		$this->clientMapper->delete($client);

		$this->authorizationCodeMapper->deleteByClient($id);
		$this->accessTokenMapper->deleteByClient($id);
		$this->refreshTokenMapper->deleteByClient($id);

		$this->logger->info('The client "' . $clientName . '" has been deleted.', ['app' => $this->appName]);
		return new JSONResponse(
			[
				'status' => 'success',
				'clientIdentifier' => $client->getIdentifier(),
				'data' => [] // OC.msg needs this
			]
		);
	}

	/**
	 * Revokes the authorization for a client.
	 *
	 * @NoAdminRequired
	 */
	public function revokeAuthorization($id): RedirectResponse {
		if (!\is_int($id)) {
			return new RedirectResponse(
				$this->urlGenerator->linkToRouteAbsolute(
					'settings.SettingsPage.getPersonal',
					['sectionid' => 'security']
				) . '#oauth2'
			);
		}

		$this->authorizationCodeMapper->deleteByClientUser($id, $this->UserId);
		$this->accessTokenMapper->deleteByClientUser($id, $this->UserId);
		$this->refreshTokenMapper->deleteByClientUser($id, $this->UserId);

		return new RedirectResponse(
			$this->urlGenerator->linkToRouteAbsolute(
				'settings.SettingsPage.getPersonal',
				['sectionid' => 'security']
			) . '#oauth2'
		);
	}

	private function sendErrorResponse(string $message): JSONResponse {
		return new JSONResponse(
			[
				'status' => 'error',
				'errorMessage' => $message,
				'data' => [] // OC.msg needs this
			]
		);
	}
}
