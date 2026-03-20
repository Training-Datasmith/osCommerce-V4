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
namespace common\api\models\AR\Products\Documents;

use common\api\models\AR\Ep_Map;
class Title extends Ep_Map
{
    protected $hide_fields = ['products_documents_id', 'language_id'];
    public static function table_name()
    {
        return TABLE_PRODUCTS_DOCUMENTS_TITLES;
    }
    public static function primary_key()
    {
        return ['products_documents_id', 'language_id'];
    }
    public static function get_all_key_codes()
    {
        $key_codes = [];
        foreach (\common\classes\language::get_all() as $lang) {
            $key_code = $lang['code'];
            $key_codes[$key_code] = ['products_documents_id' => null, 'language_id' => $lang['id']];
        }
        return $key_codes;
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_documents_id = $parent_object->products_documents_id;
        parent::parent_ep_map($parent_object);
    }
}