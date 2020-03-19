<?php declare(strict_types=1);

class client
{
    private const UA      = USERAGENT;

    private const APIHOST  = 'https://api.service-kp.com';

    private const CLIENT   = CLIENT_CLIENT;
    private const SECRET   = CLIENT_SECRET;
    private const DCODE    = 'device_code';
    private const DTOKEN   = 'device_token';
    private const AUTHHOST = 'https://api.service-kp.com/oauth2/device';

    private $accessToken;

    public function __construct(string $token)
    {
        $this->accessToken = $token;
    }

    public static function getCode(): ?stdClass {
        $data = self::postPublic(self::AUTHHOST
            . '?grant_type='    . self::DCODE
            . '&client_id='     . self::CLIENT
            . '&client_secret=' . self::SECRET);
        return $data;
    }

    /**
     * @see https://kinoapi.com/authentication.html#id12
     *
     * @param string $refreshToken
     * @return stdClass|null
     */
    public static function getExtendedAccessToken(string $refreshToken): ?stdClass {
        $data = self::postPublic(self::AUTHHOST
            . '?grant_type=refresh_token'
            . '&client_id='     . self::CLIENT
            . '&client_secret=' . self::SECRET
            . '&refresh_token=' . $refreshToken);
        return $data;
    }

    public static function verifyCode(string $code): ?stdClass
    {
        return self::postPublic(self::AUTHHOST
            . '?grant_type='    . self::DTOKEN
            . '&client_id='     . self::CLIENT
            . '&client_secret=' . self::SECRET
            . '&code=' . $code);
    }

    /**
     * @see https://kinoapi.com/api_device.html#api-device-notify
     * @param string $title
     */
    public function setClientInfo(string $title): ?stdClass
    {
        return $this->postPrivate(self::APIHOST . '/v1/device/notify',
            ['title' => $title]
        );
    }

    private function postPrivate(string $url, array $postData = null): ?stdClass
    {
        $ch = curl_init($url);
        if ($postData) {
            $postData = json_encode($postData, JSON_UNESCAPED_UNICODE | JSON_BIGINT_AS_STRING);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::UA);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->accessToken]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, false, 512, JSON_THROW_ON_ERROR);
    }

    private static function postPublic(string $url): ?stdClass
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::UA);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, false, 512, JSON_THROW_ON_ERROR);
    }

    private function get(string $url): ?stdClass
    {
        $url = ltrim($url, '/');
        $url = '/' . $url;


        $this->info("GET " . $url);
        $ch = curl_init(self::APIHOST . $url);
        curl_setopt($ch, CURLOPT_POST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, self::UA);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $this->accessToken]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);
        $response = json_decode($response, false, 512, JSON_THROW_ON_ERROR);
        if ($response && $response->status != 200) {
            $this->info('ERROR ' . $response->error);
        }
        return $response;
    }

    public function url(string $url, bool $decode = true)
    {
        $data = $this->get($url);
        if ($decode) {
            return $data;
        }
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function info(string $str): void
    {
        echo $str . "\n";
    }

    /**
     * Convert item to XML job description for DownloadMaster
     * @see https://westbyte.com/dm/index.phtml
     */
    public static function itemToXml(int $id, string $src, string $filename, string $description, string $savedir = null): string
    {
        return
            "
 <DownloadFile>
        <ID>$id</ID>
        <URL>$src</URL>
        <State>0</State>
        <SaveDir>" . DM_DIR . $savedir . "</SaveDir>
        <MaxSections>" . DM_CONCURRENCY ."</MaxSections>
        <Comment>$description</Comment>
        <SaveAs>$filename</SaveAs>
</DownloadFile>\n
";
    }

    /**
     * Convert item to NFO Kodi description
     * @see https://kodi.wiki/view/NFO_files
     */
    public static function itemToNFO(object $item, string $poster = null, string $title2 = null): string
    {
        $xml =
'<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<movie>
    <title>' . $item->title . $title2 . '</title>
    <year>'  . $item->year . '</year>
    <plot>'  . $item->plot . '</plot>
    <director>' . $item->director . '</director>' .
    ($poster ? ('
    <thumb aspect="poster" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="banner" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="clearart" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="discart" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="landscape" preview="' . $poster . '">' . $poster . '</thumb>') : '') . '  
    <uniqueid type="imdb" default="true">' . $item->imdb . '</uniqueid>
    <rating name="imdb" max="10" default="true">
        <value>' . $item->imdb_rating . '</value>
        <votes>' . $item->imdb_votes . '</votes>
    </rating>
    <userrating>' . $item->rating . '</userrating>
    #GENRE#   
    #COUNTRY#
    #ACTORS#   
</movie>
';
        $ganras = $item->genres;
        $txt    = '';
        foreach ($ganras as $g) {
            $txt .= '<genre>' . $g->title . '</genre>';
        }
        $xml = str_replace('#GENRE#', $txt . "\n", $xml);

        $countries = $item->countries;
        $txt    = '';
        foreach ($countries as $c) {
            $txt .= '<country>' . $c->title . '</country>';
        }
        $xml = str_replace('#COUNTRY#', $txt . "\n", $xml);

        $actors = explode(',', $item->cast);
        $txt    = '';
        foreach ($actors as $c) {
            $txt .= '<actor><name>' . trim($c) . '</name></actor>';
        }
        $xml = str_replace('#ACTORS#', $txt . "\n", $xml);
        return $xml;
    }

    /**
     * Convert items to XML job list for DownloadMaster
     * @see https://westbyte.com/dm/index.phtml
     */
    public static function itemsXml(string $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><DownloadList  Version="6">' . $items . '</DownloadList>';
    }
}