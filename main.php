<?php declare(strict_types=1);

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
        #for ($i = 1; $i < 2; $i++) {
            $itemsRaw = $c->url('v1/bookmarks/' . $b->id . '?page=' . $i);
            $items    = array_merge($items, $itemsRaw->items);
            if ($itemsRaw->pagination->current == $itemsRaw->pagination->total) {
                break;
            }
        }
        $data  = [];
        $sdata = [];
        $xid   = 0;
        foreach ($items as $i) {
            $xml = null;
            if (in_array($i->type, $needtype) && ($i->type === 'movie' || $i->type === 'documovie')) {
                $movie = $c->url('v1/items/' . $i->id);
                $itemData = $movie->item;

                $src      = null;
                $quality  = null;
                $poster   = (isset($itemData->posters->big)) ? $itemData->posters->big : $itemData->posters->small;

                foreach ($itemData->videos as $v) {
                    foreach ($v->files as $f) {
                        if ($f->url->http && (in_array($f->quality, explode(',', QUALITY)))) {
                            $src     = $f->url->http;
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
                    $filename = str_replace('  ', ' ', $filename);
                    $filename = str_replace('  ', ' ', $filename);
                    $filename = str_replace('  ', ' ', $filename);

                    echo 'Processing ' . $filename . "\n";

                    $xid++;
                    $data[]   = client::itemToXml($xid, $src, $filename . '.mp4', $itemData->plot, '\\' . ACTION . '\\');
                    $nfo      = client::itemToNFO($itemData, $filename . '.jpg');

                    $thumb = OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $filename . '.jpg';
                    if (!is_readable($thumb) && $poster) {
                        file_put_contents(OUTDIR . DIRECTORY_SEPARATOR . ACTION . DIRECTORY_SEPARATOR . $filename . '.jpg',
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
                $seriename = str_replace('  ', ' ', $seriename);
                $seriename = str_replace('  ', ' ', $seriename);
                $seriename = str_replace('  ', ' ', $seriename);

                $serieDir  = str_replace('  ', ' ', $seriename);
                $serieDir  = str_replace('  ', ' ', $serieDir);
                $serieDir  = str_replace('  ', ' ', $serieDir);

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
                                $src     = $f->url->http;
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
                        $filename = str_replace('  ', ' ', $filename);
                        $filename = str_replace('  ', ' ', $filename);
                        $filename = str_replace('  ', ' ', $filename);
                        $filePrefix = 'S' . str_pad((string) $season->number, 3, '0',  STR_PAD_LEFT) .
                                      'E' . str_pad((string) $episode->number, 3, '0', STR_PAD_LEFT);
                        $filename =  $filePrefix . ($filename ? (' ' . $filename) : '');
                        echo 'Processing ' . $filename . "\n";

                        $xid++;
                        $sdata[] = client::itemToXml($xid, $src, $filename . '.mp4', $serieData->plot,  ACTION . '\\' . $serieDir . '\\');

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
            sleep($code->interval + 1);
        }

        if (isset($result->access_token)) {
            $accessToken = $result->access_token;
            echo 'Your accesToken is ' . $accessToken . ' please put it into config' . "\n";
            exit;
            break;
        }
    }
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
