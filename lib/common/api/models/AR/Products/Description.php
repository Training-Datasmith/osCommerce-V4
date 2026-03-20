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
namespace common\api\models\AR\Products;

use common\api\models\AR\Ep_Map;
class Description extends Ep_Map
{
    /**
     * @var EPMap
     */
    protected $parent_object;
    protected $hide_fields = ['products_id', 'language_id', 'platform_id', 'department_id', 'products_name_soundex', 'products_description_soundex'];
    public static function get_all_key_codes()
    {
        $key_codes = [];
        $platforms = \common\models\Platforms::get_platforms_by_type('non-virtual')->all();
        foreach ($platforms as $platform) {
            foreach (\common\classes\language::get_all() as $lang) {
                $key_code = $lang['code'] . '_' . $platform->platform_id;
                $key_codes[$key_code] = ['products_id' => null, 'language_id' => $lang['id'], 'platform_id' => $platform->platform_id, 'department_id' => 0];
            }
        }
        if (defined('SUPERADMIN_ENABLED') && SUPERADMIN_ENABLED && \Yii::$app->has('department')) {
            $active_department = \Yii::$app->get('department')->get_active_department_id();
            foreach (\common\classes\department::get_catalog_assign_list() as $department) {
                if ($department['id'] != $active_department) {
                    continue;
                }
                $key_code = $lang['code'] . '_0_' . $department['id'];
                $key_codes[$key_code] = ['products_id' => null, 'language_id' => $lang['id'], 'platform_id' => \common\classes\platform::default_id(), 'department_id' => $department['id']];
            }
        }
        return $key_codes;
    }
    public static function table_name()
    {
        return TABLE_PRODUCTS_DESCRIPTION;
    }
    public static function primary_key()
    {
        return ['products_id', 'language_id', 'platform_id'];
    }
    public function parent_ep_map(Ep_Map $parent_object)
    {
        $this->products_id = $parent_object->products_id;
        $this->parent_object = $parent_object;
    }
    public function before_save($insert)
    {
        $this->products_seo_page_name = \common\helpers\Seo::make_product_slug($this, $this->parent_object);
        return parent::before_save($insert);
    }
    public function after_save($insert, $changed_attributes)
    {
        parent::after_save($insert, $changed_attributes);
        if (isset($changed_attributes['products_seo_page_name'])) {
            if ($ext = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed')) {
                $ext::track_product_links($this->products_id, $this->language_id, $this->platform_id, ['products_seo_page_name' => $this->products_seo_page_name], ['products_seo_page_name' => $changed_attributes['products_seo_page_name']]);
            }
        }
    }
}