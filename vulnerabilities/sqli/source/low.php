<?php

if( isset( $_REQUEST[ 'Submit' ] ) ) {
	// Get input
	$id = $_REQUEST[ 'id' ] ?? null;
	if( !is_string( $id ) || !ctype_digit( $id ) ) {
		$html .= '<pre>Invalid user ID.</pre>';
		return;
	}

	switch ($_DVWA['SQLI_DB']) {
		case MYSQL:
			// Check database
			$query = 'SELECT first_name, last_name FROM users WHERE user_id = ?';
			try {
				$statement = mysqli_prepare( $GLOBALS["___mysqli_ston"], $query );
				if( !$statement || !mysqli_stmt_bind_param( $statement, 's', $id ) || !mysqli_stmt_execute( $statement ) ) {
					$html .= '<pre>Unable to retrieve user.</pre>';
					return;
				}
				mysqli_stmt_bind_result( $statement, $first, $last );
			} catch( mysqli_sql_exception $e ) {
				$html .= '<pre>Unable to retrieve user.</pre>';
				return;
			}

			// Get results
			while( mysqli_stmt_fetch( $statement ) ) {

				// Feedback for end user
				$html .= "<pre>ID: {$id}<br />First name: {$first}<br />Surname: {$last}</pre>";
			}

			mysqli_stmt_close( $statement );
			mysqli_close($GLOBALS["___mysqli_ston"]);
			break;
		case SQLITE:
			global $sqlite_db_connection;

			#$sqlite_db_connection = new SQLite3($_DVWA['SQLITE_DB']);
			#$sqlite_db_connection->enableExceptions(true);

			$query = 'SELECT first_name, last_name FROM users WHERE user_id = :id';
			#print $query;
			try {
				$statement = $sqlite_db_connection->prepare( $query );
				if( !$statement || !$statement->bindValue( ':id', $id, SQLITE3_TEXT ) ) {
					$html .= '<pre>Unable to retrieve user.</pre>';
					return;
				}
				$results = $statement->execute();
			} catch (Exception $e) {
				$html .= '<pre>Unable to retrieve user.</pre>';
				return;
			}

			if ($results) {
				while ($row = $results->fetchArray()) {
					// Get values
					$first = $row["first_name"];
					$last  = $row["last_name"];

					// Feedback for end user
					$html .= "<pre>ID: {$id}<br />First name: {$first}<br />Surname: {$last}</pre>";
				}
			} else {
				$html .= '<pre>Unable to retrieve user.</pre>';
			}
			break;
	} 
}

?>
