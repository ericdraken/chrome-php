/*
 * ChromePHP - testserver.js
 * Created by: Eric Draken
 * Date: 2017/9/28
 * Copyright (c) 2017
 *
 * REF: https://stackoverflow.com/a/29046869/1938889
 */

const http = require('http');
const url = require('url');
const fs = require('fs');
const path = require('path');
const port = process.argv[2] || 9000;

http.createServer(function (req, res) {
    console.log(`${req.method} ${req.url}`);

    // parse URL
    const parsedUrl = url.parse(req.url);

    // extract URL path
    let pathname = parsedUrl.pathname;

    /** testRedirectChain() **/

    // Create a redirect chain
    if (pathname === '/307-1/') { res.writeHead(307, {'Location': '/302-1/'}); res.end(); return; }
    if (pathname === '/302-1/') { res.writeHead(302, {'Location': '/301-1/'}); res.end(); return; }
    if (pathname === '/301-1/') { res.writeHead(301, {'Location': '/index.html'}); res.end(); return; }

    /** testMixedRedirectChain() **/

    if (pathname === '/302-2/') { res.writeHead(302, {'Location': '/301-2/'}); res.end(); return; }
    if (pathname === '/301-2/') { res.writeHead(301, {'Location': '/meta-redirect-2.html'}); res.end(); return; }
    if (pathname === '/302-3/') { res.writeHead(302, {'Location': '/301-3/'}); res.end(); return; }
    if (pathname === '/301-3/') { res.writeHead(301, {'Location': '/index.html'}); res.end(); return; }

    // Limit parent folder access
    pathname = pathname.replace(/^(\.)+/, '.');

    // Index file
    if (pathname === '/')
        pathname = '/index.html';

    // Absolute path
    pathname = __dirname + pathname;

    // based on the URL path, extract the file extension. e.g. .js, .doc, ...
    const ext = path.parse(pathname).ext;

    // maps file extention to MIME typere
    const map = {
        '.ico': 'image/x-icon',
        '.html': 'text/html',
        '.js': 'text/javascript',
        '.json': 'application/json',
        '.css': 'text/css',
        '.png': 'image/png',
        '.jpg': 'image/jpeg',
        '.wav': 'audio/wav',
        '.mp3': 'audio/mpeg',
        '.svg': 'image/svg+xml',
        '.pdf': 'application/pdf',
        '.doc': 'application/msword'
    };

    fs.exists(pathname, function (exist) {
        if(!exist) {
            // if the file is not found, return 404
            res.statusCode = 404;
            res.end(`File ${pathname} not found!`);
            return;
        }

        // if is a directory search for index file matching the extention
        if (fs.statSync(pathname).isDirectory()) pathname += '/index' + ext;

        // read file from file system
        fs.readFile(pathname, function(err, data){
            if(err){
                res.statusCode = 500;
                res.end(`Error getting the file: ${err}.`);
            } else {
                // if the file is found, set Content-type and send data
                res.setHeader('Content-type', map[ext] || 'text/plain' );
                res.end(data);
            }
        });
    });

}).listen(parseInt(port));

console.log(`Test server listening on port ${port}`);
