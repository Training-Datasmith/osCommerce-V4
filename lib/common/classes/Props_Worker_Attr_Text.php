<?php

declare (strict_types=1);
namespace common\classes;

class Props_Worker_Attr_Text extends Props_Worker_Abstract
{
    /**
     * Convert POST params to xml
     * @param $params array $POST
     * @param $productId
     * @return array
     */
    public static function params_to_xml($params = [], $product_id = false)
    {
        $data = [];
        if (is_array($params['attr_text'] ?? null)) {
            $data['AttrText'] = [];
            foreach ($params['attr_text'] as $attr_id => $text_value) {
                $text_value = tep_db_prepare_input($text_value);
                $data['AttrText']['a' . $attr_id] = ['value' => $text_value, 'crc' => crc32((string) $text_value)];
            }
        }
        return $data;
    }
    /**
     * Return normalized urpid without props
     * @param type $uprid
     * @return type
     */
    public static function normalize_id($uprid)
    {
        $uprid = preg_replace('#\{a\d+\}[^{]*#', '', $uprid);
        return $uprid;
    }
    /**
     * Modify Cart contens key (product_id|uprid) particulary to props
     * @params $productId mixed normalized product id
     * @params $propsData array
     * @return mixed modified product id
     */
    public static function cart_uprid($products_id, $props_data)
    {
        if (is_array($props_data['AttrText'] ?? null)) {
            foreach ($props_data['AttrText'] as $id => $val) {
                if (preg_match('#^a(\d)+$#', $id, $match)) {
                    $products_id .= sprintf('{a%s}%s', $match[1], $val['crc']);
                }
            }
        }
        return $products_id;
    }
    /**
     * retrieve attrText array from props. attrText = [attr_id1 => Text1, attr_id2 => Text2]
     * @param $props string xml props
     * @return array|null
     */
    public static function get_attr_text($props)
    {
        if (!empty($props)) {
            $prop_data = \Yii::$app->get('PropsHelper')::xml_to_params($props);
            if (is_array($prop_data['AttrText'] ?? null)) {
                $res = [];
                foreach ($prop_data['AttrText'] as $id => $val) {
                    if (preg_match('#^a(\d+)$#', $id, $match)) {
                        $res[$match[1]] = $val['value'];
                    }
                }
                return $res;
            }
        }
    }
    /**
     * retrieve attrText array from cart
     * @param $cart \common\classes\shopping_cart
     * @param $uprid
     * @return array|null
     */
    public static function get_attr_text_cart($cart, $uprid)
    {
        if ($cart instanceof \common\classes\shopping_cart) {
            $product = $cart->get_products($uprid);
            return self::get_attr_text($product[0]['props'] ?? null);
        }
    }
}