<?php

namespace SMW\Tests\Utils;

use SMW\Utils\Logo;
use SMW\Tests\PHPUnitCompat;

/**
 * @covers \SMW\Utils\Logo
 * @group semantic-mediawiki
 *
 * @license GNU GPL v2+
 * @since 3.1
 *
 * @author mwjames
 */
class LogoTest extends \PHPUnit_Framework_TestCase {

	use PHPUnitCompat;

	public function testGet_Small() {
		$this->assertContains(
			'logo_small.png',
			Logo::get( '100x90' )
		);

		$this->assertContains(
			'logo_small.png',
			Logo::get( 'small' )
		);
	}

	public function testGet_Footer() {
		$this->assertContains(
			'logo_footer.png',
			Logo::get( 'footer' )
		);
	}

	public function testGet_Unkown() {
		$this->assertNull(
			Logo::get( 'Foo' )
		);
	}

}
