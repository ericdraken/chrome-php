ChromePHP
=========

Parallel headless Chrome operations via PHP

This library allows you control multiple headless Chrome browsers at the same time to process an operation queue faster.

PHP launches and manages headless Chrome browsers, including respawing killed browsers, which then runs NodeJS processes to interact with those Chrome instances, and returns promises for each process.

The most common use cases are:

* Crawl web sites for 404 errors, JavaScript errors and mismatched HTML tags
* Check page load times in bulk
* Capture detailed HAR snapshots
* Take screenshots in bulk
* Any automated UI testing

Prepared Solutions
------------------

* HAR capture with optional sources - `HarProcess`
* Emulated device screenshots (even full page over 16,384px) - `ScreenshotProcess`
* Page information including all JS errors - `PageInfoProcess`
* Any custom Chrome interaction - `NodeProcess`

Examples
--------

See the `/examples` folder for examples and detailed comments.

Installation
------------

Running `composer update` will install the composer packages, install NodeJS and NPM if not already installed, and install the required NodeJS packages as well. The operations of NodeJS should be completely transparent.

.npmrc
------

This file contains the project NPM overrides. By default the equivalent of "--no-bin-links" is set because NTFS shared folders cannot use automatic symlinks. 