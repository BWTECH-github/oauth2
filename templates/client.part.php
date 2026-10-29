<?php if (isset($_['client'])) {
	/** @var \OCA\OAuth2\Db\Client $client */
	$client = $_['client'];
} ?>

<?php /* Die sichtbaren Beschriftungen in den Zellen stehen fuer die gestapelte
	Darstellung (css/settings-admin.css); Vorleseprogramme bekommen sie ueber die
	Spaltenkoepfe, deshalb aria-hidden. */ ?>
<tr role="row">
	<td role="cell" class="oauth2-col-name"><?php p($client->getName()); ?></td>
	<td role="cell" class="oauth2-col-uri"><span class="oauth2-cell-label" aria-hidden="true"><?php p($l->t('Redirection URI')); ?></span><span class="oauth2-value"><?php p($client->getRedirectUri()); ?></span></td>
	<td role="cell" class="oauth2-col-code"><span class="oauth2-cell-label" aria-hidden="true"><?php p($l->t('Client Identifier')); ?></span><code class="oauth2-identifier"><?php p($client->getIdentifier()); ?></code></td>
	<td role="cell" class="oauth2-col-code"><span class="oauth2-cell-label" aria-hidden="true"><?php p($l->t('Secret')); ?></span><code><?php p($client->getSecret()); ?></code></td>
	<?php foreach ([[$client->getAllowSubdomains(), $l->t('Subdomains allowed')], [$client->getTrusted(), $l->t('Trusted client')]] as [$an, $titel]): ?>
		<td role="cell" class="oauth2-col-flag<?php if (!$an): ?> oauth2-col-flag--off<?php endif; ?>">
			<?php if ($an): ?><span class="oco-chip oco-chip--neutral" aria-hidden="true"><?php p($titel); ?></span><?php endif; ?>
			<span class="hidden-visually"><?php p($an ? $l->t('Yes') : $l->t('No')); ?></span>
		</td>
	<?php endforeach; ?>
	<td role="cell" class="oauth2-col-remove">
		<button type="button" class="button icon-delete oauth2-delete" data-id="<?php p($client->getId()) ?>" aria-label="<?php p($l->t('Delete')); ?> – <?php p($client->getName()); ?>" title="<?php p($l->t('Delete')); ?>"></button>
	</td>
</tr>
