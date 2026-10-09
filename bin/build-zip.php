<?php
/**
 * Builds the distributable plugin zip.
 *
 * WordPress.org rejects hidden files, and Plugin Check reads development files
 * such as the smoke test as if they were shipped code. Neither belongs in the
 * upload, so the zip is built from the plugin folder minus everything listed
 * in .distignore.
 *
 * Usage:  php bin/build-zip.php
 *
 * @package Mailwright_For_Contact_Form_7
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 'Run this from the command line.' );
}

$root = dirname( __DIR__ );
$slug = basename( $root );

$excluded = array();

foreach ( file( $root . '/.distignore', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) as $line ) {
	$line = trim( $line );

	if ( '' !== $line && ! str_starts_with( $line, '#' ) ) {
		$excluded[] = $line;
	}
}

/**
 * Whether a path relative to the plugin root is excluded from the zip.
 *
 * @param string $relative Relative path, with forward slashes.
 * @param array  $patterns Patterns from .distignore.
 * @return bool
 */
function mwright_is_excluded( $relative, $patterns ) {
	foreach ( $patterns as $pattern ) {
		$first = explode( '/', $relative )[0];

		if ( $relative === $pattern || $first === $pattern || fnmatch( $pattern, basename( $relative ) ) ) {
			return true;
		}
	}

	return false;
}

$zip_path = $root . '/' . $slug . '.zip';

if ( file_exists( $zip_path ) ) {
	unlink( $zip_path );
}

$zip = new ZipArchive();

if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	exit( "Could not create the zip.\n" );
}

$files = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$added   = 0;
$skipped = array();

foreach ( $files as $file ) {
	$relative = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );

	if ( mwright_is_excluded( $relative, $excluded ) ) {
		$skipped[ explode( '/', $relative )[0] ] = true;
		continue;
	}

	if ( $file->isDir() ) {
		$zip->addEmptyDir( $slug . '/' . $relative );
	} else {
		$zip->addFile( $file->getPathname(), $slug . '/' . $relative );
		++$added;
	}
}

$zip->close();

printf( "Built %s\n", basename( $zip_path ) );
printf( "  %d files, %s\n", $added, size_format_bytes( filesize( $zip_path ) ) );
printf( "  excluded: %s\n", implode( ', ', array_keys( $skipped ) ) );

/**
 * Human-readable byte size, without loading WordPress.
 *
 * @param int $bytes Size in bytes.
 * @return string
 */
function size_format_bytes( $bytes ) {
	return $bytes > 1048576
		? round( $bytes / 1048576, 1 ) . ' MB'
		: round( $bytes / 1024 ) . ' KB';
}
