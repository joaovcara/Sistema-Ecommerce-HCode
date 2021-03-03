<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Mailer;

class Product extends Model {

    public static function listAll(){
        
        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_products ORDER BY desproduct");

    }

    public static function checkList($list){

        foreach ($list as &$row) {
            
            $product = new Product();
            $product->setData($row);
            $row = $product->getValues();

        }

        return $list;

    }

    public function save(){

        $sql = new Sql();

        $results = $sql->select("CALL sp_products_save(:idproduct, :desproduct, :vlprice, :vlwidth, :vlheight, :vllength, :vlweight, :desurl)", array(
            ":idproduct"=>$this->getidproduct(),
            ":desproduct"=>$this->getdesproduct(),
            ":vlprice"=>$this->getvlprice(),
            ":vlwidth"=>$this->getvlwidth(),
            ":vlheight"=>$this->getvlheight(),
            ":vllength"=>$this->getvllength(),
            ":vlweight"=>$this->getvlweight(),
            ":desurl"=>$this->getdesurl()
        ));

        $this->setData($results[0]);

    }

    public function get($idproduct){

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_products WHERE idproduct = :idproduct", [
            ":idproduct"=>$idproduct
        ]);

        $this->setData($results[0]);

    }

    public function delete(){

        $sql = new Sql();

        $sql->query("DELETE FROM tb_products WHERE idproduct = :idproduct", [
            ":idproduct"=>$this->getidproduct()
        ]);

    }

    public function checkPhoto(){

        if(file_exists(
            $_SERVER['DOCUMENT_ROOT'] . 
            DIRECTORY_SEPARATOR . "res" . 
            DIRECTORY_SEPARATOR . "site" . 
            DIRECTORY_SEPARATOR . "img" . 
            DIRECTORY_SEPARATOR . "Products" . 
            DIRECTORY_SEPARATOR . $this->getidproduct() . ".jpg" )){
                
            $url = "/res/site/img/Products/" . $this->getidproduct() . ".jpg"; 
            
        }else{

            $url =  "/res/site/img/product.jpg";

        }

        return $this->setdesphoto($url);

    }

    public function getValues(){

        $this->checkPhoto();

        $values = parent::getValues();

        return $values;

    }

    public function setPhoto($file){

        $extension = explode('.', $file["name"]);

        $extension = end($extension);

        //Convertendo imagem para jpeg
        switch($extension){
            case "jpg":
            case "jpeg": 
                $image = imagecreatefromjpeg($file["tmp_name"]);
            break;

            case "gif":
                $image = imagecreatefromgif($file["tmp_name"]);
            break;

            case "png":
                $image = imagecreatefrompng($file["tmp_name"]);
            break;
        }

        $dist = $_SERVER['DOCUMENT_ROOT'] . 
            DIRECTORY_SEPARATOR . "res" . 
            DIRECTORY_SEPARATOR . "site" . 
            DIRECTORY_SEPARATOR . "img" . 
            DIRECTORY_SEPARATOR . "Products" . 
            DIRECTORY_SEPARATOR . $this->getidproduct() . ".jpg" ;

        imagejpeg($image, $dist);

        imagedestroy($image);

        $this->checkPhoto();

    }

    public function getFromUrl($desurl){

        $sql = new Sql();

        $rows = $sql->select("SELECT * FROM tb_products WHERE desurl = :desurl", [
            ':desurl'=>$desurl
        ]);

        $this->setData($rows[0]);

    }

    public function getCategories(){

        $sql = new Sql();

        return $sql->select("SELECT * FROM tb_categories cat INNER JOIN tb_productscategories pct ON cat.idcategory = pct.idcategory WHERE pct.idproduct = :idproduct",[
            ':idproduct'=>$this->getidproduct()
        ]);

    }

    public static function getPage($page = 1, $itemPerPage = 15){

        $start = ($page - 1) * $itemPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT sql_calc_found_rows * 
            FROM tb_products
            ORDER BY desproduct
            LIMIT $start, $itemPerPage;
        ");

        $resultsTotal = $sql->select("SELECT found_rows() AS nrtotal");

        return [
            'data'=>$results,
            'total'=>(int)$resultsTotal[0]['nrtotal'],
            'pages'=>ceil($resultsTotal[0]['nrtotal'] / $itemPerPage)
        ];

    }

    public static function getPageSearch($search, $page = 1, $itemPerPage = 15){

        $start = ($page - 1) * $itemPerPage;

        $sql = new Sql();

        $results = $sql->select("SELECT sql_calc_found_rows * 
            FROM tb_products             
            WHERE desproduct LIKE :search 
            ORDER BY desproduct
            LIMIT $start, $itemPerPage;
        ",[
            ':search'=>'%'.$search.'%'
        ]);

        $resultsTotal = $sql->select("SELECT found_rows() AS nrtotal");

        return [
            'data'=>$results,
            'total'=>(int)$resultsTotal[0]['nrtotal'],
            'pages'=>ceil($resultsTotal[0]['nrtotal'] / $itemPerPage)
        ];

    }

}

?>