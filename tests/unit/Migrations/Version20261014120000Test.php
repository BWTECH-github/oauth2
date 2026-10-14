<?php
/**
 * @copyright Copyright (c) 2026, BW-Tech GmbH
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
 *
 */

namespace OCA\OAuth2\Tests\Unit\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\TextType;
use OC\DB\MDB2SchemaReader;
use OCA\oauth2\Migrations\Version20201126140622;
use OCA\oauth2\Migrations\Version20261014120000;
use Test\TestCase;

/**
 * Autorisierungscodes merken sich die redirect_uri der Autorisierung, damit
 * der Token-Endpunkt dieselbe verlangen kann (RFC 6749 §4.1.3). Die Spalte
 * kommt auf bestehenden Installationen wie auf neuen dazu; Codes von vor dem
 * Update bleiben ohne Wert.
 *
 * @group DB
 */
class Version20261014120000Test extends TestCase {
	/** @var string */
	private $prefix;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		// der Kern lädt App-Migrationen per require_once, nicht über den Autoloader
		foreach (['Version20161122085340', 'Version20201126140622', 'Version20261014120000'] as $class) {
			require_once __DIR__ . "/../../../appinfo/Migrations/$class.php";
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->prefix = \OC::$server->getConfig()->getSystemValue('dbtableprefix', 'oc_');
	}

	/**
	 * Schema einer Installation vor diesem Update: database.xml plus die
	 * PKCE-Spalten aus Version20201126140622.
	 */
	private function schemaBeforeTheUpdate(): Schema {
		$schema = new Schema();
		$reader = new MDB2SchemaReader(
			\OC::$server->getConfig(),
			\OC::$server->getDatabaseConnection()->getDatabasePlatform()
		);
		$reader->loadSchemaFromFile(__DIR__ . '/../../../appinfo/database.xml', $schema);
		(new Version20201126140622())->changeSchema($schema, ['tablePrefix' => $this->prefix]);
		return $schema;
	}

	/**
	 * Spalten aller Tabellen mit ihren Eigenschaften.
	 */
	private function columns(Schema $schema): array {
		$result = [];
		foreach ($schema->getTables() as $table) {
			foreach ($table->getColumns() as $column) {
				$result[$table->getName() . '.' . $column->getName()] = [
					\get_class($column->getType()),
					$column->getLength(),
					$column->getNotnull(),
					$column->getDefault(),
				];
			}
		}
		\ksort($result);
		return $result;
	}

	public function testAuthCodesGetANullableRedirectUriColumn(): void {
		$schema = $this->schemaBeforeTheUpdate();
		$table = $schema->getTable($this->prefix . 'oauth2_auth_codes');
		self::assertFalse($table->hasColumn('redirect_uri'));

		(new Version20261014120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		self::assertTrue($table->hasColumn('redirect_uri'));
		$column = $table->getColumn('redirect_uri');
		self::assertFalse($column->getNotnull(), 'Codes von vor dem Update haben keine URI');
		// so lang wie die URI der Weiterleitung selbst, ohne feste Grenze
		self::assertInstanceOf(TextType::class, $column->getType());
	}

	public function testNothingElseChanges(): void {
		$schema = $this->schemaBeforeTheUpdate();
		$before = $this->columns($schema);

		(new Version20261014120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		$after = $this->columns($schema);
		unset($after[$this->prefix . 'oauth2_auth_codes.redirect_uri']);
		self::assertSame($before, $after);
	}

	public function testSecondRunIsANoop(): void {
		$schema = $this->schemaBeforeTheUpdate();
		(new Version20261014120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);
		$afterFirst = $this->columns($schema);

		(new Version20261014120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		self::assertSame($afterFirst, $this->columns($schema));
	}
}
