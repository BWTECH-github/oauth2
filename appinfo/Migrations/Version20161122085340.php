<?php
/**
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
 */

namespace OCA\oauth2\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use OC\DB\MDB2SchemaReader;
use OCP\Migration\ISchemaMigration;

class Version20161122085340 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		if ($schema->hasTable("{$prefix}oauth2_clients")) {
			$this->addMissingAuthCodesTable($schema, $prefix);
			return;
		}

		// not that valid ....
		$schemaReader = new MDB2SchemaReader(\OC::$server->getConfig(), \OC::$server->getDatabaseConnection()->getDatabasePlatform());
		$schemaReader->loadSchemaFromFile(__DIR__ . '/../database.xml', $schema);
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
	 * @param Schema $schema
	 * @param string $prefix
	 */
	private function addMissingAuthCodesTable(Schema $schema, $prefix) {
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
