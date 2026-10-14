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
 * Autorisierungscodes merken sich die redirect_uri ihrer Autorisierung, damit
 * der Token-Endpunkt dieselbe verlangen kann (RFC 6749 §4.1.3). Seit
 * Loopback-Clients jeden Port nutzen dürfen (RFC 8252 §7.3), passt zur
 * Registrierung weit mehr als die eine URI, an die der Code ging.
 *
 * TEXT statt fester Länge: Registriert sind höchstens 2000 Zeichen, die
 * tatsächliche URI kann mit Subdomain oder Fragment länger sein. NULL bleibt
 * bei Codes von vor dem Update; sie verfallen nach zehn Minuten.
 */
class Version20261014120000 implements ISchemaMigration {
	public function changeSchema(Schema $schema, array $options) {
		$prefix = $options['tablePrefix'];
		$table = $schema->getTable("{$prefix}oauth2_auth_codes");
		if (!$table->hasColumn('redirect_uri')) {
			$table->addColumn('redirect_uri', Types::TEXT, ['notNull' => false]);
		}
	}
}
