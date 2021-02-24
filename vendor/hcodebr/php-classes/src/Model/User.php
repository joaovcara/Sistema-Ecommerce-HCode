<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class User extends Model{

    const SESSION = "User";
    const SECRET = "CursoHcodePhp7_Secret";
    const SECRET_IV = "HcodePhp7_Secret_IV";
    const ERROR = "UserError";
    const ERROR_REGISTER = "UserErrorRegister";

    public static function getFromSession(){

        $user = new User();

        if(isset($_SESSION[User::SESSION]) && (int)$_SESSION[User::SESSION]['iduser'] > 0){

            $user->setData($_SESSION[User::SESSION]);

        }
        
        return $user;

    }
    
    public static function verifyLogin($inadmin = true){

        if(!User::checkLogin($inadmin)) {

            if($inadmin){

                header("Location: /admin/login");

            }else{

                header("Location: /login");

            }

            exit;

        }

    }

    public static function checkLogin($inadmin = true){

        if( !isset($_SESSION[User::SESSION])
            ||
            !$_SESSION[User::SESSION]
            ||
            !(int)$_SESSION[User::SESSION]['iduser'] > 0
        ){

            return false;

        }else {

            if($inadmin === true && (bool)$_SESSION[User::SESSION]['inadmin'] === true){

                return true;

            }else if($inadmin === false ){

                return true;

            }else {

                return false;

            }

        }

    }

    public static function login($Login, $Password){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users usr 
                            INNER JOIN tb_persons pes ON usr.idperson = pes.idperson 
                            WHERE deslogin = :LOGIN", array(
            ":LOGIN"=>$Login
        ));
        
        if(count($results) === 0){

            throw new \Exception ("Usuário inexistente ou senha inválida.");

        }

        $data = $results[0];

        if(password_verify($Password, $data["despassword"]) === true){

            $user = new User();
            
            $data['desperson'] = utf8_encode($data['desperson']);

            $user->setData($data);

            $_SESSION[User::SESSION] = $user->getValues();

            return $user;

        }else{

            throw new \Exception ("Usuário inexistente ou senha inválida.");

        }

    }

    public static function logout(){

        $_SESSION[User::SESSION] = null;

    }

    public static function listAll(){

        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_users usr INNER JOIN tb_persons psn USING(idperson) ORDER BY psn.desperson");

    }

    public function save(){

        $sql = new Sql();

        $results = $sql->select("CALL sp_users_save(:desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":desperson"=>$this->getdesperson(),
            ":deslogin"=>utf8_decode($this->getdeslogin()),
            "despassword"=>User::getPasswordHash($this->getdespassword()),
            "desemail"=>$this->getdesemail(),
            "nrphone"=>$this->getnrphone(),
            "inadmin"=>$this->getinadmin()
        ));

        $this->setData($results[0]);

    }

    public function get($iduser){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users a INNER JOIN tb_persons b USING(idperson) WHERE a.iduser = :iduser", array(
            ":iduser"=>$iduser
        ));

        $data = $results[0];

        $data['desperson'] = utf8_encode($data['desperson']);

        $this->setData($results[0]);

    }

    public function update(){
        
        $sql = new Sql();

        $results = $sql->select("CALL sp_usersupdate_save(:iduser, :desperson, :deslogin, :despassword, :desemail, :nrphone, :inadmin)", array(
            ":iduser"=>$this->getiduser(),
            ":desperson"=>utf8_decode($this->getdesperson()),
            ":deslogin"=>$this->getdeslogin(),
            "despassword"=>User::getPasswordHash($this->getdespassword()),
            "desemail"=>$this->getdesemail(),
            "nrphone"=>$this->getnrphone(),
            "inadmin"=>$this->getinadmin()
        ));

        $this->setData($results[0]);

    }

    public function delete(){

        $sql = new Sql();

        $sql->query("CALL sp_users_delete(:iduser)", array(
            ":iduser"=>$this->getiduser()
        ));

    }

    public static function getForgot($email){

        $sql = new Sql();

        $query = $sql->select("SELECT * FROM tb_persons per
                                    INNER JOIN tb_users   usr USING(idperson) 
                                         WHERE per.desemail = :email", array(
                                             "email"=>$email
                                         ));

        if(count($query) === 0 ){
            
            throw new \Exception ("Email não cadastrado");

        }else{

            $data = $query[0];

            $results = $sql->select ("CALL sp_userspasswordsrecoveries_create(:iduser, :desip)", array(
                ":iduser"=>$data["iduser"],
                ":desip"=>$_SERVER["REMOTE_ADDR"]
            ));

            if(count($results) === 0) {

                throw new \Exception("Não foi possível recuperar a senha");

            }else{

                $dataRecovery = $results[0];

                $code = openssl_encrypt($dataRecovery["idrecovery"], 'AES-128-CBC', pack("a16", User::SECRET), 0, pack("a16", User::SECRET_IV));

                $code = base64_encode($code);

                $link = "http://www.ecommerce.com.br/admin/forgot/reset?code=$code";

                $mailer = new Mailer($data["desemail"], $data["desperson"], "Redefinir senha da Hcode Store", "forgot", array(
                    "name"=>$data["desperson"],
                    "link"=>$link
                ));

                $mailer->send();

                return $data;

            }

        }
        
    }

    public static function validForgotDecrypt($code){

        $code = base64_decode($code);

        $idrecovery = openssl_decrypt($code, 'AES-128-CBC', pack("a16", User::SECRET), 0, pack("a16", User::SECRET_IV));

        $sql = new Sql();

        $result = $sql->select("SELECT * FROM tb_userspasswordsrecoveries  upr
                                   INNER JOIN tb_users 	 usr USING (iduser)
                                   INNER JOIN tb_persons psn USING (idperson)
                                        WHERE upr.idrecovery = :idrecovery
                                          AND upr.dtrecovery IS NULL
                                          AND DATE_ADD(upr.dtregister , INTERVAL 1 HOUR) >= NOW();", array(
                                              ":idrecovery"=>$idrecovery
                                          ));

        if(count($result) === 0 ){

            throw new \Exception("Não foi possível recuperar a senha.");

        }else{

            return $result[0];

        }

    }


    public static function setForgotUsed($idrecovery){

        $sql = new Sql();

        $sql->query("UPDATE tb_userspasswordrecoveries SET dtrecovery = NOW() WHERE idrecovery = :idrecovery", array(

            ":idrecovery"=>$idrecovery

        ));

    }

    public function setPassword($password){

        $sql = new Sql();

        $sql->query("UPDATE tb_users SET despassword = :password WHERE iduser = :iduser", array(
            ":password"=>$password,
            ":iduser"=>$this->getiduser()
        ));

    }

    public static function setError($msg){

        $_SESSION[User::ERROR] = $msg;

    }

    public static function getError(){

        $msg = (isset($_SESSION[User::ERROR]) && $_SESSION[User::ERROR] ? $_SESSION[User::ERROR] : '');

        User::clearError();

        return $msg;

    }

    public static function clearError(){

        $_SESSION[User::ERROR] = NULL;

    }

    public static function setErrorRegister($msg){

        $_SESSION[User::ERROR_REGISTER] = $msg;

    }

    public static function getErrorRegister(){

        $msg = (isset($_SESSION[User::ERROR_REGISTER]) && $_SESSION[User::ERROR_REGISTER]) ? $_SESSION[User::ERROR_REGISTER] : '';

        User::clearErrorRegister();

        return $msg;
    }

    public static function clearErrorRegister(){

        $_SESSION[User::ERROR_REGISTER] = NULL;

    }

    public static function checkLoginExist($login){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_users WHERE deslogin = :deslogin",[
            'deslogin'=>$login
        ]);

        return(count($results) > 0);

    }

    public static function getPasswordHash($password){

        return password_hash($password, PASSWORD_DEFAULT, [
            'cost'=>12
        ]);

    }


}

?>