<?php

namespace SMW\Tests\DataValues\ValueValidators;

use SMW\DataValues\ValueValidators\CompoundConstraintValueValidator;
use SMW\Tests\PHPUnitCompat;
use SMW\Tests\TestEnvironment;

/**
 * @covers \SMW\DataValues\ValueValidators\CompoundConstraintValueValidator
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 2.4
 *
 * @author mwjames
 */
class CompoundConstraintValueValidatorTest extends \PHPUnit\Framework\TestCase {

	use PHPUnitCompat;

	private $spyLogger;

	protected function setUp(): void {
		parent::setUp();

		$this->spyLogger = TestEnvironment::getUtilityFactory()->newSpyLogger();
	}

	public function testCanConstruct() {
		$instance = new CompoundConstraintValueValidator();
		$instance->setLogger( $this->spyLogger );

		$this->assertInstanceOf(
			CompoundConstraintValueValidator::class,
			$instance
		);
	}

	public function testHasConstraintViolation() {
		$constraintValueValidator = $this->getMockBuilder( '\SMW\DataValues\ValueValidators\ConstraintValueValidator' )
			->disableOriginalConstructor()
			->getMock();

		$constraintValueValidator->expects( $this->once() )
			->method( 'validate' )
			->with( 'Foo' );

		$constraintValueValidator->expects( $this->once() )
			->method( 'hasConstraintViolation' )
			->willReturn( true );

		$instance = new CompoundConstraintValueValidator();
		$instance->setLogger( $this->spyLogger );

		$instance->registerConstraintValueValidator( $constraintValueValidator );

		$instance->validate( 'Foo' );

		$this->assertTrue(
			$instance->hasConstraintViolation()
		);
	}

	public function testMissingConstraintValueValidatorRegThrowsException() {
		$instance = new CompoundConstraintValueValidator();
		$instance->setLogger( $this->spyLogger );

		$this->expectException( '\RuntimeException' );
		$instance->validate( 'Foo' );
	}

}
