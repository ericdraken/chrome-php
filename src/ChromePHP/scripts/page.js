/*
 * ChromePHP - page.js
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
const loggerFactory = require('./logger');
const fs = require('fs');
const purl = require('url');

const logger = loggerFactory();
const wsep = chrome.wsep;
const temp = chrome.temp;
const url = argv.url || false;
const emulation = argv.emulation || false;
const networkIdleTimeout = argv.idletime || 500; // 0.5s
const timeout = argv.timeout || 10000; // 10s
const vmcode = argv.vmcode || false;

// Check the URL
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
    "vmcodeResults" : [],
    "requests" : [],
    "failed" : [],
    "errors" : [],
    "loadTime": -1
};

let browser;
let page;
let exitCode = 0;
let requests = new Map();
let rawHTML = new Map();
let mainRequests = [];

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

    // Set the emulation
    if(emulation) {
        // Decode emulation JSON. If parsing fails, an exception will be thrown
        let emulationObj = JSON.parse(emulation);
        if (emulationObj.hasOwnProperty('userAgent') && emulationObj.hasOwnProperty('viewport'))
        {
            logger.debug('Setting viewport');
            await page.setViewport(emulationObj['viewport']);
            if(emulationObj['userAgent'].length) {
                logger.debug('Setting userAgent to %s', emulationObj['userAgent']);
                await page.setUserAgent(emulationObj['userAgent']);
            }
        } else {
            logger.warn('Emulation missing required properties');
        }
    }

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

        // Skip data-uri images
        if (request.url.indexOf('data:image') === 0)
            return;

        // Keep track of request objects
        requests.set(request.url, request);
    });

    // Save raw HTML response of all the requests
    // because we don't know which one is the last
    // in a chain of redirects and meta redirects
    page.on('response', async (response) => {
        "use strict";
        let contentType =
            response.headers.hasOwnProperty('content-type') ?
                response.headers['content-type'] :
                false;

        // Only allow text content types
        if (!contentType || contentType.indexOf('text/') !== 0) {
            return;
        }

        logger.debug("Got raw HTML for: %s", response.url);
        const buffer = await response.buffer();
        rawHTML.set(response.url, buffer.toString('utf8'));
    });

    // Monitor main frame navigation activity to
    // detect meta refresh and script-invoked redirects. This
    // is detected after the response is received, so just make
    // a note of this request for post processing
    page.on('framenavigated', (frame) => {
        "use strict";
        if (frame === page.mainFrame())
        {
            logger.debug('Main request navigated to %s', frame.url());
            mainRequests.push(frame.url());
        }
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

    // Store the initial request
    results.requestUrl = url;

    // Navigate to the URL with a timeout
    const t0 = process.hrtime();
    await page.goto(url, {
        waitUntil: 'networkidle',
        networkIdleTimeout: networkIdleTimeout,  // min idle duration to be considered 'idle'
        timeout: timeout   // Navigation must complete in this time
    }).then((response) => {

        // Note the page load time only for successful connections
        // Also, subtract the idle timeout time
        let diff = process.hrtime(t0);
        results.loadTime = (((diff[0] * 1e9) + diff[1]) - (networkIdleTimeout * 1e6)) / 1e6;  // ns --> ms
        logger.debug('Page loaded in %s s', results.loadTime / 1000.0);
        return response;

    }).then((response) => {

        // Record the last results in the redirect chain
        let obj = responseToObj(response.request(), response);
        results.ok = response.ok;
        results.status = response.status;
        results.lastResponse = obj;
        results.rawHtml = rawHTML.get(response.url) || '';
        logger.debug('Last response in main request chain: %s', response.url);
        return response;

    }).then(async (response) => {

        // If vm code is present, then execute it in a sandbox,
        // if not, then skip over this section
        if(vmcode===false) {
            logger.debug('No VM code supplied');
            return response;
        }

        try {
            let sandboxPage = new Proxy(page, {
                get: function(target, name, receiver) {
                    switch(name) {
                        case 'reload':
                        case 'close':
                        case 'exposeFunction':
                        case 'setRequestInterceptionEnabled':
                            throw Error(`${name} is disabled in user scripts`);
                    }
                    return Reflect.get(target, name, receiver);
                }
            });

            // Run any user scripts to perform any additional page actions,
            // but only allow access to proxied objects like `page` and `console`
            // to prevent user scripts from closing the page or browser, and from
            // interfering with the final operations of this process, like sending
            // the JSON data to PHP. The logger below will also alias 'log' to 'info'.
            const vmlogger = loggerFactory('vmcode');
            vmlogger.level = logger.level;
            const sandboxLogger = new Proxy(vmlogger, {
                get: function(target, name, receiver) {
                    switch(name) {
                        case 'log':
                            return Reflect.get(target, 'info', receiver);
                    }
                    return Reflect.get(target, name, receiver);
                }
            });

            let vm = require('vm'),
                sandbox = {
                    page: sandboxPage,
                    console: sandboxLogger,
                    logger: sandboxLogger,
                    require: require,
                    argv: argv,
                    vmcodeResults: results.vmcodeResults
                };

            logger.debug('Running VM code in sandbox', vmcode);
            await vm.runInNewContext(vmcode, sandbox, {
                filename: 'vmcode',
                displayErrors: true
            });

        } catch (err) {
            // Note the error in the logger, and the returned results object
            logger.error('VM script error: ', err.message);
            results.errors.push('VM script error: ' + err.message);
        }
        return response;

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
        let chain = [];

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

        // Start the chain
        chain.push(response);

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

            // Continue the chain
            chain.push(response);
        }

        // Response will be the last in the chain
        let obj = responseToObj(requestStart, response);

        // Save requests and failures
        results.requests.push(obj);
        if (!response || !response.ok) {
            results.failed.push(obj);
        }

        // Check if this is a main request chain
        if (mainRequests.length && response && response.url === mainRequests[0])
        {
            logger.debug('This is a main request: %s', requestStart.url);
            mainRequests.shift();   // Remove

            // Add this chain segment to the redirect chain
            chain.forEach((response) => {
                let obj = responseToObj(response.request(), response);
                results.redirectChain.push(obj);
            });
        }
    }

    // The last entry is represented by 'lastResponse'
    results.redirectChain.length && results.redirectChain.pop();

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