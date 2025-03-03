<?php

namespace SMW\Tests\Integration\Parser;

use MediaWiki\MediaWikiServices;
use ParserOptions;
use ParserOutput;
use RequestContext;
use SMW\Services\ServicesFactory as ApplicationFactory;
use SMW\Tests\PHPUnitCompat;
use SMW\Tests\TestEnvironment;
use Title;

/**
 * @group semantic-mediawiki
 *
 * @license GPL-2.0-or-later
 * @since 1.9
 *
 * @author mwjames
 */
class InTextAnnotationParserTemplateTransclusionTest extends \PHPUnit\Framework\TestCase {

	use PHPUnitCompat;

	private $semanticDataValidator;
	private $testEnvironment;
	private $applicationFactory;

	protected function setUp(): void {
		parent::setUp();

		$this->testEnvironment = new TestEnvironment();
		$this->semanticDataValidator = $this->testEnvironment->getUtilityFactory()->newValidatorFactory()->newSemanticDataValidator();

		$store = $this->getMockBuilder( '\SMW\Store' )
			->disableOriginalConstructor()
			->getMockForAbstractClass();

		$this->testEnvironment->registerObject( 'Store', $store );

		$this->applicationFactory = ApplicationFactory::getInstance();
	}

	protected function tearDown(): void {
		$this->testEnvironment->tearDown();
		parent::tearDown();
	}

	/**
	 * Helper method for processing a template transclusion by simulating template
	 * expensions using a callback to avoid having to integrate DB read/write
	 * process in order to access a Template
	 *
	 * @note Part of the routine has been taken from MW's ExtraParserTest
	 *
	 * @return text
	 */
	private function runTemplateTransclusion( Title $title, $text, $return ) {
		$parser = MediaWikiServices::getInstance()->getParserFactory()->create();
		$user = RequestContext::getMain()->getUser();
		$options = new ParserOptions( $user );
		$options->setTemplateCallback( static function ( $title, $parser = false ) use ( $return ) {
			$text = $return;
			$deps = [];

			return [
				'text' => $text,
				'finalTitle' => $title,
				'deps' => $deps
			];
		} );

		return $parser->preprocess( $text, $title, $options );
	}

	/**
	 * @dataProvider templateDataProvider
	 */
	public function testPreprocessTemplateAndParse( $namespace, array $settings, $text, $tmplValue, array $expected ) {
		$parserOutput = new ParserOutput();
		$title        = Title::newFromText( __METHOD__, $namespace );

		$outputText = $this->runTemplateTransclusion( $title, $text, $tmplValue );

		$this->testEnvironment->withConfiguration( $settings );

		$parserData = $this->applicationFactory->newParserData(
			$title,
			$parserOutput
		);

		$instance = $this->applicationFactory->newInTextAnnotationParser(
			$parserData
		);

		$instance->parse( $outputText );

		$this->assertContains(
			$expected['resultText'],
			$outputText
		);

		$parserData = $this->applicationFactory->newParserData(
			$title,
			$parserOutput
		);

		$this->assertInstanceOf(
			'\SMW\SemanticData',
			$parserData->getSemanticData()
		);

		$this->semanticDataValidator->assertThatPropertiesAreSet(
			$expected,
			$parserData->getSemanticData()
		);
	}

	public function templateDataProvider() {
		$provider = [];

		// #0 Bug 54967
		$provider[] = [
			NS_MAIN,
			[
				'smwgNamespacesWithSemanticLinks' => [ NS_MAIN => true ],
				'smwgLinksInValues'  => false,
				'smwgParserFeatures' => SMW_PARSER_INL_ERROR,
				'smwgMainCacheType'      => 'hash'
			],
			'[[Foo::{{Bam}}]]',
			'?bar',
			[
				'resultText'     => '[[:?bar|?bar]]',
				'propertyCount'  => 1,
				'propertyLabels' => [ 'Foo' ],
				'propertyValues' => [ '?bar' ]
			]
		];

		return $provider;
	}

}
