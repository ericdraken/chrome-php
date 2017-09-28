/*
 * ChromePHP - endpoint.js
 * Created by: Eric Draken
 * Date: 2017/9/17
 * Copyright (c) 2017
 *
 * Get the Remote WS endpoint URL from a running Chrome instance.
 * Try up to N times with a M-second delay each try. On success,
 * echo the WS endpoint URL and only this.
 */

'use strict';

// REF: https://github.com/schnerd/chrome-headless-screenshots/blob/master/index.js
const CDP = require('chrome-remote-interface');
const argv = require('minimist')(process.argv.slice(2));

const port = argv.port || 9222;
const host = argv.host || 'localhost';

let tries = 3;
const retryDelay = 1000;

(function getWsEndpoint() {
    CDP({port: port, host: host}, (client) => {

        try {
            // Echo the found WS endpoint
            console.log(client._ws.url);
        } catch (err) {
            throw new Error("Unable to get the WS endpoint URL: " + err.message);
        } finally {
            // Close the remote connection
            client && client.close();
        }

    }).on('error', (err) => {

        if (--tries) {
            setTimeout(getWsEndpoint, retryDelay);
            return;
        }

        // Cannot connect to the remote endpoint
        console.error(err);
        process.exit(1);
    });
})();
