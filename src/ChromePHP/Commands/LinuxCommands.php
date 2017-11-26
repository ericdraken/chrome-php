<?php
/**
 * ChromePHP - LinuxCommands.php
 * Created by: Eric Draken
 * Date: 2017/9/10
 * Copyright (c) 2017
 */

namespace Draken\ChromePHP\Commands;


class LinuxCommands
{
	/**
	 * Recursively remove a folder
	 */
	const rmrfDirCmd = 'rm -rf %s';

	/**
	 * Make a temporary folder return the path
	 */
	const makeTmpDirCmd = 'mktemp -d -t chrome-php.XXXXXXX';

	/**
	 * Run NodeJS
	 */
	const nodeCmd = '$(which node)';

	/**
	 * Get the installed NodeJS version
	 */
	const getNodeVersionCmd = '$(which node) && $(which node) -v';

	/**
	 * Command to grep on the remote debug port of a running chrome instance.
	 * Get the newest instance as this will be the true one, not the parent
	 * process that may have launched it
	 */
	const checkIfChromePortBoundCmd = 'pgrep -fln "remote-debugging-port=[\'\"]*%u[\'\"]*"';

	/**
	 * Command to check if anything is bound to a port already
	 */
	const checkIfPortBoundCmd = 'netstat -lnt | grep ":%u "';

	/**
	 * Show the running Chrome process tree
	 */
	const showRunningChromeTreeCmd = 'ps auxc wwf | grep chrom';

	/**
	 * Command to check how many Chrome instances are running.
	 * If chrome is not running, then only '0' will be returned.
	 * Note, using 'chrom' over 'chrome' to cover 'chromium' as well
	 * @see https://www.computerhope.com/unix/upgrep.htm
	 */
	// TODO: Better way to do this??
	const countRunningChromeProcessesCmd = 'ps -ef --no-headers | grep -v "sh -c" | grep "[r]emote-debugging-port" | wc -l';

	/**
	 * Command to kill all running headless Chrome processes.
	 * A signal can be specified. For example, 2(INT) is simply "quit" and 15(TERM) is "terminate"
	 */
	const killChromeProcessesCmd = 'pkill -%s -f "remote-debugging-port=[\'\"]*[0-9]{2,}[\'\"]*"';

	/**
	 * Kill a process
	 */
	const killSingleProcessCmd = 'kill -%u %u';

	/**
	 * Command to setup a SSH tunnel to the Chrome debugging port
	 */
	const sshTunnelCmd = 'ssh -L 0.0.0.0:%u:localhost:%u localhost -N -p %u';

	/**
	 * Command to count the number of running child processes not including
	 * Chrome and defunct/killed processes
	 */
	const countRunningChildProcessesCmd = 'pgrep -P %u | xargs --no-run-if-empty ps -h -o command -p | grep -v "pgrep" | grep -v "%s" | grep -v "defunct" | wc -l';

	/**
	 * Command to find a Chrome binary
	 * Use NOWDOC convention to avoid escaping the string
	 */
	const chromeBinaryCmd = <<<'EOT'
 CHROME_BIN_PATH1=%s;
 CHROME_BIN_PATH2=$(which chrome);
 CHROME_BIN_PATH3=$(which google-chrome);
 if [ ! -z $CHROME_BIN_PATH1 ] && [ -x $CHROME_BIN_PATH1 ];
   then CHROME_BIN_PATH=$CHROME_BIN_PATH1;
 elif [ ! -z $CHROME_BIN_PATH2 ] && [ -x $CHROME_BIN_PATH2 ];
   then CHROME_BIN_PATH=$CHROME_BIN_PATH2;
 elif [ ! -z $CHROME_BIN_PATH3 ] && [ -x $CHROME_BIN_PATH3 ];
   then CHROME_BIN_PATH=$CHROME_BIN_PATH3;
 else
   CHROME_BIN_PATH=':';
 fi;
 echo $CHROME_BIN_PATH;
EOT;

	/**
	 * Command to run Chrome in headless mode with hidden scrollbars as a background process.
	 * Use nohup to really detach the process from this script. Without it,
	 * this script will hang even though all execution has finished
	 * @see https://peter.sh/experiments/chromium-command-line-switches/
	 */
	const headlessChromeCmd = [
		// Chrome binary
		'%s',

		// Incognitio mode each time
		'--incognito',

		// SSL
		'--allow-insecure-localhost',               // localhost over TLS with self-signed cert
		// '--allow-running-insecure-content',      // See the next flag
		'--enable-strict-mixed-content-checking',   // Block all HTTP requests from HTTPS contexts
		// '--ignore-certificate-errors',           // Removed from Chromium

		// Standard settings
		'--headless',
		'--no-first-run',
		'--hide-scrollbars',
		'--mute-audio',
		'--metrics-recording-only',
		'--enable-automation',
		'--enable-devtools-experiments',
		'--metrics-recording-only',
		'--password-store=basic',
		'--use-mock-keychain',
		// '--deterministic-fetch', // Cause network fetches to complete in order, not in parallel (slow)
		// '--timeout=15000',  // Issues a stop after the specified number of milliseconds. This cancels all navigation and causes the DOMContentLoaded event to fire.
		// '--single-process', // Runs the renderer and plugins in the same process as the browser
		// '--wait-for-debugger',

		// Logging to Process (or command line)
		'--enable-logging',
		'--v=1',
		// '--log-net-log=stdout',
		// '--net-log-capture-mode=IncludeCookiesAndCredentials',  // values: "Default" "IncludeCookiesAndCredentials" "IncludeSocketBytes"

		// Disabled features
		'--disable-gpu',
		'--safebrowsing-disable-auto-update',
		'--disable-default-apps',
		'--disable-background-networking',
		'--disable-extensions',     // TODO: Will this disable PDF rendering?
		'--disable-translate',
		'--disable-sync',
		'--no-proxy-server',
		'--no-default-browser-check',
		'--no-pings',
		'--use-fake-device-for-media-stream',   // Use fake device for Media Stream to replace actual camera and microphone
		'--disable-background-timer-throttling',
		'--disable-client-side-phishing-detection',
		'--disable-hang-monitor',
		'--disable-popup-blocking',
		'--disable-prompt-on-repost',

		// Disable sandbox on linux, and for non-super user
		'--disable-setuid-sandbox',
		'--no-sandbox',             // REF:  https://chromium.googlesource.com/chromium/src/+/master/docs/linux_suid_sandbox_development.md

		// Custom settings
		'--remote-debugging-port=%s',
		'--profile-directory=%s',   // Profiles dir
		'--user-data-dir=%s',       // Where to store user data (e.g. --user-data-dir=/tmp/lighthouse.FqPZk9m)
		'--homedir=%s',             // Home dir
		'--user-agent=%s'           // Set the default user agent string
	];
}