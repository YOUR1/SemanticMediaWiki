<?php

namespace SMW\Tests\Integration\Query;

use SMW\Tests\MwDBaseUnitTestCase;
use SMW\Tests\Utils\UtilityFactory;

use SMW\Query\Language\SomeProperty;
use SMW\DataValueFactory;

use SMWQueryParser as QueryParser;
use SMWQuery as Query;
use SMWPrintRequest as PrintRequest;
use SMWPropertyValue as PropertyValue;
use SMWExporter as Exporter;

/**
 * @group SMW
 * @group SMWExtension
 *
 * @group semantic-mediawiki-integration
 * @group semantic-mediawiki-query
 *
 * @group semantic-mediawiki-database
 * @group medium
 *
 * @license GNU GPL v2+
 * @since 2.0
 *
 * @author mwjames
 */
class RecordTypeQueryTest extends MwDBaseUnitTestCase {

	private $queryResultValidator;
	private $fixturesProvider;

	private $semanticDataFactory;
	private $dataValueFactory;

	private $queryParser;
	private $purgeableSubjects =  array();

	protected function setUp() {
		parent::setUp();

		$this->dataValueFactory = DataValueFactory::getInstance();
		$this->semanticDataFactory = UtilityFactory::getInstance()->newSemanticDataFactory();

		$this->queryResultValidator = UtilityFactory::getInstance()->newValidatorFactory()->newQueryResultValidator();

		$this->fixturesProvider = UtilityFactory::getInstance()->newFixturesFactory()->newFixturesProvider();
		$this->fixturesProvider->setupDependencies( $this->getStore() );

		$this->queryParser = new QueryParser();
	}

	protected function tearDown() {

		$fixturesCleaner = UtilityFactory::getInstance()->newFixturesFactory()->newFixturesCleaner();
		$fixturesCleaner
			->purgeSubjects( $this->purgeableSubjects )
			->purgeAllKnownFacts();

		parent::tearDown();
	}

	public function testSortableRecordQuery() {

		$this->getStore()->updateData(
			$this->fixturesProvider->getFactsheet( 'Berlin' )->asEntity()
		);

		$this->getStore()->updateData(
			$this->fixturesProvider->getFactsheet( 'Paris' )->asEntity()
		);

		$expected = array(
			$this->fixturesProvider->getFactsheet( 'Berlin' )->asSubject(),
			$this->fixturesProvider->getFactsheet( 'Berlin' )->getDemographics()->getSubject()
		);

		/**
		 * PopulationDensity is specified as `_rec`
		 *
		 * @query {{#ask: [[PopulationDensity::SomeDistinctValue]] }}
		 */
		$populationDensityValue = $this->fixturesProvider->getFactsheet( 'Berlin' )->getPopulationDensityValue();

		$description = new SomeProperty(
			$populationDensityValue->getProperty(),
			$populationDensityValue->getQueryDescription( $populationDensityValue->getWikiValue() )
		);

		$propertyValue = new PropertyValue( '__pro' );
		$propertyValue->setDataItem( $populationDensityValue->getProperty() );

		$query = new Query(
			$description,
			false,
			false
		);

		$query->querymode = Query::MODE_INSTANCES;

		$query->sortkeys = array(
			$populationDensityValue->getProperty()->getKey() => 'ASC'
		);

		$query->setLimit( 100 );

		$query->setExtraPrintouts( array(
			new PrintRequest( PrintRequest::PRINT_THIS, '' ),
			new PrintRequest( PrintRequest::PRINT_PROP, null, $propertyValue )
		) );

		$queryResult = $this->getStore()->getQueryResult( $query );

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$expected,
			$queryResult
		);
	}

	/**
	 * T23926
	 */
	public function testRecordsToContainSpecialCharactersCausedByHtmlEncoding() {

		$property = $this->fixturesProvider->getProperty( 'bookrecord' );

		$semanticData = $this->semanticDataFactory
			->newEmptySemanticData( __METHOD__ );

		$this->purgeableSubjects[] = $semanticData->getSubject();

		// MW parser runs htmlspecialchars on strings therefore
		// simulating it as well
		$dataValue = $this->dataValueFactory->newPropertyObjectValue(
			$property,
			htmlspecialchars( "Title with $&%'* special characters ; 2001" ),
			'',
			$semanticData->getSubject()
		);

		$semanticData->addDataValue( $dataValue	);

		$this->getStore()->updateData( $semanticData );

		/**
		 * @query "[[Book record::Title with $&%'* special characters;2001]]"
		 */
		$description = $this->queryParser
			->getQueryDescription( "[[Book record::Title with $&%'* special characters;2001]]" );

		$query = new Query(
			$description,
			false,
			true
		);

		$queryResult = $this->getStore()->getQueryResult( $query );

		$this->queryResultValidator->assertThatQueryResultHasSubjects(
			$semanticData->getSubject(),
			$queryResult
		);
	}

}
