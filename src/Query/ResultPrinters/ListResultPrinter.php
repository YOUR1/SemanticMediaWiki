<?php

namespace SMW\Query\ResultPrinters;

use ParamProcessor\ParamDefinition;
use SMW\Localizer\Message;
use SMW\Query\QueryResult;
use SMW\Query\ResultPrinters\ListResultPrinter\ListResultBuilder;

/**
 * Print query results in lists.
 *
 * @license GPL-2.0-or-later
 *
 * @author Markus Krötzsch
 */

/**
 * SMW's printer for results in lists.
 * The implementation covers comma-separated lists, ordered and unordered lists.
 * List items may be formatted using templates.
 *
 * In the code below, one list item (with all extra information displayed for
 * it) is called a "row", while one entry in this row is called a "field".
 * Every field may in turn contain many "values".
 */
class ListResultPrinter extends ResultPrinter {

	/**
	 * Get a human readable label for this printer.
	 *
	 * @return string
	 */
	public function getName() {
		// Give grep a chance to find the usages:
		// smw_printername_list, smw_printername_ol,smw_printername_ul, smw_printername_plainlist, smw_printername_template
		return Message::get( 'smw_printername_' . $this->mFormat, Message::TEXT, Message::USER_LANGUAGE );
	}

	/**
	 * @see ResultPrinter::isDeferrable
	 *
	 * {@inheritDoc}
	 */
	public function isDeferrable() {
		return true;
	}

	/**
	 * @see ResultPrinter::getResultText
	 *
	 * @param QueryResult $queryResult
	 * @param $outputMode
	 *
	 * @return string
	 */
	protected function getResultText( QueryResult $queryResult, $outputMode ) {
		$builder = $this->getBuilder( $queryResult );

		$this->hasTemplates = $this->hasTemplates();

		$result = $builder->getResultText() . $this->getFurtherResultsText( $queryResult, $outputMode );

		if ( $result == '' ) {
			return $this->params['default'];
		}

		return $result;
	}

	/**
	 * @param QueryResult $queryResult
	 *
	 * @return ListResultBuilder
	 */
	private function getBuilder( QueryResult $queryResult ) {
		$builder = new ListResultBuilder( $queryResult, $this->mLinker, $GLOBALS['smwgPlainList'] );

		$builder->set( $this->params );

		$builder->set( [
			'link-first' => $this->mLinkFirst,
			'link-others' => $this->mLinkOthers,
			'show-headers' => $this->mShowHeaders,
		] );

		if ( $this->params['template'] !== '' && isset( $this->fullParams['sep'] ) && $this->fullParams['sep']->wasSetToDefault() === true ) {
			$builder->set( 'sep', '' );
		}

		return $builder;
	}

	/**
	 * @return bool
	 */
	private function hasTemplates() {
		return $this->params['template'] !== '' || $this->params['introtemplate'] !== '' || $this->params['outrotemplate'] !== '';
	}

	/**
	 * Get text for further results link. Used only during getResultText().
	 *
	 * @since 1.9
	 *
	 * @param QueryResult $res
	 * @param int $outputMode
	 *
	 * @return string
	 */
	private function getFurtherResultsText( QueryResult $res, $outputMode ) {
		if ( $this->linkFurtherResults( $res ) ) {

			$link = $this->getFurtherResultsLink( $res, $outputMode );
			return $link->getText( SMW_OUTPUT_WIKI, $this->mLinker );

		}

		return '';
	}

	/**
	 * @since 3.0
	 *
	 * @return bool
	 */
	public function supportsRecursiveAnnotation() {
		return true;
	}

	/**
	 * @see SMWIResultPrinter::getParamDefinitions
	 *
	 * @since 3.0
	 *
	 * @param ParamDefinition[] $definitions
	 *
	 * @return ParamDefinition[]
	 * @throws \Exception
	 */
	public function getParamDefinitions( array $definitions ) {
		$listFormatDefinitions = [

			'propsep' => [
				'message' => 'smw-paramdesc-propsep',
				'default' => Message::get( 'smw-format-list-property-separator' ),
			],

			'valuesep' => [
				'message' => 'smw-paramdesc-valuesep',
				'default' => Message::get( 'smw-format-list-value-separator' ),
			],

			'template' => [
				'message' => 'smw-paramdesc-template',
				'default' => '',
				'trim' => true,
			],

			'named args' => [
				'type' => 'boolean',
				'message' => 'smw-paramdesc-named_args',
				'default' => false,
				'aliases' => [ 'namedargs' ]
			],

			'userparam' => [
				'message' => 'smw-paramdesc-userparam',
				'default' => '',
			],

			'class' => [
				'message' => 'smw-paramdesc-class',
				'default' => '',
			],

			'introtemplate' => [
				'message' => 'smw-paramdesc-introtemplate',
				'default' => '',
			],

			'outrotemplate' => [
				'message' => 'smw-paramdesc-outrotemplate',
				'default' => '',
			],

		];

		if ( $this->mFormat !== 'ul' && $this->mFormat !== 'ol' ) {

			$listFormatDefinitions['sep'] =
				[
					'message' => 'smw-paramdesc-sep',
					'default' => ', ',
				];
		}

		$listFormatDefinitions['prefix'] = [
			'message' => 'smw-paramdesc-prefix',
			'default' => 'none',
			'values' => [ 'all', 'subject', 'none', 'auto' ],
		];

		return array_merge( $definitions, ParamDefinition::getCleanDefinitions( $listFormatDefinitions ) );
	}
}
