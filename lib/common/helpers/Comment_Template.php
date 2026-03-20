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
namespace common\helpers;

use common\classes\extended\Order_Abstract;
use common\classes\platform_config;
use common\models\Orders_Comment_Template;
use common\models\Orders_Status;
use yii\db\Expression;
class Comment_Template
{
    public static function get_visibility_variants()
    {
        $res = ['order' => BOX_CUSTOMERS_ORDERS, 'subscription' => BOX_CUSTOMERS_SUBSCRIPTION];
        foreach (\common\helpers\Hooks::get_list('comment-template/visibility-variants') as $file) {
            include $file;
        }
        return $res;
    }
    public static function get_active_variants($include_id = 0)
    {
        $fallback_languages = [];
        $fallback_languages[] = \common\helpers\Language::get_default_language_id();
        $list = [];
        $Templates = Orders_Comment_Template::find()->where(['OR', ['status' => 1], [Orders_Comment_Template::table_name() . '.comment_template_id' => $include_id]])->order_by(['sort_order' => SORT_ASC])->all();
        foreach ($Templates as $Template) {
            $text_model = $Template->get_texts()->where(['language_id' => \Yii::$app->settings->get('languages_id')])->and_where(['!=', 'comment_template', ''])->one();
            if (!$text_model) {
                $text_model = $Template->get_texts()->where(['IN', 'language_id', $fallback_languages])->and_where(['!=', 'comment_template', ''])->order_by(new Expression("IF(language_id='" . (int) $fallback_languages[0] . "',0,1)"))->one();
            }
            $list[] = ['id' => $Template->comment_template_id, 'text' => $text_model->name, 'visibility' => preg_split('/,/', $Template->visibility, -1, PREG_SPLIT_NO_EMPTY)];
        }
        return $list;
    }
    public static function get_comment_template_variants($type, $order)
    {
        $template_vars = ['CUSTOMER_NAME' => '', 'STORE_NAME' => '', 'STORE_OWNER' => '', 'EMAIL_FROM' => '', 'STORE_OWNER_EMAIL_ADDRESS' => '', 'STORE_ADDRESS' => ''];
        if (is_object($order) && $order instanceof Order_Abstract) {
            $template_vars['CUSTOMER_NAME'] = $order->customer['name'];
            $platform_config = new platform_config($order->info['platform_id']);
            $template_vars['STORE_NAME'] = $platform_config->const_value('STORE_NAME');
            $template_vars['STORE_OWNER'] = $platform_config->const_value('STORE_OWNER');
            $template_vars['EMAIL_FROM'] = $platform_config->const_value('EMAIL_FROM');
            $template_vars['STORE_OWNER_EMAIL_ADDRESS'] = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
            $template_vars['STORE_ADDRESS'] = $platform_config->const_value('STORE_ADDRESS');
        }
        $patterns = [];
        $replace = [];
        foreach ($template_vars as $k => $v) {
            $patterns[] = '(##' . preg_quote($k) . '##)';
            $replace[] = str_replace('$', '/$/', $v);
        }
        $fallback_languages = [];
        $fallback_languages[] = \common\classes\language::get_id($platform_config->get_default_language());
        $fallback_languages[] = \common\helpers\Language::get_default_language_id();
        if ($fallback_languages[1] == $fallback_languages[0]) {
            unset($fallback_languages[1]);
        }
        $list = [];
        $Templates = Orders_Comment_Template::find()->where(['LIKE', 'visibility', ",{$type},"])->and_where(['NOT LIKE', 'hide_for_platforms', ',' . intval($order->info['platform_id']) . ','])->and_where(['NOT LIKE', 'hide_from_admin', ',' . (int) $_SESSION['login_id'] . ','])->and_where(['OR', ['LIKE', 'show_for_admin_group', ',*,'], ['LIKE', 'show_for_admin_group', ',' . (int) $_SESSION['access_levels_id'] . ',']])->and_where(['status' => 1])->order_by(['sort_order' => SORT_ASC])->all();
        foreach ($Templates as $Template) {
            $text_model = $Template->get_texts()->where(['language_id' => $order->info['language_id']])->and_where(['!=', 'comment_template', ''])->one();
            if (!$text_model) {
                $text_model = $Template->get_texts()->where(['IN', 'language_id', $fallback_languages])->and_where(['!=', 'comment_template', ''])->order_by(new Expression("IF(language_id='" . (int) $fallback_languages[0] . "',0,1)"))->one();
            }
            $comment = $text_model->comment_template;
            // {{
            if (count($patterns) > 0) {
                $comment = str_replace('/$/', '$', preg_replace($patterns, $replace, $text_model->comment_template));
            }
            // }}
            $list[] = ['id' => $Template->comment_template_id, 'name' => $text_model->name, 'comment' => $comment];
        }
        return $list;
    }
    public static function render_for($type, $order)
    {
        if (defined('COMMENT_TEMPLATE_STATUS') && COMMENT_TEMPLATE_STATUS == 'False') {
            return '';
        }
        $variants = static::get_comment_template_variants($type, $order);
        if (count($variants) == 0) {
            return '';
        }
        $map_array = [];
        $mapped_statuses = Orders_Status::find()->distinct()->select(['orders_status_id', 'comment_template_id'])->where(['!=', 'comment_template_id', '0'])->as_array()->all();
        foreach ($mapped_statuses as $mapped_status) {
            $map_array[$mapped_status['orders_status_id']] = $mapped_status['comment_template_id'];
        }
        $items = ['' => ''];
        $items_options = [];
        foreach ($variants as $variant) {
            $items[$variant['id']] = $variant['name'];
            $items_options[$variant['id']]['comment'] = $variant['comment'];
        }
        ?>
        <div class="f_row">
            <div class="f_td">
                <label><?php 
        echo TEXT_COMMENT_TEMPLATE_LABEL;
        ?>:</label>
            </div>
            <div class="f_td">
                <?php 
        echo Html::drop_down_list('', '', $items, ['data-templates' => $items_options, 'class' => 'form-control', 'id' => 'commentTemplateSel']);
        ?>
            </div>
        </div>
        <script type="text/javascript">
            $(document).ready(function(){
                var $templateSelector = $('#commentTemplateSel');
                if ( $templateSelector.length==0 ) return;
                <?php 
        if (count($map_array) > 0) {
            ?>

                var mapArray = <?php 
            echo json_encode($map_array);
            ?>;
                $($templateSelector.get(0).form).find('select[name="status"]').on('change',function(){
                    var new_status = $(this).val();
                    if ( mapArray[new_status] ) {
                        $templateSelector.val(mapArray[new_status]);
                        $templateSelector.trigger('change');
                    }
                });
                <?php 
        }
        ?>
                $templateSelector.on('change',function(event){
                    var $select = $(event.target);
                    var templates = $select.data('templates');
                    if (templates[$select.val()]){
                        $select.get(0).form.elements['comments'].value = templates[$select.val()]['comment'];
                    }
                    $select.val('');
                });
            });
        </script>
        <?php 
    }
}