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
namespace common\api\models\AR\Categories;

use common\api\models\AR\Ep_Map;
use common\helpers\Seo;
class Description extends Ep_Map
{
    protected $hide_fields = ['categories_id', 'language_id', 'affiliate_id'];
    public static function table_name()
    {
        return TABLE_CATEGORIES_DESCRIPTION;
    }
    public static function primary_key()
    {
        return ['categories_id', 'language_id', 'affiliate_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->categories_id = $parent_object->categories_id;
        parent::parent_ep_map($parent_object);
    }
    public static function get_all_key_codes()
    {
        $key_codes = [];
        foreach (\common\classes\language::get_all() as $lang) {
            $key_code = $lang['code'] . '_0';
            $key_codes[$key_code] = ['categories_id' => null, 'language_id' => $lang['id'], 'affiliate_id' => 0];
        }
        return $key_codes;
    }
    public function before_save($insert)
    {
        if (empty($this->categories_seo_page_name)) {
            $this->categories_seo_page_name = Seo::make_slug($this->categories_name);
            if ($this->categories_id && $this->categories_seo_page_name) {
                $check_unique_seo_name = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS check_double ' . 'FROM ' . TABLE_CATEGORIES_DESCRIPTION . ' ' . "WHERE categories_id!='" . intval($this->categories_id) . "' " . " AND categories_seo_page_name='" . tep_db_input($this->categories_seo_page_name) . "'"));
                if ($check_unique_seo_name['check_double'] > 0) {
                    $this->categories_seo_page_name .= '-' . intval($this->categories_id);
                }
            }
        }
        return parent::before_save($insert);
    }
}