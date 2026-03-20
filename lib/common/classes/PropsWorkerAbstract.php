<?php

declare (strict_types=1);
namespace common\classes;

abstract class Props_Worker_Abstract
{
    /**
     * Convert POST params to xml
     * @param $params array $POST
     * @param $productId
     * @return array
     */
    public static function params_to_xml($params = [], $product_id = false)
    {
        return [];
    }
    /**
     * Return normalized urpid without props
     * @param type $uprid
     * @return type
     */
    public static function normalize_id($uprid)
    {
        return $uprid;
    }
    /**
     * Modify Cart contens key (product_id|uprid) particulary to props
     * @params $productId mixed normalized product id
     * @params $propsData array
     * @return mixed modified product id
     */
    public static function cart_uprid($product_id, array $props_data)
    {
        return $product_id;
    }
    public static function on_cart_add($props_data)
    {
        return $props_data;
    }
    /*
    abstract public static function explainParams($params = array(), $tax_rate = 0);
    abstract public static function cartChanged($cart);
    */
}