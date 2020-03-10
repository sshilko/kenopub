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
        $data = self::post(self::AUTHHOST
            . '?grant_type='    . self::DCODE
            . '&client_id='     . self::CLIENT
            . '&client_secret=' . self::SECRET);
        return $data;
    }

    public static function verifyCode(string $code): ?stdClass
    {
        return self::post(self::AUTHHOST
            . '?grant_type='    . self::DTOKEN
            . '&client_id='     . self::CLIENT
            . '&client_secret=' . self::SECRET
            . '&code=' . $code);
    }

    private static function post(string $url): ?stdClass
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::UA);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
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

        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, false, 512, JSON_THROW_ON_ERROR);
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
    public static function itemToXml(int $id, string $src, string $filename, string $description): string
    {
        return
            "
 <DownloadFile>
        <ID>$id</ID>
        <URL>$src</URL>
        <State>0</State>
        <SaveDir>" . DM_DIR . "</SaveDir>
        <MaxSections>" . DM_CONCURRENCY ."</MaxSections>
        <Comment>$description</Comment>
        <SaveAs>$filename</SaveAs>
        <ResumeMode>2</ResumeMode>
        <DownloadTime>1</DownloadTime>
        <ContentType>video/mp4</ContentType>
        <stodt>2</stodt>
</DownloadFile>
";
    }

    /**
     * Convert item to NFO Kodi description
     * @see https://kodi.wiki/view/NFO_files
     */
    public static function itemToNFO(object $item, string $poster): string
    {
        $xml =
'<?xml version="1.0" encoding="UTF-8" standalone="yes" ?>
<movie>
    <title>' . $item->title . '</title>
    <year>'  . $item->year . '</year>
    <plot>'  . $item->plot . '</plot>
    <director>' . $item->director . '</director>
    <thumb aspect="poster" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="banner" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="clearart" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="discart" preview="' . $poster . '">' . $poster . '</thumb>  
    <thumb aspect="landscape" preview="' . $poster . '">' . $poster . '</thumb>  
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