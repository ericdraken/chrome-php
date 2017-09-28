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

const wsep = chrome.wsep;
const temp = chrome.temp;
const url = argv.url || false;

// Sanity
if (!url) {
    logger.error("URL not present");
    process.exit(1);
}

logger.info('URL: %s', url);

let results = {
    "ok" : false,
    "requestUrl" : "",
    "response" : false,
    "rawHtml" : "",
    "renderedHtml" : "",
    "consoleLogs" : [],
    "requests" : [],
    "failed" : []
};

let browser;
let page;
let exitCode = 0;

(async() => {

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
            //const levels = ['debug', 'info', 'warn', 'error', 'log'];
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

    // Save the URL of the first request
    page.on('request', (request) => {
        "use strict";
        if (!results.requestUrl.length) {
            logger.debug( 'Got first request %s', request.url );
            results.requestUrl = request.url;
        }

        // Skip data-uri images
        if (request.url.indexOf('data:image') === 0)
            return;

        // Add the request URL
        results.requests.push(request.url);
    });

    // Save the failed request objects
    page.on('response', (response) => {
        "use strict";
        if (response.ok && results.response)
            return;

        let obj = {
            url: response.url,
            status: response.status,
            type: response.request().resourceType,
            method: response.request().method,
            requestHeaders: response.request().headers,
            reasponseHeaders: response.headers
        };

        if (results.response) {
            // Add the failed request URL object
            results.failed.push(obj);
        } else {
            // Response for the first URL
            results.response = obj;
            results.ok = response.ok;
        }
    });

    // Save raw HTML response of the first request
    page.on('response', async (response) => {
        "use strict";
        // Gate
        if (response.url !== results.requestUrl) {
            return
        }

        // Only allow text content types
        if (response.contentType.indexOf('text/') !== 0) {
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

    // TODO: on error

    // TODO: on pageerror

    logger.info('Navigating to %s', url);

    // TODO: timeout?

    // Navigate to the URL with a timeout
    await page.goto(url, {
        waitUntil: 'networkidle',
        // networkIdleTimeout : 10000
    });

    logger.debug('Getting rendered page content');

    // Only get the rendered html if
    // the raw html is present
    if (results.rawHtml.length > 0) {
        results.renderedHtml = await page.content();
    }
})()
    .catch((err) => {

        logger.error(err);
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
