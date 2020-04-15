<?php declare(strict_types=1);
#API documentation here @see https://kinoapi.com/index.html

#actually usefull settinfs
define('ACCESS_TOKEN',  'php main.php accesstoken');


#required api credentials
define('CLIENT_CLIENT', 'android');
define('CLIENT_SECRET', 'rcaqh7wodackn9ll1uggvqkx2iib6umh');

#setup configuration
define('CDN_SERVER',  'cdn.streambox.in');
define('SANE_SERVER', 'edge-de-01.streambox.in');
define('CLIENT_TITLE', 'sshilko/kenopub ' . date('Y-m-d'));
define('USERAGENT', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G960F Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.84 Mobile Safari/537.36');
define('QUALITY', '1080p,720p,480p');
define('OUTDIR', getcwd() . DIRECTORY_SEPARATOR . 'out');
define('SMBDIR', getcwd() . DIRECTORY_SEPARATOR . 'smb');
define('ACTION', isset($argv[1]) ? $argv[1] : 'help');
define('ACTIONSLIST', implode(',', array_filter(array_map(function ($i) { return (substr($i, -6) === 'action') ? substr($i, 0, strlen($i) - 6) : null; },
                      get_defined_functions(true)['user']))));

#download manager settings
define('DM_DIR', 'G:\\');
define('DM_CONCURRENCY', '2');

if (!in_array(ACTION, explode(',', ACTIONSLIST))) {
    throw new \RuntimeException(sprintf('Action "%s" was not defined', ACTION));
}
