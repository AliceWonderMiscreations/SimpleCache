CHANGELOG
=========

1.0.1 (Wed Mar 21 2018) Star Star
---------------------------------

* Added new exception for APCu
* Added exceptions for Redis
* When using -1 as TTL for set() and the default TTL is 6000 seconds or larger
it will take a random number of seconds between 0 and 15% of the default TTL
and randomly add or substract it to the default TTL to use as actual TTL. This
is to allow staggering of expiration with cache warming.

1.0.0 (Sun Mar 18 2018) Androgynous
-----------------------------------

* Initial release, forked from my SimpleCacheAPCu and SimpleCacheAPCuSodium
classes to provide a cache engine independent abstract class.
