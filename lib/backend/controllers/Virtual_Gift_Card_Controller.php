<?php

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
namespace backend\controllers;

use Yii;
/**
 * VirtualGiftCard controller
 */
class Virtual_Gift_Card_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_HEADING_GV_ADMIN', 'BOX_VIRTUAL_GIFT_CARD'];
    public function before_action($action)
    {
        if (false === \common\helpers\Acl::check_extension_allowed('CouponsAndVauchers', 'allowed')) {
            $this->redirect(['/']);
            return false;
        }
        return parent::before_action($action);
    }
    public function action_index()
    {
        $this->selected_menu = ['marketing', 'gv_admin', 'virtual-gift-card'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('virtual-gift-card/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_product_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $platforms = \common\models\Platforms::get_platforms_by_type('non-virtual')->all();
        $def_platform_id = \common\classes\platform::default_id();
        $this->view->def_platform_id = $def_platform_id;
        $this->view->platforms = $platforms;
        $check_product = tep_db_fetch_array(tep_db_query('select products_id from ' . TABLE_PRODUCTS . " where products_model = '" . tep_db_input(\common\helpers\Gifts::get_virtual_gift_card_model()) . "'"));
        if (isset($check_product['products_id'])) {
            $products_id = $check_product['products_id'];
        } else {
            $sql_data_array = ['products_date_added' => 'now()', 'products_seo_page_name' => 'virtual-gift-card', 'products_old_seo_page_name' => '', 'products_quantity' => 7777, 'products_weight' => 0, 'products_status' => 0, 'products_model' => \common\helpers\Gifts::get_virtual_gift_card_model()];
            tep_db_perform(TABLE_PRODUCTS, $sql_data_array);
            $products_id = tep_db_insert_id();
            tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $products_id . "', '0')");
        }
        $languages = \common\helpers\Language::get_languages();
        $currencies = Yii::$container->get('currencies');
        $default_currenciy = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $currencies_tabs = [];
        if (USE_MARKET_PRICES == 'True') {
            foreach ($currencies->currencies as $key => $value) {
                $gift_card_price_query = tep_db_query('select products_price, products_discount_price from ' . TABLE_VIRTUAL_GIFT_CARD_PRICES . " where products_id = '" . (int) $products_id . "' and currencies_id = '" . (int) $currencies->currencies[$key]['id'] . "' order by products_price");
                $content = '<div class="top-line"><div>' . GIFT_PRICE . '</div><div>' . GIFT_DISCOUNT_PRICE . '</div></div>';
                while ($gift_card_price = tep_db_fetch_array($gift_card_price_query)) {
                    $content .= '<div>' . tep_draw_input_field('products_price[' . $currencies->currencies[$key]['id'] . '][]', $gift_card_price['products_price']) . tep_draw_input_field('products_discount_price[' . $currencies->currencies[$key]['id'] . '][]', $gift_card_price['products_discount_price'], 'class="form-control"') . '<input type="button" value=" - " onClick="delete_row_price(this)" class="infoBoxButton"></div>';
                }
                $content .= '<div id="gift_card_price_' . $currencies->currencies[$key]['id'] . '"></div><div align="right"><input type="button" value="+" onClick="add_row_price(\'gift_card_price_' . $currencies->currencies[$key]['id'] . '\', \'' . htmlspecialchars('<div>' . tep_draw_input_field('products_price[' . $currencies->currencies[$key]['id'] . '][]', '') . tep_draw_input_field('products_discount_price[' . $currencies->currencies[$key]['id'] . '][]', '') . '<input type="button" value=" - " onClick="delete_row_price(this)" class="infoBoxButton"></div>') . '\')" class="infoBoxButton"></div>';
                $currencies_tabs[$currencies->currencies[$key]['id']] = ['title' => $currencies->currencies[$key]['title'], 'content' => $content];
            }
        } else {
            foreach ($currencies->currencies as $key => $value) {
                $content = '<div class="top-line"><div>' . GIFT_PRICE . '</div><div>' . GIFT_DISCOUNT_PRICE . '</div></div>';
                $gift_card_price_query = tep_db_query('select products_price, products_discount_price from ' . TABLE_VIRTUAL_GIFT_CARD_PRICES . " where products_id = '" . (int) $products_id . "' and currencies_id = '" . (int) $currencies->currencies[$key]['id'] . "' order by products_price");
                while ($gift_card_price = tep_db_fetch_array($gift_card_price_query)) {
                    $content .= '<div>' . tep_draw_input_field('products_price[' . $currencies->currencies[$key]['id'] . '][]', $gift_card_price['products_price'], 'class="form-control"') . tep_draw_input_field('products_discount_price[' . $currencies->currencies[$key]['id'] . '][]', $gift_card_price['products_discount_price'], 'class="form-control"') . '<input type="button" value=" - " onClick="delete_row_price(this)" class="infoBoxButton"></div>';
                }
                $content .= '<div id="gift_card_price_' . $currencies->currencies[$key]['id'] . '"></div><div class="vgc_add_line"><input type="button" value="+" onClick="add_row_price(\'gift_card_price_' . $currencies->currencies[$key]['id'] . '\', \'' . htmlspecialchars('<div>' . tep_draw_input_field('products_price[' . $currencies->currencies[$key]['id'] . '][]', '') . tep_draw_input_field('products_discount_price[' . $currencies->currencies[$key]['id'] . '][]', '') . '<input type="button" value=" - " onClick="delete_row_price(this)" class="infoBoxButton"></div>') . '\')" class="infoBoxButton"></div>';
                $currencies_tabs[$currencies->currencies[$key]['id']] = ['title' => $currencies->currencies[$key]['title'], 'content' => $content];
            }
        }
        return $this->render('index', ['products_id' => $products_id, 'languages' => $languages, 'currenciesTabs' => $currencies_tabs, 'defaultCurrenciy' => $default_currenciy]);
    }
    public function action_submit()
    {
        \common\helpers\Translation::init('admin/virtual-gift-card');
        $check_product = tep_db_fetch_array(tep_db_query('select products_id from ' . TABLE_PRODUCTS . " where products_model = '" . tep_db_input(\common\helpers\Gifts::get_virtual_gift_card_model()) . "'"));
        $products_id = $check_product['products_id'];
        if (!($products_id > 0)) {
            $sql_data_array = ['products_date_added' => 'now()', 'products_seo_page_name' => 'virtual-gift-card', 'products_old_seo_page_name' => '', 'products_quantity' => 7777, 'products_weight' => 0, 'products_status' => 0, 'products_model' => \common\helpers\Gifts::get_virtual_gift_card_model()];
            tep_db_perform(TABLE_PRODUCTS, $sql_data_array);
            $products_id = tep_db_insert_id();
            tep_db_query('insert into ' . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int) $products_id . "', '0')");
        }
        $platforms = \common\models\Platforms::get_platforms_by_type('non-virtual')->all();
        $languages = \common\helpers\Language::get_languages();
        $currencies = Yii::$container->get('currencies');
        foreach ($platforms as $platform) {
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $p_description = \common\models\Products_Description::find()->where(['products_id' => $products_id, 'language_id' => $languages[$i]['id'], 'platform_id' => $platform->platform_id])->one();
                if (!$p_description) {
                    $p_description = \common\models\Products_Description::create($products_id, $languages[$i]['id'], $platform->platform_id, 0);
                }
                $p_description->products_name = tep_db_prepare_input($_POST['products_name'][$platform->platform_id][$languages[$i]['id']]);
                $p_description->save();
            }
        }
        tep_db_query('delete from ' . TABLE_VIRTUAL_GIFT_CARD_PRICES . " where products_id = '" . (int) $products_id . "'");
        foreach ($currencies->currencies as $key => $value) {
            if (is_array($_POST['products_price'][$currencies->currencies[$key]['id']]) && count($_POST['products_price'][$currencies->currencies[$key]['id']]) > 0) {
                foreach ($_POST['products_price'][$currencies->currencies[$key]['id']] as $index => $price) {
                    $price = (float) $price;
                    if ($price > 0) {
                        $discount_price = (float) $_POST['products_discount_price'][$currencies->currencies[$key]['id']][$index];
                        if (!tep_not_null($discount_price)) {
                            $discount_price = $price;
                        }
                        $sql_data_array = ['products_id' => $products_id, 'currencies_id' => $currencies->currencies[$key]['id'], 'products_price' => $price, 'products_discount_price' => $discount_price];
                        tep_db_perform(TABLE_VIRTUAL_GIFT_CARD_PRICES, $sql_data_array);
                    }
                }
            }
        }
        $message = TEXT_PRODUCT_UPDATED_NOTICE;
        $message_type = 'success';
        ?>
        <div class="popup-box-wrap pop-mess">
            <div class="around-pop-up"></div>
            <div class="popup-box">
                <div class="pop-up-close pop-up-close-alert"></div>
                <div class="pop-up-content">
                    <div class="popup-heading"><?php 
        echo TEXT_NOTIFIC;
        ?></div>
                    <div class="popup-content pop-mess-cont pop-mess-cont-<?php 
        echo $message_type;
        ?>">
                        <?php 
        echo $message;
        ?>
                    </div> 
                </div>  
                <div class="noti-btn">
                    <div></div>
                    <div><span class="btn btn-primary"><?php 
        echo TEXT_BTN_OK;
        ?></span></div>
                </div>
            </div> 
            <script>
                $('body').scrollTop(0);
                $('.pop-mess .pop-up-close-alert, .noti-btn .btn').click(function () {
                    $(this).parents('.pop-mess').remove();
                });
            </script>
        </div>


        <?php 
        echo '<script> window.location.href="' . Yii::$app->url_manager->create_url('virtual-gift-card/') . '";</script>';
    }
}