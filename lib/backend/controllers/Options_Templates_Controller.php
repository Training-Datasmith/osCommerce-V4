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
namespace backend\controllers;

use backend\models\Product_Edit\Post_Array_Helper;
use backend\models\Product_Name_Decorator;
use common\helpers\Translation;
use Yii;
use yii\helpers\Array_Helper;
class Options_Templates_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CATALOG', 'BOX_CATALOG_CATEGORIES_PRODUCTS_ATTRIBUTES', 'BOX_CATALOG_CATEGORIES_OPTIONS_TEMPLATES'];
    public function __construct($id, $module = null)
    {
        Translation::init('admin/options-templates');
        parent::__construct($id, $module);
        $this->view->currencies_tabs = null;
        $this->view->use_market_prices = USE_MARKET_PRICES == 'True';
    }
    public function action_index()
    {
        $this->selected_menu = ['catalog', 'product_attributes', 'options-templates'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('options-templates/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="#" class="btn btn-primary" onclick="return options_templateEdit(0)">' . IMAGE_NEW_OPTIONS_TEMPLATE . '</a>';
        $this->view->options_template_table = [['title' => TABLE_HEADING_OPTIONS_TEMPLATE, 'not_important' => 0]];
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
        }
        if (!is_array($messages)) {
            $messages = [];
        }
        $t_id = Yii::$app->request->get('tID', 0);
        return $this->render('index', ['messages' => $messages, 'tID' => $t_id]);
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $keywords = $_GET['search']['value'] ?? null;
        if (($_GET['order'][0]['column'] ?? null) == 0 && isset($_GET['order'][0]['dir'])) {
            $order_col = 'ot.options_templates_name';
            $order_dir = $_GET['order'][0]['dir'] == 'desc' ? SORT_DESC : SORT_ASC;
        } else {
            $order_col = 'ot.options_templates_id';
        }
        $query = \common\models\Options_Templates::find()->alias('ot')->select('ot.options_templates_id, ot.options_templates_name, count(p2ot.products_id) as products_count')->join_with('products2OptionsTemplates p2ot')->and_filter_where(['like', 'ot.options_templates_name', $keywords])->group_by('ot.options_templates_id')->order_by([$order_col => $order_dir]);
        $options_templates_query_numrows = $query->count();
        $query->limit($length)->offset($start);
        $list = $query->as_array()->all();
        $response_list = [];
        foreach ($list as $item) {
            $response_list[] = [$item['options_templates_name'] . tep_draw_hidden_field('id', $item['options_templates_id'], 'class="cell_identify"')];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $options_templates_query_numrows, 'recordsFiltered' => $options_templates_query_numrows, 'data' => $response_list];
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $response;
    }
    public function action_statusactions()
    {
        \common\helpers\Translation::init('admin/options-templates');
        $options_templates_id = (int) Yii::$app->request->post('options_templates_id', 0);
        $this->layout = false;
        $t_info = self::get_obj_by_id($options_templates_id);
        if (!empty($t_info)) {
            echo '<div class="or_box_head">' . $t_info->options_templates_name . '</div>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<a class="btn btn-primary btn-process-order btn-edit" href="' . Yii::$app->url_manager->create_url(['options-templates/attributes', 'options_templates_id' => $options_templates_id]) . '">' . BUTTON_TEMPLATES_ATTRIBUTES . '</a>';
            //echo '<a href="' . Yii::$app->urlManager->createUrl(['options-templates/edit-catalog', 'options_templates_id' => $options_templates_id]) . '" class="btn btn-edit btn-process-order js-open-tree-popup">'.BUTTON_ASSIGN_PRODUCTS.'</a>';
            echo '<button class="btn btn-edit btn-no-margin" onclick="options_templateEdit(' . $options_templates_id . ')">' . IMAGE_EDIT . '</button><button class="btn btn-delete" onclick="options_templateDeleteConfirm(' . $options_templates_id . ')">' . IMAGE_DELETE . '</button>';
            echo '</div>';
        }
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/options-templates');
        $options_templates_id = (int) Yii::$app->request->get('options_templates_id', 0);
        $t_info = self::get_obj_by_id_or_create($options_templates_id);
        echo tep_draw_form('options_template', 'options-templates/save', 'options_templates_id=' . $t_info->options_templates_id, 'post', 'onsubmit="return options_templateSave(' . $t_info->options_templates_id . ');"');
        if ($options_templates_id) {
            echo '<div class="or_box_head">' . TEXT_EDIT_INTRO . '</div>';
        } else {
            echo '<div class="or_box_head">' . TEXT_NEW_INTRO . '</div>';
        }
        echo '<div class="main_row"><div class="main_title">' . TEXT_INFO_OPTIONS_TEMPLATES_NAME . '</div><div class="main_value">' . tep_draw_input_field('options_templates_name', $t_info->options_templates_name) . '</div></div>';
        echo '<div class="btn-toolbar btn-toolbar-order">';
        if ($options_templates_id) {
            echo '<input type="submit" value="' . IMAGE_UPDATE . '" class="btn btn-no-margin"><input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement(' . (int) $t_info->options_templates_id . ')">';
        } else {
            echo '<input type="submit" value="' . IMAGE_NEW . '" class="btn btn-no-margin"><input type="button" value="' . IMAGE_CANCEL . '" class="btn btn-cancel" onclick="resetStatement(' . (int) $t_info->options_templates_id . ')">';
        }
        echo '</div>';
        echo '</form>';
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/options-templates');
        $options_templates_id = intval(Yii::$app->request->get('options_templates_id', 0));
        $options_templates_name = tep_db_prepare_input(Yii::$app->request->post('options_templates_name', ''));
        $obj = self::get_obj_by_id_or_create($options_templates_id);
        if ($obj->is_new_record) {
            $obj->date_added = new \yii\db\Expression('NOW()');
            $action = 'added';
        } else {
            $obj->last_modified = new \yii\db\Expression('NOW()');
            $action = 'updated';
        }
        $obj->options_templates_name = $options_templates_name;
        $obj->save();
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['message' => 'Template ' . $action, 'messageType' => 'alert-success'];
    }
    public function action_confirmdelete()
    {
        $this->layout = false;
        $options_templates_id = (int) Yii::$app->request->post('options_templates_id', 0);
        $t_info = self::get_obj_by_id($options_templates_id);
        if (!empty($t_info)) {
            echo tep_draw_form('options_templates', 'options-templates', \common\helpers\Output::get_all_get_params(['tID', 'action']) . 'dID=' . $t_info->options_templates_id . '&action=deleteconfirm', 'post', 'id="item_delete" onSubmit="return options_templateDelete();"');
            echo '<div class="or_box_head">' . $t_info->options_templates_name . '</div>';
            echo TEXT_DELETE_INTRO . '<br>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button type="submit" class="btn btn-primary btn-no-margin">' . IMAGE_CONFIRM . '</button>';
            echo '<button class="btn btn-cancel" onClick="return resetStatement(' . $options_templates_id . ')">' . IMAGE_CANCEL . '</button>';
            echo tep_draw_hidden_field('options_templates_id', $options_templates_id);
            echo '</div></form>';
        }
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/options-templates');
        $options_templates_id = (int) Yii::$app->request->post('options_templates_id', 0);
        if (!$options_templates_id) {
            return;
        }
        $transaction = \Yii::$app->db->begin_transaction();
        try {
            // delete products -> products_to_options_templates -> products_attributes -> products_attributes_prices -> inventory
            foreach (\common\models\Products2options_Templates::find()->select('products_id')->where(['options_templates_id' => $options_templates_id])->each() as $product) {
                $pid = $product->products_id;
                \common\models\Products_Attributes_Prices::delete_all(['in', 'products_attributes_id', \common\models\Products_Attributes::find()->select('products_attributes_id')->where(['products_id' => $pid])]);
                \common\models\Products_Attributes::delete_all(['products_id' => $pid]);
                /** @var \common\extensions\Inventory\Inventory $ext */
                if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
                    $ext::delete_product($pid);
                }
            }
            \common\models\Products2options_Templates::delete_all(['options_templates_id' => $options_templates_id]);
            // delete options_templates -> options_templates_attributes -> options_templates_attributes_prices
            \common\models\Options_Templates_Attributes_Prices::delete_all(['in', 'options_templates_attributes_id', \common\models\Options_Templates_Attributes::find()->select('options_templates_attributes_id')->where(['options_templates_id' => $options_templates_id])]);
            \common\models\Options_Templates_Attributes::delete_all(['options_templates_id' => $options_templates_id]);
            \common\models\Options_Templates::delete_all(['options_templates_id' => $options_templates_id]);
            $transaction->commit();
            echo 'reset';
        } catch (\Throwable $e) {
            $transaction->roll_back();
            throw $e;
        }
    }
    public function action_attributes()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $currencies = Yii::$container->get('currencies');
        \common\helpers\Translation::init('admin/categories');
        $this->selected_menu = ['catalog', 'product_attributes', 'options-templates'];
        $options_templates_id = (int) Yii::$app->request->get('options_templates_id', 0);
        $t_info = self::get_obj_by_id_check($options_templates_id);
        $this->view->tax_classes = ['0' => TEXT_NONE];
        $tax_classes = \common\models\Tax_Class::find()->select('tax_class_id, tax_class_title')->order_by('tax_class_title')->as_array()->all();
        foreach ($tax_classes as $tax_class) {
            $this->view->tax_classes[$tax_class['tax_class_id']] = $tax_class['tax_class_title'];
        }
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#options_templates_attributes\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('options-templates/attributes'), 'title' => sprintf(HEADING_TITLE_EDIT_ATTRIBUTES, $t_info->options_templates_name)];
        $this->view->heading_title = sprintf(HEADING_TITLE_EDIT_ATTRIBUTES, $t_info->options_templates_name);
        $this->view->use_popup_mode = false;
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->use_popup_mode = true;
        }
        $this->view->groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
        }
        // init price tabs
        $this->view->default_currency = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed()) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'maxHeight' => '400px',
            ];
        }
        $options_list = \common\models\Products_Options::find()->select('products_options_id, products_options_name')->where(['language_id' => $languages_id])->order_by('products_options_sort_order, products_options_name')->as_array();
        $attributes = [];
        foreach ($options_list->each() as $options) {
            $values_list = \common\models\Products_Options_Values::find()->alias('pov')->select('pov.products_options_values_id, pov.products_options_values_name')->inner_join_with(['values2Options v2o' => function ($query) use ($options) {
                return $query->and_on_condition(['v2o.products_options_id' => $options['products_options_id']]);
            }])->where(['pov.language_id' => $languages_id])->order_by('products_options_values_sort_order, products_options_values_name')->as_array()->all();
            $option = [];
            foreach ($values_list as $values) {
                $option[] = ['value' => $values['products_options_values_id'], 'name' => htmlspecialchars($values['products_options_values_name'])];
            }
            $attributes[] = ['id' => $options['products_options_id'], 'label' => htmlspecialchars($options['products_options_name']), 'options' => $option];
        }
        $this->view->attributes = $attributes;
        if (!empty($attributes)) {
            $_tax = \common\helpers\Tax::get_tax_rate(\common\helpers\Tax::get_default_tax_class_id_for_products());
            $selected_attributes = [];
            $query = \common\models\Options_Templates_Attributes::find()->alias('pa')->select('pa.options_templates_attributes_id, po.products_options_id, po.products_options_name, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix, pa.products_attributes_discount_price, pa.products_options_sort_order, pa.product_attributes_one_time, pa.products_attributes_weight, pa.products_attributes_weight_prefix, ' . 'pa.default_option_value, ' . 'pa.products_attributes_units, pa.products_attributes_units_price, pa.products_attributes_filename, pa.products_attributes_maxdays, pa.products_attributes_maxcount')->join_with('productsOptions po', false)->join_with('productsOptionsValues pov', false)->where(['pa.options_templates_id' => $t_info->options_templates_id, 'po.language_id' => $languages_id, 'pov.language_id' => $languages_id])->order_by('po.products_options_sort_order, po.products_options_name, pa.products_options_sort_order, pov.products_options_values_sort_order, pov.products_options_values_name')->as_array();
            foreach ($query->each() as $data) {
                $price0 = \common\helpers\Attributes::get_template_attributes_price($data['options_templates_attributes_id'], $this->view->default_currency, 0);
                if (strpos($data['price_prefix'], '%') !== false) {
                    $gross_price_formatted = $net_price_formatted = \common\helpers\Output::percent($price0, '');
                } else {
                    $net_price_formatted = $currencies->display_price($price0, 0, 1, false);
                    $gross_price_formatted = $currencies->display_price($price0, (float) $_tax, 1, false);
                }
                if (!isset($selected_attributes[$data['products_options_id']])) {
                    $products_options_values = [];
                    $products_options_values[] = ['options_templates_attributes_id' => $data['options_templates_attributes_id'], 'products_options_values_id' => $data['products_options_values_id'], 'products_options_values_name' => $data['products_options_values_name'], 'products_attributes_weight_prefix' => $data['products_attributes_weight_prefix'], 'products_attributes_weight' => $data['products_attributes_weight'], 'default_option_value' => $data['default_option_value'], 'products_file' => $data['products_attributes_filename'], 'products_attributes_maxdays' => $data['products_attributes_maxdays'], 'products_attributes_maxcount' => $data['products_attributes_maxcount'], 'price_prefix' => $data['price_prefix'], 'prices' => \common\helpers\Attributes::get_template_attributes_prices($data['options_templates_attributes_id'], (float) $_tax), 'net_price_formatted' => $net_price_formatted, 'gross_price_formatted' => $gross_price_formatted];
                    $selected_attributes[$data['products_options_id']] = ['products_options_id' => $data['products_options_id'], 'products_options_name' => $data['products_options_name'], 'values' => $products_options_values, 'is_ordered_values' => !empty($data['products_options_sort_order']), 'ordered_value_ids' => ',' . $data['products_options_values_id']];
                } else {
                    $selected_attributes[$data['products_options_id']]['values'][] = ['options_templates_attributes_id' => $data['options_templates_attributes_id'], 'products_options_values_id' => $data['products_options_values_id'], 'products_options_values_name' => $data['products_options_values_name'], 'products_attributes_weight_prefix' => $data['products_attributes_weight_prefix'], 'products_attributes_weight' => $data['products_attributes_weight'], 'default_option_value' => $data['default_option_value'], 'products_file' => $data['products_attributes_filename'], 'products_attributes_maxdays' => $data['products_attributes_maxdays'], 'products_attributes_maxcount' => $data['products_attributes_maxcount'], 'price_prefix' => $data['price_prefix'], 'prices' => \common\helpers\Attributes::get_template_attributes_prices($data['options_templates_attributes_id'], (float) $_tax), 'net_price_formatted' => $net_price_formatted, 'gross_price_formatted' => $gross_price_formatted];
                    $selected_attributes[$data['products_options_id']]['ordered_value_ids'] .= ',' . $data['products_options_values_id'];
                    $selected_attributes[$data['products_options_id']]['is_ordered_values'] = $selected_attributes[$data['products_options_id']]['is_ordered_values'] || !empty($data['products_options_sort_order']);
                }
            }
            foreach ($selected_attributes as $__opt_id => $__opt_data) {
                if ($__opt_data['is_ordered_values']) {
                    $selected_attributes[$__opt_id]['ordered_value_ids'] .= ',';
                } else {
                    $selected_attributes[$__opt_id]['ordered_value_ids'] = '';
                }
            }
            $this->view->selected_attributes = $selected_attributes;
        }
        $products_tax_class_id = \common\helpers\Tax::get_default_tax_class_id_for_products();
        return $this->render('attributes.tpl', ['tInfo' => $t_info, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'products_tax_class_id' => $products_tax_class_id, 'products_id' => $options_templates_id, 'currencies' => $currencies]);
    }
    public function action_attributes_update()
    {
        $currencies = Yii::$container->get('currencies');
        $languages_id = \Yii::$app->settings->get('languages_id');
        $options_templates_id = Yii::$app->request->post('options_templates_id');
        $old_products_id = Yii::$app->request->post('options_templates_id');
        $currencies_ids = $groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $groups = $ext::get_groups_array();
            if (!isset($groups['0'])) {
                $groups['0'] = ['groups_id' => 0];
            }
        }
        $_def_curr_id = $currencies->currencies[DEFAULT_CURRENCY]['id'];
        if (USE_MARKET_PRICES == 'True') {
            foreach ($currencies->currencies as $key => $value) {
                $currencies_ids[$currencies->currencies[$key]['id']] = $currencies->currencies[$key]['id'];
            }
        } else {
            $currencies_ids[$_def_curr_id] = '0';
            /// here is the post and db currencies_id are different.
        }
        $db_attributes = Array_Helper::index(\common\models\Options_Templates_Attributes::find()->where(['options_templates_id' => $options_templates_id])->all(), function ($model) {
            return intval($model->options_id) . '-' . intval($model->options_values_id);
        });
        $all_inventory_ids_array = $all_inventory_uprids_array = $options = $attributes_array = [];
        $products_attributes_id = Yii::$app->request->post('products_attributes_id');
        $products_option_values_sort_order = Yii::$app->request->post('products_option_values_sort_order');
        $products_attributes_weight_prefix = Yii::$app->request->post('products_attributes_weight_prefix');
        $products_attributes_weight = Yii::$app->request->post('products_attributes_weight');
        $default_option_values = Yii::$app->request->post('default_option_value', []);
        $attr_file = Yii::$app->request->post('attr_file');
        $products_attributes_maxdays = Yii::$app->request->post('products_attributes_maxdays');
        $products_attributes_maxcount = Yii::$app->request->post('products_attributes_maxcount');
        $attr_previous_file = Yii::$app->request->post('attr_previous_file');
        $delete_attr_file = Yii::$app->request->post('delete_attr_file');
        $attr_virtual = Yii::$app->request->post('attr_file_switch');
        if (is_array($products_attributes_id)) {
            $_attr_order_array = [];
            foreach ($products_attributes_id as $option_id => $values) {
                $is_virtual_option = \common\helpers\Attributes::is_virtual_option($option_id);
                $_attr_order_array[$option_id] = array_flip(explode(',', strval($products_option_values_sort_order[$option_id])));
                //O_O copied out old code hz
                foreach ($values as $value_id => $non) {
                    $attributes_array[$option_id . '-' . $value_id] = [$option_id, $value_id];
                    if (!$is_virtual_option) {
                        if (isset($options[$option_id])) {
                            // separate array for inventory stuff
                            $options[$option_id][] = $value_id;
                        } else {
                            $options[$option_id] = [];
                            $options[$option_id][] = $value_id;
                        }
                    }
                }
            }
            foreach ($attributes_array as $val) {
                $option_id = $val[0];
                $value_id = $val[1];
                $__attr_order_array = $_attr_order_array[$option_id];
                $sql_data_array = [
                    'price_prefix' => Post_Array_Helper::get_from_post_arrays(['post' => 'inventorypriceprefix_' . $old_products_id . '-' . $option_id . '-' . $value_id, 'dbdef' => '+'], (int) $_def_curr_id),
                    'options_values_price' => Post_Array_Helper::get_from_post_arrays(['post' => 'products_group_price_' . $old_products_id . '-' . $option_id . '-' . $value_id, 'dbdef' => '0'], (int) $_def_curr_id),
                    //(USE_MARKET_PRICES == 'True'?xxxx:0)
                    'products_attributes_weight_prefix' => tep_db_prepare_input(isset($products_attributes_weight_prefix[$option_id][$value_id]) ? $products_attributes_weight_prefix[$option_id][$value_id] : '+'),
                    'default_option_value' => isset($default_option_values[$option_id][$value_id]) ? 1 : 0,
                ];
                if (isset($__attr_order_array[$value_id])) {
                    // for new def value in DB, for old - only if sort order changed.
                    $sql_data_array['products_options_sort_order'] = (int) $__attr_order_array[$value_id];
                }
                if ($ext = \common\helpers\Extensions::is_allowed('TypicalOperatingTemp')) {
                    $_ext_data = $ext::save_template_attribute($option_id, $value_id);
                    if (is_array($_ext_data)) {
                        $sql_data_array = array_merge($sql_data_array, $_ext_data);
                    }
                }
                /*
                        $attr_file = Yii::$app->request->post('attr_file');
                        $products_attributes_maxdays = Yii::$app->request->post('products_attributes_maxdays');
                        $products_attributes_maxcount = Yii::$app->request->post('products_attributes_maxcount');
                        $attr_previous_file = Yii::$app->request->post('attr_previous_file');
                         = Yii::$app->request->post('delete_attr_file');
                */
                if (isset($attr_virtual[$option_id][$value_id]) && $attr_virtual[$option_id][$value_id] == 1) {
                    //virtual
                    //delete old file
                    if (isset($delete_attr_file[$option_id][$value_id]) && $delete_attr_file[$option_id][$value_id] == 'yes' && isset($attr_previous_file[$option_id][$value_id]) && tep_not_null($attr_previous_file[$option_id][$value_id])) {
                        @unlink(DIR_FS_DOWNLOAD . $attr_previous_file[$option_id][$value_id]);
                        $sql_data_array = array_merge($sql_data_array, ['products_attributes_filename' => '', 'products_attributes_maxdays' => 0, 'products_attributes_maxcount' => 0]);
                    }
                    //save new
                    if (isset($attr_file[$option_id][$value_id]) && tep_not_null($attr_file[$option_id][$value_id]) && $attr_file[$option_id][$value_id] != 'none') {
                        $tmp_name = \Yii::get_alias('@webroot');
                        $tmp_name .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                        $tmp_name .= $attr_file[$option_id][$value_id];
                        $new_name = DIR_FS_DOWNLOAD . $attr_file[$option_id][$value_id];
                        copy($tmp_name, $new_name);
                        @unlink($tmp_name);
                        $sql_data_array = array_merge($sql_data_array, ['products_attributes_weight' => 0, 'products_attributes_filename' => tep_db_prepare_input($attr_file[$option_id][$value_id]), 'products_attributes_maxdays' => isset($products_attributes_maxdays[$option_id][$value_id]) && (int) $products_attributes_maxdays[$option_id][$value_id] > 0 ? (int) $products_attributes_maxdays[$option_id][$value_id] : 0, 'products_attributes_maxcount' => isset($products_attributes_maxcount[$option_id][$value_id]) && (int) $products_attributes_maxcount[$option_id][$value_id] > 0 ? (int) $products_attributes_maxcount[$option_id][$value_id] : 0]);
                    } else {
                        $sql_data_array = array_merge($sql_data_array, ['products_attributes_maxdays' => isset($products_attributes_maxdays[$option_id][$value_id]) && (int) $products_attributes_maxdays[$option_id][$value_id] > 0 ? (int) $products_attributes_maxdays[$option_id][$value_id] : 0, 'products_attributes_maxcount' => isset($products_attributes_maxcount[$option_id][$value_id]) && (int) $products_attributes_maxcount[$option_id][$value_id] > 0 ? (int) $products_attributes_maxcount[$option_id][$value_id] : 0]);
                    }
                } else {
                    $sql_data_array = array_merge($sql_data_array, ['products_attributes_weight' => tep_db_prepare_input(isset($products_attributes_weight[$option_id][$value_id]) ? $products_attributes_weight[$option_id][$value_id] : 0), 'products_attributes_filename' => '', 'products_attributes_maxdays' => 0, 'products_attributes_maxcount' => 0]);
                }
                //if (is_array($sql_data_array['options_values_price'])) { $sql_data_array['options_values_price'] = $sql_data_array['options_values_price'][0]; } // Kostyl'
                $attr_key = (int) $option_id . '-' . (int) $value_id;
                if (isset($db_attributes[$attr_key])) {
                    $options_templates_attribute = $db_attributes[$attr_key];
                    unset($db_attributes[$attr_key]);
                } else {
                    $options_templates_attribute = new \common\models\Options_Templates_Attributes(['options_templates_id' => $options_templates_id, 'options_id' => (int) $option_id, 'options_values_id' => (int) $value_id]);
                    $options_templates_attribute->load_default_values();
                }
                yii_setup_model($options_templates_attribute, $sql_data_array);
                //                $OptionsTemplatesAttribute->setAttributes($sql_data_array,false);
                $options_templates_attribute->save(false);
                $products_attributes_id = $options_templates_attribute->options_templates_attributes_id;
                ///group prices
                if (true) {
                    $db_prices = Array_Helper::index($options_templates_attribute->get_prices()->all(), function ($model) {
                        return $model->groups_id . '_' . $model->currencies_id;
                    });
                    if (USE_MARKET_PRICES == 'True' || \common\helpers\Extensions::is_customer_groups_allowed()) {
                        foreach ($currencies_ids as $post_currencies_id => $currencies_id) {
                            foreach ($groups as $groups_id => $non) {
                                $sql_data_array = ['attributes_group_price' => tep_db_prepare_input(Post_Array_Helper::get_from_post_arrays(['db' => 'attributes_group_price', 'dbdef' => $groups_id == 0 ? 0 : -2, 'post' => 'products_group_price_' . $old_products_id . '-' . $option_id . '-' . $value_id, 'f' => ['self', 'defGroupPrice']], $post_currencies_id, $groups_id))];
                                $save_currency_id = USE_MARKET_PRICES == 'True' ? tep_db_prepare_input($post_currencies_id) : 0;
                                $db_key = (int) $groups_id . '_' . (int) $save_currency_id;
                                if (!isset($db_prices[$db_key])) {
                                    $price_model = new \common\models\Options_Templates_Attributes_Prices(['options_templates_attributes_id' => $products_attributes_id, 'groups_id' => intval($groups_id), 'currencies_id' => $save_currency_id]);
                                    $price_model->load_default_values();
                                    yii_setup_model($price_model, $sql_data_array);
                                    //$priceModel->setAttributes($sql_data_array, false);
                                    $price_model->save(false);
                                } else {
                                    yii_setup_model($db_prices[$db_key], $sql_data_array);
                                    //$db_prices[$db_key]->setAttributes($sql_data_array, false);
                                    $db_prices[$db_key]->save(false);
                                    unset($db_prices[$db_key]);
                                }
                            }
                        }
                    }
                    foreach ($db_prices as $not_updated) {
                        $not_updated->delete();
                    }
                }
            }
        }
        foreach ($db_attributes as $not_updated) {
            $not_updated->delete();
        }
        //        $query = tep_db_query("select products_id from " . TABLE_PRODUCTS_TO_OPTIONS_TEMPLATES . " where options_templates_id = '" . tep_db_input($options_templates_id) . "'");
        //        while ($data = tep_db_fetch_array($query)) {
        //            self::copy_product_attributes_from_options_template($options_templates_id, $data['products_id']);
        //        }
        if (Yii::$app->request->is_ajax) {
            //          $this->layout = false;
        } else {
            return $this->redirect(Yii::$app->url_manager->create_url(['options-templates/index', 'tID' => $options_templates_id]));
        }
    }
    public function action_new_attribute()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $options_templates_id = (int) Yii::$app->request->post('options_templates_id');
        $products_id = $options_templates_id;
        /*arrays of new options & values */
        $products_options_ids = array_unique(explode(',', Yii::$app->request->post('products_options_id')));
        $products_options_values_ids = array_unique(explode(',', Yii::$app->request->post('products_options_values_id')));
        foreach ($products_options_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_ids[$k]);
            } else {
                $products_options_ids[$k] = intval($v);
            }
        }
        foreach ($products_options_values_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_values_ids[$k]);
            } else {
                $products_options_values_ids[$k] = intval($v);
            }
        }
        $this->view->groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
        }
        $ret = [];
        $currencies = Yii::$container->get('currencies');
        /// re-arrange data arrays for design templates
        // init price tabs
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed() && count($this->view->groups) > 0) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'maxHeight' => '400px',
            ];
        }
        $values_query = \common\models\Products_Options_Values::find()->alias('pov')->select('p2p.products_options_id, pov.products_options_values_id, pov.products_options_values_name')->inner_join_with('values2Options p2p', false)->where(['p2p.products_options_id' => $products_options_ids, 'pov.products_options_values_id' => $products_options_values_ids, 'pov.language_id' => $languages_id])->order_by('pov.products_options_values_sort_order, pov.products_options_values_name')->as_array();
        foreach ($values_query->each() as $values) {
            $values['net_price_formatted'] = $currencies->display_price(0, 0, 1, false);
            $values['gross_price_formatted'] = $currencies->display_price(0, 0, 1, false);
            $option[0] = $values;
            $is_virtual_option = \common\helpers\Attributes::is_virtual_option($values['products_options_id']);
            if (true) {
                $ret[] = ['data' => $this->render('product-new-attribute.tpl', ['options' => $option, 'products_id' => $products_id, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'currencies' => $currencies, 'products_options_id' => $values['products_options_id']]), 'is_virtual_option' => $is_virtual_option, 'products_options_values_id' => $values['products_options_values_id'], 'products_options_id' => $values['products_options_id']];
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_new_option()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/categories');
        $this->layout = false;
        $options_templates_id = (int) Yii::$app->request->post('options_templates_id');
        $products_id = $options_templates_id;
        $products_options_ids = array_unique(explode(',', Yii::$app->request->post('products_options_id')));
        $products_options_values_ids = array_unique(explode(',', Yii::$app->request->post('products_options_values_id')));
        foreach ($products_options_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_ids[$k]);
            } else {
                $products_options_ids[$k] = intval($v);
            }
        }
        foreach ($products_options_values_ids as $k => $v) {
            if (intval($v) == 0) {
                unset($products_options_values_ids[$k]);
            } else {
                $products_options_values_ids[$k] = intval($v);
            }
        }
        $this->view->groups = [];
        if ($ext = \common\helpers\Acl::check_extension_allowed('UserGroups', 'allowed')) {
            $ext::get_groups();
            //fills in $this->view->groups
        }
        $this->view->images = [];
        $ret = [];
        $attributes = [];
        $products_options_id = false;
        $currencies = Yii::$container->get('currencies');
        /// re-arrange data arrays for design templates
        // init price tabs
        $this->view->price_tabs = $this->view->price_tabparams = [];
        ////currencies tabs and params
        if ($this->view->use_market_prices) {
            $this->view->currencies_tabs = [];
            foreach ($currencies->currencies as $value) {
                $value['def_data'] = ['currencies_id' => $value['id']];
                $value['title'] = $value['symbol_left'] . ' ' . $value['code'] . ' ' . $value['symbol_right'];
                $this->view->currencies_tabs[] = $value;
            }
            $this->view->price_tabs[] = $this->view->currencies_tabs;
            $this->view->price_tabparams[] = ['cssClass' => 'tabs-currencies', 'tabs_type' => 'hTab'];
        }
        //// groups tabs and params
        if (\common\helpers\Extensions::is_customer_groups_allowed() && count($this->view->groups) > 0) {
            $this->view->groups_m = array_merge([['groups_id' => 0, 'groups_name' => TEXT_MAIN]], $this->view->groups);
            $tmp = [];
            foreach ($this->view->groups_m as $value) {
                $value['id'] = $value['groups_id'];
                $value['title'] = $value['groups_name'];
                $value['def_data'] = ['groups_id' => $value['id']];
                unset($value['groups_name']);
                unset($value['groups_id']);
                $tmp[] = $value;
            }
            $this->view->price_tabs[] = $tmp;
            unset($tmp);
            $this->view->price_tabparams[] = [
                'cssClass' => 'tabs-groups',
                // add to tabs and tab-pane
                //'callback' => 'productPriceBlock', // smarty function which will be called before children tabs , data passed as params params
                'callback_bottom' => '',
                'tabs_type' => 'lTab',
                'maxHeight' => '400px',
            ];
        }
        $values_query = \common\models\Products_Options_Values::find()->alias('pov')->select('po.products_options_id, po.products_options_name, pov.products_options_values_id, pov.products_options_values_name')->inner_join_with('productsOptions po', false)->where(['pov.products_options_values_id' => $products_options_values_ids, 'pov.language_id' => $languages_id, 'po.products_options_id' => $products_options_ids, 'po.language_id' => $languages_id])->order_by('po.products_options_name, po.products_options_id, pov.products_options_values_sort_order, pov.products_options_values_name, pov.products_options_values_id')->as_array();
        foreach ($values_query->each() as $values) {
            if ($products_options_id != $values['products_options_id']) {
                if ($products_options_id) {
                    $is_virtual_option = \common\helpers\Attributes::is_virtual_option($products_options_id);
                    if (true) {
                        $ret[] = ['data' => $this->render('product-new-option.tpl', ['products_id' => $products_id, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'currencies' => $currencies, 'attributes' => $attributes]), 'is_virtual_option' => $is_virtual_option, 'products_options_id' => $attributes[0]['products_options_id'], 'products_options_values_id' => $attributes[0]['values'][0]['products_options_values_id']];
                    }
                    $attributes = [];
                }
                $products_options_id = $values['products_options_id'];
                $attributes[0] = ['is_virtual_option' => \common\helpers\Attributes::is_virtual_option($products_options_id), 'products_options_id' => $values['products_options_id'], 'net_price_formatted' => $currencies->display_price(0, 0, 1, false), 'gross_price_formatted' => $currencies->display_price(0, 0, 1, false), 'products_options_name' => htmlspecialchars($values['products_options_name']), 'values' => []];
            }
            $attributes[0]['values'][] = ['products_options_values_id' => $values['products_options_values_id'], 'net_price_formatted' => $currencies->display_price(0, 0, 1, false), 'gross_price_formatted' => $currencies->display_price(0, 0, 1, false), 'products_options_values_name' => htmlspecialchars($values['products_options_values_name'])];
        }
        if ($products_options_id) {
            $is_virtual_option = \common\helpers\Attributes::is_virtual_option($products_options_id);
            $ret[] = ['data' => $this->render('product-new-option.tpl', ['products_id' => $products_id, 'default_currency' => $currencies->currencies[DEFAULT_CURRENCY], 'currencies' => $currencies, 'attributes' => $attributes]), 'is_virtual_option' => $is_virtual_option, 'products_options_id' => $attributes[0]['products_options_id'], 'products_options_values_id' => $attributes[0]['values'][0]['products_options_values_id']];
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return $ret;
    }
    public function action_edit_catalog()
    {
        \common\helpers\Translation::init('admin/options-templates');
        $options_templates_id = (int) Yii::$app->request->get('options_templates_id');
        $this->layout = false;
        $assigned = $this->get_assigned_catalog($options_templates_id, true);
        $tree_init_data = $this->load_tree_slice($options_templates_id, 0);
        foreach ($tree_init_data as $_idx => $_data) {
            if (isset($assigned[$_data['key']])) {
                $tree_init_data[$_idx]['selected'] = true;
            }
        }
        $selected_data = json_encode($assigned);
        return $this->render('edit-catalog.tpl', ['selected_data' => $selected_data, 'tree_data' => $tree_init_data, 'tree_server_url' => Yii::$app->url_manager->create_url(['options-templates/load-tree', 'options_templates_id' => $options_templates_id]), 'tree_server_save_url' => Yii::$app->url_manager->create_url(['options-templates/update-catalog-selection', 'options_templates_id' => $options_templates_id])]);
    }
    private function get_assigned_catalog($options_templates_id, $validate = false)
    {
        $assigned = [];
        $get_assigned_r = \common\models\Products2options_Templates::find()->alias('p2ot')->select('p2ot.products_id AS id, p2c.categories_id as cid')->left_join(\common\models\Products2Categories::table_name() . ' p2c', 'p2ot.products_id=p2c.products_id')->where(['p2ot.options_templates_id' => intval($options_templates_id)])->as_array();
        if ($validate) {
            $get_assigned_r->inner_join(\common\models\Products::table_name() . ' p', 'p.products_id=p2ot.products_id AND p.products_status=1 ' . \common\helpers\Product::get_sql_product_restrictions(['p', 'pd', 's', 'sp', 'pp']))->inner_join(\common\models\Products_Description::table_name() . ' pd', 'pd.products_id=p2ot.products_id AND pd.language_id=' . \Yii::$app->settings->get('languages_id') . ' AND pd.platform_id=' . intval(\common\classes\platform::default_id()));
        }
        foreach ($get_assigned_r->each() as $_assigned) {
            $_key = 'p' . (int) $_assigned['id'] . '_' . $_assigned['cid'];
            $assigned[$_key] = $_key;
        }
        return $assigned;
    }
    private function load_tree_slice($options_templates_id, $category_id)
    {
        $tree_init_data = [];
        $languages_id = (int) \Yii::$app->settings->get('languages_id');
        $get_categories_r = \common\models\Categories::find()->alias('c')->select("CONCAT('c',c.categories_id) as `key`, cd.categories_name as title")->inner_join_with(['descriptions cd' => function ($query) use ($languages_id, $category_id) {
            $query->and_on_condition(['cd.language_id' => $languages_id, 'cd.affiliate_id' => 0, 'c.parent_id' => (int) $category_id]);
        }], false)->order_by('c.sort_order, cd.categories_name')->as_array();
        foreach ($get_categories_r->each() as $_categories) {
            //$_categories['parent'] = (int)$category_id;
            $_categories['folder'] = true;
            $_categories['lazy'] = true;
            $_categories['selected'] = 0;
            $tree_init_data[] = $_categories;
        }
        $get_products_r = \common\models\Products::find()->alias('p')->select("SELECT concat('p',p.products_id,'_',p2c.categories_id) AS `key`, " . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS title ')->w_description('pd')->inner_join_with('categoriesList p2c')->where(['p2c.categories_id' => (int) $category_id])->order_by('p.sort_order, pd.products_name')->as_array();
        $active = $search = null;
        // php8: never assigned here
        $get_products_r = tep_db_query("SELECT concat('p',p.products_id,'_',p2c.categories_id) AS `key`, " . Product_Name_Decorator::instance()->listing_query_expression('pd', '') . ' AS title ' . 'from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_PRODUCTS_TO_CATEGORIES . ' p2c, ' . TABLE_PRODUCTS . ' p ' . "WHERE pd.products_id=p.products_id and pd.language_id='" . $languages_id . "' and pd.platform_id='" . intval(\common\classes\platform::default_id()) . "' and p2c.products_id=p.products_id and p2c.categories_id='" . (int) $category_id . "' " . ($active ? ' AND p.products_status=1 ' . \common\helpers\Product::get_sql_product_restrictions(['p', 'pd', 's', 'sp', 'pp']) . ' ' : '') . (tep_not_null($search) ? " and (pd.products_name like '%{$search}%' or pd.products_internal_name like '%{$search}%') " : '') . 'order by p.sort_order, pd.products_name');
        if (tep_db_num_rows($get_products_r) > 0) {
            while ($_product = tep_db_fetch_array($get_products_r)) {
                //$_product['parent'] = (int)$category_id;
                $_product['selected'] = !!$_product['selected'];
                $tree_init_data[] = $_product;
            }
        }
        return $tree_init_data;
    }
    private function get_category_children(&$children, $options_templates_id, $categories_id)
    {
        if (!is_array($children)) {
            $children = [];
        }
        foreach ($this->load_tree_slice($options_templates_id, $categories_id) as $item) {
            $key = $item['key'];
            $children[] = $key;
            if ($item['folder']) {
                $this->get_category_children($children, $options_templates_id, intval(substr($item['key'], 1)));
            }
        }
    }
    public function action_load_tree()
    {
        \common\helpers\Translation::init('admin/options-templates');
        $this->layout = false;
        $options_templates_id = (int) Yii::$app->request->get('options_templates_id');
        $do = Yii::$app->request->post('do', '');
        $response_data = [];
        if ($do == 'missing_lazy') {
            $category_id = Yii::$app->request->post('id');
            $selected = Yii::$app->request->post('selected');
            $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
            $selected_data = json_decode($req_selected_data, true);
            if (!is_array($selected_data)) {
                $selected_data = json_decode($selected_data, true);
            }
            if (substr($category_id, 0, 1) == 'c') {
                $category_id = intval(substr($category_id, 1));
            }
            $response_data['tree_data'] = $this->load_tree_slice($options_templates_id, $category_id);
            foreach ($response_data['tree_data'] as $_idx => $_data) {
                $response_data['tree_data'][$_idx]['selected'] = isset($selected_data[$_data['key']]);
            }
            $response_data = $response_data['tree_data'];
        }
        if ($do == 'update_selected') {
            $id = Yii::$app->request->post('id');
            $selected = Yii::$app->request->post('selected');
            $select_children = Yii::$app->request->post('select_children');
            $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
            $selected_data = json_decode($req_selected_data, true);
            if (!is_array($selected_data)) {
                $selected_data = json_decode($selected_data, true);
            }
            if (substr($id, 0, 1) == 'p') {
                list($ppid, $cat_id) = explode('_', $id, 2);
                if ($selected) {
                    // check parent categories
                    $parent_ids = [(int) $cat_id];
                    \common\helpers\Categories::get_parent_categories($parent_ids, $parent_ids[0], false);
                    foreach ($parent_ids as $parent_id) {
                        if (!isset($selected_data['c' . (int) $parent_id])) {
                            $response_data['update_selection']['c' . (int) $parent_id] = true;
                            $selected_data['c' . (int) $parent_id] = 'c' . (int) $parent_id;
                        }
                    }
                    if (!isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = true;
                        $selected_data[$id] = $id;
                    }
                } else if (isset($selected_data[$id])) {
                    $response_data['update_selection'][$id] = false;
                    unset($selected_data[$id]);
                }
            } elseif (substr($id, 0, 1) == 'c') {
                $cat_id = (int) substr($id, 1);
                if ($selected) {
                    $parent_ids = [(int) $cat_id];
                    \common\helpers\Categories::get_parent_categories($parent_ids, $parent_ids[0], false);
                    foreach ($parent_ids as $parent_id) {
                        if (!isset($selected_data['c' . (int) $parent_id])) {
                            $response_data['update_selection']['c' . (int) $parent_id] = true;
                            $selected_data['c' . (int) $parent_id] = 'c' . (int) $parent_id;
                        }
                    }
                    if ($select_children) {
                        $children = [];
                        $this->get_category_children($children, $options_templates_id, $cat_id);
                        foreach ($children as $child_key) {
                            if (!isset($selected_data[$child_key])) {
                                $response_data['update_selection'][$child_key] = true;
                                $selected_data[$child_key] = $child_key;
                            }
                        }
                    }
                    if (!isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = true;
                        $selected_data[$id] = $id;
                    }
                } else {
                    $children = [];
                    $this->get_category_children($children, $options_templates_id, $cat_id);
                    foreach ($children as $child_key) {
                        if (isset($selected_data[$child_key])) {
                            $response_data['update_selection'][$child_key] = false;
                            unset($selected_data[$child_key]);
                        }
                    }
                    if (isset($selected_data[$id])) {
                        $response_data['update_selection'][$id] = false;
                        unset($selected_data[$id]);
                    }
                }
            }
            $response_data['selected_data'] = $selected_data;
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $response_data;
    }
    public function action_update_catalog_selection()
    {
        \common\helpers\Translation::init('admin/platforms');
        $this->layout = false;
        $options_templates_id = Yii::$app->request->get('options_templates_id');
        $overwrite_existing_attributes = tep_db_prepare_input(Yii::$app->request->post('overwrite_existing_attributes'));
        $req_selected_data = tep_db_prepare_input(Yii::$app->request->post('selected_data'));
        $selected_data = json_decode($req_selected_data, true);
        if (!is_array($selected_data)) {
            $selected_data = json_decode($selected_data, true);
        }
        if (!isset($selected_data['c0'])) {
            $selected_data['c0'] = 'c0';
        }
        $assigned = $this->get_assigned_catalog($options_templates_id);
        $assigned_products = [];
        foreach ($assigned as $assigned_key) {
            if (substr($assigned_key, 0, 1) == 'p') {
                $pid = intval(substr($assigned_key, 1));
                $assigned_products[$pid] = $pid;
                unset($assigned[$assigned_key]);
            }
        }
        if (is_array($selected_data)) {
            $selected_products = [];
            foreach ($selected_data as $selection) {
                if (substr($selection, 0, 1) == 'p') {
                    $pid = intval(substr($selection, 1));
                    $selected_products[$pid] = $pid;
                    continue;
                }
                /*
                               if (isset($assigned[$selection])) {
                                   unset($assigned[$selection]);
                               } else {
                                   if (substr($selection, 0, 1) == 'c') {
                                       $cat_id = (int) substr($selection, 1);
                                       tep_db_perform(TABLE_PLATFORMS_CATEGORIES, array(
                                           'platform_id' => $platform_id,
                                           'categories_id' => $cat_id,
                                       ));
                                       unset($assigned[$selection]);
                                   }
                               }
                */
            }
            foreach ($selected_products as $pid) {
                if (isset($assigned_products[$pid])) {
                    unset($assigned_products[$pid]);
                } else if ($overwrite_existing_attributes == 'true' || !\common\helpers\Attributes::has_product_attributes($pid)) {
                    tep_db_perform(TABLE_PRODUCTS_TO_OPTIONS_TEMPLATES, ['options_templates_id' => $options_templates_id, 'products_id' => $pid]);
                    self::copy_product_attributes_from_options_template($options_templates_id, $pid);
                }
            }
        }
        /*
               foreach ($assigned as $clean_key) {
                   if (substr($clean_key, 0, 1) == 'c') {
                       $cat_id = (int) substr($clean_key, 1);
                       if ($cat_id == 0)
                           continue;
                       tep_db_query(
                               "DELETE FROM " . TABLE_PLATFORMS_CATEGORIES . " " .
                               "WHERE platform_id ='" . $platform_id . "' AND categories_id = '" . $cat_id . "' "
                       );
                       unset($assigned[$clean_key]);
                   }
               }
        */
        foreach ($assigned_products as $assigned_product_id) {
            tep_db_query('delete from ' . TABLE_PRODUCTS_TO_OPTIONS_TEMPLATES . " where options_templates_id = '" . (int) $options_templates_id . "' and products_id = '" . (int) $assigned_product_id . "'");
            $query = tep_db_query('select products_attributes_id from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $assigned_product_id . "'");
            while ($data = tep_db_fetch_array($query)) {
                tep_db_query('delete from ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES . " where products_attributes_id = '" . (int) $data['products_attributes_id'] . "'");
            }
            tep_db_query('delete from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $assigned_product_id . "'");
            /** @var \common\extensions\Inventory\Inventory $ext */
            if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
                $ext::delete_product((int) $assigned_product_id);
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['status' => 'ok'];
    }
    public static function copy_product_attributes_from_options_template($options_templates_id, $products_id)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $query = tep_db_query('select products_attributes_id from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $products_id . "'");
        while ($data = tep_db_fetch_array($query)) {
            tep_db_query('delete from ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES . " where products_attributes_id = '" . (int) $data['products_attributes_id'] . "'");
        }
        tep_db_query('delete from ' . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int) $products_id . "'");
        $product_attribute_query = tep_db_query('select * from ' . TABLE_OPTIONS_TEMPLATES_ATTRIBUTES . " where options_templates_id = '" . (int) $options_templates_id . "'");
        while ($product_attribute = tep_db_fetch_array($product_attribute_query)) {
            $str = 'insert into ' . TABLE_PRODUCTS_ATTRIBUTES . ' set ';
            foreach ($product_attribute as $key => $value) {
                if ($key != 'options_templates_attributes_id') {
                    if ($key == 'options_templates_id') {
                        $key = 'products_id';
                        $value = $products_id;
                    }
                    if (is_null($value)) {
                        $str .= ' ' . $key . ' = NULL, ';
                    } else {
                        $str .= ' ' . $key . " = '" . tep_db_input($value) . "', ";
                    }
                }
            }
            $str = substr($str, 0, strlen($str) - 2);
            tep_db_query($str);
            $products_attributes_id = tep_db_insert_id();
            $product_attribute_prices_query = tep_db_query('select * from ' . TABLE_OPTIONS_TEMPLATES_ATTRIBUTES_PRICES . " where options_templates_attributes_id = '" . (int) $product_attribute['options_templates_attributes_id'] . "'");
            while ($product_attribute_prices = tep_db_fetch_array($product_attribute_prices_query)) {
                $str = 'insert into ' . TABLE_PRODUCTS_ATTRIBUTES_PRICES . ' set ';
                foreach ($product_attribute_prices as $key => $value) {
                    if ($key != 'options_templates_attributes_id') {
                        $str .= ' ' . $key . " = '" . tep_db_input($value) . "', ";
                    } else {
                        $str .= " products_attributes_id = '" . $products_attributes_id . "', ";
                    }
                }
                $str = substr($str, 0, strlen($str) - 2);
                tep_db_query($str);
            }
        }
        /** @var \common\extensions\Inventory\Inventory $ext */
        if ($ext = \common\helpers\Extensions::is_allowed('Inventory')) {
            $total_comb = 1;
            $count_values = [];
            $inventory_uprids_array = [];
            $products_options_array = [];
            $products_options_name_query = tep_db_query('select distinct popt.products_options_id, popt.products_options_name from ' . TABLE_PRODUCTS_OPTIONS . ' popt, ' . TABLE_PRODUCTS_ATTRIBUTES . " patrib where patrib.products_id = '" . (int) $products_id . "' and patrib.options_id = popt.products_options_id and popt.language_id = '" . (int) $languages_id . "' order by popt.products_options_sort_order");
            while ($products_options_name = tep_db_fetch_array($products_options_name_query)) {
                $options_id = $products_options_name['products_options_id'];
                if (\common\helpers\Attributes::is_virtual_option($options_id)) {
                    continue;
                }
                $products_options_array[$options_id] = [];
                $count_values[$options_id] = 0;
                $products_options_query = tep_db_query('select pa.products_attributes_id, pov.products_options_values_id, pov.products_options_values_name, pa.options_values_price, pa.price_prefix from ' . TABLE_PRODUCTS_ATTRIBUTES . ' pa, ' . TABLE_PRODUCTS_OPTIONS_VALUES . " pov where pa.products_id = '" . (int) $products_id . "' and pa.options_id = '" . (int) $options_id . "' and pa.options_values_id = pov.products_options_values_id and pov.language_id = '" . (int) $languages_id . "' order by pa.products_options_sort_order");
                while ($products_options = tep_db_fetch_array($products_options_query)) {
                    $products_options['options_values_price'] = \common\helpers\Attributes::get_options_values_price($products_options['products_attributes_id']);
                    $products_options_array[$options_id][] = ['id' => $products_options['products_options_values_id'], 'text' => $products_options['products_options_values_name']];
                    $products_options_array[$options_id][sizeof($products_options_array[$options_id]) - 1]['price'] = $products_options['price_prefix'] != '-' ? $products_options['options_values_price'] : -$products_options['options_values_price'];
                    $count_values[$options_id]++;
                }
                $total_comb *= $count_values[$options_id];
            }
            if ($total_comb > 1) {
                $products_name = \common\helpers\Product::get_products_name($products_id);
                $products_price = \common\helpers\Product::get_products_price($products_id);
                for ($i = 0; $i < $total_comb; $i++) {
                    $num = $i;
                    $comb_name = '';
                    $comb_price = 0;
                    $comb_arr = [];
                    foreach ($products_options_array as $id => $array) {
                        $k = $num % $count_values[$id];
                        $comb_name .= ' ' . $array[$k]['text'];
                        $comb_price += $array[$k]['price'];
                        $comb_arr[$id] = $array[$k]['id'];
                        $num = (int) ($num / $count_values[$id]);
                    }
                    $uprid = \common\helpers\Inventory::normalize_id(\common\helpers\Inventory::get_uprid($products_id, $comb_arr));
                    $check_inventory = tep_db_fetch_array(tep_db_query('select inventory_id from ' . TABLE_INVENTORY . " where prid = '" . (int) $products_id . "' and products_id = '" . tep_db_input($uprid) . "'"));
                    if (!$check_inventory['inventory_id']) {
                        tep_db_query('insert into ' . TABLE_INVENTORY . " set products_id = '" . tep_db_input($uprid) . "', prid = '" . (int) $products_id . "', products_name = '" . tep_db_input($products_name . $comb_name) . "', inventory_price = '" . tep_db_input(abs($comb_price)) . "', price_prefix = '" . tep_db_input($comb_price < 0 ? '-' : '+') . "', inventory_full_price = '" . tep_db_input($products_price + $comb_price) . "'");
                    }
                    $inventory_uprids_array[] = $uprid;
                }
            }
            tep_db_query('delete from ' . TABLE_INVENTORY . " where prid = '" . (int) $products_id . "' and products_id not in ('" . implode("','", $inventory_uprids_array) . "')");
        }
    }
    private static function get_obj_by_id($id)
    {
        if ($id > 0) {
            return \common\models\Options_Templates::find_one(['options_templates_id' => $id]);
        }
    }
    private static function get_obj_by_id_check($id)
    {
        $res = self::get_obj_by_id($id);
        \common\helpers\Assert_User::assert_not_null($res, "There is not template with id '{$id}'");
        return $res;
    }
    private static function get_obj_by_id_or_create($id)
    {
        return $id > 0 ? self::get_obj_by_id_check($id) : new \common\models\Options_Templates();
    }
}