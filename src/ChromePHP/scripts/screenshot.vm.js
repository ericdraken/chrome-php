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
 *   vmcodeResults[] - Place results here
 */
(async () => {

    // Get the emulations JSON string
    let emulations = argv.emulations;
    let emuArr = JSON.parse(emulations);    // Throw exception on failure

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

            // Take screenshot
            let filepath = `${temp}/url-${emulationObj['viewport']['width']}x${emulationObj['viewport']['height']}.jpg`;
            await page.screenshot({path: filepath});
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
