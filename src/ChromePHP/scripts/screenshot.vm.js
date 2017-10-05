/*
 * ChromePHP - screenshot.vm.js
 * Created by: Eric Draken
 * Date: 2017/10/3
 * Copyright (c) 2017
 */

/**
 * Available globals:
 *   page - Browser tab already opened
 *   console - For logging
 *   logger - Alias for console
 *   require - To include more modules
 *   argv - Access script params
 *   temp - Temp folder to write files
 *   vmcodeResults - Place results here
 */
(async () => {

    const fs = require('fs');
    const sizeOf = require('image-size');

    // Get the emulations JSON string
    const type = 'png';
    const emulations = argv.emulations;
    const emuArr = JSON.parse(emulations);    // Throw exception on failure

    // TODO: finish this
    let url = 'url';

    // Set the object type
    vmcodeResults = [];

    for (let emulationObj of emuArr)
    {
        if (emulationObj.hasOwnProperty('userAgent') && emulationObj.hasOwnProperty('viewport'))
        {
            logger.debug('Setting viewport');
            await page.setViewport(emulationObj['viewport']);
            if(emulationObj['userAgent'].length) {
                logger.debug('Setting userAgent to %s', emulationObj['userAgent']);
                await page.setUserAgent(emulationObj['userAgent']);
            }

            // Full page mode is decided by each emulation
            let fullPageMode = emulationObj.hasOwnProperty('fullPage') && emulationObj['fullPage'];

            if (fullPageMode) {
                logger.debug('Setting fullpage mode');
            }

            // Take screenshot
            let filepath = `${temp}/${url}-${emulationObj['viewport']['width']}x${emulationObj['viewport']['height']}.${type}`;
            await page.screenshot({
                path: filepath,

                // Options
                type: type, // jpeg or png
                // quality: 100 // N/A for PNG
                fullPage: fullPageMode,
                // clip: {}
                // omitBackground: false
            });

            if (fullPageMode)
            {
                // Rename the file to the actual dimensions on full page mode
                let size = sizeOf(filepath);
                let newFilepath = `${temp}/${url}-${size.width}x${size.height}.${type}`;
                fs.renameSync(filepath, newFilepath);
                filepath = newFilepath;

                // Update the expected viewport as well
                emulationObj['viewport']['width'] = size.width;
                emulationObj['viewport']['height'] = size.height;
            }

            logger.debug('Screenshot saved to:', filepath);

            // Save the results
            vmcodeResults.push( {
                url : page.url(),
                emulation : emulationObj,
                filepath: filepath
            } );

        } else {
            logger.warn('Emulation missing required properties');
        }
    }

})();
