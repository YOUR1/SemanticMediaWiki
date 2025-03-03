<?php

namespace SMW;

use SMW\Iterators\AppendIterator;
use SMW\Iterators\ChunkedIterator;
use SMW\Iterators\CsvFileIterator;
use SMW\Iterators\MappingIterator;
use SMW\Iterators\ResultIterator;

/**
 * @license GPL-2.0-or-later
 * @since 2.5
 *
 * @author mwjames
 */
class IteratorFactory {

	/**
	 * @since 2.5
	 *
	 * @param \Wikimedia\Rdbms\ResultWrapper|Iterator|array $res
	 *
	 * @return ResultIterator
	 */
	public function newResultIterator( $res ) {
		return new ResultIterator( $res );
	}

	/**
	 * @since 2.5
	 *
	 * @param Iterator/array $iterable
	 * @param callable $callback
	 *
	 * @return MappingIterator
	 */
	public function newMappingIterator( $iterable, callable $callback ) {
		return new MappingIterator( $iterable, $callback );
	}

	/**
	 * @since 3.0
	 *
	 * @param Iterator|array $iterable
	 * @param int $chunkSize
	 *
	 * @return ChunkedIterator
	 */
	public function newChunkedIterator( $iterable, $chunkSize = 500 ) {
		return new ChunkedIterator( $iterable, $chunkSize );
	}

	/**
	 * @since 3.0
	 *
	 * @return AppendIterator
	 */
	public function newAppendIterator() {
		return new AppendIterator();
	}

	/**
	 * @since 3.0
	 *
	 * @param string $file
	 * @param bool $parseHeader
	 * @param string $delimiter
	 * @param int $length
	 *
	 * @return CsvFileIterator
	 */
	public function newCsvFileIterator( $file, $parseHeader = false, $delimiter = "\t", $length = 8000 ) {
		return new CsvFileIterator( $file, $parseHeader, $delimiter, $length );
	}

}
