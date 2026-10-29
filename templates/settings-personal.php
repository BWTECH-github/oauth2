<?php
/**
 * @author Project Seminar "sciebo@Learnweb" of the University of Muenster
 * @copyright Copyright (c) 2017, University of Muenster, ownCloud GmbH
 * Modified by BW-Tech GmbH for owncloud.online (PHP 8.4).
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

/** @var \OCA\OAuth2\Db\Client $client */
script('oauth2', 'settings');
style('oauth2', 'main');
?>

<div class="section" id="oauth2">
	<h2 class="app-name"><?php p($l->t('OAuth 2.0')); ?></h2>

	<h3><?php p($l->t('Authorized Applications')); ?></h3>
	<?php if (empty($_['clients'])) { ?>
	<p class="oauth2-empty"><?php p($l->t('No applications authorized.')); ?></p>
	<?php } else { ?>
	<?php /* Eigener Behaelter: der Kern macht jede direkte Tabelle einer Karte
		zum Rollkasten (display: block), die Zeilen blieben dann so schmal wie
		ihr Inhalt statt ueber die Karte zu laufen. */ ?>
	<div class="oauth2-authorized-wrap">
	<table class="grid oauth2-authorized">
		<thead>
		<tr>
			<th id="headerName" scope="col"><?php p($l->t('Name')); ?></th>
			<th id="headerRemove" scope="col"><span class="hidden-visually"><?php p($l->t('Revoke authorization')); ?></span></th>
		</tr>
		</thead>
		<tbody>
		<?php foreach ($_['clients'] as $client) { ?>
			<tr>
				<td><?php p($client->getName()); ?></td>
				<td class="oauth2-col-remove">
					<form class="form-inline delete" data-confirm="<?php p($l->t('Are you sure you want to delete this item?')); ?>" action="<?php p($_['urlGenerator']->linkToRoute('oauth2.settings.revokeAuthorization', ['id' => $client->getId()])); ?>" method="post">
						<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" />
						<input type="submit" class="button icon-delete" value="" aria-label="<?php p($l->t('Revoke authorization')); ?> – <?php p($client->getName()); ?>" title="<?php p($l->t('Revoke authorization')); ?>">
					</form>
				</td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
	<?php } ?>
</div>
