<?php
declare(strict_types=1);

/**
 * An abstract class to be extended when implementation the PPR-16 SimpleCache Interface.
 *
 * @package AWonderPHP/SimpleCache
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/SimpleCache
 */

namespace AWonderPHP\SimpleCache;

/**
 * An abstract class to be extended when implementation the PPR-16 SimpleCache Interface.
 */
abstract class SimpleCache
{
    /**
     * When false, the class does not attempt to write/read from cache.
     *
     * @var bool
     */
    protected $enabled = false;

    /**
     * When false, the class is somewhat tolerant of incorrect parameter type
     * and will recast if safe to do so.
     *
     * @var bool
     */
    protected $strictType = false;

    /**
     * The salt to use when generating internal key used with the cache engine.
     *
     * @var string
     */
    protected $salt = '6Dxypt3ePw2SM2zYzEVAFkDBQpxbk16z1';

    /**
     * The prefix to use with the internal key used with the cache engine.
     *
     * @var string
     */
    protected $webappPrefix = 'DEFAULT_';

    /**
     * The default TTL in seconds. 0 usually means as long as possible, but may vary by cache engine.
     *
     * @var int
     */
    protected $defaultSeconds = 0;

    /* these are needed only if using libsodium encryption */

    /**
     * The secret key to use.
     *
     * @var null|string
     */
    protected $cryptokey = null;

    /**
     * The checkForSodium() method sets to true if CPU supports it.
     *
     * @var bool
     */
    protected $aesgcm = false;

    /**
     * ALWAYS gets increments before encryption.
     *
     * @var null|string
     */
    protected $nonce = null;

    /* Protected Methods */

    /**
     * Creates hash substring to use in internal cache key.
     *
     * This class obfuscates the user supplied cache keys by using a substring
     * of the hex representation of a hash of that key. This function creates
     * the hex representation of the hash and grabs a substring.
     *
     * @param string $key The user defined key to hash.
     *
     * @return string
     */
    protected function weakHash($key): string
    {
        if (is_null($this->cryptokey)) {
            $key = $this->salt . $key;
            $key = hash('ripemd160', $key);
            // 16^16 should be enough of the hash to avoid collisions
            return substr($key, 17, 16);
        }
        $key = $key . $this->salt;
        $hash = sodium_crypto_generichash($key, $this->cryptokey, 16);
        $hexhash = sodium_bin2hex($hash);
        return substr($hexhash, 6, 20);
    }//end weakHash()

    /**
     * Checks to make sure sodium extension is available.
     *
     * Libsodium is part of PHP 7.2 but in earlier versions of PHP it needs to be installed
     * via the PECL libsodium (aka sodium) extension.
     *
     * @throws \ErrorException if no libsodium support
     *
     * @return void
     */
    protected function checkForSodium(): void
    {
        if (! function_exists('sodium_memzero')) {
            throw InvalidSetupException::noLibSodium();
        }
        if (sodium_crypto_aead_aes256gcm_is_available()) {
            $this->aesgcm = true;
        }
    }//end checkForSodium()

    /**
     * Sets the cryptokey property used by the class to encrypt/decrypt.
     *
     * @param string $cryptokey the key to use.
     *
     * @throws \TypeError
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return void
     */
    protected function setCryptoKey($cryptokey): void
    {
        if (! is_string($cryptokey)) {
            throw StrictTypeException::cryptoKeyNotString($cryptokey);
        }
        if (ctype_xdigit($cryptokey)) {
            $len = strlen($cryptokey);
            $cryptokey = sodium_hex2bin($cryptokey);
        }
        // insert check here to make sure is binary integer
        if (! isset($len)) {
            $hex = sodium_bin2hex($cryptokey);
            $len = strlen($hex);
            sodium_memzero($hex);
        }
        if ($len !== 64) {
            throw InvalidArgumentException::wrongByteSizeKey($len);
        }
        if (ctype_print($cryptokey)) {
            throw InvalidArgumentException::secretOnlyPrintable();
        }
        //test that the key supplied works
        $string = 'ABC test 123 test xyz';
        $TEST_NONCE = sodium_hex2bin('74b9e852b172df7f57ff4ab4');
        if ($this->aesgcm) {
            $ciphertext = sodium_crypto_aead_aes256gcm_encrypt($string, $TEST_NONCE, $TEST_NONCE, $cryptokey);
            $test = sodium_crypto_aead_aes256gcm_decrypt($ciphertext, $TEST_NONCE, $TEST_NONCE, $cryptokey);
        } else {
            $ciphertext = sodium_crypto_aead_chacha20poly1305_encrypt($string, $TEST_NONCE, $TEST_NONCE, $cryptokey);
            $test = sodium_crypto_aead_chacha20poly1305_decrypt($ciphertext, $TEST_NONCE, $TEST_NONCE, $cryptokey);
        }
        if ($string === $test) {
            $this->cryptokey = $cryptokey;
            $this->enabled = true;
            sodium_memzero($cryptokey);
            return;
        }
        return;
    }//end setCryptoKey()

    /**
     * Reads a JSON configuration and extracts info.
     *
     * @param string $file The path on the filesystem to the configuration file.
     *
     * @throws InvalidSetupException
     *
     * @return \stdClass Object with the extracted configuration info.
     */
    protected function readConfigurationFile($file)
    {
        if (! file_exists($file)) {
            throw InvalidSetupException::confNotFound($file);
        }
        if (! $json = file_get_contents($file)) {
            throw InvalidSetupException::confNotReadable($file);
        }
        if (! $config = json_decode($json)) {
            throw InvalidSetupException::confNotJson($file);
        }
        sodium_memzero($json);
        return $config;
    }//end readConfigurationFile()

    /**
     * Serializes the value to be cached and encrypts it.
     *
     * A specification of this function is it MUST increment the nonce BEFORE encryption and
     * verify it has incremented the nonce, throwing exception if increment failed.
     *
     * @param mixed $value The value to be serialized and encrypted.
     *
     * @throws \InvalidArgumentException
     * @throws \ErrorException
     *
     * @return \stdClass Object containing nonce and encrypted value.
     */
    protected function encryptData($value)
    {
        if (is_null($this->cryptokey)) {
            throw InvalidSetupException::nullSecret();
        }
        try {
            $serialized = serialize($value);
        } catch (\Error $e) {
            throw InvalidArgumentException::serializeFailed($e->getMessage());
        }
        $oldnonce = $this->nonce;
        if (is_null($this->nonce)) {
            // both IETF ChaCha20 and AES256GCM use 12 bytes for nonce
            $this->nonce = random_bytes(12);
        } else {
            sodium_increment($this->nonce);
        }
        if ($oldnonce === $this->nonce) {
            // This should never ever happen
            throw InvalidSetupException::nonceIncrementError();
        }
        $obj = new \stdClass;
        $obj->nonce = $this->nonce;
        if ($this->aesgcm) {
            $obj->ciphertext = sodium_crypto_aead_aes256gcm_encrypt(
                $serialized,
                $obj->nonce,
                $obj->nonce,
                $this->cryptokey
            );
        } else {
            $obj->ciphertext = sodium_crypto_aead_chacha20poly1305_ietf_encrypt(
                $serialized,
                $obj->nonce,
                $obj->nonce,
                $this->cryptokey
            );
        }
        sodium_memzero($serialized);
        // RESEARCH - How to zero out non-string? I don't believe recast
        //  will do it it properly
        if (is_string($value)) {
            sodium_memzero($value);
        }
        return $obj;
    }//end encryptData()

    /**
     * Returns the decrypted data retried from the APCu cache.
     *
     * @param object $obj     The object containing the nonce and cyphertext.
     * @param mixed  $default Always return the default if there is a problem decrypting the
     *                        the cyphertext so failure acts like a cache miss.
     *
     * @return mixed The decrypted data, or the default if decrypt failed.
     */
    protected function decryptData($obj, $default = null)
    {
        if (is_null($this->cryptokey)) {
            throw InvalidSetupException::nullSecret();
        }
        if (! isset($obj->nonce)) {
            return $default;
        }
        if (! isset($obj->ciphertext)) {
            return $default;
        }
        if ($this->aesgcm) {
            try {
                $serialized = sodium_crypto_aead_aes256gcm_decrypt(
                    $obj->ciphertext,
                    $obj->nonce,
                    $obj->nonce,
                    $this->cryptokey
                );
            } catch (\Error $e) {
                error_log($e->getMessage());
                return $default;
            }
        } else {
            try {
                $serialized = sodium_crypto_aead_chacha20poly1305_ietf_decrypt(
                    $obj->ciphertext,
                    $obj->nonce,
                    $obj->nonce,
                    $this->cryptokey
                );
            } catch (\Error $e) {
                error_log($e->getMessage());
                return $default;
            }
        }
        if ($serialized === false) {
            return $default;
        }
        try {
            $value = unserialize($serialized);
        } catch (\Error $e) {
            error_log($e->getMessage());
            return $default;
        }
        sodium_memzero($serialized);
        return $value;
    }//end decryptData()

    /* Non sodium specific functions */

    /**
     * Checks whether or not a parameter is of the iterable pseudo-type.
     *
     * @param mixed $arg The parameter to be checked.
     *
     * @throws StrictTypeException
     *
     * @return void
     */
    protected function checkIterable($arg): void
    {
        if (! is_iterable($arg)) {
            throw StrictTypeException::typeNotIterable($arg);
        }
    }//end checkIterable()

    /**
     * Takes user supplied key and creates internal cache key.
     *
     * This function takes the user defined cache key, gets the substring of a
     * hash from the weakHash function, and appends that substring to the WebApp
     * prefix string to create the actual key that will be used with the cache engine.
     *
     * @param string $key The user defined cache key to hash.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return string
     */
    protected function adjustKey($key): string
    {
        if (! $this->strictType) {
            $invalidTypes = array('array', 'object', 'boolean', 'NULL');
            $type = gettype($key);
            if (! in_array($type, $invalidTypes)) {
                $key = (string)$key;
            }
        }
        if (! is_string($key)) {
            throw StrictTypeException::keyTypeError($key);
        }
        $key = trim($key);
        if (strlen($key) === 0) {
            throw InvalidArgumentException::emptyKey();
        }
        if (strlen($key) > 255) {
            // key should not be larger
            //  than 255 character
            throw InvalidArgumentException::keyTooLong($key);
        }
        if (preg_match('/[\[\]\{\}\(\)\/\@\:\\\]/', $key) !== 0) {
            // PSR-16 says those characters not allowed
            throw InvalidArgumentException::invalidKeyCharacter($key);
        }
        $key = $this->webappPrefix . $this->weakHash($key);
        return $key;
    }//end adjustKey()

    /**
     * Sets the prefix (namespace) for the internal keys.
     *
     * @param string $str The string to use as internal key prefix.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return void
     */
    protected function setWebAppPrefix($str): void
    {
        $type = gettype($str);
        if (! is_string($str)) {
            throw StrictTypeException::cstrTypeError($str, 'WebApp Prefix');
        }
        $str = strtoupper(trim($str));
        if (strlen($str) < 3) {
            throw InvalidArgumentException::webappPrefixTooShort($str);
        }
        if (strlen($str) > 32) {
            throw InvalidArgumentException::webappPrefixTooLong($str);
        }
        if (preg_match('/[^A-Z0-9]/', $str) !== 0) {
            throw InvalidArgumentException::webappPrefixNotAlphaNumeric($str);
        }
        $this->webappPrefix = $str . '_';
    }//end setWebAppPrefix()

    /**
     * Sets the salt to use when generating the internal keys.
     *
     * @param string $str The string to use as the salt when creating the internal key prefix.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     *
     * @return void
     */
    protected function setHashSalt($str): void
    {
        $type = gettype($str);
        if (! is_string($str)) {
            throw StrictTypeException::cstrTypeError($str, 'Salt');
        }
        $str = trim($str);
        if (strlen($str) < 8) {
            throw InvalidArgumentException::saltTooShort($str);
        }
        $this->salt = $str;
    }//end setHashSalt()

    /**
     * Converts a \DateInterval object to seconds.
     *
     * @param \DateInterval $interval The date interval to be converted into seconds.
     *
     * @return int The number of seconds corresponding to the DateInterval.
     */
    protected function dateIntervalToSeconds($interval): int
    {
        $now = time();
        $dt = new \DateTime();
        $dt->add($interval);
        $ts = $dt->getTimestamp();
        $diff = $ts - $now;
        return $diff;
    }//end dateIntervalToSeconds()

    /**
     * Generates Time To Live parameter to use with the cache engine.
     *
     * This function takes either NULL, an integer or a string. When supplied
     * with an integer, if it is less than the current seconds from UNIX Epoch
     * it is treated as desired seconds the record should last. If it is larger
     * it assumed it is an expiration time and then calculates the corresponding
     * TTL. When fed a string, the `strtotime()` function is used to turn the
     * string into a UNIX seconds from Epoch expiration, and it then calculates
     * the corresponding TTL. When fed NULL, it uses the class default TTL.
     *
     * @param null|int|string|\DateInterval $ttl The length to cache or the expected expiration.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     * @psalm-suppress RedundantCondition
     *
     * @return int
     */
    protected function ttlToSeconds($ttl): int
    {
        if (is_null($ttl)) {
            return $this->defaultSeconds;
        }
        if (is_object($ttl)) {
            if ($ttl instanceof \DateInterval) {
                $seconds = $this->dateIntervalToSeconds($ttl);
                if ($seconds < 0) {
                    throw InvalidArgumentException::dateIntervalInPast();
                }
                return $seconds;
            } else {
                throw StrictTypeException::ttlTypeError($ttl);
            }
        }
        if (! $this->strictType) {
            if (is_numeric($ttl)) {
                $ttl = intval($ttl, 10);
            }
        }
        $type = gettype($ttl);
        if (! in_array($type, array('integer', 'string'))) {
            throw StrictTypeException::ttlTypeError($ttl);
        }
        $now = time();
        if (is_int($ttl)) {
            $seconds = $ttl;
            if ($seconds > $now) {
                return ($seconds - $now);
            }
            if ($seconds < 0) {
                throw InvalidArgumentException::negativeTTL($seconds);
            }
            return $seconds;
        }
        // hope it is a date string
        if ($seconds = strtotime($ttl, $now)) {
            if ($seconds > $now) {
                return ($seconds - $now);
            } else {
                throw InvalidArgumentException::dateStringInPast($ttl);
            }
        }
        throw InvalidArgumentException::invalidTTL($ttl);
    }//end ttlToSeconds()

    /**
     * Sets the default cache TTL in seconds.
     *
     * @param int|\DateInterval $ttl The default TTL to cache entries.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @psalm-suppress RedundantConditionGivenDocblockType
     * @psalm-suppress RedundantCondition
     *
     * @return void
     */
    public function setDefaultSeconds($ttl): void
    {
        $type = gettype($ttl);
        switch ($type) {
            case "integer":
                $seconds = $ttl;
                break;
            case "object":
                if ($ttl instanceof \DateInterval) {
                    $seconds = $this->dateIntervalToSeconds($ttl);
                } else {
                    throw StrictTypeException::defaultTTL($ttl);
                }
                break;
            default:
                if (! $this->strictType) {
                    if (is_numeric($ttl)) {
                        $seconds = intval($ttl);
                    }
                }
        }
        if (! isset($seconds)) {
            throw StrictTypeException::defaultTTL($ttl);
        }
        if ($seconds < 0) {
            throw InvalidArgumentException::negativeDefaultTTL($seconds);
        }
        $this->defaultSeconds = $seconds;
    }//end setDefaultSeconds()

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default (optional) Default value to return if the key does not exist.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     */
    public function get($key, $default = null)
    {
        $realKey = $this->adjustKey($key);
        if ($this->enabled) {
            return $this->cacheFetch($realKey, $default);
        }
        return $default;
    }//end get()

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                        $key   The key of the item to store.
     * @param mixed                         $value The value of the item to store, must be
     *                                             serializable.
     * @param null|int|string|\DateInterval $ttl   (optional) The TTL value of this item.
     *
     * @return bool True on success and false on failure.
     */
    public function set($key, $value, $ttl = null): bool
    {
        $realKey = $this->adjustKey($key);
        if ($this->enabled) {
            return $this->cacheStore($realKey, $value, $ttl);
        }
        return false;
    }//end set()

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function delete($key): bool
    {
        if ($this->enabled) {
            $realKey = $this->adjustKey($key);
            return $this->cacheDelete($realKey);
        }
        return false;
    }//end delete()

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable $keys    A list of keys that can obtained in a single operation.
     * @param mixed    $default Default value to return for keys that do not exist.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @return array A list of key => value pairs. Cache keys that do not exist or are
     *                                                stale will have $default as value.
     */
    public function getMultiple($keys, $default = null): array
    {
        $this->checkIterable($keys);
        $return = array();
        foreach ($keys as $userKey) {
            if (! is_string($userKey)) {
                throw StrictTypeException::iterableKeyMustBeString($userKey);
            }
            $value = $default;
            if ($this->enabled) {
                $realKey = $this->adjustKey($userKey);
                $value = $this->cacheFetch($realKey, $default);
            }
            $return[$userKey] = $value;
        }
        return $return;
    }//end getMultiple()

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable                      $pairs A list of key => value pairs for multiple
     *                                             set operation.
     * @param null|int|string|\DateInterval $ttl   (optional) The TTL value of this item.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @return bool True on success and false on failure.
     */
    public function setMultiple($pairs, $ttl = null): bool
    {
        if (! $this->enabled) {
            return false;
        }
        $this->checkIterable($pairs);
        $arr = array();
        foreach ($pairs as $key => $value) {
            if (! is_string($key)) {
                throw StrictTypeException::iterableKeyMustBeString($key);
            }
            $realKey = $this->adjustKey($key);
            $arr[$realKey] = $value;
        }
        foreach ($arr as $realKey => $value) {
            if (! $this->cacheStore($realKey, $value, $ttl)) {
                return false;
            }
        }
        return true;
    }//end setMultiple()

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable $keys A list of string-based keys to be deleted.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     */
    public function deleteMultiple($keys): bool
    {
        if (! $this->enabled) {
            return false;
        }
        $return = true;
        $this->checkIterable($keys);
        foreach ($keys as $userKey) {
            if (! is_string($userKey)) {
                throw StrictTypeException::iterableKeyMustBeString($userKey);
            }
            $realKey = $this->adjustKey($userKey);
            if (! $this->cacheDelete($realKey)) {
                $return = false;
            }
        }
        return $return;
    }//end deleteMultiple()

    /**
     * Returns the actual internal key being used with the caching engine. Needed for unit testing.
     *
     * @param string $key The key you wanted translated to the real key key.
     *
     * @throws StrictTypeException
     * @throws InvalidArgumentException
     *
     * @return string
     */
    public function getRealKey($key): string
    {
        return $this->adjustKey($key);
    }//end getRealKey()

    /* These need cache engine specific logic and are defined by the extending class */

    /**
     * A wrapper for the actual fetch from the cache.
     *
     * @param string $realKey The internal key used with the cache engine.
     * @param mixed  $default The value to return if a cache miss.
     *
     * @return mixed The value in the cached key => value pair, or $default if a cache miss
     */
    abstract protected function cacheFetch($realKey, $default);

    /**
     * A wrapper for the actual store of key => value pair in the cache.
     *
     * @param string                        $realKey The internal key used with the caching engine.
     * @param mixed                         $value   The value to be stored.
     * @param null|int|string|\DateInterval $ttl     The TTL value of this item.
     *
     * @return bool Returns True on success, False on failure
     */
    abstract protected function cacheStore($realKey, $value, $ttl): bool;

    /**
     * A wrapper for the actual delete of a key => value pair in the cache
     *
     * @param string $realKey The key for the key => value pair to be removed from the cache.
     *
     * @return bool Returns True on success, False on failure
     */
    abstract protected function cacheDelete($realKey): bool;

    /**
     * Wipes clean the entire cache's keys. This implementation only wipes for matching
     * webappPrefix (custom NON PSR-16 feature set during constructor).
     *
     * @return bool True on success and false on failure.
     */
    abstract public function clear(): bool;

    /**
     * Wipes clean the entire cache's keys regardless of webappPrefix.
     *
     * @return bool True on success and false on failure.
     */
    abstract public function clearAll(): bool;

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     */
    abstract public function has($key): bool;
}//end class

?>