<?php

namespace SMW\Property;

use RuntimeException;
use SMW\DataValueFactory;
use SMW\DIProperty;
use SMW\DIWikiPage;
use SMW\EntityCache;
use SMW\Localizer\Message;
use SMW\PropertyRegistry;
use SMW\Store;
use SMWDIBoolean as DIBoolean;

/**
 * This class should be accessed via ApplicationFactory::getPropertySpecificationLookup
 * to ensure a singleton instance.
 *
 * @license GPL-2.0-or-later
 * @since 2.4
 *
 * @author mwjames
 * @author thomas-topway-it for KM-A
 */
class SpecificationLookup {

	/**
	 * Identifies types used as part of the generate key to distinguish between
	 * instances that would create the same entity key
	 */
	const CACHE_NS_KEY_SPECIFICATIONLOOKUP = ':propertyspecificationlookup';
	const CACHE_NS_KEY_SPECIFICATIONLOOKUP_PREFERREDLABEL = ':propertyspecificationlookup:preferredlabel';
	const CACHE_NS_KEY_SPECIFICATIONLOOKUP_DESCRIPTION = ':propertyspecificationlookup:description';

	/**
	 * @var Store
	 */
	private $store;

	/**
	 * @var Cache
	 */
	private $entityCache;

	/**
	 * @var string
	 */
	private $languageCode = 'en';

	/**
	 * @var bool
	 */
	private $skipCache = false;

	/**
	 * @since 2.4
	 *
	 * @param Store $store
	 * @param EntityCache $entityCache
	 */
	public function __construct( Store $store, EntityCache $entityCache ) {
		$this->store = $store;
		$this->entityCache = $entityCache;
	}

	/**
	 * @since 3.1
	 *
	 * @param bool $skipCache
	 */
	public function skipCache( $skipCache = true ) {
		$this->skipCache = $skipCache;
	}

	/**
	 * @since 3.1
	 *
	 * @param string $languageCode
	 */
	public function setLanguageCode( $languageCode ) {
		$this->languageCode = $languageCode;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIWikiPage $subject
	 */
	public function invalidateCache( DIWikiPage $subject ) {
		$this->entityCache->invalidate( $subject );

		$this->entityCache->delete(
			$this->entityCache->makeCacheKey( self::CACHE_NS_KEY_SPECIFICATIONLOOKUP, $subject )
		);

		$this->entityCache->delete(
			$this->entityCache->makeCacheKey( self::CACHE_NS_KEY_SPECIFICATIONLOOKUP_PREFERREDLABEL, $subject )
		);

		$this->entityCache->delete(
			$this->entityCache->makeCacheKey( self::CACHE_NS_KEY_SPECIFICATIONLOOKUP_DESCRIPTION, $subject )
		);
	}

	/**
	 * @since 2.5
	 *
	 * @param DIProperty|DIWikiPage $source
	 * @param DIProperty $target
	 *
	 * @return ]|DataItem[
	 */
	public function getSpecification( $source, DIProperty $target ) {
		if ( $source instanceof DIProperty ) {
			$subject = $source->getCanonicalDiWikiPage();
		} elseif ( $source instanceof DIWikiPage ) {
			$subject = $source;
		} else {
			throw new RuntimeException( "Invalid request instance type" );
		}

		$key = $this->entityCache->makeCacheKey( self::CACHE_NS_KEY_SPECIFICATIONLOOKUP, $subject );
		$sub_key = $target->getKey();

		if (
			!$this->skipCache &&
			( $specification = $this->entityCache->fetchSub( $key, $sub_key ) ) !== false ) {
			return $specification;
		}

		$dataItems = $this->store->getPropertyValues(
			$subject,
			$target
		);

		if ( !is_array( $dataItems ) ) {
			$dataItems = [];
		}

		$this->entityCache->saveSub( $key, $sub_key, $dataItems, EntityCache::TTL_WEEK );
		$this->entityCache->associate( $subject, $key );

		return $dataItems;
	}

	/**
	 * @since 2.5
	 *
	 * @param DIProperty $property
	 *
	 * @return false|DataItem
	 */
	public function getFieldListBy( DIProperty $property ) {
		$fieldList = false;
		$dataItems = $this->getSpecification( $property, new DIProperty( '_LIST' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {
			$fieldList = end( $dataItems );
		}

		return $fieldList;
	}

	/**
	 * @since 2.5
	 *
	 * @param DIProperty $property
	 * @param string $languageCode
	 *
	 * @return string
	 */
	public function getPreferredPropertyLabelByLanguageCode( DIProperty $property, $languageCode = '' ) {
		$subject = $property->getCanonicalDiWikiPage();
		$key = $this->entityCache->makeCacheKey( self::CACHE_NS_KEY_SPECIFICATIONLOOKUP_PREFERREDLABEL, $subject );

		if ( ( $text = $this->entityCache->fetchSub( $key, $languageCode ) ) !== false ) {
			return $text;
		}

		$text = $this->getTextByLanguageCode(
			$subject,
			new DIProperty( '_PPLB' ),
			$languageCode
		);

		$this->entityCache->saveSub( $key, $languageCode, $text );
		$this->entityCache->associate( $subject, $key );

		return $text;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return bool
	 */
	public function hasUniquenessConstraint( DIProperty $property ) {
		$hasUniquenessConstraint = false;
		$dataItems = $this->getSpecification( $property, new DIProperty( '_PVUC' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {
			$hasUniquenessConstraint = end( $dataItems )->getBoolean();
		}

		return $hasUniquenessConstraint;
	}

	/**
	 * @since 3.0
	 *
	 * @param DIProperty $property
	 *
	 * @return DataItem|null
	 */
	public function getPropertyGroup( DIProperty $property ) {
		$dataItem = null;
		$dataItems = $this->getSpecification( $property, new DIProperty( '_INST' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {

			foreach ( $dataItems as $dataItem ) {
				$pv = $this->store->getPropertyValues(
					$dataItem,
					new DIProperty( '_PPGR' )
				);

				$di = end( $pv );

				if ( $di instanceof DIBoolean && $di->getBoolean() ) {
					return $dataItem;
				}
			}
		}

		return null;
	}

	/**
	 * @since 2.5
	 *
	 * @param DIProperty $property
	 *
	 * @return DataItem|null
	 */
	public function getExternalFormatterUri( DIProperty $property ) {
		$dataItem = null;
		$dataItems = $this->getSpecification( $property, new DIProperty( '_PEFU' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {
			$dataItem = end( $dataItems );
		}

		return $dataItem;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return string
	 */
	public function getAllowedPatternBy( DIProperty $property ) {
		$allowsPattern = '';
		$dataItems = $this->getSpecification( $property, new DIProperty( '_PVAP' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {
			$allowsPattern = end( $dataItems )->getString();
		}

		return $allowsPattern;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return array
	 */
	public function getAllowedValues( DIProperty $property ) {
		$allowsValues = [];
		$dataItems = $this->getSpecification( $property, new DIProperty( '_PVAL' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {
			$allowsValues = $dataItems;
		}

		return $allowsValues;
	}

	/**
	 * @since 2.5
	 *
	 * @param DIProperty $property
	 *
	 * @return array
	 */
	public function getAllowedListValues( DIProperty $property ) {
		$allowsListValue = [];
		$dataItems = $this->getSpecification( $property, new DIProperty( '_PVALI' ) );

		if ( is_array( $dataItems ) && $dataItems !== [] ) {
			$allowsListValue = $dataItems;
		}

		return $allowsListValue;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return int|false
	 */
	public function getDisplayPrecision( DIProperty $property ) {
		$displayPrecision = false;
		$dataItems = $this->getSpecification( $property, new DIProperty( '_PREC' ) );

		if ( $dataItems !== false && $dataItems !== [] ) {
			$dataItem = end( $dataItems );
			$displayPrecision = abs( (int)$dataItem->getNumber() );
		}

		return $displayPrecision;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 *
	 * @return array
	 */
	public function getDisplayUnits( DIProperty $property ) {
		$units = [];
		$dataItems = $this->getSpecification( $property, new DIProperty( '_UNIT' ) );

		if ( $dataItems !== false && $dataItems !== [] ) {
			foreach ( $dataItems as $dataItem ) {
				$units = array_merge( $units, preg_split( '/\s*,\s*/u', $dataItem->getString() ) );
			}
		}

		return $units;
	}

	/**
	 * @since 2.4
	 *
	 * @param DIProperty $property
	 * @param string $languageCode
	 * @param mixed|null $linker
	 *
	 * @return string
	 */
	public function getPropertyDescriptionByLanguageCode( DIProperty $property, $languageCode = '', $linker = null ) {
		$subject = $property->getCanonicalDiWikiPage();
		$key = $this->entityCache->makeCacheKey( self::CACHE_NS_KEY_SPECIFICATIONLOOKUP_DESCRIPTION, $subject );

		$sub_key = $languageCode . ':' . ( $linker === null ? '0' : '1' );

		if ( ( $text = $this->entityCache->fetchSub( $key, $sub_key ) ) !== false ) {
			return $text;
		}

		$text = $this->getTextByLanguageCode(
			$subject,
			new DIProperty( '_PDESC' ),
			$languageCode
		);

		// If a local property description wasn't available for a predefined property
		// the try to find a system translation
		if ( trim( $text ?? '' ) === '' && !$property->isUserDefined() ) {
			$text = $this->getPredefinedPropertyDescription( $property, $languageCode, $linker );
		}

		$text = trim( $text ?? '' );

		$this->entityCache->saveSub( $key, $sub_key, $text );
		$this->entityCache->associate( $subject, $key );

		return $text;
	}

	private function getPredefinedPropertyDescription( $property, $languageCode, $linker ) {
		$description = '';
		$key = $property->getKey();

		if ( ( $msgKey = PropertyRegistry::getInstance()->findPropertyDescriptionMsgKeyById( $key ) ) === '' ) {
			$msgKey = 'smw-property-predefined' . str_replace( '_', '-', strtolower( $key ) );
		}

		if ( !Message::exists( $msgKey ) ) {
			return $description;
		}

		$dataValue = DataValueFactory::getInstance()->newDataValueByItem(
			$property
		);

		$label = $dataValue->getFormattedLabel();

		$message = Message::get(
			[ $msgKey, $label ],
			$linker === null ? Message::ESCAPED : Message::PARSE,
			$languageCode
		);

		return $message;
	}

	/**
	 * @param DIWikiPage $subject
	 * @param DIProperty $property
	 * @param string $languageCode
	 *
	 * @return string
	 */
	private function getTextByLanguageCode( $subject, $property, $languageCode ) {
		// @TODO move in the constructor ?
		try {
			$monolingualTextLookup = $this->store->service( 'MonolingualTextLookup' );
		} catch ( \SMW\Services\Exception\ServiceNotFoundException $e ) {
			return '';
		}

		if ( $monolingualTextLookup === null ) {
			return '';
		}

		$dataValue = $monolingualTextLookup->newDataValue(
			$subject,
			$property,
			$languageCode
		);

		if ( $dataValue === null ) {
			$languageFalldownAndInverse = new LanguageFalldownAndInverse( $monolingualTextLookup, $subject, $property, $languageCode );

			// @see https://github.com/SemanticMediaWiki/SemanticMediaWiki/issues/5342
			[ $dataValue, $languageCode ] = $languageFalldownAndInverse->tryout();

			if ( $dataValue === null ) {
				return '';
			}
		}

		$dv = $dataValue->getTextValueByLanguageCode(
			$languageCode
		);

		return $dv->getShortWikiText();
	}

}
