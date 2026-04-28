<?php
/**
 * @author Thomas Müller <thomas.mueller@tmit.eu>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
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
 *
 */

namespace OCA\OAuth2\Commands;

use OCA\OAuth2\Db\ClientMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RemoveClient extends Command {
	public function __construct(private readonly ClientMapper $clientMapper) {
		parent::__construct();
	}

	#[\Override]
	protected function configure(): void {
		$this
			->setName('oauth2:remove-client')
			->setDescription('Removes an OAuth2 client')
			->addArgument(
				'client-id',
				InputArgument::REQUIRED,
				'identifier of the client - used by the client during the implicit and authorization code flow'
			);
	}

	/**
	 * @throws \OCP\AppFramework\Db\MultipleObjectsReturnedException
	 */
	#[\Override]
	protected function execute(InputInterface $input, OutputInterface $output): int {
		$id = $input->getArgument('client-id');
		try {
			$client = $this->clientMapper->findByIdentifier($id);
			$this->clientMapper->delete($client);
			$output->writeln("Client <$id> has been deleted");
		} catch (DoesNotExistException) {
			$output->writeln("Client <$id> is unknown");
			return 1;
		}
		return 0;
	}
}
