<?php
/**
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
 */

namespace OCA\oauth2\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use OC\DB\MDB2SchemaReader;
use OCP\Migration\ISchemaMigration;

class Version20161122085340 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if ($schema->hasTable("{$prefix}oauth2_clients")) {
			self::addMissingAuthCodesTable($schema, $prefix);
			return;
		}

		// not that valid ....
		$schemaReader = new MDB2SchemaReader(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection()->getDatabasePlatform());
		$fromXml = new Schema();
		$schemaReader->loadSchemaFromFile(__DIR__ . '/../database.xml', $fromXml);
		$existing = \array_filter($fromXml->getTables(), function ($table) use ($schema) {
			return $schema->hasTable($table->getName());
		});
		if ($existing === []) {
			$schemaReader->loadSchemaFromFile(__DIR__ . '/../database.xml', $schema);
			return;
		}
		// Altbestand: oauth2_clients fehlt, andere Tabellen stehen schon. Die database.xml direkt zu laden
		// scheiterte an den vorhandenen Tabellen, also nur die fehlenden übernehmen
		self::copyMissingTables($fromXml, $schema);
	}

	/**
	 * Legt jede Tabelle aus $from, die in $to fehlt, mit Spalten und Indizes in $to an.
	 */
	private static function copyMissingTables(Schema $from, Schema $to) {
		foreach ($from->getTables() as $source) {
			if ($to->hasTable($source->getName())) {
				continue;
			}
			$table = $to->createTable($source->getName());
			foreach ($source->getColumns() as $column) {
				$table->addColumn($column->getName(), Type::getTypeRegistry()->lookupName($column->getType()), [
					'notnull' => $column->getNotnull(),
					'default' => $column->getDefault(),
					'length' => $column->getLength(),
					'precision' => $column->getPrecision(),
					'scale' => $column->getScale(),
					'fixed' => $column->getFixed(),
					'unsigned' => $column->getUnsigned(),
					'autoincrement' => $column->getAutoincrement(),
					'comment' => $column->getComment(),
				])->setPlatformOptions($column->getPlatformOptions());
			}
			foreach ($source->getIndexes() as $index) {
				if ($index->isPrimary()) {
					$table->setPrimaryKey($index->getColumns(), $index->getName());
				} elseif ($index->isUnique()) {
					$table->addUniqueIndex($index->getColumns(), $index->getName());
				} else {
					$table->addIndex($index->getColumns(), $index->getName());
				}
			}
		}
	}

	/**
	 * oauth2 0.1.0 (ownCloud 10.0, März bis Oktober 2017) hat seine Tabellen
	 * noch ohne Migrationen aus der database.xml angelegt - die Tabelle für
	 * Autorisierungscodes damals unter dem Namen oauth2_authorization_codes.
	 * 0.2.0 hat sie wegen der Oracle-Namenslänge in oauth2_auth_codes
	 * umbenannt, aber diese Migration ist bei vorhandener oauth2_clients einfach
	 * ausgestiegen. Eine aus 0.1.0 übernommene Datenbank bekam die Tabelle so
	 * nie, und Version20201126140622 bricht an ihr das ganze Update ab.
	 *
	 * Deshalb wird sie hier angelegt, wenn sie fehlt - mit derselben Definition
	 * wie in der database.xml; die späteren Spalten ergänzt
	 * Version20201126140622 wie auf jeder anderen Installation. Die alte Tabelle
	 * bleibt unangetastet: Autorisierungscodes gelten zehn Minuten, zum
	 * Übernehmen ist dort nichts.
	 *
	 * Wurde die 0.1.0-Datenbank unter ownCloud 10 schon auf 0.2.x bis 0.4.x
	 * gehoben, ist diese Migration dort bereits (ohne Wirkung) verbucht und
	 * läuft hier nicht mehr; dann ruft Version20201126140622 diese Methode.
	 *
	 * @param Schema $schema
	 * @param string $prefix
	 */
	public static function addMissingAuthCodesTable(Schema $schema, $prefix) {
		$tableName = "{$prefix}oauth2_auth_codes";
		if ($schema->hasTable($tableName)) {
			return;
		}

		$table = $schema->createTable($tableName);
		$table->addColumn('id', Types::INTEGER, [
			'autoincrement' => true,
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('code', Types::STRING, [
			'length' => 64,
			'notnull' => true,
		]);
		$table->addColumn('client_id', Types::INTEGER, [
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->addColumn('user_id', Types::STRING, [
			'length' => 64,
			'notnull' => true,
		]);
		$table->addColumn('expires', Types::INTEGER, [
			'unsigned' => true,
			'notnull' => true,
		]);
		$table->setPrimaryKey(['id']);

		\OC::$server->getLogger()->info(
			"Created the missing table $tableName (database from oauth2 0.1.0, which named it oauth2_authorization_codes)",
			['app' => 'oauth2']
		);
	}
}
