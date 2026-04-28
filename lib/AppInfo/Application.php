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
		$redirectUrl = $request->getParam('redirect_url');
		if ($redirectUrl === null) {
			return;
		}

		$urlParts = \parse_url(\urldecode($redirectUrl));
		/** @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset */
		if (\strpos($urlParts['path'], 'apps/oauth2/authorize') === false) {
			return;
		}
		$params = [];
		/** @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset */
		\parse_str($urlParts['query'], $params);
		if (!isset($params['client_id'])) {
			return;
		}
		/** @var ClientMapper $mapper */
		$mapper = OC::$server->query(ClientMapper::class);
		try {
			/** @var \OCA\OAuth2\Db\Client $client */
			$client = $mapper->findByIdentifier($params['client_id']);
			Util::addScript('oauth2', 'login');
			Util::addStyle('oauth2', 'login');
			$data = ['key' => 'oauth2', 'client' => $client->getName()];
			if (isset($params['user'])) {
				$u = OC::$server->getUserManager()->get($params['user']);
				if ($u !== null) {
					$data['login_hint'] = $u->getUserName();
				}
				if (!isset($data['login_hint']) || !\is_string($data['login_hint']) || $data['login_hint'] === '') {
					$data['login_hint'] = $params['user'];
				}
			}
			Util::addHeader('data', $data);
		} catch (DoesNotExistException) {
			// ignore - the given client id is not known
		}
	}
}
