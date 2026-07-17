<?php
/**
 * Build a release zip for the Free plugin.
 *
 * @package BulkVariations
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$slug = 'coderembassy-bulk-variations-manager';
$main = $root . '/' . $slug . '.php';

if ( ! file_exists( $main ) ) {
	fwrite( STDERR, "Main plugin file not found: {$main}\n" );
	exit( 1 );
}

$dry_run = in_array( '--dry-run', $argv, true );
$version = read_plugin_version( $main );
$artifact_dir = $root . '/artifacts';
$staging_root = $artifact_dir . '/release-staging';
$package_dir = $staging_root . '/' . $slug;
$zip_path = $artifact_dir . '/' . $slug . '-' . $version . '.zip';

$runtime_paths = array(
	$slug . '.php',
	'uninstall.php',
	'readme.txt',
	'src',
	'assets/admin/admin.css',
	'assets/admin/dist',
	'assets/admin/logo-dark.png',
	'assets/admin/logo-light.png',
	'assets/frontend',
	'languages',
	'templates',
	'migrations',
);

if ( $dry_run ) {
	echo "Release: {$zip_path}\n";
	foreach ( $runtime_paths as $path ) {
		echo ( file_exists( $root . '/' . $path ) ? 'include ' : 'missing ' ) . $path . "\n";
	}
	echo "include vendor/ from composer install --no-dev\n";
	exit( 0 );
}

ensure_directory( $artifact_dir );
remove_directory( $staging_root, $artifact_dir );
ensure_directory( $package_dir );

foreach ( $runtime_paths as $path ) {
	$source = $root . '/' . $path;
	if ( ! file_exists( $source ) ) {
		continue;
	}
	copy_path( $source, $package_dir . '/' . $path );
}

copy_path( $root . '/composer.json', $package_dir . '/composer.json' );
copy_path( $root . '/composer.lock', $package_dir . '/composer.lock' );

run_command(
	array(
		composer_binary(),
		'--working-dir=' . $package_dir,
		'install',
		'--no-dev',
		'--prefer-dist',
		'--no-progress',
		'--optimize-autoloader',
	),
	$root
);

unlink_if_exists( $package_dir . '/composer.lock' );

unlink_if_exists( $zip_path );
zip_directory( $package_dir, $zip_path, $staging_root );
remove_directory( $staging_root, $artifact_dir );

echo "Created {$zip_path}\n";

/**
 * @param string $main_file Main plugin file.
 */
function read_plugin_version( string $main_file ): string {
	$contents = (string) file_get_contents( $main_file );
	if ( preg_match( '/^\s*\*\s*Version:\s*([^\s]+)/mi', $contents, $matches ) ) {
		return preg_replace( '/[^0-9A-Za-z._-]/', '', $matches[1] ) ?: '0.0.0';
	}
	return '0.0.0';
}

/**
 * @param string $dir Directory path.
 */
function ensure_directory( string $dir ): void {
	if ( is_dir( $dir ) ) {
		return;
	}
	if ( ! mkdir( $dir, 0777, true ) && ! is_dir( $dir ) ) {
		throw new RuntimeException( "Unable to create directory: {$dir}" );
	}
}

/**
 * @param string $path    Path to remove.
 * @param string $allowed Parent boundary.
 */
function remove_directory( string $path, string $allowed ): void {
	$path = rtrim( str_replace( '\\', '/', $path ), '/' );
	$allowed = rtrim( str_replace( '\\', '/', $allowed ), '/' );

	if ( ! is_dir( $path ) ) {
		return;
	}
	if ( ! str_starts_with( $path, $allowed . '/' ) ) {
		throw new RuntimeException( "Refusing to remove outside artifacts: {$path}" );
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);

	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $path );
}

/**
 * @param string $source Source path.
 * @param string $target Target path.
 */
function copy_path( string $source, string $target ): void {
	if ( is_dir( $source ) ) {
		ensure_directory( $target );
		$items = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);
		foreach ( $items as $item ) {
			$relative = substr( $item->getPathname(), strlen( $source ) + 1 );
			$destination = $target . '/' . str_replace( '\\', '/', $relative );
			if ( $item->isDir() ) {
				ensure_directory( $destination );
			} else {
				ensure_directory( dirname( $destination ) );
				copy( $item->getPathname(), $destination );
			}
		}
		return;
	}

	ensure_directory( dirname( $target ) );
	copy( $source, $target );
}

/**
 * @param array<int, string> $command Command and arguments.
 * @param string             $cwd     Working directory.
 */
function run_command( array $command, string $cwd ): void {
	$escaped = array_map( 'escapeshellarg', $command );
	$cmd = implode( ' ', $escaped );
	echo "Running {$cmd}\n";

	$descriptor = array(
		0 => STDIN,
		1 => STDOUT,
		2 => STDERR,
	);
	$process = proc_open( $cmd, $descriptor, $pipes, $cwd );
	if ( ! is_resource( $process ) ) {
		throw new RuntimeException( "Unable to start command: {$cmd}" );
	}
	$code = proc_close( $process );
	if ( 0 !== $code ) {
		throw new RuntimeException( "Command failed with exit code {$code}: {$cmd}" );
	}
}

/**
 * Resolve Composer in a way that works with the Windows Composer installer.
 */
function composer_binary(): string {
	if ( 'Windows' !== PHP_OS_FAMILY ) {
		return 'composer';
	}

	$path = (string) getenv( 'PATH' );
	foreach ( explode( PATH_SEPARATOR, $path ) as $dir ) {
		$dir = trim( $dir, " \t\n\r\0\x0B\"" );
		if ( '' === $dir ) {
			continue;
		}
		$candidate = rtrim( $dir, '\\/' ) . DIRECTORY_SEPARATOR . 'composer.bat';
		if ( file_exists( $candidate ) ) {
			return $candidate;
		}
	}

	return 'composer.bat';
}

/**
 * @param string $path File path.
 */
function unlink_if_exists( string $path ): void {
	if ( file_exists( $path ) ) {
		unlink( $path );
	}
}

/**
 * @param string $source_dir   Directory to zip.
 * @param string $zip_path     Zip path.
 * @param string $relativeRoot Parent path for archive names.
 */
function zip_directory( string $source_dir, string $zip_path, string $relativeRoot ): void {
	if ( ! class_exists( ZipArchive::class ) ) {
		throw new RuntimeException( 'PHP ZipArchive extension is required.' );
	}

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
		throw new RuntimeException( "Unable to create zip: {$zip_path}" );
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	$relativeRoot = rtrim( str_replace( '\\', '/', $relativeRoot ), '/' ) . '/';

	foreach ( $items as $item ) {
		$path = str_replace( '\\', '/', $item->getPathname() );
		$name = substr( $path, strlen( $relativeRoot ) );
		if ( $item->isDir() ) {
			$zip->addEmptyDir( $name );
		} else {
			$zip->addFile( $item->getPathname(), $name );
		}
	}

	$zip->close();
}
