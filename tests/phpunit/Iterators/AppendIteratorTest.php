<?php

namespace SMW\Tests\Iterators;

use SMW\Iterators\AppendIterator;
use SMW\Tests\PHPUnitCompat;

/**
 * @covers \SMW\Iterators\AppendIterator
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 3.0
 *
 * @author mwjames
 */
class AppendIteratorTest extends \PHPUnit\Framework\TestCase {

	use PHPUnitCompat;

	public function testCanConstruct() {
		$this->assertInstanceOf(
			AppendIterator::class,
			new AppendIterator()
		);
	}

	/**
	 * @dataProvider iterableProvider
	 */
	public function testCount( $iterable, $expected ) {
		$instance = new AppendIterator();
		$instance->add( $iterable );

		$this->assertEquals(
			$expected,
			$instance->count()
		);
	}

	public function testAddOnNonIterableThrowsException() {
		$instance = new AppendIterator();

		$this->expectException( 'RuntimeException' );
		$instance->add( 'Foo' );
	}

	public function iterableProvider() {
		$provider[] = [
			[
				1, 42, 1001, 9999
			],
			4
		];

		$iterator = new AppendIterator();
		$iterator->add( [ 0, 1 ] );

		$provider[] = [
			$iterator,
			2
		];

		$iterator = new AppendIterator();
		$iterator->add( [ 0, 1 ] );
		$iterator->add( $iterator );

		$provider[] = [
			$iterator,
			4
		];

		return $provider;
	}

}
