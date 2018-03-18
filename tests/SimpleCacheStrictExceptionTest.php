<?php
declare(strict_types=1);

/**
 * Unit testing for SimpleCache abstract class. This not only tests the concrete
 * methods that do not depend upon cache implementation but it can serve as a template
 * for implementation unit tests, just do not create the anonymous class.
 *
 * @package AWonderPHP/SimpleCache
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/SimpleCache
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for SimpleCache no strict no encryption
 */
// @codingStandardsIgnoreLine
final class SimpleCacheStrictExceptionTest extends TestCase
{
    /**
     * The test object
     *
     * @var \AWonderPHP\SimpleCache\SimpleCache
     */
    private $testStrict;

    /**
     * PHPUnit Setup, create an anonymous class instance of SimpleCache
     *
     * @return void
     */
    public function setUp()
    {
        $this->testStrict = new class extends \AWonderPHP\SimpleCache\SimpleCache {
            /**
             * Turn on the class
             *
             * @var bool
             */
            protected $enabled = true;
            
            /**
             * Set to true for strict tests
             *
             * @var bool
             */
            protected $strictType = true;

            /**
             * Emulate a cache engine with a key => value array
             *
             * @var array
             */
            public $fakeCache = array();

            /**
             * Provide a concrete weakHash function
             *
             * @param string $key A key.
             *
             * @return string
             */
            protected function weakHash($key): string
            {
                $key = $this->salt . $key;
                $key = hash('ripemd160', $key);
                // 16^16 should be enough of the hash to avoid collisions
                return substr($key, 17, 16);
            }//end weakHash()

            /**
             * Provide a concrete cacheFetch function
             *
             * @param string $realKey The real key.
             * @param mixed  $default The default return.
             *
             * @return mixed
             */
            protected function cacheFetch($realKey, $default)
            {
                if (isset($this->fakeCache[$realKey])) {
                    return $this->fakeCache[$realKey]->val;
                }
                return $default;
            }//end cacheFetch()

            /**
             * Provide a concrete cacheStore function
             *
             * @param string                           $realKey The real key.
             * @param mixed                            $value   The value.
             * @param null|int|string|\DateInterval    $ttl     The time to live.
             *
             * @return bool
             */
            protected function cacheStore($realKey, $value, $ttl): bool
            {
                $seconds = $this->ttlToSeconds($ttl);
                $v = new \stdClass;
                $v->val = $value;
                $v->ttl = $seconds;
                $this->fakeCache[$realKey] = $v;
                return true;
            }//end cacheStore()

            /**
             * Provide a concrete cache delete function
             *
             * @param string $realKey The real key.
             *
             * @return bool
             */
            function cacheDelete($realKey): bool
            {
                $arr = array();
                $arr[$realKey] = true;
                $this->fakeCache = array_diff_key($this->fakeCache, $arr);
                return true;
            }//end cacheDelete()

            /**
             * Provide a concrete clear function
             *
             * @return bool
             */
            public function clear(): bool
            {
                return true;
            }//end clear()

            /**
             * Provide a concrete clearAll function
             *
             * @return bool
             */
            public function clearAll(): bool
            {
                $this->fakeCache = array();
                return true;
            }//end clearAll()

            /**
             * Provide a concrete has function
             *
             * @param string $key The key to check for.
             *
             * @return bool
             */
            public function has($key): bool
            {
                $realKey = $this->getRealKey($key);
                if (isset($this->fakeCache[$realKey])) {
                    return true;
                }
                return false;
            }//end has()

            /**
             * Provide a method for testing the set default TTL.
             *
             * @return int
             */
            public function unitReturnDefaultSeconds(): int
            {
                return $this->defaultSeconds;
            }//end unitReturnDefaultSeconds()
        };
    }//end setUp()

    /* type error tests */

    /**
     * Feed float data when setting the default TTL. Strict only test.
     *
     * @psalm-suppress InvalidScalarArgument
     *
     * @return void
     */
    public function testDefaultTtlInvalidTypeFloat(): void
    {
        $ttl = 55.55;
        $this->expectException(\TypeError::class);
        $this->testStrict->setDefaultSeconds($ttl);
    }//end testDefaultTtlInvalidTypeFloat()

    /**
     * Use integer as key. Strict test only
     *
     * @psalm-suppress InvalidScalarArgument
     *
     * @return void
     */
    public function testCacheKeyInvalidTypeInteger(): void
    {
        $value = '99 bottles of beer on the wall';
        $key = 67;
        $this->expectException(\TypeError::class);
        $this->testStrict->set($key, $value);
    }//end testCacheKeyInvalidTypeInteger()

    /**
     * Use float as key. Strict test only
     *
     * @psalm-suppress InvalidScalarArgument
     *
     * @return void
     */
    public function testCacheKeyInvalidTypeFloat(): void
    {
        $value = '99 bottles of beer on the wall';
        $key = 67.99412;
        $this->expectException(\TypeError::class);
        $this->testStrict->set($key, $value);
    }//end testCacheKeyInvalidTypeFloat()

    /**
     * Use float for key pair ttl. Strict test only
     *
     * @psalm-suppress InvalidScalarArgument
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidTypeFloat(): void
    {
        $ttl = 76.234;
        $this->expectException(\TypeError::class);
        $this->testStrict->set('foo', 'bar', $ttl);
    }//end testSetKeyPairTtlInvalidTypeFloat()
}//end class

?>