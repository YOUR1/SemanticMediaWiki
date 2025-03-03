<?php

namespace SMW\Property\Annotators;

use SMW\Property\Annotator;
use SMW\SemanticData;

/**
 * Root object representing the initial data transfer object to interact with
 * a Decorator
 *
 * @ingroup SMW
 *
 * @license GPL-2.0-or-later
 * @since 1.9
 *
 * @author mwjames
 */
class NullPropertyAnnotator implements Annotator {

	/**
	 * @var SemanticData
	 */
	private $semanticData;

	/**
	 * @since 1.9
	 *
	 * @param SemanticData $semanticData
	 */
	public function __construct( SemanticData $semanticData ) {
		$this->semanticData = $semanticData;
	}

	/**
	 * @see Annotator::getSemanticData
	 *
	 * @since 1.9
	 */
	public function getSemanticData() {
		return $this->semanticData;
	}

	/**
	 * @see Annotator::addAnnotation
	 *
	 * @since 1.9
	 */
	public function addAnnotation() {
		return $this;
	}

}
