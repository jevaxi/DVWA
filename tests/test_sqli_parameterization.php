<?php
// Run with PHP's SQLite3 extension; set DVWA_TEST_MYSQL_HOST to also test mysqli.
// Use a disposable database: the MySQL fixture creates and drops its own table.
error_reporting( E_ALL );
set_error_handler( function( $severity, $message, $file, $line ) {
	throw new ErrorException( $message, 0, $severity, $file, $line );
} );
define( 'MYSQL', 'mysql' );
define( 'SQLITE', 'sqlite' );

function check( $condition, $message ) {
	if( !$condition ) {
		throw new RuntimeException( $message );
	}
}

function mysql_connection() {
	return new mysqli(
		getenv( 'DVWA_TEST_MYSQL_HOST' ),
		getenv( 'DVWA_TEST_MYSQL_USER' ) ?: 'root',
		getenv( 'DVWA_TEST_MYSQL_PASSWORD' ) ?: '',
		'resolve_sqli_test'
	);
}

function run_request( $backend, $id, $submit = true ) {
	global $_DVWA, $html, $sqlite_db_connection;
	$_DVWA = array( 'SQLI_DB' => $backend );
	$_REQUEST = $submit ? array( 'Submit' => 'Submit' ) : array();
	if( $id !== null ) {
		$_REQUEST['id'] = $id;
	}
	$html = '';
	if( $backend === MYSQL ) {
		$GLOBALS['___mysqli_ston'] = mysql_connection();
	}
	ob_start();
	try {
		include __DIR__ . '/../vulnerabilities/sqli/source/low.php';
		$printed = ob_get_contents();
	} finally {
		ob_end_clean();
	}
	check( $printed === '', 'The handler must not print database errors.' );
	return $html;
}

function run_backend( $backend ) {
	global $sqlite_db_connection;
	if( $backend === SQLITE ) {
		$sqlite_db_connection = new SQLite3( ':memory:' );
		$sqlite_db_connection->enableExceptions( true );
		$db = $sqlite_db_connection;
		$db->exec( 'CREATE TABLE users (user_id INTEGER PRIMARY KEY, first_name TEXT, last_name TEXT)' );
		$db->exec( "INSERT INTO users VALUES (1, 'Admin', 'User'), (2, 'Other', 'User')" );
	} else {
		$db = mysql_connection();
		$db->query( 'DROP TABLE IF EXISTS users' );
		$db->query( 'CREATE TABLE users (user_id INTEGER PRIMARY KEY, first_name VARCHAR(32), last_name VARCHAR(32))' );
		$db->query( "INSERT INTO users VALUES (1, 'Admin', 'User'), (2, 'Other', 'User')" );
	}

	foreach( array( '1' => 'Admin', '2' => 'Other', '0001' => 'Admin' ) as $id => $name ) {
		$result = run_request( $backend, (string) $id );
		check( substr_count( $result, 'First name:' ) === 1, "$backend: valid ID must return one user." );
		check( strpos( $result, 'First name: ' . $name ) !== false, "$backend: correct user must be returned." );
	}
	foreach( array( '0', '9999' ) as $id ) {
		check( run_request( $backend, $id ) === '', "$backend: unknown ID must return no user." );
	}
	foreach( array( "1' OR '1'='1", "1' OR '1'='2", '1 OR 1=1', "1' UNION SELECT 'Injected', 'User' -- ", "1'; DROP TABLE users; -- ", '', null, array( '1' ) ) as $id ) {
		$result = run_request( $backend, $id );
		check( substr_count( $result, 'First name:' ) === 0, "$backend: malformed or SQL input must return no users." );
		check( $result === '<pre>Invalid user ID.</pre>', "$backend: malformed input must return the generic validation message." );
	}
	check( run_request( $backend, '1', false ) === '', "$backend: no Submit must not query users." );
	check( strpos( run_request( $backend, '1' ), 'Admin' ) !== false, "$backend: users table must survive injection attempts." );

	if( $backend === SQLITE ) {
		$db->exec( 'DROP TABLE users' );
	} else {
		$db->query( 'DROP TABLE users' );
	}
	check( run_request( $backend, '1' ) === '<pre>Unable to retrieve user.</pre>', "$backend: database errors must not disclose SQL or schema." );
	if( $backend === SQLITE ) {
		$db->close();
	} else {
		$db->close();
	}
	echo "$backend: 16 request scenarios passed\n";
}

run_backend( SQLITE );
if( getenv( 'DVWA_TEST_MYSQL_HOST' ) ) {
	run_backend( MYSQL );
} else {
	echo "mysql: not configured; SQLite checks completed\n";
}
