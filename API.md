SimpleCache Abstract Class API Documentation
============================================

* [General Implementation Information](#general-implementation-information)
* [Abstract Class Properties](#abstract-class-properties)
* [Abstract Class Concrete Protected Methods](#abstract-class-concrete-protected-methods)
* [Abstract Class Concrete Public Methods](#abstract-class-concrete-public-methods)
* [Abstract Class Abstract Methods](#abstract-class-abstract-methods)
* [Writing The Constructor](#writing-the-constructor)
* [Magic Method Class Functions](#magic-method-class-functions)
* [Exception Classes](#exception-classes)
* [Unit Testing Your Class](#unit-testing-your-class)
* [Financial Support](#financial-support)


General Implementation Information
----------------------------------

To create a PSR-16 compliant caching engine from this class, your
`composer.json` file should contain the following:

    "require": {
        "php": "~7.1.0 || ~7.2.0",
        "awonderphp/simplecache": "^1.0",
        "psr/simple-cache": "^1.0"
    },

Of course you will also need to require the caching engine that you are
implenenting (e.g. `"ext-apcu": "^5.0"`).

If you are making use of the `libsodium` encryption capabilities, then you need
to also require `"ext-sodium": "~2.0.1"` (there was a bug in 2.0.0). PHP 7.2
users *should* already have that, but I have already come across packaged
versions of PHP 7.2 (e.g. the one `travis-ci` uses) where it was intentionally
disabled at build time. PHP 7.1 users and PHP 7.2 users with the sodium
extension disabled at build time will need to install it from PECL.

This is how you should define your extending class:

    namespace Whatever\YouWant;
    
    /**
     * class level DOCcomment
     */
    class YourClass extends \AWonderPHP\SimpleCache\SimpleCache implements \Psr\SimpleCache\CacheInterface

The `implements` is very important so that code checkers like
[`vimeo/psalm`](https://github.com/vimeo/psalm) can validate your class does
not conflict with the [PSR-16](https://www.php-fig.org/psr/psr-16/) defined
standard Common Interface for Caching Libraries.


Abstract Class Properties
-------------------------

The following properties exist in this class:

* `protected $enabled = false;`  
  Your class `__construct()` function should set that property to `true` when
  it has validated the calling environment has your chosen cache engine
  available for use.

* `protected $strictType = false;`  
  You do not need to worry about this if you do not want to. When set to `true`
  some of the concrete methods in this class perform more pedantic type
  checking of parameter arguments rather than just recasting to what the
  method expects. If you do wish to use this, it should be an optional argument
  to your class `__construct()` function that defaults to `false`.

* `protected $salt = '6Dxypt3ePw2SM2zYzEVAFkDBQpxbk16z1';`  
  This class obfuscates the `key` in the `key => value` pairs that are cached
  by the caching engine. Obfuscating the `key` does not add any security, but
  it allows any character to be used in the key the web application uses
  without worrying about bugs in the cache engine implementation. Nutshell, a
  hex hash of the key provided to the class by the web application is used as
  the actual `key` with the caching engine. This salt is used in the creation
  of the hash. Technically it does not need to exist, but I always prefer to
  use a salt when creating a hash from data. You can set this to something else
  in your class definition, and you can allow the web application to define its
  own desired salt in your class `__construct()` function. This abstract class
  provides a concrete method you class can use to allow a custom salt to be set
  when the class is instantiated.

* `protected $webappPrefix = 'DEFAULT_';`  
  Most cache engine implementations for PHP do not provide any mechanism for
  namespacing the cache entries. When the class creates the obfuscated `key`
  to use with the cache engine, this gets added as a prefix to that hash, to
  provide namespace capabilities to this cache implementation. Your class
  should allow setting of this property in the `__construct()` function. This
  abstract class provides a concrete method your class can use to allow a
  custom prefix to be set when the class is instantiated.

* `protected $defaultSeconds = 0;`  
  The default number of seconds for a cached `key => value` pair to be seen as
  still valid. With many cache engine implementations, a value of `0` means do
  not expire the `key => value` pair. This class provides a public concrete
  method that web applications can use to change the value of this property.

* `protected $cryptokey = null;`  
  This property only matters if you want the libsodium encryption features. It
  defined the secret to use when encrypting and decrypting the `value` part of
  the `key => value` pair. If used it should be set by your class
  `__construct()` function. It must be a 32-byte secret. This abstract class
  provides a concrete method your class can use to set this property when the
  class is instantiated.

* `protected $aesgcm = false;`  
  This property only matters if you want the libsodium encryption features. On
  processors that support
  [AES-NI](https://en.wikipedia.org/wiki/AES_instruction_set), the AES-GCM
  cipher is the fastest cipher to use. This is detected and set by the
  `checkForSodium()` function which should be called by your `__construct()`
  function if you are implementing the encryption features.

* `protected $nonce = null;`  
  Your class should not touch this property. In cryptography, a nonce does not
  need to be secret but it does need to be unique *every time it is used* with
  the same key. When the concrete method in this abstract class for encrypting
  data needs the nonce, if it is null it will generate a fresh nonce. If it is
  not null, it will increment the nonce so that it never uses the same nonce
  twice. Leave this alone and let the provided methods do their thing.


Abstract Class Concrete Protected Methods
-----------------------------------------

The following protected methods are provided in this class:

* `protected function weakHash($key): string`  
  The `key` provided to the class as part of the `key => value` pairs is not
  the `key` the class uses with the cache engine. The class obfuscates the
  `key` by hashing it and taking a substring of the hex representation of that
  hash. This is __not cryptographic__ but is done so that the class does not
  have to worry about what characters are allowed in a `key` by various cache
  engine implementations. This does __NOT__ make your cache safe from cache
  poison attacks. That can only be achieved by using the cryptography functions
  and then only if the attacker does not have the secret you use to encrypt
  values.

* `protected function checkForSodium(): void`  
  When your class encrypts the `value` portion of a `key => value` pair to be
  stored in cache, the PHP libsodium wrapper functions need to be available.
  This method should be called from your `__construct()` function to verify
  that the PHP environment your class is running in has them. An exception is
  thrown when `libsodium` is not available for use. This function also detects
  whether or not your CPU suppose AES-NI and set the `$aesgcm` class property
  to `true` if it does.

* `protected function setCryptoKey($cryptokey): void`  
  When your class encrypts the `value` portion of a `key => value` pair to be
  stored in cache, a 32-byte secret is needed. This function checks that the
  provided secret is a 32-byte key suitable to use as the secret, and sets the
  class `$cryptokey` property when it is suitable. An exception is thrown when
  it is not a suitable secret. This method should be called by your class
  `__construct()` function.

* `protected function readConfigurationFile($file)`  
  When your class encrypts the `value` portion of a `key => value` pair to be
  stored in cache, while not strictly required it is best if that secret is
  stored in a `JSON` configuration file. This method reads the configuration
  file and extracts the secret, along with a few other optional settings. It
  returns a `\stdClass` object containing the contents of the `JSON` file, it
  is up to your class to interpret and use what is in the `JSON` file. An
  exception is thrown if the `$file` is not a valid `JSON` file.

* `protected function encryptData($value)`  
  When your class encrypts the `value` portion of a `key => value` pair to be
  stored in cache, this is the method that does the encryption. It outputs a
  `\stdClass` object that has two properties: The `nonce` used and the
  encrypted data. This is the only method that should ever alter the class
  `$nonce` property. The `nonce` is also recorded in the Associated Data part
  of the `AEAD` encryption, but is set as a property of the output in the event
  that the cipher used ever needed to be changed to a non AEAD cipher. On
  failure to encrypt, an exception is thrown.

* `protected function decryptData($obj, $default = null)`  
  When your class encrypts the `value` portion of a `key => value` pair to be
  stored in cache, the encrypted data needs to be decrypted when retrieved
  from the cache. This method does the decryption. When it is successful, it
  returns the decrypted data. If it can not decrypt the data, it returns the
  `$default`.

* `protected function checkIterable($arg): void`  
  Some public methods required by a PSR-16 implementing class require that a
  parameter be iterable. This function checks to make sure `$arg` is of the
  `iterable` pseudo-type and throws an exception if it is not.

* `protected function adjustKey($key): string`  
  This method takes the `key` provided by the web application and turns it
  into the namespaced obfuscated `key` that is used with the cache engine. It
  returns a string, the obfuscated `key` that is used with the cache engine.

* `protected function setWebAppPrefix($str): void`  
  When the web application specifies a custom namespace it wants to use for its
  cache entries, this function will validate the namespace making sure it is
  alpha-numeric containing at least three characters but more than thirty-two.
  On successful validation, it then sets the class `$webappPrefix` property
  (appending an underscore to it). On failure, it throws an exception.

* `protected function setHashSalt($str): void`  
  When a web application specifies a custom salt it wants to use when the class
  generates the obfuscated `key`, this class verifies that the salt is at least
  eight characters long and then sets the class `$salt` property. It throws an
  exception on failure. It should be called from the constructor.

* `protected function dateIntervalToSeconds($interval): int`  
  This function takes a `\DateInterval` object and converts it into seconds. It
  is needed for PSR-16 compliance, where the web application __MUST__ be
  allowed to specify the `TTL` with a `\DateInterval` object.

* `protected function ttlToSeconds($ttl): int`  
  This class allows the `ttl` for a cache entry to specified in several
  different ways. In addition to the number of seconds or a `\DateInterval`
  object as specified by PSR-16, this class accepts a UNIX timestamp or any
  string that can be turned into a UNIX timestamp by the PHP `strtotime()`
  function. This functions turns the argument into an integer number of seconds
  to cache the entry for. It throws an exception if fed an invalid type or if
  the conversion results in a negative number of seconds.


Abstract Class Concrete Public Methods
--------------------------------------

The following public methods are provided in this class:

* `public function setDefaultSeconds($ttl): void`  
  Not strictly required by PSR-16 but mentioned in it. This method allows the
  default number of seconds to store a cached item to be set by the web
  application. It accepts an integer number of seconds or a `\DateInterval`
  object as the parameter. On success, it sets the class `$defaultSeconds`
  property. It fed an invalid argument, an exception is thrown.

* `public function get($key, $default = null)`  
  Part of PSR-16. The `$key` is the `key` as the web application sees it. It
  gets converted to the internal obfuscated `key` for the actual query to the
  cache engine. The function returns the associated `value` in the
  `key => value` pair or returns `$default` on a cache miss.

* `public function set($key, $value, $ttl = null): bool`  
  Part of PSR-16. The `$key` is the `key` as the web application sees it. It
  gets converted to the internal obfuscated `key` to store the `$value` with
  the cache engine. The `$ttl` parameter is how long the `key => value` pair
  should be considered valid to the cache engine. It can be an integer number
  of seconds, a `\DateInterval` object, a UNIX timestamp indicating when it
  should expire, or any string that can be turned into a UNIX timestamp using
  the PHP `strtotime()` function.

* `public function delete($key): bool`  
  Part of PSR-16. The `$key` is the `key` as the web application sees it. It
  gets converted to the internal obfuscated `key` that will be deleted from the
  cache-engine. Returns True on success, False on failure.

* `public function getMultiple($keys, $default = null): array`  
  Part of PSR-16. The `$keys` parameter needs to be an iterable type containing
  `key` strings to be queried for values from the cache engine. The `$default`
  parameter species the value to assign for a `key` when there is a cache miss.
  Returns an array of `key => value` pairs.

* `public function setMultiple($pairs, $ttl = null): bool`  
  Part of PSR-16. The `$pairs` parameter needs to be an iterable type
  containing `key => value` pairs to store in the cache engine. The `$ttl`
  parameter is how long the `key => value` pairs should be considered valid to
  the cache engine. It can be an integer number of seconds, a `\DateInterval`
  object, a UNIX timestamp indicating when they should expire, or any string
  that can be turned into a UNIX timestamp using the PHP `strtotime()`
  function. Returns True on success, False on failure.

* `public function deleteMultiple($keys): bool`  
  Part of PSR-16. The `$keys` parameter needs to be an iterable type containing
  `key` strings to be deleted from the cache engine. Returns True on success,
  False on failure.

* `public function getRealKey($key): string`  
  This function is _not_ part of PSR-16. It takes the `$key` parameter supplied
  to it and returns what the obfuscated key used with the cache engine would
  look like. It is useful for debugging.
  
### TTL Notes with `set()` and `setMultiple()`

When given a value of `0` the extending class should use the maximum TTL the
cache engine will cache for.

When given a value of `-1` this abstract will take 15% of the currently defined
default TTL and if that is >= 900 seconds, it takes a random number between 0
and that number and either ads it or subtracts if from the default TTL to use
with the key being set. This is done to allow automated expiration staggering
when many records are set at once, such as with cache warming, so they do not
all expire at the same time.


Abstract Class Abstract Methods
-------------------------------

These are methods you class extending this class __MUST__ define. They will
require functions specific to your chosen cache engine.

* `abstract protected function cacheFetch($realKey, $default);`  
  This function needs to take the obfuscated `key` as an argument and use it
  query your chosen cache engine for the `value` associated with that `key`.
  It should return the `value` on success and `$default` on a miss. If you
  use the cryptography features, your implementation of this method __must__
  call the protected `decryptData($obj, $default)` method to decrypt the
  data.

* `abstract protected function cacheStore($realKey, $value, $ttl): bool;`
  This function needs to take the obfuscated `key` as an argument and use it
  to set the `$value` in the cache engine associated with that `key` using the
  `$ttl` seconds specified. If your class uses the cryptography features your
  implementation of this method __must__ call the protected
  `encryptData($value)` method to turn the `$value` argument into an object
  that contains the encrypted data, and the method then caches that object. It
  should return True on success, False on failure.

* `abstract protected function cacheDelete($realKey): bool;`  
  This function needs to take the obfuscated `key` as an argument and use it
  to delete any cache entry that has that `key` from the cache engine. It
  should return True on success and False on failure.

* `abstract public function clear(): bool;`  
  Part of PSR-16. This function needs to delete every cache entry that is part
  of the namespace defined by the `$webappPrefix` class property. It is
  acceptable to PSR-16 to just delete everything, though it is better in my
  opinion to only delete the entries in the same namespace.

* `abstract public function clearAll(): bool;`  
  Not part of PSR-16 but it should do what most PSR-16 implementations do with
  `clear()` - it should completely nuke the cache, not giving a hoot what web
  applications created which entries. I personally never use it, but thought it
  should be available.

* `abstract public function has($key): bool;`  
  Part of PSR-16. The `$key` parameter should be converted to the obfuscated
  `key` used with the cache engine (e.g. via the `getRealKey` method)


Writing The Constructor
-----------------------

Your class `__construct()` function needs to do several things. It needs to
verify that the cache engine your extended class is written to implement is
available to the PHP environment that instantiated it. When that is the case
and you are __NOT__ using the cryptography features, you need to make sure
to set the class `$enabled` property to `true`. If you are using the encryption
features, then you should call the `checkForSodium()` protected method to make
sure the libsodium wrapper functions are available.

If using the cryptography capabilities, the first argument to your constructor
should be required and should either be the secret key to use for the
cryptography or the path to a JSON configuration file that contains the secret.
The protected function `setCryptoKey($cryptokey)` should be called to verify
that the secret is valid, it will also set the class `$cryptokey` for you.
Only call `setCryptoKey($cryptokey)` after you have verified the cache engine
is available for use, it will set the `$enabled` property to `true` and there
is no point in calling this function if the cache engine is not available.

Your constructor _should_ have a parameter that allows the web application to
set its own namespace. When that parameter is not null, your constructor should
call the protected `setWebAppPrefix($str)` function to validate the requested
namespace. That function will set the `$webappPrefix` class property for you.

Your constructor _may_ have a parameter that allows the web application to set
the salt used. The salt is not cryptographic in nature, forcing web
applications to use the default is acceptable, but system administrators
including myself often psychologically feel better when we are allowed to set
our own salt. If you allow web applications to specify their own salt, you
should call the protected `setHashSalt($str)` function to verify the salt has
at least characters. That function will set the `$salt` class property for you.

Finally your constructor _may_ have a parameter that allows the web application
to specify strict type enforcement. The only reason this defaults to `false` is
because PSR-16 does not mandate it. If I defaulted to `true` some web
applications that work with other PSR-16 implementations might not work with my
implementations, so for the sake of compatibility it defaults to `false`.

However when the incorrect type is supplied as a parameter, it often indicates
a code bug. Knowing about that bug so it can be fixed is better than recasting
what may be incorrect data. In my humble opinion.


Magic Method Class Functions
----------------------------

If your class uses the cryptography functions, it should have the following
magic methods defined to avoid accidental leakage of the secret:

    /**
     * Zeros and then removes the cryptokey from a var_dump of the object.
     * Also removes nonce from var_dump.
     *
     * @return array The array for var_dump().
     */
    public function __debugInfo()
    {
        $result = get_object_vars($this);
        sodium_memzero($result['cryptokey']);
        unset($result['cryptokey']);
        unset($result['nonce']);
        return $result;
    }//end __debugInfo()

    /**
     * Zeros the cryptokey property on class destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        sodium_memzero($this->cryptokey);
    }//end __destruct()

The first removes the cryptography secret from the results you would get doing
a `var_dump()` and related operations on your class object. It also removed the
nonce but only because it it meaningless data that almost always contains some
non-printable characters.

The second wipes the secret from system memory when the class instance is
destroyed.


Exception Classes
-----------------

There are three classes used for exceptions:

* `StrictTypeException`  
This class extends `\TypeError` and implements
`\Psr\SimpleCache\InvalidArgumentException`. It is used when a parameter is of
the wrong type and either can not be recast to the correct type or the class
property `$strictType` is set to `true`.
* `InvalidArgumentException`  
This class extends `\InvalidArgumentException` and implements
`\Psr\SimpleCache\InvalidArgumentException`. It is used when a parameter is of
the correct type but is otherwise invalid.
* `InvalidSetupException`  
This class extends `\ErrorException` and implements
`\Psr\SimpleCache\CacheException`. It is used when there is a problem reading a
`JSON` configuration file or when there is a problem with the system setup that
prevents the class from functioning but does not fit one of the other two.

You should extend those classes if you need exceptions messages that are not
covered by them.

If you need a completely new exception class, make sure it implements either
``\Psr\SimpleCache\InvalidArgumentException` or
`\Psr\SimpleCache\CacheException` as PSR-16 requires,


Unit Testing Your Class
-----------------------

The abstract class has some fairly extensive unit tests using
[PHPUnit 7](https://phpunit.de/) but the tests use an array to emulate a cache
engine, they do not actually test a cache engine.

The results of the most recent unit tests can be seen in the file
[UnitTestResults.txt](UnitTestResults.txt) and were run using PHP 7.1.x in
CentOS 7 using the [LibreLAMP](https://librelamp.com/) build of PHP.

You can adapt those unit tests to your own implementation. The code for them is
in the [tests](tests/) directory. You will need to modify them to use your
class obviously. Each test class uses a `setUp()` function to define an
[anonymous class](http://php.net/manual/en/language.oop5.anonymous.php) that
extends the abstract class for testing. Change that to instead create an
instance of your class rather than an anonymous class, and many of the tests
will `just work`. However some of them will need further adaptation (such as
for the TTL related tests).


Financial Support
-----------------

It took me an incredible amount of time to both create the unit tests that
ensure the class does what it is suppose to and write this documentation.

If you financially benefit from this class, I would greatly appreciate a
donation. I am currently not doing very well, see the file
[SURVIVAL.md](SURVIVAL.md) for details.


---------------------------------------------
__EOF__