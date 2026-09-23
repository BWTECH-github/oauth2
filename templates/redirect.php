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
 */

/*
 * Weiterleitungsseite für vertrauenswürdige Clients (PageController::authorize).
 * js/redirect.js springt sofort zum Link-Ziel; der Link bleibt sichtbar, falls
 * der Sprung ausbleibt (Skript blockiert, fremdes Schema ohne Nutzergeste).
 */
style('oauth2', 'authorization');
script('oauth2', 'redirect');
?>

<div class="error">
	<p><b><?php p($l->t('Returning to “%s”', [$_['client_name']])); ?></b></p>
	<br>
	<p><?php p($l->t('You will be redirected to the application automatically. If nothing happens, use the button below.')); ?></p>
	<br>
	<a id="oauth2-redirect-link" class="button" href="<?php p($_['redirect_url']); ?>" autofocus><?php p($l->t('Continue to “%s”', [$_['client_name']])); ?></a>
</div>
