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

namespace OCA\OAuth2\Tests\Unit\Migrations;

use OCA\oauth2\Migrations\Version20260926120000;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use Test\TestCase;

/**
 * Der Legacy-Job aus oauth2 0.1.0 wird entfernt - genau dieser Eintrag, sonst
 * nichts aus oc_jobs.
 *
 * @group DB
 */
class Version20260926120000Test extends TestCase {
	private const OTHER_ARGUMENT = ['OCA\Other\BackgroundJob\CleanUp', 'run'];

	/** @var IDBConnection */
	private $db;

	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();
		require_once __DIR__ . '/../../../appinfo/Migrations/Version20260926120000.php';
	}

	protected function setUp(): void {
		parent::setUp();
		$this->db = \OC::$server->getDatabaseConnection();
		$this->removeTestJobs();
	}

	protected function tearDown(): void {
		$this->removeTestJobs();
		parent::tearDown();
	}

	private function removeTestJobs(): void {
		$jobList = \OC::$server->getJobList();
		$jobList->remove(Version20260926120000::LEGACY_JOB_CLASS, Version20260926120000::LEGACY_JOB_ARGUMENT);
		$jobList->remove(Version20260926120000::LEGACY_JOB_CLASS, self::OTHER_ARGUMENT);
	}

	/**
	 * @return array[] alle Zeilen aus oc_jobs (id, class, argument)
	 */
	private function jobs(): array {
		$qb = $this->db->getQueryBuilder();
		$result = $qb->select('id', 'class', 'argument')
			->from('jobs')
			->orderBy('id')
			->execute();
		$rows = $result->fetchAllAssociative();
		$result->free();
		return $rows;
	}

	public function testLegacyJobFrom010IsRemovedAndNothingElse(): void {
		$jobList = \OC::$server->getJobList();
		// so hat oauth2 0.1.0 ihn eingetragen (appinfo/app.php)
		$jobList->add(Version20260926120000::LEGACY_JOB_CLASS, Version20260926120000::LEGACY_JOB_ARGUMENT);
		$jobList->add(Version20260926120000::LEGACY_JOB_CLASS, self::OTHER_ARGUMENT);
		$before = $this->jobs();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->once())->method('info');
		(new Version20260926120000())->run($out);

		self::assertFalse($jobList->has(Version20260926120000::LEGACY_JOB_CLASS, Version20260926120000::LEGACY_JOB_ARGUMENT));
		self::assertTrue($jobList->has(Version20260926120000::LEGACY_JOB_CLASS, self::OTHER_ARGUMENT));
		$expected = \array_values(\array_filter($before, static function (array $row) {
			return !($row['class'] === Version20260926120000::LEGACY_JOB_CLASS
				&& $row['argument'] === \json_encode(Version20260926120000::LEGACY_JOB_ARGUMENT));
		}));
		self::assertCount(\count($before) - 1, $expected);
		self::assertEquals($expected, $this->jobs());
	}

	public function testSecondRunIsANoop(): void {
		\OC::$server->getJobList()->add(Version20260926120000::LEGACY_JOB_CLASS, Version20260926120000::LEGACY_JOB_ARGUMENT);
		(new Version20260926120000())->run($this->createMock(IOutput::class));
		$afterFirst = $this->jobs();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->never())->method('info');
		(new Version20260926120000())->run($out);

		self::assertEquals($afterFirst, $this->jobs());
	}

	public function testWithoutLegacyJobNothingChanges(): void {
		$before = $this->jobs();

		$out = $this->createMock(IOutput::class);
		$out->expects($this->never())->method('info');
		(new Version20260926120000())->run($out);

		self::assertEquals($before, $this->jobs());
	}
}
