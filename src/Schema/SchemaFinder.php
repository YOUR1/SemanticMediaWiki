<?php

namespace SMW\Schema;

use Onoi\Cache\Cache;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\Listener\ChangeListener\ChangeListeners\PropertyChangeListener;
use SMW\Listener\ChangeListener\ChangeRecord;
use SMW\Property\SpecificationLookup;
use SMW\Store;
use SMWDataItem as DataItem;
use SMWDIBlob as DIBlob;

/**
 * @private
 *
 * @license GPL-2.0-or-later
 * @since 3.1
 *
 * @author mwjames
 */
class SchemaFinder {

	/**
	 * Persistent cache namespace
	 */
	const CACHE_NAMESPACE = 'smw:schema';

	/**
	 * List by types
	 */
	const TYPE_LIST = 'type/list';

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @var SpecificationLookup
	 */
	private $propertySpecificationLookup;

	/**
	 * @var Cache
	 */
	private $cache;

	/**
	 * @var int
	 */
	private $cacheTTL;

	/**
	 * @since 3.1
	 *
	 * @param Store $store
	 * @param SpecificationLookup $propertySpecificationLookup
	 * @param Cache $cache
	 */
	public function __construct( Store $store, SpecificationLookup $propertySpecificationLookup, Cache $cache ) {
		$this->store = $store;
		$this->propertySpecificationLookup = $propertySpecificationLookup;
		$this->cache = $cache;
		$this->cacheTTL = 60 * 60 * 24 * 7;
	}

	/**
	 * @since 3.2
	 *
	 * @param PropertyChangeListener $propertyChangeListener
	 */
	public function registerPropertyChangeListener( PropertyChangeListener $propertyChangeListener ) {
		$propertyChangeListener->addListenerCallback( new DIProperty( '_SCHEMA_TYPE' ), [ $this, 'invalidateCache' ] );
	}

	/**
	 * @since 3.2
	 *
	 * @param DIProperty $property
	 * @param ChangeRecord $changeRecord
	 */
	public function invalidateCache( DIProperty $property, ChangeRecord $changeRecord ) {
		if ( $property->getKey() !== '_SCHEMA_TYPE' ) {
			return;
		}

		foreach ( $changeRecord as $record ) {

			if ( !$record->has( 'row.o_hash' ) ) {
				continue;
			}

			$this->cache->delete(
				smwfCacheKey( self::CACHE_NAMESPACE, [ self::TYPE_LIST, $record->get( 'row.o_hash' ) ] )
			);
		}
	}

	/**
	 * @since 3.1
	 */
	public function getConstraintSchema( DataItem $dataItem ): ?SchemaList {
		return $this->newSchemaList( $dataItem, new DIProperty( '_CONSTRAINT_SCHEMA' ) );
	}

	/**
	 * @since 3.1
	 */
	public function newSchemaList( DataItem $dataItem, DIProperty $property ): ?SchemaList {
		$dataItems = $this->propertySpecificationLookup->getSpecification(
			$dataItem,
			$property
		);

		if ( $dataItems === null || $dataItems === false ) {
			return null;
		}

		$schemaList = [];

		foreach ( $dataItems as $subject ) {
			$this->findSchemaDefinition( $subject, $schemaList );
		}

		return new SchemaList( $schemaList );
	}

	/**
	 * @since 3.1
	 *
	 * @param string $type
	 *
	 * @return SchemaList
	 */
	public function getSchemaListByType( $type ) {
		$schemaList = [];
		$key = smwfCacheKey( self::CACHE_NAMESPACE, [ self::TYPE_LIST, $type ] );

		if ( ( $subjects = $this->cache->fetch( $key ) ) === false ) {
			$subjects = [];

			$dataItems = $this->store->getPropertySubjects(
				new DIProperty( '_SCHEMA_TYPE' ),
				new DIBlob( $type )
			);

			foreach ( $dataItems as $dataItem ) {
				$subjects[] = $dataItem->getSerialization();
			}

			$this->cache->save( $key, $subjects, $this->cacheTTL );
		}

		foreach ( $subjects as $subject ) {
			$this->findSchemaDefinition( DIWikiPage::doUnserialize( $subject ), $schemaList );
		}

		return new SchemaList( $schemaList );
	}

	private function findSchemaDefinition( $subject, &$schemaList ) {
		if ( !$subject instanceof DIWikiPage ) {
			return;
		}

		$definitions = $this->propertySpecificationLookup->getSpecification(
			$subject,
			new DIProperty( '_SCHEMA_DEF' )
		);

		$name = str_replace( '_', ' ', $subject->getDBKey() );

		foreach ( $definitions as $definition ) {
			$content = [];

			if ( $definition->getString() !== '' ) {
				$content = json_decode( $definition->getString(), true );
			}

			$schemaList[] = new SchemaDefinition(
				$name,
				$content
			);
		}
	}

}
