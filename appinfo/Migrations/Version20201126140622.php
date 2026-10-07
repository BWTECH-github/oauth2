<?php
namespace OCA\oauth2\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

class Version20201126140622 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		// Eine Datenbank aus oauth2 0.1.0, die unter ownCloud 10 auf 0.2.x bis
		// 0.4.x gehoben wurde, hat Version20161122085340 schon verbucht, aber
		// nie eine oauth2_auth_codes bekommen - ohne sie bräche getTable() hier
		// das ganze Update ab.
		Version20161122085340::addMissingAuthCodesTable($schema, $prefix);
		$table = $schema->getTable("{$prefix}oauth2_auth_codes");
		if (!$table->hasColumn('code_challenge')) {
			$table->addColumn('code_challenge', Types::STRING, ['notNull' => false]);
		}
		if (!$table->hasColumn('code_challenge_method')) {
			$table->addColumn('code_challenge_method', Types::STRING, ['notNull' => false]);
		}
	}
}
