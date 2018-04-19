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

    public function __construct($params)
    {
        $this->token = $params->apiToken;
        $this->affid = $params->apiAffid;
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

    public function productSearch($param, $output = 'xml')
    {
        $return = $this->connect("productsearch", [
                "_token" => $this->token,
                "q" => (isset($param['query'])) ? $param['query'] : "",
                "ps" => (isset($param['numresults']) ? $param['numresults'] : ""),
            ]
        );

        if($output == "xml") {
            return $return;
        }

        if($output == "array") {
            $data = ($this->xmlToArray($return));

            $productList = [];
            $products = ($data['resultset']['searchresult']['items']['product']);
            foreach ($products as $product) {
                $specifications = $this->getProductSpecifications($product['specification'] ?? []);

                $extra = array_key_exists("type_extra", $product) ? $product['type_extra'] : "";
                $extra = is_array($extra) ? implode(",", $extra) : $extra;
                $price = "";
                if(array_key_exists("prices", $product)) {
                    if(is_array($product['prices']) && array_key_exists("range", $product['prices'])) {
                        if(is_array($product['prices']['range']) && array_key_exists("$", $product['prices']['range'])) {
                            $price = $product['prices']['range']["$"];
                        }
                    }
                }
                $productcode = "";
                if(is_array($product['productgroup']) && array_key_exists("productcode", $product['productgroup'])) {
                    $productcode = $product['productgroup']['productcode'];
                }
                $productcount = "";
                if(is_array($product['prices']) && array_key_exists("count", $product['prices'])) {
                    $productcount = $product['prices']['count'];
                }

                $productList[] =  [
                    "id" => $product['productid'],
                    "brand" => $product['brand'],
                    "type" => $product['type'] . ($extra == "" ? "" : " (".$extra.")"),
                    "ean" => $product['ean'],
                    "specifications" => $specifications,
                    "category" => $productcode,
                    "price_range" => $price,
                    "price_count" => $productcount,
                ];
            }

            return $productList;
        }

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



    public function xmlToArray($xml, $options = array()) {
        $defaults = array(
            'namespaceSeparator' => ':',//you may want this to be something other than a colon
            'attributePrefix' => '@',   //to distinguish between attributes and nodes with the same name
            'alwaysArray' => array(),   //array of xml tag names which should always become arrays
            'autoArray' => true,        //only create arrays for tags which appear more than once
            'textContent' => '$',       //key used for the text content of elements
            'autoText' => true,         //skip textContent key if node has no attributes or child nodes
            'keySearch' => false,       //optional search and replace on tag and attribute names
            'keyReplace' => false       //replace values for above search values (as passed to str_replace())
        );
        $options = array_merge($defaults, $options);
        $namespaces = $xml->getDocNamespaces();
        $namespaces[''] = null; //add base (empty) namespace

        //get attributes from all namespaces
        $attributesArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->attributes($namespace) as $attributeName => $attribute) {
                //replace characters in attribute name
                if ($options['keySearch']) $attributeName =
                    str_replace($options['keySearch'], $options['keyReplace'], $attributeName);
                $attributeKey = $options['attributePrefix']
                    . ($prefix ? $prefix . $options['namespaceSeparator'] : '')
                    . $attributeName;
                $attributesArray[$attributeKey] = (string)$attribute;
            }
        }

        //get child nodes from all namespaces
        $tagsArray = array();
        foreach ($namespaces as $prefix => $namespace) {
            foreach ($xml->children($namespace) as $childXml) {
                //recurse into child nodes
                $childArray = $this->xmlToArray($childXml, $options);
                list($childTagName, $childProperties) = each($childArray);

                //replace characters in tag name
                if ($options['keySearch']) $childTagName =
                    str_replace($options['keySearch'], $options['keyReplace'], $childTagName);
                //add namespace prefix, if any
                if ($prefix) $childTagName = $prefix . $options['namespaceSeparator'] . $childTagName;

                if (!isset($tagsArray[$childTagName])) {
                    //only entry with this key
                    //test if tags of this type should always be arrays, no matter the element count
                    $tagsArray[$childTagName] =
                        in_array($childTagName, $options['alwaysArray']) || !$options['autoArray']
                            ? array($childProperties) : $childProperties;
                } elseif (
                    is_array($tagsArray[$childTagName]) && array_keys($tagsArray[$childTagName])
                    === range(0, count($tagsArray[$childTagName]) - 1)
                ) {
                    //key already exists and is integer indexed array
                    $tagsArray[$childTagName][] = $childProperties;
                } else {
                    //key exists so convert to integer indexed array with previous value in position 0
                    $tagsArray[$childTagName] = array($tagsArray[$childTagName], $childProperties);
                }
            }
        }

        //get text content of node
        $textContentArray = array();
        $plainText = trim((string)$xml);
        if ($plainText !== '') $textContentArray[$options['textContent']] = $plainText;

        //stick it all together
        $propertiesArray = !$options['autoText'] || $attributesArray || $tagsArray || ($plainText === '')
            ? array_merge($attributesArray, $tagsArray, $textContentArray) : $plainText;

        //return node as array
        return array(
            $xml->getName() => $propertiesArray
        );
    }
    
    /**
     * Get a html list of product specifications.
     *
     * @param array $specifications
     * @return string
     */
    protected function getProductSpecifications($specifications = [])
    {
        if (array_has($specifications, 'label')) {
            $specifications = [$specifications];
        }

        return collect($specifications)
            ->map(function ($specification) {
                return ($specification['label'] ?? '') . ":" . $this->getProductSpecificationValue($specification['value'] ?? '') . '<br/>';
            })
            ->implode('');

    }

    /**
     * Get the value of the product specification.
     *
     * @param string $value
     * @return string
     */
    protected function getProductSpecificationValue($value)
    {
        if (starts_with($value, 'http')) {
            return '<img src="' . $value . '">';
        }

        return $value;
    }
}
