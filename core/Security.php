<?php
declare(strict_types=1);
final class Security {
    public static function encrypt(string $plain): string {
        if($plain==='')return '';
        $key=hash('sha256',(string)app_config('app_key'),true);$iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($plain,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);
        if($cipher===false)throw new RuntimeException('Could not encrypt sensitive configuration.');
        return base64_encode($iv.$tag.$cipher);
    }
    public static function decrypt(string $encoded): string {
        if($encoded==='')return '';$raw=base64_decode($encoded,true);if($raw===false||strlen($raw)<28)return '';
        $key=hash('sha256',(string)app_config('app_key'),true);$iv=substr($raw,0,12);$tag=substr($raw,12,16);$cipher=substr($raw,28);
        $plain=openssl_decrypt($cipher,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);return $plain===false?'':$plain;
    }
}
