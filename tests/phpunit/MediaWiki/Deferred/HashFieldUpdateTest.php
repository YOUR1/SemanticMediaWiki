<?php

namespace SMW\Tests\MediaWiki\Deferred;

use SMW\MediaWiki\Deferred\HashFieldUpdate;
use SMW\Tests\TestEnvironment;

/**
 * @covers \SMW\MediaWiki\Deferred\HashFieldUpdate
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 3.1
 *
 * @author mwjames
 */
class HashFieldUpdateTest extends \PHPUnit\Framework\TestCase {

	private $testEnvironment;
	private $connection;
	private $spyLogger;

	protected function setUp(): void {
		parent::setUp();
		$this->testEnvironment = new TestEnvironment();

		$this->spyLogger = $this->testEnvironment->getUtilityFactory()->newSpyLogger();

		$this->connection = $this->getMockBuilder( '\SMW\MediaWiki\Connection\Database' )
			->disableOriginalConstructor()
			->getMock();
	}

	protected function tearDown(): void {
		$this->testEnvironment->tearDown();
		parent::tearDown();
	}

	public function testCanConstruct() {
		$this->assertInstanceOf(
			HashFieldUpdate::class,
			new HashFieldUpdate( $this->connection, 42, '__hash__' )
		);
	}

	public function testAddUpdate() {
		$this->connection->expects( $this->once() )
			->method( 'update' )
			->with(
				$this->anything(),
				[ 'smw_hash' => '' ],
				[ 'smw_id' => 1001 ] );

		HashFieldUpdate::$isCommandLineMode = true;
		HashFieldUpdate::addUpdate( $this->connection, 1001, '' );
	}

	public function testDoUpdate() {
		$this->connection->expects( $this->once() )
			->method( 'update' )
			->with(
				$this->anything(),
				[ 'smw_hash' => '__hash__' ],
				[ 'smw_id' => 42 ] );

		$instance = new HashFieldUpdate(
			$this->connection,
			42,
			'__hash__'
		);

		$instance->setLogger(
			$this->spyLogger
		);

		$instance->doUpdate();
	}

}
