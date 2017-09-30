ChromePHP
=========

Parallel headless Chrome operations via PHP

This library allows you control multiple headless Chrome browsers at the same time to process an operation queue faster.

PHP launches and manages headless Chrome browsers, including respawing killed browsers, which then runs NodeJS processes to interact with those Chrome instances, and returns promises for each process.

The most common use cases are:

* Crawl web sites for 404 errors, JavaScript errors and mismatched HTML tags
* Check page load times in bulk
* Take screenshots in bulk
* Unit test web interactions
* Keep tabs on keyword rankings
* Monitor web infrastructure and partner sites
* Follow mixed redirect chains (301, 302, meta-refresh, document.location, etc)

Installation
------------

Running `composer update` will install the composer packages, install NodeJS and NPM if not already installed, and install the required NodeJS packages as well. The operations of NodeJS should be completely transparent.

.npmrc
------

This file contains the project NPM overrides. By default the equivalent of "--no-bin-links" is set because NTFS shared folders cannot use automatic symlinks. 