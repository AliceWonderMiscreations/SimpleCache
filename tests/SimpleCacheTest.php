<?php
declare(strict_types=1);

/**
 * Unit testing for SimpleCache abstract class
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
final class SimpleCacheTest extends TestCase
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
             * Emulate a cache engine
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
             * @param string $realKey The real key.
             * @param mixed  $value   The value.
             * @param int    $ttl     The time to live.
             *
             * @return bool
             */
            protected function cacheStore($realKey, $value, $ttl): bool
            {
                $v = new \stdClass;
                $v->val = $value;
                $v->ttl = $ttl;
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
                unset($this->fakeCache[$realKey]);
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
                return true;
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

    /**
     * Cache test miss should return null, not false.
     *
     * @return void
     */
    public function testMissReturnsNull(): void
    {
        $key = 'I do not exist';
        $a = $this->testNotStrict->get($key);
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
        $this->testNotStrict->set($key, $expected);
        $actual = $this->testNotStrict->get($key);
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
        $this->testNotStrict->set($key, $expected);
        $actual = $this->testNotStrict->get($key);
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
        $this->testNotStrict->set($key, $expected);
        $actual = $this->testNotStrict->get($key);
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
        $this->testNotStrict->set($key, true);
        $actual = $this->testNotStrict->get($key);
        $this->assertTrue($actual);
        $this->testNotStrict->set($key, false);
        $actual = $this->testNotStrict->get($key);
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
        $this->testNotStrict->set($key, $arr);
        $a = $this->testNotStrict->get($key);
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
        $this->testNotStrict->set($key, $testObj);
        $a = $this->testNotStrict->get($key);
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
     * Set default TTL with integer
     *
     * @return void
     */
    public function testSetDefaultSecondsWithInteger(): void
    {
        $expected = 5;
        $this->testNotStrict->setDefaultSeconds($expected);
        $actual = $this->testNotStrict->unitReturnDefaultSeconds();
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
        $this->testNotStrict->setDefaultSeconds($interval);
        $expected = 273600;
        $actual = $this->testNotStrict->unitReturnDefaultSeconds();
        $this->assertEquals($expected, $actual);
    }//end testDefaultSecondsWithDateInterval()
}//end class

?>