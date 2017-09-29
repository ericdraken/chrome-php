/*
 * ChromePHP - dom.js
 * Created by: Eric Draken
 * Date: 2017/9/25
 * Copyright (c) 2017
 */

'use strict';

// Bootstrap
(function() {
    let argv = require('minimist')(process.argv.slice(2));
    global.chrome = {
        wsep : argv['chrome-wsep'] || false,
        port : argv['chrome-port'] || 9222,
        host : argv['chrome-host'] || 'localhost',
        temp : argv['chrome-temp'] || '/tmp'
    };
})();

const argv = require('minimist')(process.argv.slice(2));
const puppeteer = require('puppeteer');
const logger = require('./logger');
const fs = require('fs');
const purl = require('url');

const wsep = chrome.wsep;
const temp = chrome.temp;
const url = argv.url || false;
const networkIdleTimeout = argv.idletime || 500; // 0.5s
const timeout = argv.timeout || 10000; // 10s

// Sanity
if (!url) {
    logger.error("URL not present");
    process.exit(1);
}

logger.info('URL: %s', url);

let results = {
    "ok" : false,
    "status" : 0,
    "requestUrl" : "",
    "lastResponse" : false,
    "redirectChain" : [],
    "rawHtml" : "",
    "renderedHtml" : "",
    "consoleLogs" : [],
    "requests" : [],
    "failed" : [],
    "errors" : [],
    "loadTime": -1
};

let browser;
let page;
let exitCode = 0;
let requests = new Map();

(async() => {

    // Convert a response to an object
    let responseToObj = (request, response) => {
        return {
            url: request.url,
            status: response ? response.status : 0,
            type: request.resourceType,
            method: request.method,
            requestHeaders: request.headers,
            responseHeaders: response ? response.headers : {}
        };
    };

    // Connect to a running Chrome instance
    logger.debug( 'Connecting to %s', wsep );
    browser = await puppeteer.connect({browserWSEndpoint: wsep});
    logger.debug( 'Connected' );

    // Open a new tab
    page = await browser.newPage();

    // Logging
    logger.debug('Intercepting console logs');
    const levels = ['debug', 'info', 'warn', 'error', 'log'];
    page.evaluateOnNewDocument(function(levels) {
        (function(){
            let c = console;
            levels.forEach((level) => {
                c[level+'old'] = c[level];
                console[level] = function () {
                    let args = Array.prototype.slice.call(arguments); // toArray
                    args.unshift(level);
                    c[level+'old'].apply(this, args);
                };
            });
        })();
    }, levels);

    // Save console messages
    page.on('console', (...args) => {
        if(args.length > 1) {
            let level = args[0];
            if (levels.includes(level)) {
                for (let i = 1; i < args.length; ++i) {
                    results.consoleLogs.push(level.toUpperCase() + ': ' + args[i]);
                }
            }
        } else {
            // Backup for other console functions
            for (let i = 1; i < args.length; ++i) {
                results.consoleLogs.push(`${i}: ${args[i]}`);
            }
        }
    });

    // Save all the request objects to later
    // map them to their responses in order
    // to follow redirect chains
    page.on('request', (request) => {
        "use strict";
        if (!results.requestUrl.length) {
            logger.debug( 'Got first request %s', request.url );
            results.requestUrl = request.url;
        }

        // Skip data-uri images
        if (request.url.indexOf('data:image') === 0)
            return;

        // Keep track of request objects
        requests.set(request.url, request);
    });

    // Save redirected request objects
    page.on('response', (response) => {
        "use strict";
        // Only interested in the first redirect chain
        if (results.lastResponse)
            return;

        let obj = responseToObj(response.request(), response);
        let status = parseInt(response.status, 10);

        // Record the redirects of the initial request
        if (status >= 300 && status <= 399) {
            logger.debug('Got response %s from %s', status, response.request().url);
            results.redirectChain.push(obj);
        }
        // Response for the first request
        else
        {
            logger.debug('First request: %s from %s', status, response.request().url);
            results.ok = response.ok;
            results.status = status;
            results.lastResponse = obj;
        }
    });

    // Save raw HTML response of the initial request
    page.on('response', async (response) => {
        "use strict";
        // Gate
        if (response.url !== results.requestUrl) {
            return
        }

        let contentType =
            response.headers.hasOwnProperty('content-type') ?
                response.headers['content-type'] :
                false;

        // Only allow text content types
        if (!contentType || contentType.indexOf('text/') !== 0) {
            return;
        }

        logger.debug("Got response for: %s", response.url);
        const buffer = await response.buffer();
        results.rawHtml = buffer.toString('utf8');
    });

    page.on('load', () => {
        "use strict";

        // Pause all media and stop buffering
        logger.debug('Pausing media');
        page.frames().forEach((frame) => {
            frame.evaluate(() => {
                document.querySelectorAll('video, audio').forEach(m => {
                    if (!m) return;
                    if (m.pause) m.pause();
                    m.preload = 'none';
                });
            });
        });
    });

    // Keep track of errors and exceptions
    // errorObj is 'new Error(message)', but the docs
    // say the return type is "<string> The exception message"
    let errorLogging = (errorObj) => {
        "use strict";
        logger.warn('%s: %s', errorObj.name, errorObj.message);
        results.errors.push(errorObj.toString());
    };
    page.on('error', errorLogging);
    page.on('pageerror', errorLogging);

    logger.info('Navigating to %s', url);

    // Navigate to the URL with a timeout
    let t0 = process.hrtime();
    await page.goto(url, {
        waitUntil: 'networkidle',
        networkIdleTimeout: networkIdleTimeout,  // min idle duration to be considered 'idle'
        timeout: timeout   // Navigation must complete in this time
    }).then(() => {

        // Note the page load time only for successful connections
        // Also, subtract the idle timeout time
        let diff = process.hrtime(t0);
        results.loadTime = (((diff[0] * 1e9) + diff[1]) - (networkIdleTimeout * 1e6)) / 1e6;  // ns --> ms
        logger.debug('Page loaded in %s s', results.loadTime / 1000.0);

    }).catch((err) => {
        // REF: https://github.com/GoogleChrome/puppeteer/blob/master/docs/api.md#pagegotourl-options
        // The page.goto will throw an error if:
        //
        // * there's an SSL error (e.g. in case of self-signed certificates).
        // * target URL is invalid.
        // * the timeout is exceeded during navigation.
        // * the main resource failed to load.

        results.errors.push(err.message);
        logger.error('Navigation error:', err.message);
    });

    logger.debug('Getting rendered page content');

    // Only get the rendered html if
    // the raw html is present
    if (results.rawHtml.length > 0) {
        results.renderedHtml = await page.content();
    }

    logger.debug('There were %s requests', requests.size);

    // Follow redirect chains to match requests to
    // their final responses. In other words, compress the
    // redirect chain down into a single request-response pair
    while (requests.size)
    {
        // "Unshift" the first entry in the map
        let next = requests.entries().next().value;
        let requestStart = next[1];
        logger.debug('Next request is %s', requestStart.url);

        // Error condition
        if (!requests.delete(next[0])) {
            logger.error('Next request not found: %s', next[0]);
            break;
        }

        let response = requestStart.response();

        // Follow redirects
        while (response && response.status >= 300 && response.status <= 399)
        {
            // This is an error condition
            if (!response.headers.hasOwnProperty('location'))
                break;

            // Search for the next request in the chain
            let location = response.headers['location'];

            logger.debug('searching for %s', location);

            // A location header may be relative
            if (!requests.has(location))
            {
                // Search again for a full URL
                if (location.indexOf('/')===0)
                {
                    let parts = purl.parse(response.url);
                    location = parts.href.replace(parts.path, '') + location;

                    // An error condition
                    if (!requests.has(location))
                        break;
                }
            }

            logger.debug('Found %s', location);

            let nextRequest = requests.get(location);
            requests.delete(location);

            logger.debug(nextRequest.url);

            response = nextRequest.response();
        }

        // Response will be the last in the chain
        let obj = responseToObj(requestStart, response);

        // Save requests and failures
        results.requests.push(obj);
        if (!response || !response.ok) {
            results.failed.push(obj);
        }
    }

})()
    .catch((err) => {

        // Clean logging
        logger.error('UncaughtException:', err.message);
        exitCode = 1;

    }).then(async () => {

    logger.debug('Closing tab');

    // Close the page, not the browser
    if (page) {
        await page.removeAllListeners();
        await page.close();
    }

    // Write the serialized object to disk and return the path
    let path = temp+'/'+process.pid+'.json';
    fs.writeFileSync( path, JSON.stringify(results), { encoding: 'utf8' } );

    logger.info('Saved JSON to %s', path);

    // Send back the JSON file path
    console.log(path);

    process.exit(exitCode);
});

process.on('SIGINT', () => {
    logger.error('SIGINT received');
    if (page) page.close();
    process.exit();
});

process.on('unhandledRejection', (reason, p) => {
    logger.error('Unhandled Rejection at:', p, 'reason:', reason);
});