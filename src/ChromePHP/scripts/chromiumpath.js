/*
 * ChromePHP - chromiumpath.js
 * Created by: Eric Draken
 * Date: 2017/9/17
 * Copyright (c) 2017
 *
 * Echo the local Puppeteer Chromium binary path if installed
 */

const argv = require('minimist')(process.argv.slice(2));
const fs = require('fs');

// Path to the node_modules folder e.g. // ['./../../../node_modules]/puppeteer';
const basePath = (argv.modulesPath || '.').replace(/\/+$/, '') + '/puppeteer';

if(!fs.existsSync(basePath)) {
    console.error('node_modules path does not exist. Supplied: '+basePath);
    process.exit(1);
}

const Downloader = require(basePath + '/utils/ChromiumDownloader');
const revision = require(basePath + '/package').puppeteer.chromium_revision;

const platform = Downloader.currentPlatform();
const revisionInfo = Downloader.revisionInfo(platform, revision);

// Check if the local Chrome is downloaded
if (revisionInfo.downloaded) {
    console.log(revisionInfo.executablePath);
} else {
    console.error('Puppeteer local Chrome binary not found');
    process.exit(1);
}