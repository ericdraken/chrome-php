/*
 * ChromePHP - screenshot.vm.js
 * Created by: Eric Draken
 * Date: 2017/10/3
 * Copyright (c) 2017
 */

// Construct the saved screenshot path
const buildFilepath = (temp, url, w, h, s, type) => {
    return `${temp}/${url}-${w}x${h}x${s}.${type}`;
};

/**
 * Available globals:
 *   page - Browser tab already opened
 *   console - For logging
 *   logger - Alias for console
 *   require - To include more modules
 *   argv - Access script params
 *   temp - Temp folder to write files
 *   vmcodeResults - Place results here. Initialize this!
 */
(async () => {

    const fs = require('fs');
    const sizeOf = require('image-size');

    // Get the emulations JSON string
    const type = 'png';
    const emulations = argv.emulations;
    const emuArr = JSON.parse(emulations);    // Throw exception on failure

    /**
     * This is the maximum high the canvas can be, so if the
     * upscaled height exceeds this, then we need to take
     * multiple screenshots and stitch them together
     * @type {number}
     */
    const chromeHeightLimit = 16384;

    // Use the URL as part of the screenshot file name
    let url = slugify(page.url());

    // Set the object type
    vmcodeResults = [];

    for (let emulationObj of emuArr)
    {
        if (!emulationObj.hasOwnProperty('userAgent') || !emulationObj.hasOwnProperty('viewport')) {
            let msg = 'Emulation missing required properties';
            logger.error(msg);
            vmcodeResults.push(new Error(msg));
            continue;
        }

        let vp = emulationObj['viewport'],
            w = vp['width'],
            h = vp['height'],
            s = vp['deviceScaleFactor'],
            filepath = buildFilepath(temp, url, w, h, s, type),
            size = -1;

        logger.debug('Setting viewport');
        await page.setViewport(vp);
        if(emulationObj['userAgent'].length) {
            logger.debug('Setting userAgent to %s', emulationObj['userAgent']);
            await page.setUserAgent(emulationObj['userAgent']);
        }

        // Full page mode is decided by each emulation
        let fullPageMode = emulationObj.hasOwnProperty('fullPage') && emulationObj['fullPage'];
        if (fullPageMode)
        {
            // We won't stitch horizontally
            const pageWidth = await page.$eval('body', el => el.clientWidth);
            if (pageWidth >= chromeHeightLimit) {
                let msg = 'Page width greater than Chrome canvas size limit';
                logger.error(msg);
                vmcodeResults.push(new Error(msg));
                continue;
            }

            // Get actual page height
            const pageHeight = await page.$eval('body', el => el.clientHeight);
            logger.debug('Setting fullpage mode. Detected page height: %s', pageHeight);

            if (pageHeight < chromeHeightLimit)
            {
                // Normal fullpage screenshot
                await page.screenshot({
                    path: filepath,
                    type: type, // jpeg or png
                    // quality: 100 // N/A for PNG
                    fullPage: fullPageMode,
                    // clip: {}
                    omitBackground: false
                });
            }
            else
            {
                // Need to stitch slices together
                console.debug("Taking multiple screenshots");

                // Use a softer height limit due to memory concerns
                const chromeSoftHeightLimit = chromeHeightLimit/4;

                // Do not load the ImageMagick wrapper for Node.js
                const gm = require('gm').subClass({imageMagick: false});

                // Save the temporary slices to disk
                let slicesArr = [];
                for (let y = 0; y < pageHeight; y += chromeSoftHeightLimit)
                {
                    let diff = pageHeight - y;
                    let sliceHeight = diff > chromeSoftHeightLimit ? chromeSoftHeightLimit : diff;

                    // Take a slice screenshot
                    let sliceOutFile = `${filepath}.${y}.${type}`;
                    await page.screenshot({
                        path: sliceOutFile,
                        type: type, // jpeg or png
                        // quality: 100 // N/A for PNG
                        fullPage: false,
                        clip: {
                            x: 0,
                            y: y,
                            width: pageWidth,
                            height: sliceHeight
                        },
                        omitBackground: false
                    });
                    slicesArr.push(sliceOutFile);
                    logger.debug('Screenshot slice saved to: %s', sliceOutFile);
                }

                // Combine the slices
                let res = false;
                slicesArr.forEach(function(slice) {
                    if (!res) {
                        res = gm(slice);
                    } else {
                        res.append(slice, false);
                    }
                    logger.debug('Appending slice %s', slice);
                });

                // GM is async, so Bluebird allows us to wait for its file save operation
                let Promise = require('bluebird');
                Promise.promisifyAll(gm.prototype);

                // Save the combined slices
                await res.writeAsync(filepath)
                    .then(() => {
                    "use strict";
                        logger.debug('Screenshot slices saved to: %s', filepath);
                    })
                    .catch((err) => {
                    "use strict";
                        logger.error('Exception while saving screenshot:', err);
                    });
            }

            // Update the screenshot file name
            size = sizeOf(filepath);
            w = size.width;
            h = size.height;

            // Rename the file to the page actual dimensions
            let newFilepath = buildFilepath(temp, url, w, h, s, type);
            fs.renameSync(filepath, newFilepath);
            filepath = newFilepath;
        }
        else
        {
            // Normal screenshot
            await page.screenshot({
                path: filepath,
                type: type, // jpeg or png
                // quality: 100 // N/A for PNG
                fullPage: fullPageMode,
                // clip: {}
                omitBackground: false
            });

            // Update the screenshot size
            size = sizeOf(filepath);
            w = size.width;
            h = size.height;
        }

        logger.debug('Screenshot saved to:', filepath);

        // Save the results
        vmcodeResults.push( {
            url : page.url(),
            emulation : emulationObj,
            filepath: filepath,
            width: w,
            height: h,
            scale: s,
            fullPage: fullPageMode,
            filesize: size
        } );
    }

})();

// Original: https://gist.github.com/mathewbyrne/1280286
function slugify(text)
{
    return text.toString().toLowerCase()
        .replace(/\s+/g, '-')           // Replace spaces with -
        .replace(/[:/&?.=]/g, '-')      // Replace special URL chars with -
        .replace(/[^\w\-]+/g, '')       // Remove all non-word chars
        .replace(/--+/g, '-')           // Replace multiple - with single -
        .replace(/^-+/, '')             // Trim - from start of text
        .replace(/-+$/, '');            // Trim - from end of text
}
