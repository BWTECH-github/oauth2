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

style('oauth2', 'authorization');
?>

<div class="error oauth2-consent">
	<form class="oauth2-consent-form" action="" method="post">
		<p class="oauth2-consent-title">
			<strong><?php p($l->t('The “%s“ application would like permission to access your account', [$_['client_name']])); ?></strong>
		</p>
		<p><?php print_unescaped($l->t('You are logged in as %s.', [$_['current_user']])); ?></p>
		<p><?php p($l->t('The application will gain access to your username and will be allowed to manage files, folders and shares.')); ?></p>
		<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>" />
		<button type="submit" class="login-button" autofocus><?php p($l->t('Authorize')); ?></button>
	</form>
	<?php /* Zweite Wahl als Textverweis wie "Passwort vergessen?" der
		Anmeldeseite - als grauer Knopf sah er gesperrt aus. */ ?>
	<a class="oauth2-switch-user" href="<?php p($_['logout_url']); ?>"><?php p($l->t('Switch users to continue')); ?></a>
</div>
