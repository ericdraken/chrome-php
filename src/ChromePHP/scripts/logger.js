/*
 * ChromePHP - logger.js
 * Created by: Eric Draken
 * Date: 2017/9/26
 * Copyright (c) 2017
 *
 * Log all events to stderr with a customized
 * timestamp and process PID
 *
 * NOTE: Set TZ and an environment variable e.g. TZ = 'America/Vancouver'
 */

const process = require('process');
const winston = require('winston');
const winstonStderr = require('winston-stderr');

const level = process.env.LOG_LEVEL || 'warning';

const logger = new winston.Logger({
    transports: [
        new winstonStderr({
            level: level,
            timestamp: function () {
                // e.g. 21:58:26.056
                // REF: https://stackoverflow.com/a/28149561/1938889
                let tzoffset = (new Date()).getTimezoneOffset() * 60000; // Offset in milliseconds
                let localISOTime = (new Date(Date.now() - tzoffset)).toISOString().slice(0,-1);
                return localISOTime.substr(11, 12);
            },
            label: process.pid
        })
    ]
});

logger.exitOnError = false;

module.exports = logger;
