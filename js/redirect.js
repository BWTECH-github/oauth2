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

/*
 * Weiterleitungsseite für vertrauenswürdige Clients (templates/redirect.php).
 * Die Seite beendet die Weiterleitungskette nach dem Anmelde- bzw. 2FA-Formular;
 * erst dieser Sprung führt zur redirect_uri. Eine Navigation per Skript fällt
 * nicht unter die CSP-Direktive form-action der Formularseite. replace() hält
 * die Seite aus dem Verlauf, „Zurück“ führt nicht erneut hierher.
 */
(function () {
	'use strict';

	function redirect() {
		var link = document.getElementById('oauth2-redirect-link');
		var target = link ? link.getAttribute('href') : null;
		if (target) {
			window.location.replace(target);
		}
	}

	// Das Skript steht im Kopf der Seite, der Link erst im Rumpf
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', redirect);
	} else {
		redirect();
	}
})();
