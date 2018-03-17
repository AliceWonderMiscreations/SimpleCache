<?php
declare(strict_types=1);

/**
 * Unit testing for SimpleCache abstract class with libsodium. This not only tests the
 * concrete methods that do not depend upon cache implementation but it can serve as a
 * template for implementation unit tests, just do not create the anonymous class.
 *
 * This test only test with aesgcm but does test with chacha20poly1305
 *
 * @package AWonderPHP\SimpleCache
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/SimpleCache
 */

use PHPUnit\Framework\TestCase;

/**
 * Test class for SimpleCache no strict no encryption
 */
// @codingStandardsIgnoreLine
final class SimpleCacheSodiumTest extends TestCase
{
    /**
     * The test object
     *
     * @var \AWonderPHP\SimpleCache\SimpleCache
     */
    private $testSodiumNotStrict;

    /**
     * PHPUnit Setup, create an anonymous class instance of SimpleCache
     *
     * @return void
     */
    public function setUp()
    {
        $this->testSodiumNotStrict = new class extends \AWonderPHP\SimpleCache\SimpleCache {
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
                    $obj = $this->fakeCache[$realKey]->val;
                    return $this->decryptData($obj, $default);
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
                $obj = $this->encryptData($value);
                $v = new \stdClass;
                $v->val = $obj;
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
            
            /**
             * Set the crypto key to use for testing
             *
             * @return void
             */
            public function setTestCryptoKey()
            {
                $this->cryptokey = hex2bin('c2060408cf4602ec2013c6aa77654b6ed1ad41cd0fcdce97ab067f4e971a7605');
            }//end setTestCryptoKey()

        };
        $this->testSodiumNotStrict->setTestCryptoKey();
    }//end setUp()

    /**
     * Real key should not be the key used by set. This function directly
     * looks at the emulated cache.
     *
     @return void
     */
    public function testCacheKeyObfuscation(): void
    {
        $key = "I am a test key";
        $value = "I am a test value";
        $this->testSodiumNotStrict->set($key, $value);
        $keys = array_keys($this->testSodiumNotStrict->fakeCache);
        $actualKey = $keys[0];
        $storedObject = $this->testSodiumNotStrict->fakeCache[$actualKey];
        $storedValue = $storedObject->val;
//        $this->assertEquals($value, $storedValue);
        $arr = explode('_', $actualKey);
        $this->assertEquals('DEFAULT', $arr[0]);
        $bool = ctype_xdigit($arr[1]);
        $this->assertTrue($bool);
    }//end testCacheKeyObfuscation()

    /**
     * Cache test miss should return null, not false.
     *
     * @return void
     */
    public function testMissReturnsNull(): void
    {
        $key = 'I do not exist';
        $a = $this->testSodiumNotStrict->get($key);
        $this->assertNull($a);
    }//end testMissReturnsNull()

    /**
     * Set and retrieve a string.
     *
     * @return void
     */
    public function testSetAndRetrieveString(): void
    {
        $key = "A test key";
        $expected = "Fubar String";
        $this->testSodiumNotStrict->set($key, $expected);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertEquals($expected, $actual);
    }//end testSetAndRetrieveString()

    /**
     * Set and retrieve an integer.
     *
     * @return void
     */
    public function testSetAndRetrieveInteger(): void
    {
        $key = "A test key";
        $expected = 27;
        $this->testSodiumNotStrict->set($key, $expected);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertEquals($expected, $actual);
    }//end testSetAndRetrieveInteger()

    /**
     * Set and retrieve a float.
     *
     * @return void
     */
    public function testSetAndRetrieveFloat(): void
    {
        $key = "A test key";
        $expected = 7.234;
        $this->testSodiumNotStrict->set($key, $expected);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertEquals($expected, $actual);
    }//end testSetAndRetrieveFloat()

    /**
     * Set and retrieve a Boolean, test both true and false.
     *
     * @return void
     */
    public function testSetAndRetrieveBoolean(): void
    {
        $key = "A test key";
        $this->testSodiumNotStrict->set($key, true);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertTrue($actual);
        $this->testSodiumNotStrict->set($key, false);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertFalse($actual);
    }//end testSetAndRetrieveBoolean()

    /**
     * Set and retrieve an array.
     *
     * @return void
     */
    public function testSetAndRetrieveArray(): void
    {
        $obj = new \stdClass;
        $obj->animal = "Frog";
        $obj->mineral = "Quartz";
        $obj->vegetable = "Spinach";
        $arr = array(
            "testInt" => 5,
            "testFloat" => 3.278,
            "testString" => "WooHoo",
            "testBoolean" => true,
            "testNull" => null,
            "testArray" => array(1, 2, 3, 4, 5),
            "testObject" => $obj
        );
        $key = "TestArray";
        $this->testSodiumNotStrict->set($key, $arr);
        $a = $this->testSodiumNotStrict->get($key);
        $bool = is_array($a);
        $this->assertTrue($bool);
        $this->assertEquals($arr['testInt'], $a['testInt']);
        $this->assertEquals($arr['testFloat'], $a['testFloat']);
        $this->assertEquals($arr['testString'], $a['testString']);
        $this->assertEquals($arr['testBoolean'], $a['testBoolean']);
        $this->assertNull($a['testNull']);
        $this->assertEquals($arr['testArray'], $a['testArray']);
        $this->assertEquals($arr['testObject'], $a['testObject']);
    }//end testSetAndRetrieveArray()

    /**
     * Set and retrieve a n object.
     *
     * @return void
     */
    public function testSetAndRetrieveObject(): void
    {
        $obj = new \stdClass;
        $obj->animal = "Frog";
        $obj->mineral = "Quartz";
        $obj->vegetable = "Spinach";
        $testObj = new \stdClass;
        $testObj->testInt = 5;
        $testObj->testFloat = 3.278;
        $testObj->testString = "WooHoo";
        $testObj->testBoolean = true;
        $testObj->testNull = null;
        $testObj->testArray = array(1,2,3,4,5);
        $testObj->testObject = $obj;
        $key = "TestObject";
        $this->testSodiumNotStrict->set($key, $testObj);
        $a = $this->testSodiumNotStrict->get($key);
        $bool = is_object($a);
        $this->assertTrue($bool);

        $this->assertEquals($testObj->testInt, $a->testInt);
        $this->assertEquals($testObj->testFloat, $a->testFloat);
        $this->assertEquals($testObj->testString, $a->testString);
        $this->assertEquals($testObj->testBoolean, $a->testBoolean);
        $this->assertNull($a->testNull);
        $this->assertEquals($testObj->testArray, $a->testArray);
        $this->assertEquals($testObj->testObject, $a->testObject);
    }//end testSetAndRetrieveObject()

    /**
     * Delete a key
     *
     * @return void
     */
    public function testDeleteAKey(): void
    {
        $this->testSodiumNotStrict->set('Test Key 1', 'foo 1');
        $this->testSodiumNotStrict->set('Test Key 2', 'foo 2');
        $this->testSodiumNotStrict->set('Test Key 3', 'foo 3');
        $bool = $this->testSodiumNotStrict->has('Test Key 1');
        $this->assertTrue($bool);
        $bool = $this->testSodiumNotStrict->has('Test Key 2');
        $this->assertTrue($bool);
        $bool = $this->testSodiumNotStrict->has('Test Key 3');
        $this->assertTrue($bool);

        $this->testSodiumNotStrict->delete('Test Key 2');
        $bool = $this->testSodiumNotStrict->has('Test Key 1');
        $this->assertTrue($bool);
        $bool = $this->testSodiumNotStrict->has('Test Key 2');
        $this->assertFalse($bool);
        $bool = $this->testSodiumNotStrict->has('Test Key 3');
        $this->assertTrue($bool);
    }//end testDeleteAKey()

    /**
     * Test Key Length of 1 character
     *
     * @return void
     */
    public function testAcceptKeyLengthOf1(): void
    {
        $key = 'j';
        $expected = 'fooBar 2000';
        $this->testSodiumNotStrict->set($key, $expected);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertEquals($expected, $actual);
    }//end testAcceptKeyLengthOf1()

    /**
     * Test Key Length of 255 characters
     *
     * @return void
     */
    public function testAcceptKeyLengthOf255(): void
    {
        $a='AAAAABB';
        $b='BBBBBBBB';
        $key = '';
        for ($i=0; $i<=30; $i++) {
            $key .= $b;
        }
        $key .= $a;
        $keylength = strlen($key);
        $this->assertEquals(255, $keylength);

        $expected = 'fooBar 2001';
        $this->testSodiumNotStrict->set($key, $expected);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertEquals($expected, $actual);
    }//end testAcceptKeyLengthOf255()

    /**
     * Accept multibyte character key
     *
     * @return void
     */
    public function testAcceptMultibyteCharacterKey(): void
    {
        $key = 'いい知らせ';
        $expected = 'חדשות טובות';
        $this->testSodiumNotStrict->set($key, $expected);
        $actual = $this->testSodiumNotStrict->get($key);
        $this->assertEquals($expected, $actual);
    }//end testAcceptMultibyteCharacterKey()

    /**
     * Set ttl for key => value pair as integer
     *
     * @return void
     */
    public function testSetCacheLifeAsInteger(): void
    {
        $key = 'Cache Life As Integer';
        $value = 'Some Value';
        $seconds = 27;
        $this->testSodiumNotStrict->set($key, $value, $seconds);
        $realKey = $this->testSodiumNotStrict->getRealKey($key);
        $realObject = $this->testSodiumNotStrict->fakeCache[$realKey];
        $realTTL = $realObject->ttl;
        $this->assertEquals($seconds, $realTTL);
    }//end testSetCacheLifeAsInteger()

    /**
     * Set ttl for key => value with unix timestamp
     *
     * @return void
     */
    public function testSetCacheLifeAsUnixTimestamp(): void
    {
        $key = 'Cache Life As TS';
        $value = 'Some Value';
        $rnd = rand(34, 99);
        $ttl = time() + $rnd;
        $this->testSodiumNotStrict->set($key, $value, $ttl);
        $realKey = $this->testSodiumNotStrict->getRealKey($key);
        $realObject = $this->testSodiumNotStrict->fakeCache[$realKey];
        $realTTL = $realObject->ttl;
        $this->assertEquals($rnd, $realTTL);
    }//end testSetCacheLifeAsUnixTimestamp()

    /**
     * Set ttl for key => value with date range
     *
     * @return void
     */
    public function testSetCacheLifeAsStringWithDateRange(): void
    {
        $key = "Cache Life as string with date range";
        $value = "Staying Alive";
        $ttl = '+1 week';
        $this->testSodiumNotStrict->set($key, $value, $ttl);
        $realKey = $this->testSodiumNotStrict->getRealKey($key);
        $realObject = $this->testSodiumNotStrict->fakeCache[$realKey];
        $realTTL = $realObject->ttl;
        $this->assertEquals($realTTL, 604800);
    }//end testSetCacheLifeAsStringWithDateRange()

    /**
     * Set TTL for key => value with date as string
     *
     * @return void
     */
    public function testSetCacheLifeAsStringWithFixedDate(): void
    {
        $key = "Cache Life as string with date";
        $value = "Staying More Alive";
        $a = (24 * 60 * 60);
        $b = (48 * 60 * 60);
        $dateUnix = time() + $b;
        $dateString = date('Y-m-d', $dateUnix);
        $this->testSodiumNotStrict->set($key, $value, $dateString);
        $realKey = $this->testSodiumNotStrict->getRealKey($key);
        $realObject = $this->testSodiumNotStrict->fakeCache[$realKey];
        $realTTL = $realObject->ttl;
        $a--;
        $b++;
        $this->assertLessThan($realTTL, $a);
        $this->assertLessThan($b, $realTTL);
    }//end testSetCacheLifeAsStringWithFixedDate()

    /**
     * Test with a very very very large TTL but not current timestamp large
     *
     * @return void
     */
    public function testSetCacheLifeAsVeryVeryLargeInteger(): void
    {
        $key = "Cache Life As Huge Integer";
        $value = "Size Matters";
        $ttl = time() - 7;
        $this->testSodiumNotStrict->set($key, $value, $ttl);
        $realKey = $this->testSodiumNotStrict->getRealKey($key);
        $realObject = $this->testSodiumNotStrict->fakeCache[$realKey];
        $realTTL = $realObject->ttl;
        $this->assertEquals($realTTL, $ttl);
    }//end testSetCacheLifeAsVeryVeryLargeInteger()

    /**
     * Test setting $key => $value pair as a DateInterval object
     *
     * @return void
     */
    public function testSetCacheLifeAsDateIntervalObject(): void
    {
        $key = "Cache Life As Date Interval";
        $value = "yum";
        $ttl = new \DateInterval('P3DT4H');
        $expected = 273600;
        $this->testSodiumNotStrict->set($key, $value, $ttl);
        $realKey = $this->testSodiumNotStrict->getRealKey($key);
        $realObject = $this->testSodiumNotStrict->fakeCache[$realKey];
        $realTTL = $realObject->ttl;
        $this->assertEquals($realTTL, $expected);
    }//end testSetCacheLifeAsDateIntervalObject()

    /**
     * Set default TTL with integer
     *
     * @return void
     */
    public function testSetDefaultSecondsWithInteger(): void
    {
        $expected = 5;
        $this->testSodiumNotStrict->setDefaultSeconds($expected);
        $actual = $this->testSodiumNotStrict->unitReturnDefaultSeconds();
        $this->assertEquals($expected, $actual);
    }//end testSetDefaultSecondsWithInteger()

    /**
     * Set default TTL with DateInterval
     *
     * @return void
     */
    public function testDefaultSecondsWithDateInterval(): void
    {
        $interval = new \DateInterval('P3DT4H');
        $this->testSodiumNotStrict->setDefaultSeconds($interval);
        $expected = 273600;
        $actual = $this->testSodiumNotStrict->unitReturnDefaultSeconds();
        $this->assertEquals($expected, $actual);
    }//end testDefaultSecondsWithDateInterval()

    /**
     * Set multiple key => value pairs
     *
     * @return void
     */
    public function testSetMultipleKeyValuePairsAtOnce(): void
    {
        $obj = new \stdClass;
        $obj->animal = "Frog";
        $obj->mineral = "Quartz";
        $obj->vegetable = "Spinach";
        $arr = array(
            "testInt" => 5,
            "testFloat" => 3.278,
            "testString" => "WooHoo",
            "testBoolean" => true,
            "testNull" => null,
            "testArray" => array(1, 2, 3, 4, 5),
            "testObject" => $obj
        );
        $arr['Hello'] = null;
        $arr['Goodbye'] = null;

        $this->testSodiumNotStrict->setMultiple($arr);

        foreach (array(
            'testInt',
            'testFloat',
            'testString',
            'testBoolean',
            'testArray',
            'testObject'
        ) as $key) {
            $a = $this->testSodiumNotStrict->get($key);
            switch ($key) {
                case 'testObject':
                    $this->assertEquals($a->animal, $obj->animal);
                    $this->assertEquals($a->mineral, $obj->mineral);
                    $this->assertEquals($a->vegetable, $obj->vegetable);
                    break;
                default:
                    $this->assertEquals($arr[$key], $a);
            }
        }
        // test the three that should be null
        foreach (array('testNull', 'Hello', 'Goodbye') as $key) {
            $a = $this->testSodiumNotStrict->get($key);
            $this->assertNull($a);
            $bool = $this->testSodiumNotStrict->has($key);
            $this->assertTrue($bool);
        }
    }//end testSetMultipleKeyValuePairsAtOnce()

    /**
     * Test Get Multiple Pairs At Once
     *
     * @return void
     */
    public function testGetMultipleKeyValuePairsAtOnce(): void
    {
        $obj = new \stdClass;
        $obj->animal = "Frog";
        $obj->mineral = "Quartz";
        $obj->vegetable = "Spinach";
        $arr = array(
            "testInt" => 5,
            "testFloat" => 3.278,
            "testString" => "WooHoo",
            "testBoolean" => true,
            "testNull" => null,
            "testArray" => array(1, 2, 3, 4, 5),
            "testObject" => $obj
        );
        $arr['Hello'] = null;
        $arr['Goodbye'] = null;

        $this->testSodiumNotStrict->setMultiple($arr);

        $tarr = array();
        $tarr[] = 'testBoolean';
        $tarr[] = 'testFloat';
        $tarr[] = 'testCacheMiss';
        $tarr[] = 'testString';
        $result = $this->testSodiumNotStrict->getMultiple($tarr);

        $boolean = array_key_exists('testBoolean', $result);
        $this->assertTrue($boolean);
        $boolean = array_key_exists('testFloat', $result);
        $this->assertTrue($boolean);
        $boolean = array_key_exists('testCacheMiss', $result);
        $this->assertTrue($boolean);
        $boolean = array_key_exists('testString', $result);
        $this->assertTrue($boolean);

        $this->assertEquals($result['testBoolean'], $arr['testBoolean']);
        $this->assertEquals($result['testFloat'], $arr['testFloat']);
        $this->assertEquals($result['testString'], $arr['testString']);
        $this->assertNull($result['testCacheMiss']);
    }//end testGetMultipleKeyValuePairsAtOnce()

    /**
     * Test deleting multiple keys at once
     *
     * @return void
     */
    public function testDeleteMultipleKeyValuePairsAtOnce(): void
    {
        $arr = array();
        $records = rand(220, 370);
        for ($i=0; $i <= $records; $i++) {
            $key = 'KeyNumber-' . $i;
            $val = 'ValueNumber-' . $i;
            $arr[$key] = $val;
        }
        $start = count($arr);
        $this->testSodiumNotStrict->setMultiple($arr);

        $del = array();
        $n = rand(75, 167);
        $max = $records - 5;
        for ($i=0; $i<$n; $i++) {
            $key = 'KeyNumber-' . rand(5, $max);
            if (! in_array($key, $del)) {
                $del[] = $key;
            }
        }
        $delcount = count($del);
        $expected = $start - $delcount;

        $this->testSodiumNotStrict->deleteMultiple($del);
        $hits = 0;
        for ($i=0; $i<= $records; $i++) {
            $key = 'KeyNumber-' . $i;
            if ($this->testSodiumNotStrict->has($key)) {
                $hits++;
            }
        }
        $this->assertEquals($expected, $hits);
    }//end testDeleteMultipleKeyValuePairsAtOnce()
}//end class

?>