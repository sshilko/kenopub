<?php declare(strict_types=1);
#API documentation here @see https://kinoapi.com/index.html

#actually usefull settinfs
define('ACCESS_TOKEN', '');
define('CLIENT_CLIENT', '');
define('CLIENT_SECRET', '');





#setup configuration
define('USERAGENT', 'Mozilla/5.0 (Linux; Android 8.0.0; SM-G960F Build/R16NW) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/62.0.3202.84 Mobile Safari/537.36');
define('OUTDIR', getcwd() . DIRECTORY_SEPARATOR . 'out');
define('ACTION', isset($argv[1]) ? $argv[1] : 'help');
define('ACTIONSLIST', implode(',', array_map(function ($i) { return (substr($i, -6) === 'action') ? substr($i, 0, strlen($i) - 6) : null; },
                                   get_defined_functions(true)['user'])));

if (!in_array(ACTION, explode(',', ACTIONSLIST))) {
    throw new \RuntimeException(sprintf('Action "%s" was not defined', ACTION));
}
