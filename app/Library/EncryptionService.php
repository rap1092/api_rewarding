<?php

namespace App\Library;

use Illuminate\Support\Str;
use Exception;

class EncryptionService
{
    public $key = "9fa8b5c8e27d7967a7eabfa4d307b6cf";
    private $iv;
    public function __construct($iv){
        $this->iv = $iv;
    }

    public function encryptAES($plaintext)
    {
        $this->iv = substr($this->iv, 0, 16);
        $encryptedData = openssl_encrypt($plaintext, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);
        $encryptedData = base64_encode($encryptedData);
        return $encryptedData;
    }

    public function decryptAES($encryptedData)
    {
        $this->iv = substr($this->iv, 0, 16);
        $encryptedData = base64_decode($encryptedData);
        $decryptedData = openssl_decrypt($encryptedData, 'aes-256-cbc', $this->key, OPENSSL_RAW_DATA, $this->iv);        
        return $decryptedData;    
    }

    public function encrypt($plaintext){
        $encrypt= $this->encryptAES($plaintext);
        return $encrypt;
    }

    public function decrypt($encryptedData){
        $decrypt = $this->decryptAES($encryptedData);
        return $decrypt;
    }
    

    
}
