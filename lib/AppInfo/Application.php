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

namespace OCA\OAuth2\AppInfo;

use OC;
use OCA\OAuth2\AuthModule;
use OCA\OAuth2\Db\AccessTokenMapper;
use OCA\OAuth2\Db\AuthorizationCodeMapper;
use OCA\OAuth2\Db\Client;
use OCA\OAuth2\Db\ClientMapper;
use OCA\OAuth2\Db\RefreshTokenMapper;
use OCA\OAuth2\Hooks\UserHooks;
use OCA\OAuth2\Sabre\OAuth2;
use OCP\AppFramework\App;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\SabrePluginEvent;
use OCP\Util;
use Sabre\DAV\Auth\Plugin;

class Application extends App {
	public function __construct(array $urlParams = []) {
		parent::__construct('oauth2', $urlParams);

		$container = $this->getContainer();

		$container->registerService('Logger', static fn ($c) => $c->query('ServerContainer')->getLogger());

		$container->registerService('UserManager', static fn ($c) => $c->query('ServerContainer')->getUserManager());

		$container->registerService('UserHooks', static fn ($c) => new UserHooks(
			$c->query('ServerContainer')->getUserManager(),
			$c->query(AuthorizationCodeMapper::class),
			$c->query(AccessTokenMapper::class),
			$c->query(RefreshTokenMapper::class),
			$c->query('Logger'),
			$c->query('AppName')
		));

		$dispatcher = $this->getContainer()->getServer()->getEventDispatcher();
		$dispatcher->addListener('OCA\DAV\Connector\Sabre::authInit', static function ($event) {
			if ($event instanceof SabrePluginEvent) {
				$authPlugin = $event->getServer()->getPlugin('auth');
				if ($authPlugin instanceof Plugin) {
					$authPlugin->addBackend(
						new OAuth2(
							OC::$server->getSession(),
							OC::$server->getUserSession(),
							OC::$server->getRequest(),
							new AuthModule(),
							'principals/'
						)
					);
				}
			}
		});
	}

	public function boot(): void {
		$this->getContainer()->query('UserHooks')->register();
		$request = $this->getContainer()->getServer()->getRequest();
		if ($request->getMethod() !== 'GET') {
			return;
		}
		$params = self::authorizeParams($request->getParam('redirect_url'));
		// client_id[]=… ergibt ein Array, findByIdentifier() wirft dafür eine
		// InvalidArgumentException; solche Eingaben übergehen. Ebenso
		// ungültiges UTF-8 wie client_id=%FF: PostgreSQL lehnt es mit
		// SQLSTATE 22021 ab.
		$clientId = $params['client_id'] ?? null;
		if (!self::isUtf8String($clientId)) {
			return;
		}
		$client = $this->findClient($clientId);
		if ($client === null) {
			return;
		}
		Util::addScript('oauth2', 'login');
		Util::addStyle('oauth2', 'login');
		$data = ['key' => 'oauth2', 'client' => $client->getName()];
		// user[]=… ebenso: kein Anmeldehinweis statt eines Arrays im
		// Kopfelement; ungültiges UTF-8 erreicht die Datenbank nicht.
		$userId = $params['user'] ?? null;
		if (self::isUtf8String($userId)) {
			$data['login_hint'] = $this->findLoginName($userId) ?? $userId;
		}
		// Leerer Text statt null: Kerne ohne OC_Template::renderHeaders()
		// – Core- und SaaS-Tags bis v11.0.22, DEV-Tags bis v11.0.37 und der
		// Redesign-Kern bis zur Übernahme – geben ein Kopfelement ohne
		// Text als <data …/> aus. Selbstschließend sind in HTML nur void
		// elements, <data> bliebe offen und umschlösse die Anmeldekarte,
		// die dann links oben statt mittig steht. Mit '' wird daraus bei
		// jedem Kern <data …></data>.
		Util::addHeader('data', $data, '');
	}

	/**
	 * Parameter der Autorisierung, zu der die Anmeldeseite zurückführt;
	 * leer, wenn redirect_url nicht auf apps/oauth2/authorize zeigt.
	 */
	private static function authorizeParams(mixed $redirectUrl): array {
		// Nur Zeichenketten auswerten: redirect_url[]=… kommt als Array an,
		// urldecode() bräche mit einem TypeError ab, und statt der
		// Anmeldeseite käme HTTP 500.
		if (!\is_string($redirectUrl)) {
			return [];
		}
		$urlParts = \parse_url(\urldecode($redirectUrl));
		if (!\is_array($urlParts) || !isset($urlParts['path'], $urlParts['query'])
			|| !\str_contains($urlParts['path'], 'apps/oauth2/authorize')) {
			return [];
		}
		$params = [];
		\parse_str($urlParts['query'], $params);
		return $params;
	}

	/**
	 * Nur Zeichenketten in gültigem UTF-8 dürfen aus der Adresse an die
	 * Datenbank gehen.
	 */
	private static function isUtf8String(mixed $value): bool {
		return \is_string($value) && \mb_check_encoding($value, 'UTF-8');
	}

	/**
	 * Client zu client_id=…; null, wenn es keinen gibt oder die Abfrage
	 * scheitert.
	 */
	private function findClient(string $clientId): ?Client {
		/** @var ClientMapper $mapper */
		$mapper = OC::$server->query(ClientMapper::class);
		try {
			/** @var Client $client */
			$client = $mapper->findByIdentifier($clientId);
		} catch (DoesNotExistException) {
			// ignore - the given client id is not known
			return null;
		} catch (\Throwable $e) {
			// Ein Fehler der Abfrage darf die Anmeldeseite nicht mit HTTP 500
			// abbrechen; sie erscheint dann ohne Hinweis auf den Client.
			$this->logLookupFailure($e, 'client');
			return null;
		}
		return $client;
	}

	/**
	 * Anmeldename des Kontos aus user=…; null, wenn es keins gibt oder die
	 * Abfrage scheitert – dann bleibt der übergebene Wert der Hinweis.
	 */
	private function findLoginName(string $userId): ?string {
		try {
			$user = OC::$server->getUserManager()->get($userId);
		} catch (\Throwable $e) {
			$this->logLookupFailure($e, 'user');
			return null;
		}
		$name = $user?->getUserName();
		return \is_string($name) && $name !== '' ? $name : null;
	}

	/**
	 * Gefangen, aber nicht verschwiegen: Der Fehler landet im Log.
	 */
	private function logLookupFailure(\Throwable $e, string $subject): void {
		$this->getContainer()->query('Logger')->logException($e, [
			'app' => 'oauth2',
			'message' => "Looking up the $subject from the redirect_url of the login page failed",
		]);
	}
}
