<?php

/**
 * @license GPL-2.0-or-later
 * @since 0.1
 *
 * @author mwjames
 */

error_reporting( E_ALL | E_STRICT );
date_default_timezone_set( 'UTC' );

if ( PHP_SAPI !== 'cli' ) {
	die( 'Not an entry point' );
}
