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

namespace OCA\oauth2\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use OCP\Migration\ISchemaMigration;

/**
 * Ein ausgetauschtes Refresh-Token bleibt für die Gnadenfrist stehen:
 * rotated_at nennt den Zeitpunkt des Austauschs, successor_id das neue
 * Refresh-Token. Wiederholt ein Client den Austausch, weil die Antwort
 * verloren ging, bekommt er so dasselbe Paar statt invalid_grant.
 *
 * Dazu zwei Indizes: Der Token-Endpunkt sucht das Refresh-Token bei jedem
 * Austausch über token (bisher ohne Index, ein Durchlauf durch die ganze
 * Tabelle), und der Austausch räumt die Vorgänger über successor_id ab.
 * Nicht eindeutig: Ein Update soll nicht an einer Altlast scheitern.
 *
 * Bestehende Refresh-Tokens bleiben gültig (beide Spalten NULL).
 */
class Version20261019120000 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		$table = $schema->getTable("{$prefix}oauth2_refresh_tokens");
		if (!$table->hasColumn('rotated_at')) {
			$table->addColumn('rotated_at', Types::INTEGER, ['notNull' => false, 'unsigned' => true]);
		}
		if (!$table->hasColumn('successor_id')) {
			$table->addColumn('successor_id', Types::INTEGER, ['notNull' => false, 'unsigned' => true]);
		}
		if (!$table->hasIndex('oauth2_rt_token')) {
			$table->addIndex(['token'], 'oauth2_rt_token');
		}
		if (!$table->hasIndex('oauth2_rt_successor')) {
			$table->addIndex(['successor_id'], 'oauth2_rt_successor');
		}
	}
}
