/*
 * ChromePHP - version.js
 * Created by: Eric Draken
 * Date: 2017/9/7
 * Copyright (c) 2017
 */

const CDP = require('chrome-remote-interface');
const argv = require('minimist')(process.argv.slice(2));

const port = argv.port || 9222;
const host = argv.host || 'localhost';

try {
    CDP.Version({port: port, host: host}, function(err, versionInfo) {
        if (err)
        {
            console.error(err);
            process.exit(1);
        }
        console.log(versionInfo['Browser']);
    });
} catch(err) {
    console.error(err);
    process.exit(1);
}


