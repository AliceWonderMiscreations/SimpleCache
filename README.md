SimpleCache
===========

An abstract class for implementations of PSR-16 to extend.

This package provides an abstract class independent of cache back-end that
classes implementing [PSR-16](https://www.php-fig.org/psr/psr-16/) can extend.

For a working class that extends this abstract class, use
[SimpleCacheAPCu](https://github.com/AliceWonderMiscreations/SimpleCacheAPCu).


About PHP-FIG and PSR-16
------------------------

PHP-FIG is the [PHP Framework Interop Group](https://www.php-fig.org/). They
exist largely to create standards that make it easier for different developers
around the world to create different projects that will work well with each
other. PHP-FIG was a driving force behind the PSR-0 and PSR-4 auto-load
standards for example that make it *much much* easier to integrate PHP class
libraries written by other people into your web applications.

The PHP-FIG previously released PSR-6 as a Caching Interface standard but the
interface requirements of PSR-6 are beyond the needs of many web application
developers. KISS - ‘Keep It Simple Silly’ applies for many of us who do not
need some of the features PSR-6 requires.

To meet the needs of those of us who do not need what PSR-6 implements,
[PSR-16](https://www.php-fig.org/psr/psr-16/) was developed and is now an
accepted standard.

When I read PSR-16, the defined interface was not *that* different from my
own APCu caching class that I have personally been using for years. So I
decided to make my class meet the interface requirements.

I then decided to abstract it, so that those who use something other than APCu
can easily adapt this to what they use.


Coding Standard
---------------

The coding standard used is primarily
[PSR-2](https://www.php-fig.org/psr/psr-2/) except with the closing `?>`
allowed, and the addition of some
[PHPDoc](https://en.wikipedia.org/wiki/PHPDoc) requirements largely but not
completely borrowed from the
[PEAR standard](http://pear.php.net/manual/en/standards.php).

The intent is switch PHPDoc standard to
[PSR-5](https://github.com/phpDocumentor/fig-standards/blob/master/proposed/phpdoc.md)
if it ever becomes an accepted standard.

The `phpcs` sniff rules being used: [psr2.phpcs.xml](psr2.phpcs.xml)


About AWonderPHP
----------------

I may become homeless before the end of 2018. I do not know how to survive, I
try but what I try, it always seems to fail. This just is not a society people
like me are meant to be a part of.

If I do become homeless, I fear my mental health will deteriorate at an
accelerated rate and I do not want to witness that happening to myself.

AWonderPHP is my attempt to clean up and package a lot of the PHP classes I
personally use so that something of me will be left behind.

If you wish to help, please see the [SURVIVAL.md](SURVIVAL.md) file.

Thank you for your time.


-------------------------------------------------
__EOF__