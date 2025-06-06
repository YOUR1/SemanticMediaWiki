<?php

namespace SMW\Tests;

use SMW\PropertyAliasFinder;

/**
 * @covers \SMW\PropertyAliasFinder
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 2.1
 *
 * @author mwjames
 */
class PropertyAliasFinderTest extends \PHPUnit\Framework\TestCase {

	private $cache;
	private $store;

	protected function setUp(): void {
		parent::setUp();

		$this->cache = $this->getMockBuilder( '\Onoi\Cache\Cache' )
			->disableOriginalConstructor()
			->getMock();

		$this->store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();
	}

	public function testCanConstruct() {
		$languageIndependentPropertyLabels = [];

		$this->assertInstanceOf(
			PropertyAliasFinder::class,
			new PropertyAliasFinder( $this->cache )
		);
	}

	public function testFindPropertyAliasById() {
		$propertyAliases = [ 'Bar' => '_Foo' ];

		$instance = new PropertyAliasFinder(
			$this->cache,
			$propertyAliases
		);

		$this->assertEquals(
			$propertyAliases,
			$instance->getKnownPropertyAliases()
		);

		$this->assertEquals(
			'Bar',
			$instance->findPropertyAliasById( '_Foo' )
		);
	}

	public function testFindPropertyIdByAlias() {
		$canonicalPropertyAliases = [ 'Bar' => '_Foo' ];

		$instance = new PropertyAliasFinder(
			$this->cache,
			[],
			$canonicalPropertyAliases
		);

		$this->assertEquals(
			'_Foo',
			$instance->findPropertyIdByAlias( 'Bar' )
		);
	}

	public function testRegisterAliasByFixedLabel() {
		$instance = new PropertyAliasFinder(
			$this->cache
		);

		$instance->registerAliasByFixedLabel( '_Foo', 'Bar' );

		$this->assertEquals(
			'_Foo',
			$instance->findPropertyIdByAlias( 'Bar' )
		);
	}

	public function testRegisterAliasByFixedLabel_withContentLanguage() {
		$instance = new PropertyAliasFinder(
			$this->cache
		);

		$instance->setContentLanguageCode( 'en' );

		$instance->registerAliasByMsgKey( '_Foo', 'smw-bar' );

		$this->assertEquals(
			[ '⧼smw-bar⧽' => '_Foo' ],
			$instance->getKnownPropertyAliases()
		);
	}

	public function testGetKnownPropertyAliasesByLanguageCodeCached() {
		$this->cache->expects( $this->once() )
			->method( 'fetch' )
			->willReturn( [ '⧼smw-bar⧽' => '_Foo' ] );

		$instance = new PropertyAliasFinder(
			$this->cache
		);

		$instance->setContentLanguageCode( 'en' );
		$instance->registerAliasByMsgKey( '_Foo', 'smw-bar' );

		$this->assertEquals(
			[ '⧼smw-bar⧽' => '_Foo' ],
			$instance->getKnownPropertyAliasesByLanguageCode( 'en' )
		);

		$this->assertEquals(
			[ '⧼smw-bar⧽' => '_Foo' ],
			$instance->getKnownPropertyAliases()
		);
	}

	public function testGetKnownPropertyAliasesByLanguageCode() {
		$this->cache->expects( $this->once() )
			->method( 'fetch' )
			->willReturn( false );

		$instance = new PropertyAliasFinder(
			$this->cache
		);

		$instance->registerAliasByMsgKey( '_Foo', 'smw-bar' );

		$msgKey = '⧼smw-bar⧽';

		$this->assertEquals(
			[ $msgKey => '_Foo' ],
			$instance->getKnownPropertyAliasesByLanguageCode( 'en' )
		);
	}

}
