<?php if (isset($_['client'])) {
	/** @var \OCA\OAuth2\Db\Client $client */
	$client = $_['client'];
} ?>

<tr>
	<td><?php p($client->getName()); ?></td>
	<td><?php p($client->getRedirectUri()); ?></td>
	<td><code class="oauth2-identifier"><?php p($client->getIdentifier()); ?></code></td>
	<td><code><?php p($client->getSecret()); ?></code></td>
	<?php if ($client->getAllowSubdomains()): ?>
		<td class="icon-32 icon-checkmark"><span class="hidden-visually"><?php p($l->t('Yes')); ?></span></td>
	<?php else: ?>
		<td><span class="hidden-visually"><?php p($l->t('No')); ?></span></td>
	<?php endif; ?>
	<?php if ($client->getTrusted()): ?>
		<td class="icon-32 icon-checkmark"><span class="hidden-visually"><?php p($l->t('Yes')); ?></span></td>
	<?php else: ?>
		<td><span class="hidden-visually"><?php p($l->t('No')); ?></span></td>
	<?php endif; ?>
	<td>
		<button type="button" class="button icon-delete" data-id="<?php p($client->getId()) ?>" aria-label="<?php p($l->t('Delete')); ?>" title="<?php p($l->t('Delete')); ?>"></button>
	</td>
</tr>
