<?php
/**
 * Diagnostic logging: WP_DEBUG_LOG and/or VCE_DEBUG (dedicated file + admin page).
 *
 * @package Virtual_Card_Elementor
 */

namespace Virtual_Card_Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Writes to PHP error_log when WP_DEBUG_LOG is on, and to uploads/vce-debug.log when VCE_DEBUG is on.
 */
final class Debug_Log {

	private const LOG_MAX_BYTES = 524288;

	private const LOG_KEEP_BYTES = 400000;

	/**
	 * Dedicated file logging (wp-config: define( 'VCE_DEBUG', true );).
	 */
	public static function vce_debug_active(): bool {
		return defined( 'VCE_DEBUG' ) && VCE_DEBUG;
	}

	/**
	 * Mirror to PHP error_log when WP core debug log is enabled.
	 */
	private static function mirror_php_error_log(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG
			&& defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;
	}

	/**
	 * Whether any VCE diagnostic logging runs.
	 */
	public static function enabled(): bool {
		return self::vce_debug_active() || self::mirror_php_error_log();
	}

	/**
	 * Browser → REST logging (admins only, requires VCE_DEBUG).
	 */
	public static function vce_debug_client_enabled(): bool {
		return self::vce_debug_active() && is_user_logged_in() && current_user_can( 'manage_options' );
	}

	/**
	 * Register + localize vce-debug-client.js (call before registering scripts that depend on it).
	 */
	public static function register_debug_client_assets(): bool {
		if ( ! self::vce_debug_client_enabled() ) {
			return false;
		}
		if ( wp_script_is( 'vce-debug-client', 'registered' ) ) {
			return true;
		}
		wp_register_script(
			'vce-debug-client',
			VCE_PLUGIN_URL . 'assets/js/vce-debug-client.js',
			[],
			vce_asset_version( 'assets/js/vce-debug-client.js' ),
			true
		);
		wp_localize_script(
			'vce-debug-client',
			'vceDebugClient',
			[
				'enabled' => true,
				'restUrl' => rest_url( 'vce/v1/debug-client' ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			]
		);
		return true;
	}

	/**
	 * Absolute path to the dedicated log file (uploads, not web-served by default).
	 */
	public static function log_file_path(): string {
		$dir = wp_upload_dir();
		if ( ! empty( $dir['error'] ) ) {
			return '';
		}
		return trailingslashit( $dir['basedir'] ) . 'vce-debug.log';
	}

	/**
	 * @param string $message Single line, no secrets.
	 */
	public static function log( string $message ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$short = '[VCE] ' . $message;
		if ( self::mirror_php_error_log() ) {
			error_log( $short );
		}
		if ( self::vce_debug_active() ) {
			self::append_file_line( '[' . gmdate( 'Y-m-d H:i:s' ) . ' UTC] ' . $short );
		}
	}

	/**
	 * Log a line originating from the browser client.
	 */
	public static function log_client( string $message ): void {
		$message = preg_replace( '/\s+/', ' ', $message );
		$message = substr( $message, 0, 4000 );
		self::log( '[JS] ' . $message );
	}

	/**
	 * Register shutdown handler to log fatal PHP errors (VCE_DEBUG only).
	 */
	public static function register_shutdown_logger(): void {
		if ( ! self::vce_debug_active() ) {
			return;
		}
		register_shutdown_function(
			static function (): void {
				$e = error_get_last();
				if ( ! $e || ! in_array( (int) $e['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
					return;
				}
				Debug_Log::log(
					'php_fatal type=' . $e['type'] . ' msg=' . $e['message'] . ' file=' . $e['file'] . ' line=' . $e['line']
				);
			}
		);
	}

	/**
	 * Tail of the dedicated log for the admin UI.
	 *
	 * @return string Readable text or empty.
	 */
	public static function read_log_tail( int $max_bytes = 120000 ): string {
		$path = self::log_file_path();
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		$size = filesize( $path );
		if ( false === $size || $size <= 0 ) {
			return '';
		}
		$read = $size > $max_bytes ? $max_bytes : $size;
		$h    = fopen( $path, 'rb' );
		if ( ! $h ) {
			return '';
		}
		if ( $size > $read ) {
			fseek( $h, -$read, SEEK_END );
		}
		$data = fread( $h, $read );
		fclose( $h );
		return is_string( $data ) ? $data : '';
	}

	public static function clear_log_file(): bool {
		$path = self::log_file_path();
		if ( '' === $path ) {
			return false;
		}
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		return (bool) file_put_contents( $path, '[' . gmdate( 'Y-m-d H:i:s' ) . " UTC] [VCE] log cleared\n", LOCK_EX );
	}

	private static function append_file_line( string $line ): void {
		$path = self::log_file_path();
		if ( '' === $path ) {
			return;
		}
		$dir = dirname( $path );
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}
		self::maybe_rotate_log( $path );
		if ( "\n" !== substr( $line, -1 ) ) {
			$line .= "\n";
		}
		file_put_contents( $path, $line, FILE_APPEND | LOCK_EX );
	}

	private static function maybe_rotate_log( string $path ): void {
		if ( ! is_readable( $path ) ) {
			return;
		}
		$size = filesize( $path );
		if ( false === $size || $size < self::LOG_MAX_BYTES ) {
			return;
		}
		$contents = file_get_contents( $path );
		if ( ! is_string( $contents ) ) {
			return;
		}
		$keep = substr( $contents, -self::LOG_KEEP_BYTES );
		file_put_contents(
			$path,
			'[' . gmdate( 'Y-m-d H:i:s' ) . " UTC] [VCE] --- log rotated (size cap) ---\n" . $keep,
			LOCK_EX
		);
	}
}
