$(document).ready(function () {
	var $section = $('#oauth2');
	var $form = $('#oauth2-new-client');
	var msg = '#oauth2_save_msg';

	// Rueckmeldung im Statusfeld der Karte. OC.msg erwartet die Meldung unter
	// response.data.message, der Server schickt sie als errorMessage - ohne
	// diese Uebersetzung blieb "Speichern ..." stehen (rot bei Fehlern).
	function zeigeErgebnis(data) {
		if (data && data.status === 'success') {
			OC.msg.finishedSuccess(msg, t('settings', 'Saved'));
			return true;
		}
		OC.msg.finishedError(msg, (data && data.errorMessage) || t('core', 'Error'));
		return false;
	}

	function zeigeFehler() {
		OC.msg.finishedError(msg, t('core', 'Error'));
	}

	function loescheClient($button) {
		OC.msg.startAction(msg, t('oauth2', 'Deleting...'));
		$.post(
			OC.generateUrl('apps/oauth2/clients/{id}/delete', {id: $button.attr('data-id')}),
			{}
		).done(function (data) {
			if (!zeigeErgebnis(data) || !data.clientIdentifier) {
				$button.trigger('focus');
				return;
			}
			var $row = $section.find('.oauth2-identifier').filter(function () {
				return $(this).text() === data.clientIdentifier;
			}).closest('tr');
			// Fokus nicht ins Leere fallen lassen: naechster Loeschknopf,
			// sonst der vorige, sonst das Namensfeld des Formulars
			var $next = $row.next('tr').find('.oauth2-delete');
			if (!$next.length) {
				$next = $row.prev('tr').find('.oauth2-delete');
			}
			$row.remove();
			if ($section.find('.oauth2-clients .oauth2-identifier').length === 0) {
				$section.find('.no-clients-message').removeClass('hidden');
				$section.find('.oauth2-clients').addClass('hidden');
			}
			($next.length ? $next : $('#oauth2-client-name')).trigger('focus');
		}).fail(function () {
			zeigeFehler();
			$button.trigger('focus');
		});
	}

	// Loeschen nimmt dem Client alle Autorisierungen - deshalb mit Rueckfrage
	// im Kerndialog (Escape bricht ab, der Kerndialog gibt den Fokus zurueck)
	$section.on('click', '.oauth2-clients .oauth2-delete', function (event) {
		event.preventDefault();
		var $button = $(this);
		OC.dialogs.confirm(
			t('oauth2', 'Are you sure you want to delete this item?'),
			t('oauth2', 'Delete'),
			function (confirmed) {
				if (confirmed) {
					loescheClient($button);
				}
			},
			true,
			t('core', 'Cancel'),
			t('oauth2', 'Delete')
		);
	});

	// Echtes Formular: Enter in einem Feld legt den Client an wie der Knopf
	$form.on('submit', function (event) {
		event.preventDefault();
		OC.msg.startSaving(msg);
		$.post(
			OC.generateUrl('apps/oauth2/clients'),
			$form.serializeArray()
		).done(function (data) {
			if (!zeigeErgebnis(data)) {
				return;
			}
			$section.find('.oauth2-clients').removeClass('hidden');
			$section.find('.no-clients-message').addClass('hidden');
			$section.find('.oauth2-clients > tbody').append(data.rowHtml);
			$form.find('input[type="text"]').val('');
			$form.find('input[type="checkbox"]').prop('checked', false);
		}).fail(zeigeFehler);
	});
});
