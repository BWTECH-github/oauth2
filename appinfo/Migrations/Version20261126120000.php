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

use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\Migration\IOutput;
use OCP\Migration\ISimpleMigration;

/**
 * Gespeicherte Weiterleitungs-URIs ohne Leerraum am Anfang und Ende. Mit
 * „http://localhost:* “ (Leerzeichen am Ende, etwa beim Eintragen kopiert)
 * galt der Platzhalter-Port nicht, jede Anmeldung des Clients endete mit
 * „Anfrage nicht gültig“. Gekürzt wird wie in Utilities::trimRedirectUri nur
 * Leerraum am Anfang und Ende; alles andere bleibt, wie es ist, auch Leerraum
 * mitten in der Adresse. Besteht die URI nur aus Leerraum, bleibt sie stehen:
 * Leer wäre sie unter Oracle NULL, und die Spalte verlangt einen Wert.
 */
class Version20261126120000 implements ISimpleMigration {
	/**
	 * @param IOutput $out
	 */
	public function run(IOutput $out) {
		$db = \OC::$server->getDatabaseConnection();
		$qb = $db->getQueryBuilder();
		$result = $qb->select('id', 'name', 'redirect_uri')
			->from('oauth2_clients')
			->execute();
		$clients = $result->fetchAllAssociative();
		$result->free();

		foreach ($clients as $client) {
			$redirectUri = (string)$client['redirect_uri'];
			$trimmed = \trim($redirectUri);
			if ($trimmed === $redirectUri || $trimmed === '') {
				continue;
			}
			$update = $db->getQueryBuilder();
			$update->update('oauth2_clients')
				->set('redirect_uri', $update->createNamedParameter($trimmed))
				->where($update->expr()->eq('id', $update->createNamedParameter((int)$client['id'], IQueryBuilder::PARAM_INT)))
				->execute();
			$out->info("Removed whitespace at the start or end of the redirect URI of the client <{$client['name']}>.");
		}
	}
}
