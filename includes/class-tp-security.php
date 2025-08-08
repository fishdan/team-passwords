<?php
if (!defined('ABSPATH')) exit;

class TP_Security {
    public static function encrypt($plaintext) {
        $key = TP_SECRET_KEY;
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }

    public static function decrypt($ciphertext_b64) {
        $key = TP_SECRET_KEY;
        $data = base64_decode($ciphertext_b64);
        $iv = substr($data, 0, 16);
        $ciphertext = substr($data, 16);
        return openssl_decrypt($ciphertext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    }
}
