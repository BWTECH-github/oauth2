/**
 * @author Project Seminar "sciebo@Learnweb" of the University of Muenster
 * @copyright Copyright (c) 2017, University of Muenster
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
 */

$(document).ready(function () {
	// Rueckfrage im Kerndialog statt window.confirm(): gleiche Optik wie jede
	// andere Rueckfrage, Escape bricht ab und der Kerndialog gibt den Fokus an
	// den Widerrufsknopf zurueck. form.submit() loest kein submit-Ereignis aus,
	// die Bestaetigung laeuft also nicht im Kreis.
	$('#oauth2').on('submit', 'form.delete', function (event) {
		event.preventDefault();
		var form = this;
		OC.dialogs.confirm(
			form.getAttribute('data-confirm'),
			t('oauth2', 'Revoke authorization'),
			function (confirmed) {
				if (confirmed) {
					form.submit();
				}
			},
			true,
			t('core', 'Cancel'),
			t('oauth2', 'Revoke authorization')
		);
	});
});
