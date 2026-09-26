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
use OC\DB\MDB2SchemaReader;
use OCA\oauth2\Migrations\Version20161122085340;
use OCA\oauth2\Migrations\Version20170724162518;
use OCA\oauth2\Migrations\Version20201123114127;
use OCA\oauth2\Migrations\Version20201126140622;
use OCA\oauth2\Migrations\Version20220312110422;
use Test\TestCase;

/**
 * Eine Datenbank aus oauth2 0.1.0 (Tabelle oauth2_authorization_codes statt
 * oauth2_auth_codes) muss die ganze Migrationskette bis heute durchlaufen.
 *
 * Braucht die Datenbankplattform für MDB2SchemaReader, daher die Gruppe DB.
 *
 * @group DB
 */
class Version20161122085340Test extends TestCase {
	/** @var string */
	private $prefix;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		// der Kern lädt App-Migrationen per require_once, nicht über den Autoloader
		foreach (['Version20161122085340', 'Version20170724162518', 'Version20201123114127', 'Version20201126140622', 'Version20220312110422'] as $class) {
			require_once __DIR__ . "/../../../appinfo/Migrations/$class.php";
		}
	}

	protected function setUp(): void {
		parent::setUp();
		// MDB2SchemaReader setzt für *dbprefix* den konfigurierten Präfix ein
		$this->prefix = \OC::$server->getConfig()->getSystemValue('dbtableprefix', 'oc_');
	}

	private function schemaFromXml(string $file): Schema {
		$schema = new Schema();
		$reader = new MDB2SchemaReader(
			\OC::$server->getConfig(),
			\OC::$server->getDatabaseConnection()->getDatabasePlatform()
		);
		$reader->loadSchemaFromFile($file, $schema);
		return $schema;
	}

	private function schema010(): Schema {
		return $this->schemaFromXml(__DIR__ . '/fixtures/database-0.1.0.xml');
	}

	private function runChain(Schema $schema): void {
		$options = ['tablePrefix' => $this->prefix];
		(new Version20161122085340())->changeSchema($schema, $options);
		(new Version20170724162518())->changeSchema($schema, $options);
		(new Version20201123114127())->changeSchema($schema, $options);
		(new Version20201126140622())->changeSchema($schema, $options);
		(new Version20220312110422())->changeSchema($schema, $options);
	}

	private function columnNames(Schema $schema, string $table): array {
		$names = \array_keys($schema->getTable($this->prefix . $table)->getColumns());
		\sort($names);
		return $names;
	}

	/**
	 * Struktureller Fingerabdruck eines Schemas: Tabellen, Spalten mit ihren
	 * Eigenschaften und Indizes. Unabhängig vom Comparator-API der DBAL-Version.
	 */
	private function signature(Schema $schema): array {
		$tables = [];
		foreach ($schema->getTables() as $table) {
			$columns = [];
			foreach ($table->getColumns() as $column) {
				$columns[$column->getName()] = [
					\get_class($column->getType()),
					$column->getLength(),
					$column->getNotnull(),
					$column->getAutoincrement(),
					$column->getUnsigned(),
					$column->getDefault(),
				];
			}
			\ksort($columns);
			$indexes = [];
			foreach ($table->getIndexes() as $index) {
				$indexes[$index->getName()] = [$index->getColumns(), $index->isUnique(), $index->isPrimary()];
			}
			\ksort($indexes);
			$tables[$table->getName()] = [$columns, $indexes];
		}
		\ksort($tables);
		return $tables;
	}

	public function testSchemaFrom010GetsTheMissingAuthCodesTable(): void {
		$schema = $this->schema010();
		self::assertFalse($schema->hasTable($this->prefix . 'oauth2_auth_codes'));

		(new Version20161122085340())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		self::assertTrue($schema->hasTable($this->prefix . 'oauth2_auth_codes'));
		self::assertSame(
			['client_id', 'code', 'expires', 'id', 'user_id'],
			$this->columnNames($schema, 'oauth2_auth_codes')
		);
		$table = $schema->getTable($this->prefix . 'oauth2_auth_codes');
		self::assertSame(['id'], $table->getPrimaryKey()->getColumns());
		self::assertTrue($table->getColumn('id')->getAutoincrement());
		self::assertSame(64, $table->getColumn('code')->getLength());
		self::assertTrue($table->getColumn('user_id')->getNotnull());
		// die alte Tabelle bleibt, wie sie ist
		self::assertTrue($schema->hasTable($this->prefix . 'oauth2_authorization_codes'));
	}

	public function testNewTableMatchesTheDatabaseXmlDefinition(): void {
		$fromXml = $this->signature($this->schemaFromXml(__DIR__ . '/../../../appinfo/database.xml'));
		$schema = $this->schema010();

		(new Version20161122085340())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		$name = $this->prefix . 'oauth2_auth_codes';
		// Spalten gleich wie bei einer Neuinstallation; der Name des
		// Primärschlüssels ist Sache der Plattform und bleibt außen vor
		self::assertSame($fromXml[$name][0], $this->signature($schema)[$name][0]);
	}

	public function testWholeChainRunsOnSchemaFrom010(): void {
		$schema = $this->schema010();
		$before = $this->signature($schema);

		// vorher brach Version20201126140622 hier mit einer SchemaException ab
		$this->runChain($schema);

		self::assertContains('code_challenge', $this->columnNames($schema, 'oauth2_auth_codes'));
		self::assertContains('code_challenge_method', $this->columnNames($schema, 'oauth2_auth_codes'));
		self::assertContains('trusted', $this->columnNames($schema, 'oauth2_clients'));
		self::assertContains('access_token_id', $this->columnNames($schema, 'oauth2_refresh_tokens'));
		// vorhandene Spalten bleiben unverändert, es kommt nur hinzu
		$after = $this->signature($schema);
		foreach ($before as $table => [$columns]) {
			foreach ($columns as $column => $definition) {
				self::assertSame($definition, $after[$table][0][$column], "$table.$column");
			}
		}
	}

	public function testSecondRunIsANoop(): void {
		$schema = $this->schema010();
		$this->runChain($schema);
		$afterFirst = $this->signature($schema);

		$this->runChain($schema);

		self::assertSame($afterFirst, $this->signature($schema));
	}

	public function testCurrentSchemaIsLeftAlone(): void {
		$schema = $this->schemaFromXml(__DIR__ . '/../../../appinfo/database.xml');
		$before = $this->signature($schema);

		(new Version20161122085340())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		self::assertSame($before, $this->signature($schema));
	}

	public function testEmptySchemaStillGetsAllTables(): void {
		$schema = new Schema();

		(new Version20161122085340())->changeSchema($schema, ['tablePrefix' => $this->prefix]);

		foreach (['oauth2_clients', 'oauth2_auth_codes', 'oauth2_access_tokens', 'oauth2_refresh_tokens'] as $table) {
			self::assertTrue($schema->hasTable($this->prefix . $table), $table);
		}
	}
}
