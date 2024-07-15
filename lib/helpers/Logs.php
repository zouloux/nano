<?php

namespace Nano\helpers;


class Logs
{
	// ------------------------------------------------------------------------- LEVELS

	// Something happened but it can be OK in the process
	public static string $LEVEL_NOTICE = "notice";
	// Something happened, OK in the process, but it should not happen
	public static string $LEVEL_WARNING = "warning";
	// Something bad happened, NOT OK in the process
	public static string $LEVEL_ERROR = "error";
	// Something ver bad happened, NOT OK in the process, the process crashes
	public static string $LEVEL_CRITICAL = "critical";

	// ------------------------------------------------------------------------- CREATE LOG BUFFER

	public static function create ( $category = "" )
	{
		return new Logs( $category );
	}

	public string $category = "";

	public function __construct ( string $category )
	{
		$this->category = $category;
	}

	// ------------------------------------------------------------------------- ADD LOG LINE

	protected array $_lines = [];

	/**
	 * Add a log line. AKA, log something.
	 *
	 * @param string $level Level of dangerousness of the event. Use statics if possible.
	 * @param string $topic Subject of the event. Can be the function raising it.
	 * @param string $message Human-readable message associated to the event.
	 * @param mixed $data Any data to log with, will be json encoded.
	 *
	 * @return void
	 */
	public function add ( string $level, string $topic, string $message, $data = null )
	{
		$this->_lines[] = [ $level, $topic, $message, $data ];
	}

	public function notice ( string $topic, string $message, $data = null )
	{
		$this->add( self::$LEVEL_NOTICE, $topic, $message, $data );
	}

	public function warning ( string $topic, string $message, $data = null )
	{
		$this->add( self::$LEVEL_WARNING, $topic, $message, $data );
	}

	public function error ( string $topic, string $message, $data = null )
	{
		$this->add( self::$LEVEL_ERROR, $topic, $message, $data );
	}

	public function critical ( string $topic, string $message, $data = null )
	{
		$this->add( self::$LEVEL_CRITICAL, $topic, $message, $data );
	}

	// ------------------------------------------------------------------------- BUFFER TO STRING

	// Number of spaces to align items after level
	public $levelSpaces = 8;

	/**
	 * Convert this buffer to a human-readable stream.
	 * @return string
	 */
	public function toString ()
	{
		$buffer = "";
		foreach ( $this->_lines as $line ) {
			$level         = strtoupper( $line[ 0 ] );
			$buffer        .= "[" . $level . "]";
			$missingSpaces = $this->levelSpaces - strlen( $level );
			$buffer        .= str_repeat( " ", $missingSpaces );
			$buffer        .= " " . $line[ 1 ] . " - " . $line[ 2 ];
			if ( ! is_null( $line[ 3 ] ) ) {
				$buffer .= "\n";
				$buffer .= str_repeat( " ", $this->levelSpaces + 3 );
				$buffer .= json_encode( $line[ 3 ] );
			}
			$buffer .= "\n";
		}

		return $buffer;
	}

	// ------------------------------------------------------------------------- SAVE BUFFER TO FILE

	protected static string $__logsPath;

	public static function setLogsPath ( string $path )
	{
		self::$__logsPath = $path;
	}

	protected string $_filePath;

	/**
	 * Save log to file system.
	 * Will be stored into /var/logs/$category/$YYYY/$MM/$DD/$HH:$MM:$SS-$ID.log.
	 * @return false|int
	 */
	public function save ()
	{
		if ( empty( self::$__logsPath ) )
			return false;
		if ( !isset( $this->_filePath ) )
		{
			$directories = [
				// Target Prestashop logs directory
				self::$__logsPath,
				// Log category
				$this->category ?? "default",
				// In date folders to avoid huge folder which will slow down the whole system
				date( 'Y' ),
				date( 'm' ),
				date( 'd' ),
			];
			// Create directory
			$directory = implode( "/", $directories );
			if ( ! file_exists( $directory ) ) {
				@mkdir( $directory, 0777, true );
			}
			// Unique filename
			$fileName        = date( "H:i:s" ) . "-" . uniqid() . ".log";
			$this->_filePath = $directory . "/" . $fileName;
		}
		// Convert buffer to string
		$buffer = $this->toString();
		// Inject to file and return successfulness
		return file_put_contents( $this->_filePath, $buffer );
	}

	// ------------------------------------------------------------------------- LEVELS DETECTION

	/**
	 * Count lines by their level.
	 */
	public function countLevels ()
	{
		$levels = [
			self::$LEVEL_NOTICE   => 0,
			self::$LEVEL_WARNING  => 0,
			self::$LEVEL_ERROR    => 0,
			self::$LEVEL_CRITICAL => 0,
		];
		foreach ( $this->_lines as $line )
			++$levels[ $line[ 0 ] ];
		return $levels;
	}

	/**
	 * Check if this log buffer has any critical error.
	 * Critical errors are levels ERROR or FATAL.
	 * @return bool
	 */
	public function hasCritical ()
	{
		$levels = $this->countLevels();
		return $levels[ Logs::$LEVEL_ERROR ] > 0 || $levels[ Logs::$LEVEL_CRITICAL ] > 0;
	}
}

