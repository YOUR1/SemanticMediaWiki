<?php

namespace SMW\Query\ResultPrinters;

use SMW\Query\ExportPrinter;
use SMW\Query\QueryResult;
use SMWQuery;
use SMWQueryProcessor;

/**
 * Base class for file export result printers
 *
 * @since 1.8
 * @license GPL-2.0-or-later
 *
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
abstract class FileExportPrinter extends ResultPrinter implements ExportPrinter {

	/**
	 * @var bool
	 */
	private $httpHeader = true;

	/**
	 * @see ExportPrinter::isExportFormat
	 *
	 * @since 1.8
	 *
	 * @return bool
	 */
	public function isExportFormat() {
		return true;
	}

	/**
	 * @see 3.0
	 */
	public function disableHttpHeader() {
		$this->httpHeader = false;
	}

	/**
	 * @see ExportPrinter::outputAsFile
	 *
	 * @since 1.8
	 *
	 * @param QueryResult $queryResult
	 * @param array $params
	 */
	public function outputAsFile( QueryResult $queryResult, array $params ) {
		$result = $this->getFileResult( $queryResult, $params );

		$this->httpHeader(
			'Content-type: ' . $this->getMimeType( $queryResult ) . '; charset=UTF-8'
		);

		$fileName = $this->getFileName(
			$queryResult
		);

		if ( $fileName !== false ) {
			$utf8Name = rawurlencode( $fileName );
			$fileName = iconv( "UTF-8", "ASCII//TRANSLIT", $fileName );

			$this->httpHeader(
				"content-disposition: attachment; filename=\"$fileName\"; filename*=UTF-8''$utf8Name;"
			);
		}

		echo $result;
	}

	/**
	 * @see ExportPrinter::getFileName
	 *
	 * @since 1.8
	 *
	 * @param QueryResult $queryResult
	 *
	 * @return string|bool
	 */
	public function getFileName( QueryResult $queryResult ) {
		return false;
	}

	/**
	 * File exports use MODE_INSTANCES on special pages (so that instances are
	 * retrieved for the export) and MODE_NONE otherwise (displaying just a download link).
	 *
	 * @param $mode
	 *
	 * @return int
	 */
	public function getQueryMode( $mode ) {
		return $mode == SMWQueryProcessor::SPECIAL_PAGE ? SMWQuery::MODE_INSTANCES : SMWQuery::MODE_NONE;
	}

	/**
	 * `ResultPrinter::getResult` is marked as final making any attempt to test
	 * this method futile hence we isolate its access to ensure to be able to
	 * verify the access sequence (#4375).
	 */
	protected function getFileResult( $queryResult, $params ) {
		return $this->getResult( $queryResult, $params, SMW_OUTPUT_FILE );
	}

	private function httpHeader( $string ) {
		$this->httpHeader ? header( $string ) : '';
	}

}
