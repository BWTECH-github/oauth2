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

namespace OCA\OAuth2;

require_once __DIR__ . '/../vendor/autoload.php';

use OC;
use Rowbot\URL\Exception\TypeError;
use Rowbot\URL\URL;

class Utilities {
	/**
	 * Hosts des eigenen Rechners, wie der URL-Parser sie liefert.
	 */
	private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

	/**
	 * Loopback-IP-Literale nach RFC 8252 §7.3.
	 */
	private const LOOPBACK_IPS = ['127.0.0.1', '[::1]'];

	/**
	 * Generates a random string with 64 characters.
	 */
	public static function generateRandom(): string {
		return OC::$server->getSecureRandom()->generate(
			64,
			'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'
		);
	}

	/**
	 * Validates a redirection URI.
	 */
	public static function validateRedirectUri(string $expected, string $actual, bool $allowSubdomains): bool {
		$withoutWildcardPort = self::removeWildcardPort($expected);
		$validatePort = $withoutWildcardPort === $expected;
		$expected = $withoutWildcardPort;
		try {
			$expectedUrl = new URL($expected);
			$actualUrl = new URL($actual);
			// Benutzer-Info (user:pass@) nur, wenn genau sie registriert ist
			if ($expectedUrl->username !== $actualUrl->username || $expectedUrl->password !== $actualUrl->password) {
				return false;
			}

			// RFC 8252 §7.3: Für Loopback-Registrierungen muss jeder Port gehen -
			// der Client bekommt ihn erst beim Anmelden vom Betriebssystem. Das
			// gilt für die Loopback-IPs 127.0.0.1 und [::1]; localhost bleibt,
			// wie es war (RFC 8252 §8.3 rät davon ab). Pfad und Query müssen wie
			// registriert sein.
			if (self::isLoopbackRegistration($expectedUrl, !$validatePort) && self::isLoopbackIpRedirect($actual, $actualUrl)) {
				return $expectedUrl->pathname === $actualUrl->pathname
					&& $expectedUrl->search === $actualUrl->search;
			}

			if (\strcmp($expectedUrl->protocol, $actualUrl->protocol) !== 0) {
				return false;
			}

			if (!self::validateDomain($expectedUrl, $actualUrl, $allowSubdomains)) {
				return false;
			}

			if ($validatePort && $expectedUrl->port !== $actualUrl->port) {
				return false;
			}

			if ($expectedUrl->pathname !== $actualUrl->pathname) {
				return false;
			}

			if ($expectedUrl->search !== $actualUrl->search) {
				return false;
			}

			return true;
		} catch (TypeError) {
			return false;
		}
	}

	/**
	 * Entfernt den Platzhalter-Port aus http://localhost:*, http://127.0.0.1:*
	 * und http://[::1]:* (jeder Port erlaubt). Andere Hosts kennen keinen
	 * Platzhalter; dort bleibt die URI, wie sie ist, und ist keine gültige URL.
	 */
	public static function removeWildcardPort(string $redirectUri): string {
		return (string)\preg_replace('~^http://(localhost|127\.0\.0\.1|\[::1\]):\*(?=$|[/?#])~', 'http://$1', $redirectUri);
	}

	public static function isValidUrl(string $redirectUri): bool {
		$redirectUri = self::removeWildcardPort($redirectUri);
		return \filter_var($redirectUri, FILTER_VALIDATE_URL) !== false;
	}

	/**
	 * Ob der Host (wie der URL-Parser ihn liefert) der eigene Rechner ist.
	 * Solche Clients dürfen nicht vertrauenswürdig sein: Sie bekämen den Code
	 * ohne Zustimmung an einen Port, auf dem jedes Programm lauschen kann.
	 */
	public static function isLoopbackHost(string $hostname): bool {
		return \in_array($hostname, self::LOOPBACK_HOSTS, true);
	}

	/**
	 * Ob die redirect_uri der Token-Anfrage dieselbe ist wie die der
	 * Autorisierung (RFC 6749 §4.1.3). Verglichen wird die Form des
	 * URL-Parsers: http://127.0.0.1:49152 und http://127.0.0.1:49152/ sind
	 * dieselbe Adresse, ein anderer Port oder Host nicht.
	 */
	public static function isSameRedirectUri(string $authorized, string $presented): bool {
		try {
			return (new URL($authorized))->href === (new URL($presented))->href;
		} catch (TypeError) {
			return false;
		}
	}

	// See https://tools.ietf.org/pdf/rfc7636.pdf#56
	public static function base64url_encode(string $data): string {
		return \rtrim(\strtr(\base64_encode($data), '+/', '-_'), '=');
	}

	/**
	 * Loopback-Registrierung: http mit Loopback-IP (Port egal) oder
	 * http://localhost:* - so ist der Desktop-Client eingetragen, und der
	 * Platzhalter erlaubt dort ohnehin jeden Port. localhost mit festem Port
	 * bleibt fest.
	 */
	private static function isLoopbackRegistration(URL $expectedUrl, bool $hasWildcardPort): bool {
		if ($expectedUrl->protocol !== 'http:') {
			return false;
		}
		return \in_array($expectedUrl->hostname, self::LOOPBACK_IPS, true)
			|| ($hasWildcardPort && $expectedUrl->hostname === 'localhost');
	}

	/**
	 * Weiterleitung auf eine Loopback-IP. Geprüft wird auch die Zeichenkette
	 * selbst - wörtlich http://127.0.0.1 bzw. http://[::1], optional mit Port:
	 * Der Browser folgt ihr, nicht diesem Parser, und http:127.0.0.1 etwa löst
	 * er bei einem Server unter http relativ zu dessen Adresse auf.
	 * Schreibweisen wie 2130706433 oder Benutzer-Info braucht kein Client.
	 */
	private static function isLoopbackIpRedirect(string $actual, URL $actualUrl): bool {
		return \preg_match('~^http://(127\.0\.0\.1|\[::1\])(:[0-9]{1,5})?(?=$|[/?#])~', $actual) === 1
			&& \in_array($actualUrl->hostname, self::LOOPBACK_IPS, true);
	}

	private static function validateDomain(URL $expectedUrl, URL $actualUrl, bool $allowSubdomains): bool {
		if (!$allowSubdomains) {
			return \strcmp($expectedUrl->hostname, $actualUrl->hostname) === 0;
		}

		$expectedUrlParts = \array_reverse(\explode('.', $expectedUrl->hostname));
		$actualUrlParts = \array_reverse(\explode('.', $actualUrl->hostname));
		foreach ($expectedUrlParts as $i => $p) {
			if ($p !== $actualUrlParts[$i]) {
				return false;
			}
		}

		return true;
	}
}
