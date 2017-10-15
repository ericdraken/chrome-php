/*
 * ChromePHP - har.js
 * Created by: Eric Draken
 * Date: 2017/10/12
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

const temp = chrome.temp;

const argv = require('minimist')(process.argv.slice(2));
const CHC = require('chrome-har-capturer');
const loggerFactory = require('./logger');
const logger = loggerFactory();

const url = argv.url || false;
const emulation = argv.emulation || false;
const timeout = argv.timeout || 10000; // 10s
const ignoreCertErrors = argv.ignorecerterrors || false;
const saveContent = argv.savecontent || false;

// Check the URL
if (!url) {
    logger.error("URL not present");
    process.exit(1);
}

logger.info('URL: %s', url);

// Defaults
let width = 800,
    height = 600,
    userAgent = false;

// Set the emulation
if(emulation) {
    // Decode emulation JSON. If parsing fails, an exception will be thrown
    let emulationObj = JSON.parse(emulation);
    if (emulationObj.hasOwnProperty('userAgent') && emulationObj.hasOwnProperty('viewport'))
    {
        logger.debug('Setting viewport');
        width = emulationObj['viewport']['width'];
        height = emulationObj['viewport']['height'];
        if(emulationObj['userAgent'].length) {
            logger.debug('Setting userAgent to %s', emulationObj['userAgent']);
            userAgent = emulationObj['userAgent'];
        }
    } else {
        logger.warn('Emulation missing required properties');
    }
}

// Set the user agent if supplied
async function preHook(url, client) {
    const {Network} = client;
    if (typeof userAgent === 'string') {
        logger.debug('Setting user agent to %s', userAgent);
        await Network.setUserAgentOverride({
            userAgent: userAgent
        });
    }

    // Bypass TLS errors
    if (ignoreCertErrors) {
        const {Security} = client;
        // Ignore all certificate errors
        Security.certificateError(({eventId}) => {
            Security.handleCertificateError({
                eventId,
                action: 'continue'
            });
        });

        // Enable the override
        await Security.enable();
        await Security.setOverrideCertificateErrors({override: true});
    }
}

logger.debug('Starting HAR');

let exitCode = 1;
let lastError = null;

// Capture a HAR
CHC.run([url], {
    host: chrome.host,
    port: chrome.port,
    width: width,
    height: height,
    content: saveContent,
    timeout: timeout,
    parallel: false,
    preHook: preHook,
    postHook: null
}).on('load', (url) => {
    logger.debug('Loading: %s', url);
}).on('done', (url) => {
    logger.debug('Loaded: %s', url);
}).on('fail', (url, err) => {
    // Add this error to the HAR object
    lastError = err.message;
    logger.error('Failed: %s, with error: %s', url, err);
}).on('har', async (har) => {

    // No extra comments in HAR please
    delete har.log.creator.comment;

    // Temporarily hold this error as well (it will be removed from the final HAR)
    har.lastError = lastError;
    if (lastError) {
        har.log.comment = lastError;
    }

    const fs = require('fs');
    const json = JSON.stringify(har, null, 4);

    // Write the serialized object to disk and return the path
    let path = temp+'/'+process.pid+'.json';
    fs.writeFileSync( path, json, { encoding: 'utf8' } );

    logger.info('Saved JSON to %s', path);

    // Send back the JSON file path
    console.log(path);
    exitCode = 0;

    logger.debug('Finished HAR');
});

process.on('SIGINT', () => {
    logger.error('SIGINT received');
    process.exit(exitCode);
});

process.on('unhandledRejection', (reason, p) => {
    logger.error('Unhandled Rejection at:', p, 'reason:', reason);
});