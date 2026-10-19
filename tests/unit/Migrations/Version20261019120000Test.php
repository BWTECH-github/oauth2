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
use Doctrine\DBAL\Types\IntegerType;
use OC\DB\MDB2SchemaReader;
use OCA\oauth2\Migrations\Version20201126140622;
use OCA\oauth2\Migrations\Version20261014120000;
use OCA\oauth2\Migrations\Version20261019120000;
use Test\TestCase;

/**
 * Refresh-Tokens bekommen rotated_at und successor_id (Gnadenfrist nach dem
 * Austausch) sowie Indizes auf token und successor_id. Bestehende Zeilen
 * bleiben unverbraucht (NULL).
 *
 * @group DB
 */
class Version20261019120000Test extends TestCase {
	/** @var string */
	private $prefix;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		// der Kern lädt App-Migrationen per require_once, nicht über den Autoloader
		foreach (['Version20161122085340', 'Version20201126140622', 'Version20261014120000', 'Version20261019120000'] as $class) {
			require_once __DIR__ . "/../../../appinfo/Migrations/$class.php";
		}
	}

	protected function setUp(): void {
		parent::setUp();
		$this->prefix = \OC::$server->getConfig()->getSystemValue('dbtableprefix', 'oc_');
	}

	/**
	 * Schema einer Installation von 0.9.0: database.xml plus die Spalten aus
	 * Version20201126140622 und Version20261014120000.
	 */
	private function schemaBeforeTheUpdate(): Schema {
		$schema = new Schema();
		$reader = new MDB2SchemaReader(
			\OC::$server->getConfig(),
			\OC::$server->getDatabaseConnection()->getDatabasePlatform()
		);
		$reader->loadSchemaFromFile(__DIR__ . '/../../../appinfo/database.xml', $schema);
		(new Version20201126140622())->changeSchema($schema, ['tablePrefix' => $this->prefix]);
		(new Version20261014120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);
		return $schema;
	}

	/**
	 * Spalten und Indizes aller Tabellen mit ihren Eigenschaften.
	 */
	private function describe(Schema $schema): array {
		$result = [];
		foreach ($schema->getTables() as $table) {
			foreach ($table->getColumns() as $column) {
				$result['column ' . $table->getName() . '.' . $column->getName()] = [
					\get_class($column->getType()),
					$column->getLength(),
					$column->getNotnull(),
					$column->getDefault(),
					$column->getUnsigned(),
				];
			}
			foreach ($table->getIndexes() as $index) {
				$result['index ' . $table->getName() . '.' . $index->getName()] = [$index->getColumns(), $index->isUnique()];
			}
		}
		\ksort($result);
		return $result;
	}

	public function testRefreshTokensGetRotationColumnsAndIndexes(): void {
		$schema = $this->schemaBeforeTheUpdate();
		$table = $schema->getTable($this->prefix . 'oauth2_refresh_tokens');
		self::assertFalse($table->hasColumn('rotated_at'));
		self::assertFalse($table->hasColumn('successor_id'));

		(new Version20261019120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		foreach (['rotated_at', 'successor_id'] as $name) {
			self::assertTrue($table->hasColumn($name));
			$column = $table->getColumn($name);
			self::assertInstanceOf(IntegerType::class, $column->getType());
			self::assertFalse($column->getNotnull(), 'bestehende Refresh-Tokens bleiben unverbraucht');
			self::assertTrue($column->getUnsigned());
		}
		self::assertTrue($table->hasIndex('oauth2_rt_token'));
		self::assertSame(['token'], $table->getIndex('oauth2_rt_token')->getColumns());
		// nicht eindeutig: ein Update scheitert nicht an doppelten Altlasten
		self::assertFalse($table->getIndex('oauth2_rt_token')->isUnique());
		self::assertTrue($table->hasIndex('oauth2_rt_successor'));
		self::assertSame(['successor_id'], $table->getIndex('oauth2_rt_successor')->getColumns());
	}

	public function testNothingElseChanges(): void {
		$schema = $this->schemaBeforeTheUpdate();
		$before = $this->describe($schema);

		(new Version20261019120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		$after = $this->describe($schema);
		foreach (['column %s.rotated_at', 'column %s.successor_id', 'index %s.oauth2_rt_token', 'index %s.oauth2_rt_successor'] as $key) {
			unset($after[\sprintf($key, $this->prefix . 'oauth2_refresh_tokens')]);
		}
		self::assertSame($before, $after);
	}

	public function testSecondRunIsANoop(): void {
		$schema = $this->schemaBeforeTheUpdate();
		(new Version20261019120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);
		$afterFirst = $this->describe($schema);

		(new Version20261019120000())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		self::assertSame($afterFirst, $this->describe($schema));
	}
}
