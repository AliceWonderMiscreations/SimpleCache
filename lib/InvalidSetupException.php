<?php
declare(strict_types=1);

/**
 * Invalid Setup Exception.
 *
 * @package AWonderPHP/SimpleCache
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/SimpleCache
 */

namespace AWonderPHP\SimpleCache;

/**
 * Throws a \ErrorException exception.
 */
class InvalidSetupException extends \ErrorException implements \Psr\SimpleCache\CacheException
{
    /**
     * Error message when libsodium is not available.
     *
     * @return \ErrorException
     */
    public static function noLibSodium()
    {
        return new self(sprintf(
            'This class requires functions from the PECL libsodium extension.'
        ));
    }//end noLibSodium()

    /**
     * Error message when specified configuration file is not found.
     *
     * @param string $file The specified path to the file that can not be found.
     *
     * @return \ErrorException
     */
    public static function confNotFound(string $file)
    {
        return new self(sprintf(
            'The specified configuration file %s could not be found.',
            $file
        ));
    }//end confNotFound()

    /**
     * Error message when specified configuration file is not readable.
     *
     * @param string $file The specified path to the file that can not be read.
     *
     * @return \ErrorException
     */
    public static function confNotReadable(string $file)
    {
        return new self(sprintf(
            'The specified configuration file %s could not be read.',
            $file
        ));
    }//end confNotReadable()

    /**
     * Error message when specified configuration file does not contain valid JSON.
     *
     * @param string $file The specified path to the file with broken JSON.
     *
     * @return \ErrorException
     */
    public static function confNotJson(string $file)
    {
        return new self(sprintf(
            'The file %s did not contain valid JSON data.',
            $file
        ));
    }//end confNotJson()

    /**
     * Error message when the encryption secret is null. This should never happen.
     *
     * @return \ErrorException
     */
    public static function nullSecret()
    {
        return new self(sprintf(
            'The encryption secret is null. This should not have happened, something is broken'
        ));
    }//end nullSecret()

    /**
     * Error message when the class can not increment the nonce. This should never happen.
     *
     * @return \ErrorException
     */
    public static function nonceIncrementError()
    {
        return new self(sprintf(
            'The class nonce failed to increment. This should not have happened, something is broken'
        ));
    }//end nonceIncrementError()

    /**
     * Error message when the Redis object is null. This should never happen.
     *
     * @return \ErrorException
     */
    public static function nullRedis()
    {
        return new self(sprintf(
            'The Redis object is null. This should not have happened, something is broken'
        ));
    }//end nullRedis()

    /**
     * Error message when the Redis ping does not respond with pong.
     *
     * @return \ErrorException
     */
    public static function apcuNotAvailable()
    {
        return new self(sprintf(
            'APCu is either not installed or not enabled.'
        ));
    }//end apcuNotAvailable()

    /**
     * Error message when the Redis ping does not respond with pong.
     *
     * @return \ErrorException
     */
    public static function pingNoPongRedis()
    {
        return new self(sprintf(
            'I was not able to ping the Redis server.'
        ));
    }//end pingNoPongRedis()
}//end class

?>