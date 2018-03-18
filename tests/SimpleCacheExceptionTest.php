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
final class SimpleCacheExceptionTest extends TestCase
{
    /**
     * The test object
     *
     * @var \AWonderPHP\SimpleCache\SimpleCache
     */
    private $testNotStrict;

    /**
     * PHPUnit Setup, create an anonymous class instance of SimpleCache
     *
     * @return void
     */
    public function setUp()
    {
        $this->testNotStrict = new class extends \AWonderPHP\SimpleCache\SimpleCache {
            /**
             * Turn on the class
             *
             * @var bool
             */
            protected $enabled = true;

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
     * Feed null data when setting the default TTL
     *
     * @return void
     */
    public function testDefaultTtlInvalidTypeNull(): void
    {
        $ttl = null;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setDefaultSeconds($ttl);
    }//end testDefaultTtlInvalidTypeNull()

    /**
     * Feed boolean data when setting the default TTL
     *
     * @return void
     */
    public function testDefaultTtlInvalidTypeBoolean(): void
    {
        $ttl = false;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setDefaultSeconds($ttl);
    }//end testDefaultTtlInvalidTypeBoolean()

    /**
     * Feed array data when setting the default TTL
     *
     * @return void
     */
    public function testDefaultTtlInvalidTypeArray(): void
    {
        $ttl = array(1,3,5);
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setDefaultSeconds($ttl);
    }//end testDefaultTtlInvalidTypeArray()

    /**
     * Feed stdClass object data when setting the default TTL
     *
     * @return void
     */
    public function testDefaultTtlInvalidTypeObject(): void
    {
        $ttl = new \stdClass;
        $ttl->foo = 7;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setDefaultSeconds($ttl);
    }//end testDefaultTtlInvalidTypeObject()

    /**
     * Use null as key
     *
     * @return void
     */
    public function testCacheKeyInvalidTypeNull(): void
    {
        $value = '99 bottles of beer on the wall';
        $key = null;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set($key, $value);
    }//end testCacheKeyInvalidTypeNull()

    /**
     * Use boolean as key
     *
     * @return void
     */
    public function testCacheKeyInvalidTypeBoolean(): void
    {
        $value = '99 bottles of beer on the wall';
        $key = true;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set($key, $value);
    }//end testCacheKeyInvalidTypeBoolean()

    /**
     * Use array as key
     *
     * @return void
     */
    public function testCacheKeyInvalidTypeArray(): void
    {
        $value = '99 bottles of beer on the wall';
        $key = array(3,4,5);
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set($key, $value);
    }//end testCacheKeyInvalidTypeArray()

    /**
     * Use object as key
     *
     * @return void
     */
    public function testCacheKeyInvalidTypeObject(): void
    {
        $value = '99 bottles of beer on the wall';
        $key = new \stdClass;
        $key->key = 'foo';
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set($key, $value);
    }//end testCacheKeyInvalidTypeObject()

    /**
     * Use boolean for key pair ttl
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidTypeBoolean(): void
    {
        $ttl = true;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set('foo', 'bar', $ttl);
    }//end testSetKeyPairTtlInvalidTypeBoolean()

    /**
     * Use array for key pair ttl
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidTypeArray(): void
    {
        $ttl = array(3,4,5);
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set('foo', 'bar', $ttl);
    }//end testSetKeyPairTtlInvalidTypeArray()

    /**
     * Use object for key pair ttl
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidTypeObject(): void
    {
        $ttl = new \stdClass;
        $ttl->foobar = "fubar";
        $this->expectException(\TypeError::class);
        $this->testNotStrict->set('foo', 'bar', $ttl);
    }//end testSetKeyPairTtlInvalidTypeObject()

    /**
     * Set multiple not iterable null.
     *
     * @return void
     */
    public function testSetMultipleInvalidTypeNull(): void
    {
        $keyValuePairs = null;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setMultiple($keyValuePairs);
    }//end testSetMultipleInvalidTypeNull()

    /**
     * Set multiple not iterable integer.
     *
     * @return void
     */
    public function testSetMultipleInvalidTypeInteger(): void
    {
        $keyValuePairs = 5;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setMultiple($keyValuePairs);
    }//end testSetMultipleInvalidTypeInteger()

    /**
     * Set multiple not iterable float.
     *
     * @return void
     */
    public function testSetMultipleInvalidTypeFloat(): void
    {
        $keyValuePairs = 51.50;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setMultiple($keyValuePairs);
    }//end testSetMultipleInvalidTypeFloat()

    /**
     * Set multiple not iterable boolean.
     *
     * @return void
     */
    public function testSetMultipleInvalidTypeBoolean(): void
    {
        $keyValuePairs = true;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setMultiple($keyValuePairs);
    }//end testSetMultipleInvalidTypeBoolean()

    /**
     * Set multiple not iterable string.
     *
     * @return void
     */
    public function testSetMultipleInvalidTypeString(): void
    {
        $keyValuePairs = 'This is a string';
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setMultiple($keyValuePairs);
    }//end testSetMultipleInvalidTypeString()

    /**
     * Set multiple with iterable but key not string
     *
     * @return void
     */
    public function testSetMultipleInvalidTypeKeyWithinNotString(): void
    {
        $obj = new \stdClass;
        $obj->animal = "Frog";
        $obj->mineral = "Quartz";
        $obj->vegetable = "Spinach";
        $keyValuePairs = array(
            "testInt" => 5,
            "testFloat" => 3.278,
            "testString" => "WooHoo",
            "testBoolean" => true,
            "testNull" => null,
            "testArray" => array(1, 2, 3, 4, 5),
            "testObject" => $obj
        );
        // this is what triggers it
        $keyValuePairs[] = 'Hello';
        $this->expectException(\TypeError::class);
        $this->testNotStrict->setMultiple($keyValuePairs);
    }//end testSetMultipleInvalidTypeKeyWithinNotString()

    /**
     * Get multiple not iterable null.
     *
     * @return void
     */
    public function testGetMultipleInvalidTypeNull(): void
    {
        $keyValuePairs = null;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->getMultiple($keyValuePairs);
    }//end testGetMultipleInvalidTypeNull()

    /**
     * Get multiple not iterable integer.
     *
     * @return void
     */
    public function testGetMultipleInvalidTypeInteger(): void
    {
        $keyValuePairs = 978;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->getMultiple($keyValuePairs);
    }//end testGetMultipleInvalidTypeInteger()

    /**
     * Get multiple not iterable float.
     *
     * @return void
     */
    public function testGetMultipleInvalidTypeFloat(): void
    {
        $keyValuePairs = 97.8;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->getMultiple($keyValuePairs);
    }//end testGetMultipleInvalidTypeFloat()

    /**
     * Get multiple not iterable boolean.
     *
     * @return void
     */
    public function testGetMultipleInvalidTypeBoolean(): void
    {
        $keyValuePairs = true;
        $this->expectException(\TypeError::class);
        $this->testNotStrict->getMultiple($keyValuePairs);
    }//end testGetMultipleInvalidTypeBoolean()

    /**
     * Get multiple not iterable string.
     *
     * @return void
     */
    public function testGetMultipleInvalidTypeString(): void
    {
        $keyValuePairs = "I like to party sometimes until four";
        $this->expectException(\TypeError::class);
        $this->testNotStrict->getMultiple($keyValuePairs);
    }//end testGetMultipleInvalidTypeString()

    /**
     * Get multiple with iterable but key not string
     *
     * @return void
     */
    public function testGetMultipleInvalidTypeKeyWithinNotString(): void
    {
        $obj = new \stdClass;
        $obj->animal = "Frog";
        $obj->mineral = "Quartz";
        $obj->vegetable = "Spinach";
        $keyValuePairs = array(
            "testInt" => 5,
            "testFloat" => 3.278,
            "testString" => "WooHoo",
            "testBoolean" => true,
            "testNull" => null,
            "testArray" => array(1, 2, 3, 4, 5),
            "testObject" => $obj
        );
        // this is what triggers it
        $keyValuePairs[] = 'Hello';
        $this->expectException(\TypeError::class);
        $this->testNotStrict->getMultiple($keyValuePairs);
    }//end testGetMultipleInvalidTypeKeyWithinNotString()

    /* Invalid Argument Tests */

    /**
     * Try setting a negative default ttl integer
     *
     * @return void
     */
    public function testDefaultTtlInvalidArgumentNegativeInteger(): void
    {
        $ttl = -7;
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->setDefaultSeconds($ttl);
    }//end testDefaultTtlInvalidArgumentNegativeInteger()

    /**
     * Try setting a negative default ttl DateInterval
     *
     * @return void
     */
    public function testDefaultTtlInvalidArgumentNegativeDateInterval(): void
    {
        $Today = new \DateTime('2012-01-02');
        $YesterDay = new \DateTime('2012-01-01');
        $interval = $Today->diff($YesterDay);
        $interval = $YesterDay->diff($Today);
        $interval->d = "-1";
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->setDefaultSeconds($interval);
    }//end testDefaultTtlInvalidArgumentNegativeDateInterval()

    /**
     * Try setting empty key
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentEmptyKey(): void
    {
        $key = '    ';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentEmptyKey()

    /**
     * Try setting barely too long key
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyBarelyTooLong(): void
    {
        $value = 'Test Value';
        $a='AAAAABB';
        $b='BBBBBBBB';
        $key = 'z';
        for ($i=0; $i<=30; $i++) {
            $key .= $b;
        }
        $key .= $a;
        $keylength = strlen($key);
        $this->assertEquals(256, $keylength);
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyBarelyTooLong()

    /**
     * Reserved Character In Key Left Curly
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsLeftCurly(): void
    {
        $key = 'key{key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsLeftCurly()

    /**
     * Reserved Character In Key Right Curly
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsRightCurly(): void
    {
        $key = 'key}key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsRightCurly()

    /**
     * Reserved Character In Key Left Parenthesis
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsLeftParenthesis(): void
    {
        $key = 'key(key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsLeftParenthesis()

    /**
     * Reserved Character In Key Right Parenthesis
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsRightParenthesis(): void
    {
        $key = 'key)key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsRightParenthesis()

    /**
     * Reserved Character In Key Forward Slash
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsForwardSlash(): void
    {
        $key = 'key/key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsForwardSlash()

    /**
     * Reserved Character In Key Back Slash
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsBackSlash(): void
    {
        $key = 'key\key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsBackSlash()

    /**
     * Reserved Character In Key atmark
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsAtmark(): void
    {
        $key = 'key@key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsAtmark()

    /**
     * Reserved Character In Key colon
     *
     * @return void
     */
    public function testSetKeyPairInvalidArgumentKeyContainsColon(): void
    {
        $key = 'key:key';
        $value = 'Test Value';
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value);
    }//end testSetKeyPairInvalidArgumentKeyContainsColon()

    /**
     * Negative TTL in set integer
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidArgumentNegativeInteger(): void
    {
        $key = "foo";
        $value = "bar";
        $ttl = -379;
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value, $ttl);
    }//end testSetKeyPairTtlInvalidArgumentNegativeInteger()

    /**
     * Negative TTL Date String In Past
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidArgumentDateStringInPast(): void
    {
        $key = "foo";
        $value = "bar";
        $ttl = "1984-02-21";
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value, $ttl);
    }//end testSetKeyPairTtlInvalidArgumentDateStringInPast()

    /**
     * Negative TTL Date Range In Past
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidArgumentDateRangeInPast(): void
    {
        $key = "foo";
        $value = "bar";
        $ttl = "-1 week";
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value, $ttl);
    }//end testSetKeyPairTtlInvalidArgumentDateRangeInPast()

    /**
     * Negative TTL Date Interval In Past
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidArgumentNegativeDateInterval(): void
    {
        $key = "foo";
        $value = "bar";
        $Today = new \DateTime('2012-01-02');
        $YesterDay = new \DateTime('2012-01-01');
        $interval = $Today->diff($YesterDay);
        $interval = $YesterDay->diff($Today);
        $interval->d = "-1";
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value, $interval);
    }//end testSetKeyPairTtlInvalidArgumentNegativeDateInterval()

    /**
     * Bogus String TTL
     *
     * @return void
     */
    public function testSetKeyPairTtlInvalidArgumentBogusString(): void
    {
        $key = "foo";
        $value = "bar";
        $ttl = "LKvfs4dh#";
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->set($key, $value, $ttl);
    }//end testSetKeyPairTtlInvalidArgumentBogusString()

    /**
     * Test key in iterable not legal
     *
     * @return void
     */
    public function testSetMultipleInvalidArgumentKeyInIterableHasReservedCharacter(): void
    {
        $arr = array(
            'key1' => 'value1',
            'key2' => 'value2',
            'ke}y3' => 'value3',
            'key4' => 'value4',
            'key5' => 'value5'
        );
        $this->expectException(\InvalidArgumentException::class);
        $this->testNotStrict->setMultiple($arr);
    }//end testSetMultipleInvalidArgumentKeyInIterableHasReservedCharacter()
}//end class

?>