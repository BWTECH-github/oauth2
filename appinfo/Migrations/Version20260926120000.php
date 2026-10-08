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
 *
 */

namespace OCA\oauth2\Migrations;

use OCP\Migration\IOutput;
use OCP\Migration\ISimpleMigration;

/**
 * oauth2 0.1.0 hat seinen Aufräumjob bei jeder Anfrage als Legacy-Job
 * eingetragen (appinfo/app.php): Klasse OC\BackgroundJob\Legacy\RegularJob mit
 * dem Argument [OCA\OAuth2\BackgroundJob\CleanUp, run]. Ab 0.2.0 kommt der Job
 * über info.xml als TimedJob OCA\OAuth2\BackgroundJob\CleanUp, der alte
 * Eintrag blieb aber in oc_jobs stehen - auch nach jedem späteren Update. Die
 * Kern-Klasse gibt es weder in ownCloud 10.16 noch hier; der Eintrag lässt sich
 * nicht bauen, wird von JobList::getNext() übersprungen und bei jedem Versuch
 * protokolliert. Entfernt wird genau dieser eine Eintrag, nichts sonst.
 */
class Version20260926120000 implements ISimpleMigration {
	public const LEGACY_JOB_CLASS = 'OC\BackgroundJob\Legacy\RegularJob';
	public const LEGACY_JOB_ARGUMENT = ['OCA\OAuth2\BackgroundJob\CleanUp', 'run'];

	/**
	 * @param IOutput $out
	 */
	public function run(IOutput $out) {
		$jobList = \OC::$server->getJobList();
		if (!$jobList->has(self::LEGACY_JOB_CLASS, self::LEGACY_JOB_ARGUMENT)) {
			return;
		}
		$jobList->remove(self::LEGACY_JOB_CLASS, self::LEGACY_JOB_ARGUMENT);
		$out->info('Removed the background job oauth2 0.1.0 had registered as ' . self::LEGACY_JOB_CLASS
			. '; the cleanup keeps running as OCA\OAuth2\BackgroundJob\CleanUp.');
	}
}
