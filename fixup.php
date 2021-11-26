<?php

$tasks = json_decode( shell_exec( 'task status:pending export' ), true );

foreach ( $tasks as $task ) {
	if ( isset( $task['phab'] ) && $task['phab'] ) {
		// assume it's already processed.
		continue;
	}
	// Process gerrit copy/pasted tasks.
	if ( strpos( $task['description'], 'https://gerrit.wikimedia.org' ) ) {
		$parts = explode( '|', $task['description'] );
		$gerritUrl = trim( array_pop( $parts ) );
		$humanDescription = implode( '', $parts );
		$parsedUrl = parse_url( $gerritUrl );
		$parts = array_filter( explode( '/', $parsedUrl['path'] ), static function ( $part ) {
			return $part && !in_array( $part, [ 'r', 'c', '+' ] );
		} );
		$changeId = array_pop( $parts );
		$project = implode( '/', $parts );
		$restResponseUrl = 'https://gerrit.wikimedia.org/r/changes/' .
			urlencode( $project ) . '~' . (int)$changeId;
		$curlHandler = curl_init();
		curl_setopt( $curlHandler, CURLOPT_URL, $restResponseUrl . '?o=CURRENT_REVISION' );
		curl_setopt( $curlHandler, CURLOPT_RETURNTRANSFER, true );
		$content = curl_exec( $curlHandler );
		$content = json_decode( str_replace( ')]}\'', '', $content ), true );
		$currentRevision = current( array_keys( $content['revisions'] ) );
		curl_setopt( $curlHandler, CURLOPT_URL, $restResponseUrl . '/revisions/' . $currentRevision . '/commit' );
		$content = curl_exec( $curlHandler );
		$content = json_decode( str_replace( ')]}\'', '', $content ), true );
		$matches = [];
		preg_match( '/^Bug:[^\r\n]*/m', $content['message'], $matches );
		list( $_, $bug ) = explode( 'Bug: ', current( $matches ) );
		curl_close( $curlHandler );
		// Modify the task
		$humanDescription = trim( str_replace( $changeId . ': ', '', $humanDescription ) );

		$cmd = sprintf( 'task %s modify "%s" phab:%s gerrit:%d project:%s +codereview',
			$task['uuid'],
			$humanDescription,
			$bug, (int)$changeId,
			$project
		);
		echo "running $cmd\n";
		shell_exec( $cmd );
		$cmd = sprintf( 'task %s annotate "%s"', $task['uuid'], $gerritUrl );
		echo "running $cmd\n";
		shell_exec( $cmd );
	}

	// Process phab links.
	if ( strpos( $task['description'], 'https://phabricator.wikimedia.org/' ) === 0 ) {
		$parsedUrl = parse_url( $task['description'] );
		$phid = trim( $parsedUrl['path'], '/' );
		$cmd = sprintf( 'echo \'%s\' | arc call-conduit -- phid.lookup', json_encode( [ 'names' => [ $phid ] ] ) );
		echo "running $cmd\n";
		$content = json_decode( shell_exec( $cmd ), true );
		$cmd = sprintf( 'task %s modify "%s" phab:%s',
			$task['uuid'],
			$content['response'][$phid]['fullName'],
			$phid
		);
		echo "running $cmd\n";
		shell_exec( $cmd );
		echo "running $cmd\n";
		$cmd = sprintf( 'task %s annotate "%s"', $task['uuid'], $task['description'] );
		shell_exec( $cmd );
	}

}
