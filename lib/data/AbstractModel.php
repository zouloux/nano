<?php

namespace {
  // illuminate/database 12 requires this to work
  if ( !function_exists( 'base_path' ) ) {
    function base_path ( $path = "" ) {
      return __DIR__.( $path ? DIRECTORY_SEPARATOR.$path : $path );
    }
  }
}


namespace Nano\data { // namespace is wrapped to allow base_path to be global

use Carbon\Carbon;
use Exception;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use ReflectionClass;
use ReflectionProperty;

/**
 * Use with illuminate/database 12
 */

abstract class AbstractModel
{
	// ------------------------------------------------------------------------- DB

	public static function initDatabase ( string $sqlitePath, string $modelsDirectory, bool $migrate = false, bool $enableWAL = true ) : Capsule {
		// Allow database file in migration phase only
		if ( $migrate && !file_exists( $sqlitePath ) )
			touch( $sqlitePath );
		// Connect DB
		$capsule = new Capsule();
		$capsule->addConnection([
			'driver'   => 'sqlite',
			'database' => $sqlitePath,
		]);
		$capsule->setAsGlobal();
		// Enable WAL mode and set synchronous to FULL for better perfs
		$connection = $capsule->getConnection();
		if ( $enableWAL )
			$connection->statement('PRAGMA journal_mode=WAL;');
		$connection->statement('PRAGMA synchronous=FULL;');
		// If we are in migration phase
		if ( $migrate )
			self::migrateDatabase( $modelsDirectory );
		return $capsule;
	}

	protected static function migrateDatabase ( string $modelsDirectory ): void {
		$files = scandir($modelsDirectory);
		echo "<pre>";
		echo "Migrating models in $modelsDirectory<br/>";
		foreach ($files as $file) {
			if (!is_file("$modelsDirectory/$file") || pathinfo($file, PATHINFO_EXTENSION) !== 'php')
				continue;
			// Extract file name without extension
			$name = pathinfo($file, PATHINFO_FILENAME);
			// Replace the .model pre-extension if existing
			$name = str_replace('.model', '', $name);
			// Separate underscore and map to CamelCase
			$parts = explode('_', $name);
			$parts = array_map('ucfirst', $parts);
			$className = implode('', $parts).'Model';
			// form_usage.model.php -> FormUsageModel
			echo "<br/>Model $name :<br/>";
			if ( !class_exists($className) || !method_exists($className, 'migrate') ) {
				echo "- Unable to load";
				continue;
			}
			try {
				$className::migrate();
			}
			catch ( Exception $e ) {
				echo "- Unable to migrate";
				dump($e);
			}
		}
		exit;
	}

	// -------------------------------------------------------------------------

	const TABLE_NAME = "";

	protected static function structure ( Blueprint $table ) {
		throw new Exception("AbstractModel::structure // Model structure implementation missing.");
	}

	protected static function upgrade ( Blueprint $table, int $version ) : bool {
		return false;
	}

	protected static function afterUpgrade ( int $version ) {}

	protected static function initTableVersionUpgrade ( string $refererTableName ): array {
		// Create the upgrade table if it does not exist
		$upgradeTableName = "table_versions";
		if ( !Capsule::schema()->hasTable( $upgradeTableName ) ) {
			Capsule::schema()->create(
				$upgradeTableName,
				function ( Blueprint $table ) {
					$table->increments("id");
					$table->string("name");
					$table->integer("version")->default(0);
				}
			);
		}
		// Get the table upgrade version
		$table = Capsule::table( $upgradeTableName );
		$upgrade = $table->where([ "name" => $refererTableName ])->get()->first();
		// Create it to zero if not existing
		if ( is_null($upgrade) )
			$table->insert([ "name" => $refererTableName ]);
		// Return version with upgrade function
		$upgradeVersion = function ( $newVersion ) use ( $table, $refererTableName ) {
			$table->where([ "name" => $refererTableName ])->update([ "version" => $newVersion ]);
		};
		return [ $upgrade->version ?? 0, $upgradeVersion ];
	}

	public static function migrate (): void {
		if ( empty( static::TABLE_NAME ) )
			throw new Exception("AbstractModel::migrate // Set TABLE_NAME const in model class.");
		// Create schema only if table does not already exist
		if ( !Capsule::schema()->hasTable( static::TABLE_NAME ) ) {
			echo "- Created table ".static::TABLE_NAME."<br/>";
			Capsule::schema()->create(
				static::TABLE_NAME,
				function ( Blueprint $table ) {
					$table->increments( 'id' );
					$table->timestamps();
					static::structure( $table );
				}
			);
		}
		else echo "- Table already existing<br/>";
		// Init the upgrade system and get the version and the upgrade function
		[$version, $upgradeVersion] = static::initTableVersionUpgrade( static::TABLE_NAME );
		$originalVersion = $version;
		echo "- Version $version<br/>";
		// Loop through all versions until we migrated completely
		$upgradeNext = true;
		while ( $upgradeNext ) {
			Capsule::schema()->table(
				static::TABLE_NAME,
				function ( Blueprint $table ) use ( &$version, &$upgradeNext ) {
					$r = static::upgrade( $table, ++$version );
					if ( $r )
						echo "- Upgraded to $version <br/>";
					if ( !$r )
						$upgradeNext = false;
				}
			);
		}
		// Save the new version number
		if ( $originalVersion !== $version )
			$upgradeVersion( $version - 1 );
		static::afterUpgrade( $version );
	}

	public static function table () : Builder {
		return Capsule::table( static::TABLE_NAME );
	}

	// ------------------------------------------------------------------------- DEFAULT HELPERS


	public static function getOneWhere ( string $key, mixed $value ) : static|null {
		$array = static::table()->where( $key, $value )->get()->first();
		return is_null( $array ) ? null : new static( (array) $array );
	}

	public static function getOneWhereMultiple ( array $where ) : static|null {
		$table = static::table();
		foreach ( $where as $key => $value )
			$table = $table->where( $key, $value );
		$array = $table->get()->first();
		return is_null( $array ) ? null : new static( (array) $array );
	}

	/**
	 * @return static[]
	 */
	public static function getAllWhere ( string $key, mixed $value, $orderBy = "updated_at", $direction = "desc" ) : array {
		$list = static::table()->where( $key, $value )->orderBy( $orderBy, $direction )->get();
		return static::convertList( $list );
	}

	protected static function convertList ( $list ): array {
		$output = [];
		foreach ( $list as $item )
			$output[] = new static( (array) $item );
		return $output;
	}


	public static function getByID ( int $id ) : static|null {
		return static::getOneWhere( "id", $id );
	}

	/**
	 * @return static[]
	 */
	public static function getAll ( $orderBy = "updated_at", $direction = "desc" ) : array {
		$list = static::table()->orderBy( $orderBy, $direction )->get();
		return static::convertList( $list );
	}


	/**
	 * Paginate through results with optional search and filters.
	 * Search options :
	 * - searchQuery -> A string to exec search on some columns.
	 * - searchColumns -> An array with the name of columns to search in.
	 * - searchExplode -> Boolean to explode every word of the search query.
	 *                    Default is false.
	 *
	 * Filter options :
	 * - exclusiveFilter -> An array of key / value to filter out.
	 *                      Key is the column name,
	 *                      Value is the exactly needed value.
	 *                      Will remove results from search.
	 *
	 * Pagination options :
	 * - pageIndex -> Pagination page index, starting by 0
	 * - pageLength -> Max element by page, default is 10
	 *
	 * Ordering options :
	 * - orderBy -> Order by this column name, default is "updated_at"
	 * - orderDirection -> Ordering direction, default is "desc"
	 *
	 * @param array{searchQuery?: string, searchColumns?: string[], exclusiveFilters?: array<string, mixed>, searchExplode?:boolean, pageIndex?: int, pageLength?: int, orderBy?: string, orderDirection?: string} $options The configuration options for pagination and search.*
	 * @param callable|null $customQuery Customer query with $sqlQuery as argument, useful for join filtering
	 * @return array Returns an associative array with paginated results and pagination details.
	 */
	public static function paginate ( array $options = [], ?callable $customQuery = null ) : array {
		// Merge default options with provided options
		$options = [
			"searchQuery" => "",
			"searchColumns" => [],
			"searchExplode" => false,
			"exclusiveFilters" => [],
			"pageIndex" => 0,
			"pageLength" => 10,
			"orderBy" => "updated_at",
			"orderDirection" => "desc",
			...$options
		];
		$sqlQuery = static::table();
		// Apply search if needed
		if ( !empty($options["searchQuery"]) && !empty($options["searchColumns"])) {
			$sqlQuery->where(function ($query) use ($options) {
				$searchTerms = $options["searchExplode"] ? explode(' ', $options["searchQuery"]) : [$options["searchQuery"]];
				foreach ($searchTerms as $term) {
					$query->where(function ($subQuery) use ($term, $options) {
						foreach ($options["searchColumns"] as $index => $column) {
							$method = $index === 0 ? 'where' : 'orWhere';
							$subQuery->$method($column, 'LIKE', '%' . $term . '%');
						}
					}, null, null, 'or');
				}
			});
		}
		// Apply exclusive filters
		$exclusiveFilters = $options["exclusiveFilters"];
		if ( !empty($exclusiveFilters) ) {
			foreach ( $exclusiveFilters as $filter => $value ) {
				if ( $value === "@not-null" )
					$sqlQuery->whereNotNull($filter);
				else
					$sqlQuery->where($filter, $value);
			}
		}
		// Run custom query handler
		if ( !is_null($customQuery) )
			$customQuery($sqlQuery);
		// Get total count for pagination
		$totalCount = $sqlQuery->count();
		$totalPages = ceil($totalCount / $options["pageLength"]);
		// Apply pagination
		$offset = $options["pageIndex"] * $options["pageLength"];
		$list = $sqlQuery
			->orderBy( $options["orderBy"], $options["orderDirection"] )
			->offset( $offset )
			->limit( $options["pageLength"] )
			->get();
		// Convert and return with pagination options
		return [
			"list" => static::convertList($list),
			"pages" => [
				"count" => $totalCount,
				"total" => $totalPages,
				"current" => $options["pageIndex"],
			],
			"order" => [
				"orderBy" => $options["orderBy"],
				"orderDirection" => $options["orderDirection"]
			],
		];
	}

	// -------------------------------------------------------------------------

	protected static $propertyTypesCache = [];

	protected static function listValueObjectProperties () {
		// Check if cached value object exists
		$class = static::class;
		if ( isset(self::$propertyTypesCache[$class]) )
			return self::$propertyTypesCache[$class];
		$reflectionClass = new ReflectionClass( $class );
		$properties = $reflectionClass->getProperties();
		$propertyNames = [];
		foreach ( $properties as $property ) {
			// Only list instance properties
			if ( $property->isStatic() )
				continue;
			$name = $property->getName();
			$reflectionProperty = new ReflectionProperty( $class, $name );
			$type = $reflectionProperty->getType();
			if ( is_null($type) )
				continue;
			$propertyNames[ $name ] = $type->getName();
		}
		// Save to cache
		self::$propertyTypesCache[$class] = $propertyNames;
		return $propertyNames;
	}

	// ------------------------------------------------------------------------- CONSTRUCTOR

	public function __construct ( ?array $from = null ) {
		if ( ! is_null( $from ) )
			$this->inject( $from );
	}

	// ------------------------------------------------------------------------- ARRAY <=> OBJECT

	public function inject ( array $data ) : void {
		$properties = static::listValueObjectProperties();
		foreach ( $properties as $name => $type ) {
			if ( !isset( $data[ $name ] ) )
				continue;
			$value = $data[ $name ];
			if ( is_string( $value ) ) {
				if ( $type === "bool" ) {
					$value = strtolower( $value );
					$value = $value == "true" || $value == "1" || $value == "on";
				} else if ( $type === "int" ) {
					$value = intval( $value );
				} else if ( $type === "float" ) {
					$value = floatval( $value );
				} else if ( $type === "array" ) {
					try {
						$value = json_decode( $value, JSON_NUMERIC_CHECK );
					} catch ( Exception $error ) {
						// FIXME : Can cause data destruction
//						dump( $error );
						$value = [];
					}
				}
			}
			if ( is_null( $value ) )
				continue;
			// FIXME : other conversions
			$this->$name = $value;
		}
	}

	// ------------------------------------------------------------------------- COMMON VALUE OBJECT PROPERTIES

	public int $id;
	public int $created_at;
	public int $updated_at;

	// ------------------------------------------------------------------------- SAVE

	public function toArray ( $forDB = false ): array {
		$properties = static::listValueObjectProperties();
		$output = [];
		foreach ( $properties as $name => $type ) {
			if ( ! isset( $this->$name ) ) {
				continue;
			}
			$value = $this->$name;
			if ( $forDB && $type === "array" && is_array( $value ) )
				$value = json_encode( $value, JSON_NUMERIC_CHECK );
			$output[ $name ] = $value;
		}

		return $output;
	}

	protected function beforeSave ( array $values ) : array {
		return $values;
	}

	public function save () : bool {
		// Convert this value object to array and filter through before save middleware
		$values = $this->toArray( true );
		$values = $this->beforeSave( $values );
		// This function will refresh local created and updated timestamps from db
		$refreshTimestamps = function ( $id ) {
			$record = static::table()->where( 'id', $id )->first( [ 'created_at', 'updated_at' ] );
			$this->created_at = $record->created_at;
			$this->updated_at = $record->updated_at;
		};
		// Insert
		$doInsert = ( !isset($this->id) || !static::table()->where('id', $this->id)->exists() );

		if ( $doInsert ) {
			$id = static::table()->insertGetId( [
				...$values,
				'created_at' => Carbon::now()->timestamp,
				'updated_at' => Carbon::now()->timestamp,
			]);
			if ( is_int( $id ) && $id > 0 ) {
				$refreshTimestamps( $id );
				$this->id = $id;
				return true;
			}
			return false;
		} // Update
		else {
			$success = (bool) static::table()->where( 'id', $this->id )->update([
				...$values,
				"updated_at" => Carbon::now()->timestamp
			]);
			if ( $success )
				$refreshTimestamps( $this->id );
			return $success;
		}
	}

	public function delete () : bool {
		if ( !isset( $this->id ) )
			return false;
		return static::table()->delete( $this->id );
	}
}
// end of namespace
}
