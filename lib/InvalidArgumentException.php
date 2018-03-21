<?php
declare(strict_types=1);

/**
 * Invalid Argument Exception.
 *
 * @package AWonderPHP/SimpleCache
 * @author  Alice Wonder <paypal@domblogger.net>
 * @license https://opensource.org/licenses/MIT MIT
 * @link    https://github.com/AliceWonderMiscreations/SimpleCache
 */

namespace AWonderPHP\SimpleCache;

/**
 * Throws a \InvalidArgumentException exception.
 */
class InvalidArgumentException extends \InvalidArgumentException implements \Psr\SimpleCache\InvalidArgumentException
{
    /**
     * Exception message when the supplied cache key is an empty string.
     *
     * @return \InvalidArgumentException
     */
    public static function emptyKey()
    {
        return new self(sprintf(
            'The cache key you supplied was an empty string. It must contain at least one character.'
        ));
    }//end emptyKey()

    /**
     * Exception message when the supplied cache key contains reserved characters.
     *
     * @param string $key The key that contains illegal characters.
     *
     * @return \InvalidArgumentException
     */
    public static function invalidKeyCharacter(string $key)
    {
        return new self(sprintf(
            'Cache keys may not contain any of the following characters: "  %s  " but your key "  %s  " does.',
            '{}()/\@:',
            $key
        ));
    }//end invalidKeyCharacter()

    /**
     * Exception message when the supplied cache key exceeds 255 characters.
     *
     * @param string $key The key that contains too many characters.
     *
     * @return \InvalidArgumentException
     */
    public static function keyTooLong(string $key)
    {
        $length = strlen($key);
        return new self(sprintf(
            'Cache keys may not be longer than 255 characters. Your key is %s characters long.',
            $length
        ));
    }//end keyTooLong()

    /**
     * Exception message when a negative integer is supplies as the default TTL.
     *
     * @param int $seconds The integer argument that is less than zero.
     *
     * @return \InvalidArgumentException
     */
    public static function negativeDefaultTTL(int $seconds)
    {
        return new self(sprintf(
            'The default TTL can not be a negative number. You supplied %s.',
            $seconds
        ));
    }//end negativeDefaultTTL()

    /**
     * Exception message when a negative TTL is supplied when storing a key => value pair.
     *
     * @param int $seconds The integer argument that is less than zero.
     *
     * @return \InvalidArgumentException
     */
    public static function negativeTTL(int $seconds)
    {
        return new self(sprintf(
            'The TTL can not be a negative number. You supplied %s.',
            $seconds
        ));
    }//end negativeTTL()

    /**
     * Exception message when a date string contains a date in the past.
     *
     * @param string $str The date string that evaluates to before present.
     *
     * @return \InvalidArgumentException
     */
    public static function dateStringInPast(string $str)
    {
        return new self(sprintf(
            'The cache expiration can not be in the past. You supplied %s.',
            $str
        ));
    }//end dateStringInPast()

    /**
     * Exception message when a \DateInterval is in the past.
     *
     * @return \InvalidArgumentException
     */
    public static function dateIntervalInPast()
    {
        return new self(sprintf(
            'The cache expiration can not be in the past.'
        ));
    }//end dateIntervalInPast()

    /**
     * Exception message when a date string can not be evaluated.
     *
     * @param string $str The date string that can not be evaluated.
     *
     * @return \InvalidArgumentException
     */
    public static function invalidTTL(string $str)
    {
        $errorString = 'The cache expiration must be a non-zero TTL in seconds, seconds from UNIX epoch, ';
        $errorString .= 'a DateInterval, or an expiration date string. You supplied: %s';
        return new self(sprintf(
            $errorString,
            $str
        ));
    }//end invalidTTL()

    /**
     * Exception message when the supplied salt is too short.
     *
     * @param string $str The provided salt that is too short.
     *
     * @return \InvalidArgumentException
     */
    public static function saltTooShort(string $str)
    {
        $length = strlen($str);
        if ($length === 0) {
            return new self(sprintf(
                'The internal key salt must be at least 8 characters. You supplied an empty salt.'
            ));
        } else {
            return new self(sprintf(
                'The internal key salt must be at least 8 characters. You supplied a %s character salt: %s',
                $length,
                $str
            ));
        }
    }//end saltTooShort()

    /**
     * Exception message when WebApp Prefix too short.
     *
     * @param string $str The provided WebApp Prefix.
     *
     * @return \InvalidArgumentException
     */
    public static function webappPrefixTooShort(string $str)
    {
        $length = strlen($str);
        if ($length === 0) {
            return new self(sprintf(
                'The WebApp Prefix must be at least 3 characters. You supplied an empty Prefix.'
            ));
        } else {
            return new self(sprintf(
                'The WebApp Prefix must be at least 3 characters. You supplied a %s character Prefix: %s',
                $length,
                $str
            ));
        }
    }//end webappPrefixTooShort()

    /**
     * The exception message when WebApp Prefix too long.
     *
     * @param string $str The provided WebApp Prefix.
     *
     * @return \InvalidArgumentException
     */
    public static function webappPrefixTooLong(string $str)
    {
        $length = strlen($str);
        return new self(sprintf(
            'The WebApp Prefix must not have more than 32 characters. You supplied a %s character Prefix.',
            $length
        ));
    }//end webappPrefixTooLong()

    /**
     * The exception message when the WebApp Prefix is not alphanumeric.
     *
     * @param string $str The provided WebApp Prefix.
     *
     * @return \InvalidArgumentException
     */
    public static function webappPrefixNotAlphaNumeric(string $str)
    {
        return new self(sprintf(
            'The WebApp Prefix can only contain A-Z letters and 0-9 numbers. You supplied: %s',
            $str
        ));
    }//end webappPrefixNotAlphaNumeric()

    /**
     * The exception message when the supplied encryption key is not 32 bytes.
     *
     * @param int $len The number of bytes in supplied encryption key.
     *
     * @return \InvalidArgumentException
     */
    public static function wrongByteSizeKey(int $len)
    {
        $bytes = intdiv($len, 2);
        return new self(sprintf(
            'The secret key must be 32 bytes. You provided a %s byte key.',
            $bytes
        ));
    }//end wrongByteSizeKey()

    /**
     * The exception message when the supplied encryption key only has printable characters.
     *
     * @return \InvalidArgumentException
     */
    public static function secretOnlyPrintable()
    {
        return new self(sprintf(
            'The secret key you supplied only contains printable characters.'
        ));
    }//end secretOnlyPrintable()

    /**
     * The exception message when pre-encryption serialization has failed.
     *
     * @param string $str The captured exception from the failed serialization attempt.
     *
     * @return \InvalidArgumentException
     */
    public static function serializeFailed(string $str)
    {
        return new self(sprintf(
            'Serialization failed with following message: %s',
            $str
        ));
    }//end serializeFailed()
}//end class

?>