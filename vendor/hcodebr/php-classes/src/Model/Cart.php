<?php

namespace Hcode\Model;

use \Hcode\DB\Sql;
use \Hcode\Model;
use \Hcode\Model\Product;
use \Hcode\Model\User;

class Cart extends Model
{

    const SESSION = "Cart";
    const SESSION_ERROR = "CartError";

    public static function getFromSession()
    {

        $cart = new Cart();

        if (isset($_SESSION[Cart::SESSION]) && (int) $_SESSION[Cart::SESSION]['idcart'] > 0) {

            $cart->get((int) $_SESSION[Cart::SESSION]['idcart']);

        } else {

            $cart->getFromSessionID();

            if (!(int) $cart->getidcard() > 0) {

                $data = [
                    'dessessionid' => session_id(),
                ];

                if (User::checkLogin(false)) {

                    $user = User::getFromSession();

                    $data['iduser'] = $user->getiduser();

                }

                $cart->setData($data);

                $cart->save();

                $cart->setToSession();

            }

        }

        return $cart;

    }

    public function setToSession()
    {

        $_SESSION[Cart::SESSION] = $this->getValues();

    }

    public function getFromSessionID()
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_carts WHERE dessessionid = :dessessionid", [
            ':dessessionid' => session_id(),
        ]);

        if (count($results) > 0) {

            $this->setData($results[0]);

        }

    }

    public function get(int $idcart)
    {

        $sql = new Sql();

        $results = $sql->select("SELECT * FROM tb_carts WHERE idcart = :idcart", [
            ':idcart' => $idcart,
        ]);

        if (count($results) > 0) {

            $this->setData($results[0]);

        }

    }

    public function save()
    {

        $sql = new Sql();

        $results = $sql->select("CALL sp_carts_save(:idcart, :dessessionid, :iduser, :deszipcode, :vlfreight, :nrdays)", [
            ':idcart' => $this->getidcart(),
            ':dessessionid' => $this->getdessessionid(),
            ':iduser' => $this->getiduser(),
            ':deszipcode' => $this->getdeszipcode(),
            ':vlfreight' => $this->getvlfreight(),
            ':nrdays' => $this->getnrdays(),
        ]);

        if (count($results) > 0) {

            $this->setData($results[0]);

        }

    }

    public function addProduct(Product $product)
    {

        $sql = new Sql();

        $sql->query("INSERT INTO tb_cartsproducts (idcart, idproduct) VALUES (:idcart, :idproduct)", [
            ':idcart' => $this->getidcart(),
            ':idproduct' => $product->getidproduct(),
        ]);

        $this->getCalculateTotal();

    }

    public function updateFreight()
    {

        if ($this->getdeszipcode() != '') {

            $this->setFreight($this->getdeszipcode());

        }

    }

    public function removeProduct(Product $product, $all = false)
    {

        $sql = new Sql();

        if ($all) {

            $sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL", [
                ':idcart' => $this->getidcart(),
                ':idproduct' => $product->getidproduct(),
            ]);

        } else {

            $sql->query("UPDATE tb_cartsproducts SET dtremoved = NOW() WHERE idcart = :idcart AND idproduct = :idproduct AND dtremoved IS NULL LIMIT 1", [
                ':idcart' => $this->getidcart(),
                ':idproduct' => $product->getidproduct(),
            ]);

        }

        $this->getCalculateTotal();

    }

    public function getProducts()
    {

        $sql = new Sql();

        $rows = $sql->select("SELECT pro.idproduct, pro.desproduct, pro.vlprice, pro.vlwidth, pro.vlheight, pro.vllength, pro.vlweight, pro.desurl, COUNT(*) AS qtd, SUM(pro.vlprice) AS total
                                FROM tb_cartsproducts cpr
                          INNER JOIN tb_products pro ON cpr.idproduct = pro.idproduct
                               WHERE cpr.idcart = :idcart AND cpr.dtremoved IS NULL
                            GROUP BY pro.idproduct, pro.desproduct, pro.vlprice, pro.vlwidth, pro.vlheight, pro.vllength, pro.vlweight, pro.desurl
                            ORDER BY pro.desproduct;
        ", [
            ':idcart' => $this->getidcart(),
        ]);

        return Product::checkList($rows);

    }

    public function getProductsTotals()
    {

        $sql = new Sql();

        $results = $sql->select("SELECT SUM(vlprice)  AS total,
		                                SUM(vlwidth)  AS largura,
                                        SUM(vlheight) AS altura,
                                        SUM(vllength) AS comprimento,
                                        SUM(vlweight) AS peso,
                                        COUNT(*)      AS qtd
                                        from tb_products pro
                                        INNER JOIN tb_cartsproducts cpr ON pro.idproduct = cpr.idproduct
                                        WHERE cpr.idcart = :idcart AND dtremoved IS NULL
        ", [
            ':idcart' => $this->getidcart(),
        ]);

        if (count($results) > 0) {

            return $results[0];

        } else {

            return [];

        }

    }

    public function setFreight($zipcode)
    {

        $zipcode = str_replace('-', '', $zipcode);

        $totals = $this->getProductsTotals();

        if ($totals['qtd'] > 0) {

            if ($totals['altura'] < 2) {
                $totals['altura'] = 2;
            }

            $qs = http_build_query([
                'nCdEmpresa' => '',
                'sDsSenha' => '',
                'nCdServico' => '40010',
                'sCepOrigem' => '17602045',
                'sCepDestino' => $zipcode,
                'nVlPeso' => $totals['peso'],
                'nCdFormato' => '1',
                'nVlComprimento' => $totals['comprimento'],
                'nVlAltura' => $totals['altura'],
                'nVlLargura' => $totals['largura'],
                'nVlDiametro' => '0',
                'sCdMaoPropria' => 'S',
                'nVlValorDeclarado' => $totals['total'],
                'sCdAvisoRecebimento' => 'S',
            ]);

            $xml = simplexml_load_file("http://ws.correios.com.br/calculador/CalcPrecoPrazo.asmx/CalcPrecoPrazo?" . $qs);

            $result = $xml->Servicos->cServico;
            
            if ($result['msgErro'] != '') {

                Cart::setMsgError($result->msgErro);

            } else {

                Cart::clearMsgError();

            }

            $this->setnrdays($result->PrazoEntrega);
            $this->setvlfreight(Cart::formatValueToDecimal($result->Valor));
            $this->setdeszipcode($zipcode);

            $this->save();

            return $result;

        } else {

        }

    }

    public static function formatValueToDecimal($value): float
    {

        $value = str_replace('.', '', $value);
        return str_replace(',', '.', $value);

    }

    public static function setMsgError($msg)
    {

        $_SESSION[Cart::SESSION_ERROR] = $msg;

    }

    public static function getMsgError()
    {

        $msg = (isset($_SESSION[Cart::SESSION_ERROR])) ? $_SESSION[Cart::SESSION_ERROR] : "";

        Cart::clearMsgError();

        return $msg;

    }

    public static function clearMsgError()
    {

        $_SESSION[Cart::SESSION_ERROR] = null;

    }

    public function getValues()
    {

        $this->getCalculateTotal();

        return parent::getValues();
    }

    public function getCalculateTotal()
    {

        $this->updateFreight();

        $totals = $this->getProductsTotals();

        $this->setsubtotal($totals['total']);
        $this->settotal($totals['total'] + $this->getvlfreight());

        return $totals;

    }

    public function removeSession()
    {
        $_SESSION[Cart::SESSION] = null;
        session_regenerate_id();
    }

}
