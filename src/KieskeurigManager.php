<?php

namespace Phf\Kieskeurig;

use stdClass;

class KieskeurigManager
{
    private $token;
    private $affid;
    private $serviceUrl = "https://rest-ext.kieskeurig.nl";

    private $searchTypes = [
        "affiliateprices" => "/nl/product.nsf/affiliateprices",
        "productsearch" => "/nl/product.nsf/wssearch ",
        "toplists" => "/nl/product.nsf/affiliateredirect?readform",
        "ids" => "/nl/product.nsf/wsproductprices"
    ];

    public function __construct($token, $affId)
    {
        $this->token = $token;
        $this->affid = $affId;
    }

    public function connect($function, $param)
    {
        ini_set("default_socket_timeout", 25);
        $url = $this->serviceUrl . $this->searchTypes[$function] . "?" . http_build_query($param);
        return simplexml_load_file($url);
    }

    public function productItemSearch($param)
    {
        return $this->connect("affiliateprices", [
                "_token" => $this->token,
                "affid" => $this->affid,
                "results" => (isset($param['numresults'])) ? $param['numresults'] : 20,
                "prodid" => (isset($param['kk_id'])) ? $param['kk_id'] : "",
                "ean" => (isset($param['ean'])) ? $param['ean'] : "",
                "output" => (isset($param['output'])) ? $param['output'] : "XML",
                "country" => (isset($param['country'])) ? $param['country'] : "NL",
            ]
        );
    }

    public function productIdSearch($param)
    {
        return $this->connect("ids", [
                "_token" => $this->token,
                "affid" => $this->affid,
                "results" => (isset($param['numresults'])) ? $param['numresults'] : 1,
                "productid" => (isset($param['ean'])) ? $param['ean'] : "",
                "output" => (isset($param['output'])) ? $param['output'] : "XML",
                "country" => (isset($param['country'])) ? $param['country'] : "NL",
            ]
        );
    }

    public function productSearch($param)
    {
        return $this->connect("productsearch", [
                "_token" => $this->token,
                "q" => (isset($param['query'])) ? $param['query'] : "",
                "ps" => (isset($param['numresults']) ? $param['numresults'] : ""),
            ]
        );
    }

    /**
     * @return mixed
     */
    public function getShops($result)
    {
        $shops = array();
        foreach ($result->SHOP as $_shop) {
            $shop = new stdClass();
            $shop->shop_id = (string)$_shop->SHOPID;
            $shop->shopname = (string)$_shop->SHOPNAME;
            $shop->shoplogo = (string)$_shop->LOGO;
            $shop->levertijd = "Binnen " . (string)$_shop->DELIVERYTIME;
            $shop->levertijd .= ((int)$_shop->DELIVERYTIME == 1) ? ' werkdag' : ' werkdagen';
            $shop->price = str_replace(',', '', (string)$_shop->PICKUPPRICE);
            $shop->postage = (string)$_shop->SHOPNAME;
            $shop->producturl = (string)$_shop->SHOPLINK;
            $shop->source = (string)'kieskeurig';

            if ($shop->price > 0) {
                $shops[] = $shop;
            }

//                if (!empty($shop->price)) {
//                    $allPrices[] = $shop->price;
//                }
        }

        return $shops;
    }
}