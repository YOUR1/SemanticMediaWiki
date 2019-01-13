<?php

namespace SMW\SQLStore;

use Hooks;
use Onoi\MessageReporter\MessageReporter;
use Onoi\MessageReporter\MessageReporterAwareTrait;
use Onoi\MessageReporter\MessageReporterFactory;
use SMW\CompatibilityMode;
use SMW\MediaWiki\Jobs\EntityIdDisposerJob;
use SMW\MediaWiki\Jobs\PropertyStatisticsRebuildJob;
use SMW\Options;
use SMW\Site;
use SMW\Utils\File;
use SMW\Exception\FileNotWritableException;

/**
 * @private
 *
 * @license GNU GPL v2+
 * @since 2.5
 *
 * @author mwjames
 */
class Installer implements MessageReporter {

	use MessageReporterAwareTrait;

	/**
	 * Optimize option
	 */
	const OPT_TABLE_OPTIMIZE = 'installer.table.optimize';

	/**
	 * Job option
	 */
	const OPT_SUPPLEMENT_JOBS = 'installer.supplement.jobs';

	/**
	 * Import option
	 */
	const OPT_IMPORT = 'installer.import';

	/**
	 * Related to ExtensionSchemaUpdates
	 */
	const OPT_SCHEMA_UPDATE = 'installer.schema.update';

	/**
	 * `smw_hash` field population
	 */
	const POPULATE_HASH_FIELD_COMPLETE = 'populate.smw_hash_field_complete';

	/**
	 * @var TableSchemaManager
	 */
	private $tableSchemaManager;

	/**
	 * @var TableBuilder
	 */
	private $tableBuilder;

	/**
	 * @var TableIntegrityExaminer
	 */
	private $tableIntegrityExaminer;

	/**
	 * @var Options
	 */
	private $options;

	/**
	 * @var File
	 */
	private $file;

	/**
	 * @since 2.5
	 *
	 * @param TableSchemaManager $tableSchemaManager
	 * @param TableBuilder $tableBuilder
	 * @param TableIntegrityExaminer $tableIntegrityExaminer
	 */
	public function __construct( TableSchemaManager $tableSchemaManager, TableBuilder $tableBuilder, TableIntegrityExaminer $tableIntegrityExaminer ) {
		$this->tableSchemaManager = $tableSchemaManager;
		$this->tableBuilder = $tableBuilder;
		$this->tableIntegrityExaminer = $tableIntegrityExaminer;
		$this->options = new Options();
	}

	/**
	 * @since 3.0
	 *
	 * @param Options|array $options
	 */
	public function setOptions( $options ) {

		if ( !$options instanceof Options ) {
			$options = new Options( $options );
		}

		$this->options = $options;
	}

	/**
	 * @since 3.0
	 *
	 * @param File $file
	 */
	public function setFile( File $file ) {
		$this->file = $file;
	}

	/**
	 * @since 2.5
	 *
	 * @param boolean $verbose
	 */
	public function install( $verbose = true ) {

		// If for some reason the enableSemantics was not yet enabled
		// still allow to run the tables create in order for the
		// setup to be completed
		if ( CompatibilityMode::extensionNotEnabled() ) {
			CompatibilityMode::enableTemporaryCliUpdateMode();
		}

		$messageReporter = $this->newMessageReporter( $verbose );

		$messageReporter->reportMessage( "\nSelected storage engine: \"SMWSQLStore3\" (or an extension thereof)\n" );
		$messageReporter->reportMessage( "\nSetting up standard database configuration for SMW ...\n\n" );

		$this->tableBuilder->setMessageReporter(
			$messageReporter
		);

		$this->tableIntegrityExaminer->setMessageReporter(
			$messageReporter
		);

		foreach ( $this->tableSchemaManager->getTables() as $table ) {
			$this->tableBuilder->create( $table );
		}

		$this->tableIntegrityExaminer->checkOnPostCreation( $this->tableBuilder );

		$messageReporter->reportMessage( "\nDatabase initialized completed.\n" );

		$this->table_optimization( $messageReporter );
		$this->supplement_jobs( $messageReporter );

		$file = $this->file !== null ? $this->file : new File();

		self::setUpgradeKey( $GLOBALS, $messageReporter, $file );

		Hooks::run(
			'SMW::SQLStore::Installer::AfterCreateTablesComplete',
			[
				$this->tableBuilder,
				$messageReporter,
				$this->options
			]
		);

		if ( $this->options->has( self::OPT_SCHEMA_UPDATE ) ) {
			$messageReporter->reportMessage( "\n" );
		}

		return true;
	}

	/**
	 * @since 2.5
	 *
	 * @param boolean $verbose
	 */
	public function uninstall( $verbose = true ) {

		$messageReporter = $this->newMessageReporter( $verbose );

		$messageReporter->reportMessage( "\nSelected storage engine: \"SMWSQLStore3\" (or an extension thereof)\n" );
		$messageReporter->reportMessage( "\nDeleting all database content and tables generated by SMW ...\n\n" );

		$this->tableBuilder->setMessageReporter(
			$messageReporter
		);

		foreach ( $this->tableSchemaManager->getTables() as $table ) {
			$this->tableBuilder->drop( $table );
		}

		$this->tableIntegrityExaminer->checkOnPostDestruction( $this->tableBuilder );

		Hooks::run(
			'SMW::SQLStore::Installer::AfterDropTablesComplete',
			[
				$this->tableBuilder,
				$messageReporter,
				$this->options
			]
		);

		$messageReporter->reportMessage( "\nStandard and auxiliary tables with all corresponding data\n" );
		$messageReporter->reportMessage( "have been removed successfully.\n" );

		return true;
	}

	/**
	 * @since 3.0
	 *
	 * @param array $vars
	 */
	public static function loadSchema( &$vars ) {

		// @see #3506
		$file = File::dir( $vars['smwgConfigFileDir'] . '/.smw.json' );

		// Doesn't exist? The `Setup::init` will take care of it by trying to create
		// a new file and if it fails or unable to do so wail raise an exception
		// as we expect to have access to it.
		if ( is_readable( $file ) ) {
			$vars['smw.json'] = json_decode( file_get_contents( $file ), true );
		}
	}

	/**
	 * @since 3.0
	 *
	 * @param boolean $isCli
	 *
	 * @return boolean
	 */
	public static function isGoodSchema( $isCli = false ) {

		if ( $isCli && defined( 'MW_PHPUNIT_TEST' ) ) {
			return true;
		}

		if ( $isCli === false && ( PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg' ) ) {
			return true;
		}

		// #3563, Use the specific wiki-id as identifier for the instance in use
		$id = Site::id();

		if ( !isset( $GLOBALS['smw.json'][$id]['upgrade_key'] ) ) {
			return false;
		}

		return self::makeUpgradeKey( $GLOBALS ) === $GLOBALS['smw.json'][$id]['upgrade_key'];
	}

	/**
	 * @since 3.1
	 *
	 * @param array $vars
	 *
	 * @return []
	 */
	public static function incompleteTasks( $vars ) {

		$id = Site::id();
		$tasks = [];

		// Key field => [ value that constitutes the `INCOMPLETE` state, error msg ]
		$checks = [
			self::POPULATE_HASH_FIELD_COMPLETE => [ false, 'smw-install-incomplete-populate-hash-field' ]
		];

		foreach ( $checks as $key => $value ) {
			if ( isset( $vars['smw.json'][$id][$key] ) && $vars['smw.json'][$id][$key] === $value[0] ) {
				$tasks[] = $value[1];
			}
		}

		return $tasks;
	}

	/**
	 * @since 3.0
	 *
	 * @param array $vars
	 *
	 * @return string
	 */
	public static function makeUpgradeKey( $vars ) {

		// Only recognize those properties that require a fixed table
		$pageSpecialProperties = array_intersect(
			$vars['smwgPageSpecialProperties'],
			PropertyTableInfoFetcher::getFixedSpecialPropertyList()
		);

		// Sort to ensure the key contains the same order
		sort( $vars['smwgFixedProperties'] );
		sort( $pageSpecialProperties );

		// The following settings influence the "shape" of the tables required
		// therefore use the content to compute a key that reflects any
		// changes to them
		$components = [
			$vars['smwgUpgradeKey'],
			$vars['smwgFixedProperties'],
			$vars['smwgEnabledFulltextSearch'],
			$pageSpecialProperties
		];

		return sha1( json_encode( $components ) );
	}

	/**
	 * @since 3.0
	 *
	 * @param array $vars
	 * @param MessageReporter $messageReporter|null
	 * @param File $file|null
	 */
	public static function setUpgradeKey( $vars, MessageReporter $messageReporter = null, File $file = null ) {

		// #3563, Use the specific wiki-id as identifier for the instance in use
		$key = self::makeUpgradeKey( $vars );
		$id = Site::id();

		if (
			isset( $vars['smw.json'][$id]['upgrade_key'] ) &&
			$key === $vars['smw.json'][$id]['upgrade_key'] ) {
			return false;
		}

		if ( $messageReporter !== null ) {
			$messageReporter->reportMessage( "\nSetting $id upgrade key ..." );
		}

		self::setUpgradeFile( $vars, [ 'upgrade_key' => $key ], $file );

		if ( $messageReporter !== null ) {
			$messageReporter->reportMessage( "\n   ... done.\n" );
		}
	}

	/**
	 * @since 3.0
	 *
	 * @param File $file
	 * @param array $vars
	 */
	public static function setUpgradeFile( $vars, $args = [], File $file = null ) {

		$configFile = $vars['smwgConfigFileDir'] . '/.smw.json';

		if ( $file === null ) {
			$file = new File();
		}

		$id = Site::id();

		if ( !isset( $vars['smw.json'] ) ) {
			$vars['smw.json'] = [];
		}

		foreach ( $args as $key => $value ) {
			$vars['smw.json'][$id][$key] = $value;
		}

		try {
			$file->write(
				$configFile,
				json_encode( $vars['smw.json'], JSON_PRETTY_PRINT )
			);
		} catch( FileNotWritableException $e ) {
			// Users may not have `wgShowExceptionDetails` enabled and would
			// therefore not see the exception error message hence we fail hard
			// and die
			die(
				"\n\nERROR: " . $e->getMessage() . "\n" .
				"\n       The \"smwgConfigFileDir\" setting should point to a" .
				"\n       directory that is persistent and writable!\n"
			);
		}
	}

	/**
	 * @since 2.5
	 *
	 * @param string $message
	 */
	public function reportMessage( $message ) {
		ob_start();
		print $message;
		ob_flush();
		flush();
		ob_end_clean();
	}

	private function newMessageReporter( $verbose = true ) {

		if ( $this->messageReporter !== null && !$this->options->safeGet( self::OPT_SCHEMA_UPDATE, false ) ) {
			return $this->messageReporter;
		}

		$messageReporterFactory = MessageReporterFactory::getInstance();

		if ( !$verbose ) {
			$messageReporter = $messageReporterFactory->newNullMessageReporter();
		} else {
			$messageReporter = $messageReporterFactory->newObservableMessageReporter();
			$messageReporter->registerReporterCallback( [ $this, 'reportMessage' ] );
		}

		return $messageReporter;
	}

	private function table_optimization( $messageReporter ) {

		if ( !$this->options->safeGet( self::OPT_TABLE_OPTIMIZE, false ) ) {
			return $messageReporter->reportMessage( "\nSkipping the table optimization.\n" );
		}

		$messageReporter->reportMessage( "\nRunning table optimization (this may take a moment) ...\n\n" );

		foreach ( $this->tableSchemaManager->getTables() as $table ) {
			$this->tableBuilder->optimize( $table );
		}

		$messageReporter->reportMessage( "\nOptimization completed.\n" );
	}

	private function supplement_jobs( $messageReporter ) {

		if ( !$this->options->safeGet( self::OPT_SUPPLEMENT_JOBS, false ) ) {
			return $messageReporter->reportMessage( "\nSkipping supplement job creation.\n" );
		}

		$messageReporter->reportMessage( "\nAdding property statistics rebuild job ...\n" );

		$title = \Title::newFromText( 'SMW\SQLStore\Installer' );

		$job = new PropertyStatisticsRebuildJob(
			$title,
			PropertyStatisticsRebuildJob::newRootJobParams( 'smw.propertyStatisticsRebuild', $title ) + [ 'waitOnCommandLine' => true ]
		);

		$job->insert();

		$messageReporter->reportMessage( "   ... done.\n" );
		$messageReporter->reportMessage( "\nAdding entity disposer job ...\n" );

		$job = new EntityIdDisposerJob(
			$title,
			EntityIdDisposerJob::newRootJobParams( 'smw.entityIdDisposer', $title ) + [ 'waitOnCommandLine' => true ]
		);

		$job->insert();

		$messageReporter->reportMessage( "   ... done.\n" );
	}

}
