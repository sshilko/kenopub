<?php declare(strict_types=1);

setlocale(LC_ALL, 'en_US.UTF-8');
mb_internal_encoding("UTF-8");
ini_set('default_charset', 'UTF-8');

include_once 'client.php';

include_once 'config.php';

call_user_func(ACTION . 'Action', $argv) && exit(0);

/**
 * GET api endpoint
 *
 * @param $args
 */
function urlAction(array $args) {
    $c = new client(ACCESS_TOKEN);
    echo $c->url((string) ($args[2] ?? '/'), false) . "\n";
}

function seriesAction() {
    syncData(['serial']);
}

function moviesAction() {
    syncData(['movie', 'documovie']);
}

/**
 * Parse bookmarks and save them into local files/folders
 */
function syncData(array $needtype = ['movie', 'documovie']) {
    @mkdir(OUTDIR . DIRECTORY_SEPARATOR . ACTION, 0777, true);

    $c = new client(ACCESS_TOKEN);
    $bookmarks = $c->url('/v1/bookmarks');
    if (!isset($bookmarks->items)) {
        return;
    }

    #$nn = 0;
    foreach ($bookmarks->items as $b) {
        $end   = false;
        $items = [];
        for ($i = 1; $i < PHP_INT_MAX; $i++) {
            usleep(500000);
        #for ($i = 1; $i < 2; $i++) {
            $itemsRaw = $c->url('v1/bookmarks/' . $b->id . '?page=' . $i);
            if ($itemsRaw) {
                $items = array_merge($items, $itemsRaw->items);
                if ($itemsRaw->pagination->current == $itemsRaw->pagination->total) {
                    break;
                }
            } else {
                echo "Failed ... continuing \n";
                continue;
            }
        }
        $data  = [];
        $sdata = [];
        $xid   = 0;
        foreach ($items as $i) {
            $xml = null;
            if (in_array($i->type, $needtype) && ($i->type === 'movie' || $i->type === 'documovie')) {
                $movie = $c->url('v1/items/' . $i->id);
                if (!$movie) {
                    echo "Failed... continuing \n";
                    continue;
                }
                $itemData = $movie->item;

                $src      = null;
                $quality  = null;
                $poster   = (isset($itemData->posters->big)) ? $itemData->posters->big : $itemData->posters->small;

                foreach ($itemData->videos as $v) {
                    foreach ($v->files as $f) {
                        if ($f->url->http && (in_array($f->quality, explode(',', QUALITY)))) {
                            $src     = normalizeKinoUrl($f->url->http);
                            $quality = $f->quality;
                            break 2;
                        }
                    }
                }

                if (is_string($src)) {
                    #$id, string $src, string $filename, string $description
                    $filename = str_replace(['/', ':'. '\''],
                                            ['',  '',  ''],
                                            $itemData->title . '.' . $itemData->year . '.' . $quality . '.' . $i->id);

                    $filename = preg_replace("/[^[:alnum:][:space:].!]/u", '', $filename);
                    $filename = trim(preg_replace("/\s{2,}/", ' ',             $filename));
                    $filename = mb_convert_encoding(trim($filename), 'UTF-8');

                    echo 'Processing ' . $filename . "\n";

                    $xid++;
                   #$data[]   = client::itemToXml($xid, $src, $filename . '.mp4', $itemData->plot, '\\' . ACTION . '\\');
                    $data[]   = client::itemToXml($xid, $src, $filename . '.mp4', $itemData->plot);
                    $nfo      = client::itemToNFO($itemData, $filename . '.jpg');

                    $thumb = OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $filename . '.jpg';
                    if (!is_readable($thumb) && $poster) {
                        $jpgfile = OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $filename . '.jpg';
                        $jpgfile = \Normalizer::normalize($jpgfile, Normalizer::FORM_C);
                        file_put_contents($jpgfile,
                                          file_get_contents($poster));
                    }
                    file_put_contents(OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $filename . '.nfo',
                                      mb_convert_encoding($nfo, 'UTF-8'));
                } else {
                    echo "NO SRC found for " . $itemData->title . ' [' . $i->id . '] ' . json_encode($itemData, JSON_UNESCAPED_UNICODE) . "\n";
                }

            } elseif (in_array($i->type, $needtype) && $i->type === 'serial') {
                #if ($nn > 1) {
                #    break;
                #}
                #$nn++;
                $serie = $c->url('v1/items/' . $i->id);
                $serieData = $serie->item;
                $seriename = str_replace(['/', ':'. '\''],
                                         ['',  '',  ''],
                                         $serieData->title . '.' . $serieData->year . '.' . $i->id);

                $seriename = preg_replace("/[^[:alnum:][:space:].!]/u", '', $seriename);
                $seriename = trim(preg_replace("/\s{2,}/", ' ',             $seriename));
                $seriename = mb_convert_encoding(trim($seriename), 'UTF-8');

                $serieDir  = $seriename;

                $seriePath = OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $serieDir;
                @mkdir($seriePath, 0777, true);

                echo 'Processing ' . $seriename . "\n";
                foreach ($serieData->seasons as $season) {
                    foreach ($season->episodes as $episode) {

                        $src      = null;
                        $quality  = null;
                        $poster   = $episode->thumbnail;

                        foreach ($episode->files as $f) {
                            if ($f->url->http && (in_array($f->quality, explode(',', QUALITY)))) {
                                $src     = normalizeKinoUrl($f->url->http);
                                $quality = $f->quality;
                                break;
                            }
                        }
                        if (!$src) {
                            echo 'Failed to find SRC for ' . $seriename . ' episode ' . $episode->title;
                            continue;
                        }

                        #$id, string $src, string $filename, string $description
                        $filename = str_replace(['/', ':'. '\''],
                                                ['',  '',  ''],
                                                $episode->title . '.' . $serieData->year . '.' . $quality . '.' . $i->id . '.' . $episode->id);

                        $filename = preg_replace("/[^[:alnum:][:space:].!]/u", '', $filename);
                        $filename = trim(preg_replace("/\s{2,}/", ' ',             $filename));

                        $filePrefix = 'S' . str_pad((string) $season->number, 3, '0',  STR_PAD_LEFT) .
                                      'E' . str_pad((string) $episode->number, 3, '0', STR_PAD_LEFT);
                        $filename   =  $filePrefix . ($filename ? (' ' . $filename) : '');
                        echo 'Processing ' . $filename . "\n";

                        $filename = mb_convert_encoding(trim($filename), 'UTF-8');

                        $xid++;
                       #$sdata[] = client::itemToXml($xid, $src, $filename . '.mp4', $serieData->plot,  ACTION . '\\' . $serieDir . '\\');
                        $sdata[] = client::itemToXml($xid, $src, $filename . '.mp4', $serieData->plot);

                        $thumb = $seriePath . DIRECTORY_SEPARATOR . $filename . '.jpg';
                        $posterPath = $filename . '.jpg';
                        if (!is_readable($thumb) && $poster) {
                            $posterData = @file_get_contents($poster);
                            if ($posterData) {
                                file_put_contents($thumb, $posterData);
                            } else {
                                $thumb = null;
                                $posterPath = null;
                            }
                        }

                        $nfo = client::itemToNFO($serieData, $posterPath, ' ' . $filePrefix . ' ' . $episode->title);
                        file_put_contents($seriePath . DIRECTORY_SEPARATOR . $filename . '.nfo', mb_convert_encoding($nfo, 'UTF-8'));
                    }
                }
            } else {
                echo "Skipping " . $i->type . ' ' . $i->title . ' [' . $i->id . '] ' . "\n";
            }
        }

        /**
         * Save as Download master joblist
         */
        if (count($data)) {
            $xml = client::itemsXml(implode("\n", $data));
            file_put_contents(OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $b->id . '-' . ACTION . '.xml',
                              mb_convert_encoding($xml, 'UTF-8'));
        }

        if (count($sdata)) {
            $xml = client::itemsXml(implode("\n", $sdata));
            file_put_contents(OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $b->id . '-' . ACTION . '.xml',
                              mb_convert_encoding($xml, 'UTF-8'));
        }
    }
}

/**
 * Get accessToken for API access
 */
function accessTokenAction()
{
    $code = client::getCode();
    for ($i = 0; $i < 5; $i++) {
        echo 'Please visit ' . $code->verification_uri
            . ' and input ' . $code->user_code
            . ' into activation device input' . "\n";

        $result = client::verifyCode($code->code);
        if (!isset($result->status) || $result->status != 200) {
            sleep($code->interval * 2);
        }

        if (isset($result->access_token) && isset($result->refresh_token)) {
            #$result = client::getExtendedAccessToken($result->refresh_token);
            $accessToken = $result->access_token;
            file_put_contents('ACCESS_TOKEN', $accessToken);

            echo 'Your accesToken is ' . $accessToken . ' please put it into config' . "\n";
            #echo 'Your refreshToken is ' . $result->refresh_token . ' please put it into config' . "\n";

            (new client($accessToken))->setClientInfo(CLIENT_TITLE);
            echo "Device info sent\n";

            exit;
            break;
        }
    }
}

function normalizeKinoUrl(string $url)
{
    $query = parse_url($url, PHP_URL_QUERY);
    if (!empty($query)) {
        $url = str_replace($query, '', $url);
        $url = rtrim($url, '?');
    }
    return str_replace(CDN_SERVER, SANE_SERVER, $url);
}

/**
 * Help
 * @param array $args
 */
function helpAction(array $args)
{
    echo 'Keno.pub simple API client' . "\n";
    echo 'Usage `' . $args[0] . ' [' . ACTIONSLIST . ']`' . "\n";

}
