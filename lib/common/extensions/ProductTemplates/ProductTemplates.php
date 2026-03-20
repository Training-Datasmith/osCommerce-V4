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
namespace common\extensions\Product_Templates;

use Yii;
class Product_Templates extends \common\classes\modules\Module_Extensions
{
    public static function get_description()
    {
        return 'This extension allows to customize the product page display.';
    }
    public static function allowed()
    {
        return self::enabled();
    }
    public static function get_admin_hooks()
    {
        $path = \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR . 'ProductTemplates' . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR;
        return [['page_name' => 'categories/productedit', 'page_area' => '', 'extension_file' => $path . 'categories.productedit.php'], ['page_name' => 'categories/productedit', 'page_area' => 'details-right-column', 'extension_file' => $path . 'categories.productedit.details-right-column.tpl']];
    }
    public static function product_block()
    {
        if (!self::allowed()) {
            return '';
        }
        return \common\extensions\Product_Templates\Render::widget(['template' => 'product-block.tpl', 'params' => []]);
    }
    public static function productedit($products_id)
    {
        if (!self::allowed()) {
            return '';
        }
        $platforms = tep_db_query('
            SELECT p.platform_id, t.theme_name, t.title
            FROM ' . TABLE_PLATFORMS_PRODUCTS . ' p
                left join ' . TABLE_PLATFORMS_TO_THEMES . ' p2t on p.platform_id = p2t.platform_id
                left join ' . TABLE_THEMES . " t on t.id = p2t.theme_id\r\n            WHERE p2t.is_default = 1 and products_id = '" . $products_id . "'\r\n            ");
        $themes = [];
        $show_block = false;
        while ($platform = tep_db_fetch_array($platforms)) {
            $templates = tep_db_query('
                select setting_value
                from ' . TABLE_THEMES_SETTINGS . "\r\n                where\r\n                    theme_name = '" . $platform['theme_name'] . "' and\r\n                    setting_group = 'added_page' and\r\n                    setting_name = 'product'\r\n            ");
            if (tep_db_num_rows($templates) > 0) {
                while ($item = tep_db_fetch_array($templates)) {
                    $platform['themes'][] = $item['setting_value'];
                }
                $show_block = true;
            }
            $themes[$platform['platform_id']] = $platform;
        }
        $list = \common\classes\platform::get_list(false);
        foreach ($list as $key => $item) {
            if (isset($themes[$item['id']]) && $themes[$item['id']]) {
                $list[$key]['active'] = 1;
                $list[$key]['theme_name'] = $themes[$item['id']]['theme_name'];
                $list[$key]['theme_title'] = $themes[$item['id']]['title'];
                if (!empty($themes[$item['id']]['themes'])) {
                    $list[$key]['templates'] = $themes[$item['id']]['themes'];
                    $set_template = tep_db_fetch_array(tep_db_query('
                        select template_name
                        from ' . TABLE_PRODUCT_TO_TEMPLATE . "\r\n                        where\r\n                            products_id = '" . $products_id . "' and\r\n                            platform_id = '" . $item['id'] . "' and\r\n                            theme_name = '" . $themes[$item['id']]['theme_name'] . "'\r\n                    "));
                    if (isset($set_template['template_name']) && !empty($set_template['template_name'])) {
                        $list[$key]['template'] = $set_template['template_name'];
                    }
                }
            } else {
                $list[$key]['active'] = 0;
            }
        }
        $template['list'] = $list;
        $template['show_block'] = $show_block;
        return $template;
    }
    public static function product_submit($products_id)
    {
        if (!self::allowed()) {
            return '';
        }
        $product_template = Yii::$app->request->post('product_template');
        $list = self::productedit($products_id);
        foreach ($list['list'] as $item) {
            if ($products_id && $item['id'] && ($item['theme_name'] ?? null)) {
                tep_db_query('
                delete from ' . TABLE_PRODUCT_TO_TEMPLATE . "\r\n                where\r\n                    products_id = '" . $products_id . "' and\r\n                    platform_id = '" . $item['id'] . "' and\r\n                    theme_name = '" . $item['theme_name'] . "'");
                if ($product_template[$item['id']] ?? null) {
                    $data = ['products_id' => $products_id, 'platform_id' => $item['id'], 'theme_name' => $item['theme_name'], 'template_name' => $product_template[$item['id']]];
                    tep_db_perform(TABLE_PRODUCT_TO_TEMPLATE, $data);
                }
            }
        }
    }
    public static function product_delete($products_id)
    {
        if (!self::allowed()) {
            return '';
        }
        tep_db_query('
            delete from ' . TABLE_PRODUCT_TO_TEMPLATE . "\r\n            where products_id = '" . $products_id . "'");
    }
}