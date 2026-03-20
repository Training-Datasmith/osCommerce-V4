<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace backend\models\EP\Provider\Magento\maps;

class Address_Map
{
    private static $dependence = [];
    public static function get_map()
    {
        return ['entry_firstname' => 'firstname', 'entry_lastname' => 'lastname', 'entry_street_address' => 'street', 'entry_postcode' => 'postcode', 'entry_city' => 'city', 'entry_company' => 'company', 'entry_suburb' => '', 'entry_state' => 'region', 'entry_country_iso2' => 'country_id', 'is_default' => 'is_default_shipping', 'is_shiping_address' => 'is_shiping_address', 'is_billing_address' => 'is_billing_address'];
    }
    public static function aplly_mapping($data, $params = [])
    {
        $response = [];
        $map = self::get_map();
        if (isset($data['addresses']) && is_array($data['addresses'])) {
            $new_addresses = [];
            foreach ($data['addresses'] as $address) {
                $pattern = $map;
                foreach ($map as $tl_key => $mg_key) {
                    $pattern[$tl_key] = $address[$mg_key];
                }
                $pattern['save_lookup'] = $address['customer_address_id'];
                $new_addresses[] = $pattern;
            }
            $response = $new_addresses;
        }
        return $response;
    }
}