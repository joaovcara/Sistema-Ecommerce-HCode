<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;

class Address extends Model{

    const SESSION_ERROR = "AddressError";

    public static function getCep($cep){

        $cep = str_replace("-", "", $cep);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "http://viacep.com.br/ws/$cep/json/");

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $data = json_decode(curl_exec($ch), true);

        curl_close($ch);

        return $data;

    }

    public function loadFromCep($cep){

        $data = Address::getCep($cep);
        
        if(isset($data['logradouro']) && $data['logradouro']){

            $this->setdesaddress($data['logradouro']);
            $this->setdescomplement($data['complemento']);
            $this->setdesdistrict($data['bairro']);
            $this->setdescity($data['localidade']);
            $this->setdesstate($data['uf']);
            $this->setdescountry('Brasil');
            $this->setdeszipcode($cep);

        }

    }

    public function save(){

        $sql = new Sql();

        $results = $sql->select("CALL sp_addresses_save(:idaddress, :idperson, :desaddress, :desnumber, :descomplement, :descity, :desstate, :descountry, :deszipcode, :desdistrict)", [
            ':idaddress'=>$this->getidaddress(),
            ':idperson'=>$this->getidperson(),
            ':desaddress'=>$this->getdesaddress(),
            ':desnumber'=>$this->getdesnumber(),
            ':descomplement'=>$this->getdescomplement(),
            ':descity'=>$this->getdescity(),
            ':desstate'=>$this->getdesstate(),
            ':descountry'=>$this->getdescountry(),
            ':deszipcode'=>$this->getdeszipcode(),
            ':desdistrict'=>$this->getdesdistrict()
        ]);
        
        if(count($results) > 0){

            $this->setData($results[0]);

        }

    }

    public static function setMsgError($msg)
    {

        $_SESSION[Address::SESSION_ERROR] = $msg;

    }

    public static function getMsgError()
    {

        $msg = (isset($_SESSION[Address::SESSION_ERROR])) ? $_SESSION[Address::SESSION_ERROR] : "";

        Cart::clearMsgError();

        return $msg;

    }

    public static function clearMsgError()
    {

        $_SESSION[Address::SESSION_ERROR] = null;

    }
    
}
?>