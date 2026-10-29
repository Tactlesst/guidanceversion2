<?php
final class WebsiteConfiguration {
    public static function normalize($value): string {
        if (!is_string($value)) throw new InvalidArgumentException('Enter the full website address, starting with https:// or http://.');
        $url = rtrim(trim($value), '/');
        $p = parse_url($url);
        if (strlen($url)>1500 || !filter_var($url,FILTER_VALIDATE_URL) || !$p || !in_array(strtolower($p['scheme']??''),['http','https'],true) || isset($p['user']) || isset($p['pass']) || isset($p['query']) || isset($p['fragment']) || preg_match('/[\x00-\x20\x7f]/',$url)) {
            throw new InvalidArgumentException('Enter a full website address without a username, password, query or fragment. Include its folder if applicable.');
        }
        return $url;
    }
    public static function managed(): bool { return (bool)getenv('GUIDANCE_BASE_URL'); }
    public static function save($value, ?string $path=null): void {
        if (self::managed()) throw new RuntimeException('Your hosting environment manages the website address. Update GUIDANCE_BASE_URL on the server.');
        $url=self::normalize($value);
        $path=$path??__DIR__.'/../../config/app.local.php';
        $local=is_file($path)?require $path:[];
        if(!is_array($local)) throw new RuntimeException('Website settings could not be read.');
        $local['base_url']=$url;
        $data="<?php\n// Website settings. Protected from browser access by config/.htaccess.\nreturn ".var_export($local,true).";\n";
        $temp=$path.'.'.bin2hex(random_bytes(8)).'.tmp';
        try {
            if(file_put_contents($temp,$data,LOCK_EX)!==strlen($data)) throw new RuntimeException('Website settings could not be saved.');
            @chmod($temp,0600);
            if(!rename($temp,$path)) throw new RuntimeException('Website settings could not be saved.');
        } finally {if(is_file($temp))@unlink($temp);}
    }
}
