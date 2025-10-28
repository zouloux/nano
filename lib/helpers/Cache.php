<?php

namespace Nano\helpers;

use Exception;
use Nano\core\App;
use Nano\core\Env;

class Cache {

	// --------------------------------------------------------------------------- STATICS

	// Static registry of cache instances
	protected static array $__instances = [];

	/**
	 * Create a new cache instance
	 * @param string $key Unique key for this cache instance
	 * @param string $cacheMethod Can be "apcu" / "file" / "none" or "auto" for "apcu" with "file" fallback
	 * @param string|null $cachePath Absolute path for the "file" cache system
	 * @param bool $annihilate All functions will work, but nothing will be cached. Useful to test in a dev environment without a cache.
	 * @return Cache
	 * @throws Exception
	 */
	public static function createInstance (string $key, string $cacheMethod, ?string $cachePath = null, bool $annihilate = false): Cache {
		$instance = new Cache($key, $cacheMethod, $cachePath, $annihilate);
		self::$__instances[$key] = $instance;
		return $instance;
	}

	/**
	 * Get an existing cache instance
	 * @param string $key Unique key for the cache instance
	 * @return Cache
	 * @throws Exception
	 */
	public static function getInstance (string $key): Cache {
		if (!isset(self::$__instances[$key]))
			throw new Exception("Cache::get // Cache instance with key '$key' not found");
		return self::$__instances[$key];
	}

	public static function hasInstance (string $key): bool {
		return isset(self::$__instances[$key]);
	}

	public static function getInstances () {
		return self::$__instances;
	}

	// --------------------------------------------------------------------------- INSTANCE

	// Can be "auto" in dot env to be defined automatically
	// "apcu" | "file" | "none"
	protected string $_cacheMethod = "";

	// File path to cache directory for file cache system
	protected string $_cachePath;

	// Cache key for this instance
	protected string $_key;
	public function getKey () { return $this->_key; }

	/**
	 * Create and configure a new cache system.
	 * @param string $key Key of cache, will be used as cache prefix for namspaces
	 * @param string $cacheMethod Can be "apcu" / "file" / "none" or "auto" for "apcu" with "file" fallback
	 * @param string|null $cachePath Absolute path for the "file" cache system
	 * @param bool $annihilate All functions will work, but nothing will be cached. Useful to test in a dev environment without a cache.
	 * @return void
	 * @throws Exception
	 */
	public function __construct (string $key, string $cacheMethod, ?string $cachePath = null, bool $annihilate = false) {
		$this->_key = $key;
		if ($annihilate) {
//			App::stdout("[cache] '$key' anihilated.");
			$this->_cacheMethod = "none";
			return;
		}
//		App::stdout("[cache] '$key' created with '$cacheMethod' method.");
		// Check cache path directory
		if (($cacheMethod === "file" || $cacheMethod === "auto") && (is_null($cachePath))) {
			throw new \Exception("Cache::init // Invalid cache path $cachePath");
		}
		// Disable cache feature but keep api working
		if ($cacheMethod === "none" || $cacheMethod === "apcu" || $cacheMethod === "file") {
			$this->_cacheMethod = $cacheMethod;
		}
		// Auto mode, we check if apcu is available, otherwise we select file cache
		else if ($cacheMethod === "auto") {
			$this->_cacheMethod = function_exists("apcu_fetch") ? "apcu" : "file";
		}
		// Invalid cache method
		else {
			throw new \Exception("Cache::init // Invalid cache method $cacheMethod set from dot env");
		}
		// Init cache directory for file cache
		if ($this->_cacheMethod === "file") {
			$this->_cachePath = $cachePath;
			$this->cacheInitDirectory();
		}
	}

	/**
	 * Check if cache is active and not anihilated
	 * @return bool
	 */
	public function isActive(): bool {
		return !empty($this->_cacheMethod) && $this->_cacheMethod !== "none";
	}

	/**
	 * INTERNAL
	 * Get a file cache file path from its key.
	 */
	protected function cacheGetFilePath($key): string {
		return rtrim($this->_cachePath) . '/' . md5($key);
	}

	/**
	 * INTERNAL
	 * Init cache directory for file cache system.
	 */
	protected function cacheInitDirectory(): void {
		if (!file_exists($this->_cachePath))
			mkdir($this->_cachePath, 0777, true);
	}

	/**
	 * Clear all cache.
	 * @return bool
	 * @throws \Exception
	 */
	public function clear(): bool {
		if (empty($this->_cacheMethod))
			throw new Exception("Cache::clear // Invalid cache method");
		if ($this->_cacheMethod === "apcu")
			return apcu_clear_cache();
		else if ($this->_cacheMethod === "file") {
			// Reset cache folder for file cache system
			if (file_exists($this->_cachePath))
				FileSystem::recursiveRemove($this->_cachePath);
			$this->cacheInitDirectory();
			return true;
		}
		else
			return true;
	}

	/**
	 * Check if a value is stored in cache on this key
	 * @param string $key Cache object key, prefixed with cache instance key.
	 * @return boolean
	 * @throws \Exception
	 */
	public function has(string $key): bool {
		if (empty($this->_cacheMethod))
			throw new Exception("Cache::has // Invalid cache method");
		$key = $this->_key.$key;
		if ($this->_cacheMethod === "apcu")
			return apcu_exists($key);
		else if ($this->_cacheMethod === "file") {
			return file_exists($this->cacheGetFilePath($key));
		}
		else
			return false;
	}

	/**
	 * Retrieve a value from cache.
	 * @param string $key Cache object key, prefixed with cache instance key.
	 * @return mixed
	 * @throws \Exception
	 */
	public function getValue(string $key): mixed {
		if (empty($this->_cacheMethod))
			throw new Exception("Cache::getValue // Invalid cache method");
		$key = $this->_key.$key;
		if ($this->_cacheMethod === "apcu")
			return apcu_fetch($key);
		else if ($this->_cacheMethod === "file") {
			$path = $this->cacheGetFilePath($key);
			return file_exists($path) ? unserialize(file_get_contents($path)) : null;
		}
		else
			return null;
	}

	/**
	 * Store value to current cache system.
	 * @param string $key Cache object key, prefixed with cache instance key.
	 * @param mixed $data Data to store, can be any type of value.
	 * @return bool
	 * @throws \Exception
	 */
	public function set(string $key, mixed $data): bool {
		if (empty($this->_cacheMethod))
			throw new Exception("Cache::set // Invalid cache method");
		$key = $this->_key.$key;
		if ($this->_cacheMethod === "apcu")
			return !!apcu_store($key, $data);
		else if ($this->_cacheMethod === "file") {
			$path = $this->cacheGetFilePath($key);
			return file_put_contents($path, serialize($data)) !== false;
		}
		else
			return true;
	}

	/**
	 * Get a cached value with a handler.
	 * Will call the $getHandler until it returns a value.
	 * This value will be cached and will be returned without calling $getHandler again, until cache is cleared.
	 * @param string $key Cache object key, prefixed with instance key.
	 * @param callable $getHandler Function to return value to cache. Will be called until it returns a value. This value will be stored in cache.
	 * @param callable|null $retrieveHandler Called when value is retrieved from cache. TODO : ARGUMENTS
	 * @param bool $disableCache Set to true to disable cache, can be useful in some cases where you want the cache to be bypassed and the handler always called.
	 * @return false|mixed
	 * @throws Exception
	 */
	public function define(string $key, callable $getHandler, ?callable $retrieveHandler = null, bool $disableCache = false): mixed {
		if (empty($this->_cacheMethod))
			throw new Exception("Cache::define // Invalid cache method");
		// Always call handler if cache is disabled
		if ($this->_cacheMethod === "none" || $disableCache)
			return $getHandler();
		// Store key without prefix for file cache system
		$keyNoPrefix = $key;
		$key = $this->_key.$key;
		// Check existence in cache for apcu or file
		$isAPCU = $this->_cacheMethod === "apcu";
		if ($isAPCU ? apcu_exists($key) : file_exists($this->cacheGetFilePath($key))) {
			// Call retrieve handler
			// FIXME : What about the arguments here ?
			if (!is_null($retrieveHandler))
				$retrieveHandler();
			// Get value from cache
			$fetched = $isAPCU ? apcu_fetch($key) : $this->getValue($keyNoPrefix);
			// Return value if not null, otherwise, keep going to call get handler
			if (!is_null($fetched))
				return $fetched;
		}
		// Get value from handler because cache failed
		$result = $getHandler();
		// If we have any value, store it in cache
		if (!is_null($result)) {
			$isAPCU
				? apcu_store($key, $result)
				: $this->set($keyNoPrefix, $result);
		}
		return $result;
	}

	/**
	 * Count entries in cache
	 * @return int|mixed|null
	 */
	public function count(): mixed {
		$isAPCU = $this->_cacheMethod === "apcu";
		// todo : support other cache methods
		if (!$isAPCU)
			return null;
		$info = apcu_cache_info();
		return $info['num_entries'] ?? 0;
	}
}
