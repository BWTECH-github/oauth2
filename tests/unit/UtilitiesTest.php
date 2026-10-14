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

namespace OCA\OAuth2\Tests\Unit;

use OCA\OAuth2\Utilities;
use PHPUnit\Framework\TestCase;

class UtilitiesTest extends TestCase {
	public function testGenerateRandom(): void {
		$random = Utilities::generateRandom();

		$this->assertEquals(64, \strlen($random));
		$this->assertFalse(\strpos($random, '+'));
		$this->assertFalse(\strpos($random, '/'));
	}

	public function providesUrlsToValidate(): array {
		return [
			[false, 'http://owncloud.org:80/test?q=1', 'https://owncloud.org:80/test?q=1', false],
			[true, 'https://owncloud.org:80/test?q=1', 'https://sso.owncloud.org:80/test?q=1', true],
			[false, 'https://owncloud.org:80/test?q=1', 'https://sso.owncloud.de:80/test?q=1', true],
			[false, 'https://owncloud.org:80/test?q=1', 'https://sso.owncloud.org:80/test?q=1', false],
			[false, 'https://owncloud.org:80/test?q=1', 'https://owncloud.org:90/test?q=1', false],
			[false, 'https://owncloud.org:80/tests?q=1', 'https://owncloud.org:80/test?q=1', false],
			[false, 'https://owncloud.org:80/test?q=1', 'https://owncloud.org:80/test?q=0', false],
			[true, 'http://localhost:*/test?q=1', 'http://localhost:12345/test?q=1', false],
			[false, 'http://excepted.com', 'http://aaa\@excepted.com', false],
			[false, 'https://trustedclient.com', 'https://munity.trustedclient.community.', true],
			[false, 'https://trustedclient.com', 'https://munity.trustedclient.community', true]
		];
	}

	/**
	 * Loopback-Weiterleitung nach RFC 8252 §7.3: Ist die registrierte URI eine
	 * Loopback-Adresse (http://localhost:* des Desktop-Clients, 127.0.0.1 oder
	 * [::1]), gilt für 127.0.0.1 und [::1] jeder Port. Pfad und Query müssen
	 * passen; localhost selbst bleibt, wie es war.
	 */
	public function providesLoopbackRedirects(): array {
		return [
			// angenommen: Desktop-Client (http://localhost:*) auf die Loopback-IP
			'localhost:* -> 127.0.0.1 mit Port' => [true, 'http://localhost:*', 'http://127.0.0.1:49152', false],
			'localhost:* -> 127.0.0.1 mit Port und Schrägstrich' => [true, 'http://localhost:*', 'http://127.0.0.1:1234/', false],
			'localhost:* -> 127.0.0.1 ohne Port' => [true, 'http://localhost:*', 'http://127.0.0.1/', false],
			'localhost:* -> [::1] mit Port' => [true, 'http://localhost:*', 'http://[::1]:49152', false],
			'localhost:* -> [::1] mit Port und Schrägstrich' => [true, 'http://localhost:*', 'http://[::1]:1234/', false],
			'localhost:* mit Pfad und Query -> 127.0.0.1 gleich' => [true, 'http://localhost:*/oc/10.0?q=1', 'http://127.0.0.1:1234/oc/10.0?q=1', false],
			'localhost:* mit Subdomain-Erlaubnis -> 127.0.0.1' => [true, 'http://localhost:*', 'http://127.0.0.1:1234', true],
			// angenommen: als Loopback-IP registrierte Clients, Port beliebig
			'127.0.0.1 mit Port -> anderer Port' => [true, 'http://127.0.0.1:8080/cb', 'http://127.0.0.1:50000/cb', false],
			'127.0.0.1 ohne Port -> Port' => [true, 'http://127.0.0.1/cb', 'http://127.0.0.1:50000/cb', false],
			'127.0.0.1 -> [::1]' => [true, 'http://127.0.0.1/cb', 'http://[::1]:50000/cb', false],
			'[::1] -> 127.0.0.1' => [true, 'http://[::1]/cb', 'http://127.0.0.1:50000/cb', false],
			'127.0.0.1:* -> 127.0.0.1' => [true, 'http://127.0.0.1:*/cb', 'http://127.0.0.1:50000/cb', false],
			'[::1]:* -> [::1]' => [true, 'http://[::1]:*', 'http://[::1]:50000', false],
			// abgelehnt: Pfad und Query müssen passen
			'localhost:* -> 127.0.0.1 anderer Pfad' => [false, 'http://localhost:*', 'http://127.0.0.1:1234/evil', false],
			'localhost:* -> [::1] anderer Pfad' => [false, 'http://localhost:*', 'http://[::1]:1234/evil', false],
			'127.0.0.1/cb -> 127.0.0.1 ohne Pfad' => [false, 'http://127.0.0.1/cb', 'http://127.0.0.1:1234/', false],
			'localhost:* -> 127.0.0.1 mit Query' => [false, 'http://localhost:*', 'http://127.0.0.1:1234/?q=1', false],
			'localhost:* mit Query -> 127.0.0.1 andere Query' => [false, 'http://localhost:*/oc?q=1', 'http://127.0.0.1:1234/oc?q=2', false],
			// abgelehnt: andere Schemata
			'localhost:* -> https://127.0.0.1' => [false, 'http://localhost:*', 'https://127.0.0.1:1234', false],
			'localhost:* -> ftp://127.0.0.1' => [false, 'http://localhost:*', 'ftp://127.0.0.1:1234/', false],
			'https://127.0.0.1 ist keine Loopback-Registrierung' => [false, 'https://127.0.0.1/cb', 'https://127.0.0.1:1234/cb', false],
			'localhost:* -> http: ohne //' => [false, 'http://localhost:*', 'http:127.0.0.1:1234/', false],
			// abgelehnt: Benutzer-Info in der URI
			'localhost:* -> Benutzer an 127.0.0.1' => [false, 'http://localhost:*', 'http://user@127.0.0.1:1234/', false],
			'localhost:* -> Benutzer und Passwort an [::1]' => [false, 'http://localhost:*', 'http://user:pw@[::1]:1234/', false],
			'localhost:* -> Benutzer an localhost' => [false, 'http://localhost:*', 'http://user@localhost:1234/', false],
			'https -> Benutzer am registrierten Host' => [false, 'https://owncloud.org/cb', 'https://evil@owncloud.org/cb', false],
			// abgelehnt: fremde Hosts, auch wenn sie nach Loopback aussehen
			'localhost:* -> 127.0.0.1:Port@evil.com' => [false, 'http://localhost:*', 'http://127.0.0.1:1234@evil.com/', false],
			'localhost:* -> 127.0.0.1:Port\@evil.com' => [false, 'http://localhost:*', 'http://127.0.0.1:1234\@evil.com', false],
			'localhost:* -> 127.0.0.1.evil.com' => [false, 'http://localhost:*', 'http://127.0.0.1.evil.com:1234/', false],
			'localhost:* -> localhost.evil.com' => [false, 'http://localhost:*', 'http://localhost.evil.com:1234/', false],
			'localhost:* -> evil.com' => [false, 'http://localhost:*', 'http://evil.com:1234/', false],
			'localhost:* -> 127.0.0.2' => [false, 'http://localhost:*', 'http://127.0.0.2:1234/', false],
			'localhost:* -> IPv4 als IPv6' => [false, 'http://localhost:*', 'http://[::ffff:127.0.0.1]:1234/', false],
			'localhost:* -> IPv4 als Zahl' => [false, 'http://localhost:*', 'http://2130706433:1234/', false],
			// abgelehnt: keine Loopback-Registrierung, kein beliebiger Port
			'https-Registrierung -> 127.0.0.1' => [false, 'https://owncloud.org', 'http://127.0.0.1:1234/', false],
			'http-Registrierung -> 127.0.0.1' => [false, 'http://example.com/cb', 'http://127.0.0.1:1234/cb', false],
			'https -> anderer Port' => [false, 'https://owncloud.org/cb', 'https://owncloud.org:8443/cb', false],
			'https mit Platzhalter-Port' => [false, 'https://owncloud.org:*/cb', 'https://owncloud.org:8443/cb', false],
			'http mit Platzhalter-Port' => [false, 'http://owncloud.org:*', 'http://owncloud.org:1234', false],
			// abgelehnt: :* ist nur am Ende des Hosts ein Platzhalter, sonst
			// würde aus localhost:*.example.com der fremde Host localhost.example.com
			'localhost:*.example.com -> localhost.example.com' => [false, 'http://localhost:*.example.com', 'http://localhost.example.com:1234/', false],
			'localhost:*.example.com -> localhost.example.com ohne Port' => [false, 'http://localhost:*.example.com', 'http://localhost.example.com/', false],
			'127.0.0.1:*.example.com -> 127.0.0.1.example.com' => [false, 'http://127.0.0.1:*.example.com', 'http://127.0.0.1.example.com:1234/', false],
			// localhost bleibt, wie es war
			'localhost:* -> localhost beliebiger Port' => [true, 'http://localhost:*', 'http://localhost:1234', false],
			'localhost mit Port -> gleicher Port' => [true, 'http://localhost:8080/cb', 'http://localhost:8080/cb', false],
			'localhost mit Port -> anderer Port' => [false, 'http://localhost:8080/cb', 'http://localhost:9090/cb', false],
			'localhost mit Port -> 127.0.0.1' => [false, 'http://localhost:8080/cb', 'http://127.0.0.1:8080/cb', false],
			'127.0.0.1 -> localhost' => [false, 'http://127.0.0.1/cb', 'http://localhost:1234/cb', false],
			// eigene Schemata der Mobil-Apps unverändert
			'oc:// gleich' => [true, 'oc://android.owncloud.com', 'oc://android.owncloud.com', false],
			'oc:// anderer Host' => [false, 'oc://android.owncloud.com', 'oc://evil.com', false],
		];
	}

	/**
	 * @dataProvider providesUrlsToValidate
	 * @dataProvider providesLoopbackRedirects
	 * @param $expectedResult
	 * @param $expectedRedirect
	 * @param $actualRedirect
	 * @param $allowSubDomain
	 */
	public function testValidateRedirectUri($expectedResult, $expectedRedirect, $actualRedirect, $allowSubDomain): void {
		$this->assertEquals(
			$expectedResult,
			Utilities::validateRedirectUri(
				$expectedRedirect,
				$actualRedirect,
				$allowSubDomain
			)
		);
	}

	public function providesUrls(): array {
		return [
			[true, 'http://localhost:*'],
			[true, 'http://localhost:*/oc/10.0'],
			[true, 'oc://com.android'],
			[true, 'file://test.txt'],
			[false, 'x'],
			[false, 'http://owncloud.org:*'],
			// :* mitten im Host ist kein Platzhalter-Port
			[false, 'http://localhost:*.example.com'],
			[false, 'http://localhost:*example.com'],
			// Platzhalter-Port auch für die Loopback-IPs, nur mit http
			[true, 'http://127.0.0.1:*'],
			[true, 'http://127.0.0.1:*/cb'],
			[true, 'http://[::1]:*'],
			[true, 'http://[::1]/cb'],
			[false, 'https://127.0.0.1:*'],
			[false, 'http://127.0.0.2:*'],
		];
	}

	/**
	 * @dataProvider providesUrls
	 * @param $expected
	 * @param $url
	 */
	public function testIsValidUrl($expected, $url): void {
		$this->assertEquals($expected, Utilities::isValidUrl($url));
	}

	public function providesLoopbackHosts(): array {
		return [
			[true, 'localhost'],
			[true, '127.0.0.1'],
			[true, '[::1]'],
			[false, '::1'],
			[false, '127.0.0.2'],
			[false, 'localhost.evil.com'],
			[false, 'owncloud.org'],
			[false, ''],
		];
	}

	/**
	 * @dataProvider providesLoopbackHosts
	 */
	public function testIsLoopbackHost(bool $expected, string $hostname): void {
		self::assertSame($expected, Utilities::isLoopbackHost($hostname));
	}

	/**
	 * Token-Anfrage gegen die Autorisierung (RFC 6749 §4.1.3): dieselbe URI,
	 * gleichwertige Schreibweisen zählen als dieselbe.
	 */
	public function providesRedirectUriPairs(): array {
		return [
			'identisch' => [true, 'http://127.0.0.1:49152', 'http://127.0.0.1:49152'],
			'Schrägstrich ohne Pfad' => [true, 'http://127.0.0.1:49152', 'http://127.0.0.1:49152/'],
			'Groß-/Kleinschreibung von Schema und Host' => [true, 'http://localhost:1234/cb', 'HTTP://LOCALHOST:1234/cb'],
			'Standardport' => [true, 'https://owncloud.org/cb', 'https://owncloud.org:443/cb'],
			'anderer Port' => [false, 'http://127.0.0.1:49152', 'http://127.0.0.1:49153'],
			'andere Loopback-Adresse' => [false, 'http://127.0.0.1:49152', 'http://[::1]:49152'],
			'localhost statt IP' => [false, 'http://127.0.0.1:49152', 'http://localhost:49152'],
			'anderer Pfad' => [false, 'http://127.0.0.1:49152/a', 'http://127.0.0.1:49152/b'],
			'andere Query' => [false, 'https://owncloud.org/cb?a=1', 'https://owncloud.org/cb?a=2'],
			'Benutzer-Info' => [false, 'http://127.0.0.1:49152', 'http://u@127.0.0.1:49152'],
			'Subdomain' => [false, 'https://a.owncloud.org/cb', 'https://b.owncloud.org/cb'],
			'ungültig' => [false, 'http://127.0.0.1:49152', 'x'],
			'leer' => [false, 'http://127.0.0.1:49152', ''],
		];
	}

	/**
	 * @dataProvider providesRedirectUriPairs
	 */
	public function testIsSameRedirectUri(bool $expected, string $issuedFor, string $presented): void {
		self::assertSame($expected, Utilities::isSameRedirectUri($issuedFor, $presented));
	}

	public function testBase64url_encode(): void {
		$data = \random_bytes(32);
		$encoded = Utilities::base64url_encode($data);
		$this->assertEquals(43, \strlen($encoded));
		$this->assertFalse(\strpos($encoded, '+'));
		$this->assertFalse(\strpos($encoded, '/'));
		$this->assertFalse(\strpos($encoded, '='));
	}
}
