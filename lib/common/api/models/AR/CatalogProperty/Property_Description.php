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
namespace common\api\models\AR\Catalog_Property;

use common\api\models\AR\Ep_Map;
class Property_Description extends Ep_Map
{
    public static function table_name()
    {
        return TABLE_PROPERTIES_DESCRIPTION;
    }
    public static function primary_key()
    {
        return ['properties_id', 'language_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->properties_id = $parent_object->properties_id;
        parent::parent_ep_map($parent_object);
    }
    public static function get_all_key_codes()
    {
        $key_codes = [];
        foreach (\common\classes\language::get_all() as $lang) {
            $key_code = $lang['code'] . '';
            $key_codes[$key_code] = ['properties_id' => null, 'language_id' => $lang['id']];
        }
        return $key_codes;
    }
}