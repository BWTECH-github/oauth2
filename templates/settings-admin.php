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
script('oauth2', 'settings-admin');
style('oauth2', 'main');
style('oauth2', 'settings-admin');

if (!empty($_['clients'])) {
	$noClientsExtraClass = 'hidden';
	$clientTableExtraClass = '';
} else {
	$noClientsExtraClass = '';
	$clientTableExtraClass = 'hidden';
}
?>

<div class="section" id="oauth2">
	<h2 class="app-name"><?php p($l->t('OAuth 2.0')); ?></h2>

	<h3><?php p($l->t('Registered clients')); ?></h3>
	<p class="no-clients-message <?php p($noClientsExtraClass) ?>">
		<?php p($l->t('No clients registered.')); ?>
	</p>
	<table class="grid oauth2-clients <?php p($clientTableExtraClass) ?>" role="table">
		<?php /* Explizite Rollen: css/settings-admin.css stapelt die Zellen jeder
			Zeile (display: flex), dabei verwerfen Browser sonst die Tabellensemantik. */ ?>
		<thead role="rowgroup">
			<tr role="row">
				<th id="headerName" scope="col" role="columnheader"><?php p($l->t('Name')); ?></th>
				<th id="headerRedirectUri" scope="col" role="columnheader"><?php p($l->t('Redirection URI')); ?></th>
				<th id="headerClientIdentifier" scope="col" role="columnheader"><?php p($l->t('Client Identifier')); ?></th>
				<th id="headerSecret" scope="col" role="columnheader"><?php p($l->t('Secret')); ?></th>
				<th id="headerSubdomains" scope="col" role="columnheader"><?php p($l->t('Subdomains allowed')); ?></th>
				<th id="headerTrusted" scope="col" role="columnheader"><?php p($l->t('Trusted client')); ?></th>
				<th id="headerRemove" scope="col" role="columnheader"><span class="hidden-visually"><?php p($l->t('Delete')); ?></span></th>
			</tr>
		</thead>
		<tbody role="rowgroup">
		<?php
			foreach ($_['clients'] as $client):
				include('client.part.php');
			endforeach;
?>
		</tbody>
	</table>

	<h3><?php p($l->t('Add client')); ?></h3>
	<form id="oauth2-new-client" class="oauth2-new-client" novalidate>
		<div class="oauth2-field">
			<label for="oauth2-client-name"><?php p($l->t('Name')); ?></label>
			<input id="oauth2-client-name" name="name" type="text" autocomplete="off" spellcheck="false">
		</div>
		<div class="oauth2-field oauth2-field--uri">
			<label for="oauth2-redirect-uri"><?php p($l->t('Redirection URI')); ?></label>
			<input id="oauth2-redirect-uri" name="redirect_uri" type="text" inputmode="url" autocomplete="off" spellcheck="false" placeholder="https://">
		</div>
		<div class="oauth2-options">
			<span class="oauth2-option">
				<input name="allow_subdomains" id="allow_subdomains" type="checkbox" class="checkbox" value="1"/>
				<label for="allow_subdomains"><?php p($l->t('Allow subdomains'));?></label>
			</span>
			<span class="oauth2-option">
				<input name="trusted" id="trusted" type="checkbox" class="checkbox" value="1"/>
				<label for="trusted"><?php p($l->t('Trusted client'));?></label>
			</span>
		</div>
		<div class="oauth2-actions">
			<button id="oauth2_submit" type="submit" class="oco-btn-primary"><?php p($l->t('Add')); ?></button>
			<span id="oauth2_save_msg" class="msg" role="status" aria-live="polite"></span>
		</div>
	</form>
</div>
