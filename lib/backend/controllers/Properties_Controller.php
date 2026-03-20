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

use common\helpers\Seo;
use Yii;
use yii\helpers\Array_Helper;
class Properties_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CATALOG', 'BOX_CATALOG_PROPERTIES'];
    private $properties_types_array = [];
    public function __construct($id, $module = null)
    {
        global $language;
        \common\helpers\Translation::init('admin/properties');
        \common\helpers\Translation::init('admin/main');
        $this->properties_types_array[''] = TEXT_PLEASE_CHOOSE;
        $this->properties_types_array['text'] = TEXT_TEXT;
        $this->properties_types_array['number'] = TEXT_NUMBER;
        $this->properties_types_array['interval'] = TEXT_NUMBER_INTERVAL;
        $this->properties_types_array['flag'] = TEXT_PR_FLAG;
        $this->properties_types_array['file'] = TEXT_PR_FILE;
        parent::__construct($id, $module);
    }
    protected function get_possible_properties_flags()
    {
        return ['display_product' => ['label' => defined('TEXT_PRODUCT_INFO') ? TEXT_PRODUCT_INFO : '', 'show_on_listing' => true], 'display_listing' => ['label' => defined('TEXT_LISTING') ? TEXT_LISTING : '', 'show_on_listing' => true], 'display_filter' => ['label' => defined('TEXT_FILTER') ? TEXT_FILTER : '', 'show_on_listing' => true], 'display_search' => ['label' => defined('TEXT_SEARCH') ? TEXT_SEARCH : ''], 'display_compare' => ['label' => defined('TEXT_COMPARE') ? TEXT_COMPARE : ''], 'products_groups' => ['label' => defined('TEXT_PRODUCTS_GROUPS') ? TEXT_PRODUCTS_GROUPS : '', 'show_on_listing' => true]];
    }
    public function action_index()
    {
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('properties/index'), 'title' => TEXT_PROPERTIES_TITLE];
        $this->view->heading_title = TEXT_PROPERTIES_TITLE;
        $this->selected_menu = ['catalog', 'properties'];
        $p_id = Yii::$app->request->get('pID', 0);
        $par_id = Yii::$app->request->get('parID', 0);
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['properties/edit', 'parID' => $par_id]) . '" class="btn btn-primary"><i class="icon-file-text"></i>' . ucwords(TEXT_CREATE_NEW_PROPERTY) . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['properties/category', 'parID' => $par_id]) . '" class="btn btn-primary addprbtn"><i class="icon-folder-close-alt"></i>' . ucwords(TEXT_CREATE_NEW_CATEGORY) . '</a>';
        $this->view->property_table = [['title' => TABLE_HEADING_CATEGORIES_PROPERTIES, 'not_important' => 0]];
        foreach ($this->get_possible_properties_flags() as $prop_config) {
            if (!isset($prop_config['show_on_listing']) || !$prop_config['show_on_listing']) {
                continue;
            }
            $this->view->property_table[] = ['title' => $prop_config['label'], 'not_important' => 0];
        }
        $this->view->property_table[] = ['title' => TABLE_HEADING_PROPERTIES_TYPE, 'not_important' => 0];
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
        }
        if (!is_array($messages)) {
            $messages = [];
        }
        return $this->render('index', ['messages' => $messages, 'pID' => $p_id, 'parID' => $par_id]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $form_filter = Yii::$app->request->get('filter', []);
        parse_str($form_filter, $filter);
        $search = '';
        if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
            $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            $search .= " and (properties_name like '%" . $keywords . "%' or properties_name_alt like '%" . $keywords . "%')";
        }
        $listing_flags = [];
        $select_columns = '';
        foreach ($this->get_possible_properties_flags() as $prop_field => $prop_config) {
            if (!isset($prop_config['show_on_listing']) || !$prop_config['show_on_listing']) {
                continue;
            }
            $listing_flags[$prop_field] = '';
            $select_columns .= "p.{$prop_field}, ";
        }
        $current_page_number = $start / $length + 1;
        $response_list = [];
        if ($filter['parID'] > 0) {
            $parent_query = tep_db_query('select parent_id from ' . TABLE_PROPERTIES . " where properties_id = '" . (int) $filter['parID'] . "'");
            if ($parent = tep_db_fetch_array($parent_query)) {
                $row = ['<span class="parent_cats"><i class="icon-circle"></i><i class="icon-circle"></i><i class="icon-circle"></i></span><input class="cell_identify" type="hidden" value="' . $parent['parent_id'] . '"><input class="cell_type" type="hidden" value="parent">'];
                foreach ($listing_flags as $listing_flag) {
                    $row[] = '';
                }
                $row[] = '';
                $response_list[] = $row;
            }
        }
        $properties_query_raw = "select p.properties_id, p.properties_type, {$select_columns} if(length(pd.properties_name_alt) > 0, pd.properties_name_alt, pd.properties_name) as properties_name, pd.properties_image, p.date_added, p.last_modified from " . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . " pd where p.properties_id = pd.properties_id and pd.language_id = '" . (int) $languages_id . "' and parent_id = '" . (int) $filter['parID'] . "' " . $search . " order by (p.properties_type = 'category') desc, p.sort_order, properties_name";
        $properties_split = new \Split_Page_Results($current_page_number, $length, $properties_query_raw, $properties_query_numrows);
        $properties_query = tep_db_query($properties_query_raw);
        while ($properties = tep_db_fetch_array($properties_query)) {
            if ($properties['properties_type'] == 'category') {
                $row = ['<div class="handle_cat_list state-disabled"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="cat_name"><b>' . $properties['properties_name'] . '</b><input class="cell_identify" type="hidden" value="' . $properties['properties_id'] . '"><input class="cell_type" type="hidden" value="category"></div></div>'];
                foreach ($listing_flags as $listing_flag) {
                    $row[] = '';
                }
                $row[] = '';
            } else {
                $image = \common\helpers\Image::info_image($properties['properties_image'], $properties['properties_name'], 50, 50);
                $row = ['<div class="handle_cat_list state-disabled"><span class="handle"><i class="icon-hand-paper-o"></i></span><div class="prod_name">' . (tep_not_null($image) && $image != TEXT_IMAGE_NONEXISTENT ? '<span class="prodImgC">' . $image . '</span>' : '<span class="cubic"></span>') . '<table class="wrapper"><tr><td><span class="prodNameC">' . $properties['properties_name'] . '</span></td></tr></table>' . '<input class="cell_identify" type="hidden" value="' . $properties['properties_id'] . '"><input class="cell_type" type="hidden" value="property"></div></div>'];
                foreach (array_keys($listing_flags) as $listing_flag) {
                    $row[] = '<input type="checkbox" class="js-listing_switcher" data-id="' . $properties['properties_id'] . '" name="' . $listing_flag . '" ' . ($properties[$listing_flag] ? ' checked' : '') . '>';
                }
                $row[] = $this->properties_types_array[$properties['properties_type']];
            }
            $response_list[] = $row;
        }
        $response = ['draw' => $draw, 'recordsTotal' => $properties_query_numrows, 'recordsFiltered' => $properties_query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_statusactions()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $parent_id = Yii::$app->request->post('parent_id', 0);
        $properties_id = Yii::$app->request->post('properties_id', 0);
        $this->layout = false;
        if ($properties_id > 0) {
            $properties = tep_db_fetch_array(tep_db_query('select p.properties_id, p.properties_type, if(length(pd.properties_name_alt) > 0, pd.properties_name_alt, pd.properties_name) as properties_name, p.date_added, p.last_modified from ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . " pd where p.properties_id = pd.properties_id and pd.language_id = '" . (int) $languages_id . "' and p.parent_id = '" . (int) $parent_id . "' and p.properties_id = '" . (int) $properties_id . "'"));
            $p_info = new \Object_Info($properties, false);
            if ($p_info->properties_id > 0) {
                if ($p_info->properties_type == 'category') {
                    $children_properties = \common\helpers\Properties::get_properties_tree($p_info->properties_id, '', [['id' => $p_info->properties_id]], false);
                    $children_property_ids = \yii\helpers\Array_Helper::get_column($children_properties, 'id');
                    $stat = tep_db_fetch_array(tep_db_query('SELECT COUNT(DISTINCT products_id) AS assigned_to_products ' . 'FROM ' . TABLE_PROPERTIES_TO_PRODUCTS . ' ' . "WHERE properties_id IN ('" . implode("','", $children_property_ids) . "') "));
                } else {
                    $stat = tep_db_fetch_array(tep_db_query('SELECT COUNT(DISTINCT products_id) AS assigned_to_products ' . 'FROM ' . TABLE_PROPERTIES_TO_PRODUCTS . ' ' . "WHERE properties_id='" . intval($p_info->properties_id) . "' "));
                }
                echo '<div class="or_box_head">' . $p_info->properties_name . '</div>';
                echo '<div class="col_desc">Used in ' . $stat['assigned_to_products'] . ' products</div>';
                echo '<div class="btn-toolbar btn-toolbar-order">';
                echo '<a href="' . Yii::$app->url_manager->create_url(['properties/' . ($p_info->properties_type == 'category' ? 'category' : 'edit'), 'pID' => $properties_id]) . '"><button class="btn btn-edit btn-no-margin">' . IMAGE_EDIT . '</button></a>';
                if ($p_info->properties_type != 'category') {
                    echo '<button onclick="confirmMoveProperty(\'' . $p_info->properties_id . '\')" class="btn">' . IMAGE_MOVE . '</button>';
                }
                echo '<button class="btn btn-delete btn-no-margin" onclick="propertyDeleteConfirm(' . $properties_id . ')">' . IMAGE_DELETE . '</button>';
                echo '</div>';
            }
        }
    }
    public function action_move_confirm()
    {
        $parent_id = \Yii::$app->request->post('parID', 0);
        $properties_id = \Yii::$app->request->post('properties_id', 0);
        $this->layout = false;
        if ($properties_id > 0) {
            tep_db_query('update ' . TABLE_PROPERTIES . " set parent_id = '" . (int) $parent_id . "'  where properties_id = '" . (int) $properties_id . "' and properties_type != 'category'");
        }
        return '';
    }
    public function action_move()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $properties_id = \Yii::$app->request->post('properties_id', 0);
        $this->layout = false;
        if ($properties_id > 0) {
            $properties = tep_db_fetch_array(tep_db_query('select p.properties_id, p.properties_type, if(length(pd.properties_name_alt) > 0, pd.properties_name_alt, pd.properties_name) as properties_name, p.date_added, p.last_modified from ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . " pd where p.properties_id = pd.properties_id and pd.language_id = '" . (int) $languages_id . "' and p.properties_id = '" . (int) $properties_id . "'"));
            $p_info = new \Object_Info($properties, false);
            if ($p_info->properties_id > 0) {
                if ($p_info->properties_type == 'category') {
                    return false;
                } else {
                    return $this->render('move.tpl', ['pInfo' => $p_info]);
                }
            }
        }
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('properties/edit'), 'title' => TEXT_PROPERTIES_TITLE];
        $this->view->heading_title = TEXT_PROPERTIES_TITLE;
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#property_edit\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $this->selected_menu = ['catalog', 'properties'];
        $this->view->use_popup_mode = false;
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->use_popup_mode = true;
        }
        $properties_id = Yii::$app->request->get('pID', 0);
        $properties = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_PROPERTIES . " where properties_id = '" . (int) $properties_id . "'"));
        if (!$properties) {
            $properties['parent_id'] = Yii::$app->request->get('parID', 0);
        }
        $p_info = new \Object_Info($properties, false);
        $this->view->properties_types = $this->properties_types_array;
        $this->view->multi_choices[''] = TEXT_PLEASE_CHOOSE;
        $this->view->multi_choices['0'] = TEXT_SINGLE;
        $this->view->multi_choices['1'] = TEXT_MULTIPLE;
        $this->view->multi_lines[''] = TEXT_PLEASE_CHOOSE;
        $this->view->multi_lines['0'] = TEXT_SINGLE_LINE;
        $this->view->multi_lines['1'] = TEXT_MULTILINE;
        $this->view->decimals[''] = TEXT_PLEASE_CHOOSE;
        $this->view->decimals['0'] = '1234';
        $this->view->decimals['1'] = '1234.5';
        $this->view->decimals['2'] = '1234.56';
        $this->view->decimals['3'] = '1234.567';
        $this->view->decimals['4'] = '1234.5678';
        $this->view->decimals['5'] = '1234.56789';
        $this->view->decimals['9'] = '1234.56789xxx';
        $p = \common\models\Properties::find()->alias('p')->join_with(['backendname'])->select('properties_name, properties_name_alt, p.properties_id')->and_where('display_filter=1 and properties_type="text"')->order_by('properties_name')->as_array()->index_by('properties_id')->column();
        $p = ['' => TEXT_PLEASE_CHOOSE] + $p;
        $this->view->filter_by_property = $p;
        $default_language_id = $languages_id;
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            if ($languages[$i]['code'] == DEFAULT_LANGUAGE) {
                $default_language_id = $languages[$i]['id'];
            }
        }
        if (strlen(\common\helpers\Properties::get_properties_description($properties_id, $default_language_id)) > 0) {
            $this->view->additional_info = 1;
        }
        $this->view->properties_values = [];
        $this->view->properties_values_sorted_ids = [];
        $property_type_value = Array_Helper::get_value($properties, 'properties_type');
        $properties_values_query = tep_db_query('select values_id, properties_id, language_id, values_text, values_number, values_number_upto, values_alt, values_seo_page_name as values_seo, values_color, values_image, values_prefix, values_postfix, maps_id, sort_order from ' . TABLE_PROPERTIES_VALUES . " where properties_id = '" . (int) $properties_id . "' order by sort_order, " . ($property_type_value == 'number' || $property_type_value == 'interval' ? 'values_number' : 'values_text'));
        while ($properties_values = tep_db_fetch_array($properties_values_query)) {
            if ($property_type_value == 'number' || $property_type_value == 'interval') {
                $properties_values['values'] = (float) number_format($properties_values['values_number'], $properties['decimals'], '.', '');
                $properties_values['values_number_upto'] = (float) number_format($properties_values['values_number_upto'], $properties['decimals'], '.', '');
            } else {
                $properties_values['values'] = $properties_values['values_text'];
            }
            /**
             * @var $imageMaps \common\extensions\ImageMaps\models\ImageMaps
             */
            if ($image_maps = \common\helpers\Extensions::get_model('ImageMaps', 'ImageMaps')) {
                if ($properties_values['maps_id'] && !empty($image_maps)) {
                    if ($map = $image_maps::find_one($properties_values['maps_id'])) {
                        $properties_values['mapsId'] = $properties_values['maps_id'];
                        $properties_values['mapsImage'] = $map->image;
                        $properties_values['mapsTitle'] = $map->get_title($properties_values['language_id']);
                    }
                }
            }
            $this->view->properties_values[$properties_values['language_id']][$properties_values['values_id']] = $properties_values;
            if ($properties_values['language_id'] == $default_language_id) {
                $this->view->properties_values_sorted_ids[$properties_values['values_id']] = $properties_values['values_id'];
            }
        }
        return $this->render('edit.tpl', ['languages' => $languages, 'default_language' => DEFAULT_LANGUAGE, 'pInfo' => $p_info]);
    }
    public function action_category()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('properties/category'), 'title' => TEXT_PROPERTIES_TITLE];
        $this->view->heading_title = TEXT_PROPERTIES_TITLE;
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#property_edit\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $this->selected_menu = ['catalog', 'properties'];
        $this->view->use_popup_mode = false;
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->use_popup_mode = true;
        }
        $properties_id = Yii::$app->request->get('pID', 0);
        $properties = tep_db_fetch_array(tep_db_query('select * from ' . TABLE_PROPERTIES . " where properties_id = '" . (int) $properties_id . "'"));
        if (!$properties) {
            $properties['parent_id'] = Yii::$app->request->get('parID', 0);
        }
        $p_info = new \Object_Info($properties, false);
        $default_language_id = $languages_id;
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            if ($languages[$i]['code'] == DEFAULT_LANGUAGE) {
                $default_language_id = $languages[$i]['id'];
            }
        }
        if (strlen(\common\helpers\Properties::get_properties_description($properties_id, $default_language_id)) > 0) {
            $this->view->additional_info = 1;
        }
        return $this->render('category.tpl', ['languages' => $languages, 'default_language' => DEFAULT_LANGUAGE, 'pInfo' => $p_info]);
    }
    public function action_save()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $parent_id = Yii::$app->request->post('parent_id', 0);
        $properties_id = Yii::$app->request->post('properties_id', 0);
        $properties_type = Yii::$app->request->post('properties_type', 'text');
        $multi_choice = Yii::$app->request->post('multi_choice', 0);
        $multi_line = Yii::$app->request->post('multi_line', 0);
        $filter_by_property = Yii::$app->request->post('filter_by_property', 0);
        $filter_steps = Yii::$app->request->post('filter_steps', 0);
        $decimals = Yii::$app->request->post('decimals', 0);
        $display_product = Yii::$app->request->post('display_product', 0);
        $display_listing = Yii::$app->request->post('display_listing', 0);
        $display_filter = Yii::$app->request->post('display_filter', 0);
        $display_search = Yii::$app->request->post('display_search', 0);
        $display_compare = Yii::$app->request->post('display_compare', 0);
        $display_as_image = Yii::$app->request->post('display_as_image', 0);
        $display_filter_as = Yii::$app->request->post('display_filter_as', '');
        $products_groups = Yii::$app->request->post('products_groups', 0);
        $extra_values = (int) \Yii::$app->request->post('extra_values', 0);
        $range_select = (int) \Yii::$app->request->post('range_select', 0);
        $same_all_languages = Yii::$app->request->post('same_all_languages', 0);
        $additional_info = tep_db_prepare_input(Yii::$app->request->post('additional_info', 0));
        $properties_name = tep_db_prepare_input(Yii::$app->request->post('properties_name', []));
        $properties_name_alt = tep_db_prepare_input(Yii::$app->request->post('properties_name_alt', []));
        $properties_description = tep_db_prepare_input(Yii::$app->request->post('properties_description', []));
        $properties_seo_page_name = tep_db_prepare_input(Yii::$app->request->post('properties_seo_page_name', []));
        $properties_image = Yii::$app->request->post('properties_image', []);
        $properties_image_loaded = Yii::$app->request->post('properties_image_loaded', []);
        $properties_image_delete = Yii::$app->request->post('properties_image_delete', []);
        $properties_units_title = tep_db_prepare_input(Yii::$app->request->post('properties_units_title', []));
        $properties_color = tep_db_prepare_input(Yii::$app->request->post('properties_color', []));
        $values = tep_db_prepare_input(Yii::$app->request->post('values', []));
        $values_upto = tep_db_prepare_input(Yii::$app->request->post('values_upto', []));
        $values_alt = tep_db_prepare_input(Yii::$app->request->post('values_alt', []));
        $values_seo = tep_db_prepare_input(Yii::$app->request->post('values_seo', []));
        $upload_docs = tep_db_prepare_input(Yii::$app->request->post('upload_docs', []));
        $values_color = tep_db_prepare_input(Yii::$app->request->post('values_color', []));
        $maps_id = tep_db_prepare_input(Yii::$app->request->post('maps_id', []));
        $values_image = tep_db_prepare_input(Yii::$app->request->post('values_image', []));
        $values_image_loaded = tep_db_prepare_input(Yii::$app->request->post('values_image_loaded', []));
        $values_image_delete = tep_db_prepare_input(Yii::$app->request->post('values_image_delete', []));
        $values_prefix = tep_db_prepare_input(Yii::$app->request->post('values_prefix', []));
        $values_postfix = tep_db_prepare_input(Yii::$app->request->post('values_postfix', []));
        $tmp_sort_order = tep_db_prepare_input(Yii::$app->request->post('sort_order', []));
        $sort_order = [];
        if (is_array($tmp_sort_order)) {
            foreach ($tmp_sort_order as $lang => $value) {
                if (count($value) > 1) {
                    asort($value);
                    $i = 1;
                    foreach ($value as $key => $value) {
                        $sort_order[$key][$lang] = $i++;
                    }
                }
            }
        }
        $sql_data_array = ['properties_type' => $properties_type, 'multi_choice' => $multi_choice, 'multi_line' => $multi_line, 'filter_by_property' => $filter_by_property, 'filter_steps' => $filter_steps, 'decimals' => $decimals, 'display_product' => $display_product, 'display_listing' => $display_listing, 'display_filter' => $display_filter, 'display_search' => $display_search, 'display_compare' => $display_compare, 'display_as_image' => $display_as_image, 'display_filter_as' => $display_filter_as, 'extra_values' => $extra_values, 'range_select' => $range_select, 'products_groups' => $products_groups];
        if ($properties_id == 0) {
            $insert_sql_data = ['parent_id' => $parent_id, 'date_added' => 'now()'];
            $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
            tep_db_perform(TABLE_PROPERTIES, $sql_data_array);
            $properties_id = tep_db_insert_id();
        } else {
            $update_sql_data = ['last_modified' => 'now()'];
            $sql_data_array = array_merge($sql_data_array, $update_sql_data);
            tep_db_perform(TABLE_PROPERTIES, $sql_data_array, 'update', "properties_id = '" . (int) $properties_id . "'");
        }
        $default_language_id = $languages_id;
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            if ($languages[$i]['code'] == DEFAULT_LANGUAGE) {
                $default_language_id = $languages[$i]['id'];
            }
        }
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            if (trim($properties_name[$languages[$i]['id']] ?? null) == '' || $same_all_languages) {
                $properties_name[$languages[$i]['id']] = $properties_name[$default_language_id] ?? null;
            }
            if (trim($properties_name_alt[$languages[$i]['id']] ?? null) == '' || $same_all_languages) {
                $properties_name_alt[$languages[$i]['id']] = $properties_name_alt[$default_language_id] ?? null;
            }
            if (!$additional_info) {
                $properties_description[$languages[$i]['id']] = '';
            } elseif (trim($properties_description[$languages[$i]['id']] ?? null) == '' || $same_all_languages) {
                $properties_description[$languages[$i]['id']] = $properties_description[$default_language_id];
            }
            if (trim($properties_seo_page_name[$languages[$i]['id']] ?? null) == '') {
                $properties_seo_page_name[$languages[$i]['id']] = Seo::make_slug($properties_name[$languages[$i]['id']]);
            }
            if (trim($properties_seo_page_name[$languages[$i]['id']] ?? null) == '') {
                $properties_seo_page_name[$languages[$i]['id']] = $properties_id;
            }
            if (trim($properties_seo_page_name[$languages[$i]['id']] ?? null) == '' || $same_all_languages) {
                $properties_seo_page_name[$languages[$i]['id']] = $properties_seo_page_name[$default_language_id];
            }
            if (trim($properties_units_title[$languages[$i]['id']] ?? null) == '' || $same_all_languages) {
                $properties_units_title[$languages[$i]['id']] = $properties_units_title[$default_language_id] ?? null;
            }
            if (tep_not_null($properties_units_title[$languages[$i]['id']])) {
                $check = tep_db_fetch_array(tep_db_query('select properties_units_id from ' . TABLE_PROPERTIES_UNITS . " where properties_units_title = '" . tep_db_input($properties_units_title[$languages[$i]['id']]) . "'"));
                if (($check['properties_units_id'] ?? null) > 0) {
                    $properties_units_id = $check['properties_units_id'];
                } else {
                    tep_db_perform(TABLE_PROPERTIES_UNITS, ['properties_units_title' => $properties_units_title[$languages[$i]['id']]]);
                    $properties_units_id = tep_db_insert_id();
                }
            }
            if (trim($properties_color[$languages[$i]['id']] ?? null) == '' || $same_all_languages) {
                $properties_color[$languages[$i]['id']] = $properties_color[$default_language_id] ?? null;
            }
            $sql_data_array = ['properties_name' => $properties_name[$languages[$i]['id']], 'properties_name_alt' => $properties_name_alt[$languages[$i]['id']], 'properties_description' => $properties_description[$languages[$i]['id']], 'properties_seo_page_name' => $properties_seo_page_name[$languages[$i]['id']], 'properties_units_id' => intval($properties_units_id ?? null), 'properties_color' => $properties_color[$languages[$i]['id']]];
            $check = tep_db_fetch_array(tep_db_query('select count(*) as properties_description_exists from ' . TABLE_PROPERTIES_DESCRIPTION . " where properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $languages[$i]['id'] . "'"));
            if ($check['properties_description_exists']) {
                tep_db_perform(TABLE_PROPERTIES_DESCRIPTION, $sql_data_array, 'update', "properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $languages[$i]['id'] . "'");
            } else {
                $insert_sql_data = ['properties_id' => $properties_id, 'language_id' => $languages[$i]['id']];
                $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
                tep_db_perform(TABLE_PROPERTIES_DESCRIPTION, $sql_data_array);
            }
            if ((trim($properties_image_loaded[$languages[$i]['id']] ?? null) == '' || $same_all_languages) && trim($properties_image_loaded[$default_language_id] ?? null) != '') {
                $properties_image_loaded[$languages[$i]['id']] = $properties_image_loaded[$default_language_id];
            }
            if ((trim($properties_image_delete[$languages[$i]['id']] ?? null) == '' || $same_all_languages) && trim($properties_image_delete[$default_language_id] ?? null) != '') {
                $properties_image_delete[$languages[$i]['id']] = $properties_image_delete[$default_language_id];
            }
            if ((trim($properties_image[$languages[$i]['id']] ?? null) == '' || $same_all_languages) && trim($properties_image[$default_language_id] ?? null) != '') {
                $properties_image[$languages[$i]['id']] = $properties_image[$default_language_id];
            }
            $properties_description = \common\models\Properties_Description::find_one(['properties_id' => $properties_id, 'language_id' => $languages[$i]['id']]);
            $properties_description->properties_image = \common\helpers\Image::prepare_saving_image($properties_description->properties_image ?? '', $properties_image[$languages[$i]['id']], $properties_image_loaded[$languages[$i]['id']], 'properties' . DIRECTORY_SEPARATOR . $properties_id, $properties_image_delete[$languages[$i]['id']]);
            $properties_description->save(false);
        }
        $all_values_id = [];
        if (in_array($properties_type, ['text', 'number', 'interval', 'file'])) {
            foreach ($values as $val_id => $val) {
                if (trim($values[$val_id][$default_language_id]) == '' && trim($upload_docs[$val_id][$default_language_id]) == '') {
                    continue;
                    // Skip empty lines
                }
                if (strstr($val_id, 'new')) {
                    $max_value = tep_db_fetch_array(tep_db_query('select max(values_id) + 1 as next_id from ' . TABLE_PROPERTIES_VALUES));
                    $values_id = $max_value['next_id'];
                    if (!($values_id > 0)) {
                        $values_id = 1;
                    }
                } elseif ($val_id > 0) {
                    $values_id = $val_id;
                } else {
                    continue;
                }
                for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                    // {{
                    if ($properties_type == 'file') {
                        if ((trim($upload_docs[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) && trim($upload_docs[$val_id][$default_language_id]) != '') {
                            $upload_docs[$val_id][$languages[$i]['id']] = $upload_docs[$val_id][$default_language_id];
                        }
                        if ($upload_docs[$val_id][$languages[$i]['id']] != '') {
                            $path = \Yii::get_alias('@webroot');
                            $path .= DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                            $tmp_name = $path . $upload_docs[$val_id][$languages[$i]['id']];
                            $new_name = DIR_FS_CATALOG_IMAGES . 'prop-' . $properties_id . '-' . $upload_docs[$val_id][$languages[$i]['id']];
                            @copy($tmp_name, $new_name);
                            @unlink($tmp_name);
                            $values[$val_id][$languages[$i]['id']] = 'prop-' . $properties_id . '-' . $upload_docs[$val_id][$languages[$i]['id']];
                        }
                    }
                    // }}
                    if (trim($values[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $values[$val_id][$languages[$i]['id']] = $values[$val_id][$default_language_id];
                    }
                    if ($properties_type == 'interval') {
                        if (trim($values_upto[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                            $values_upto[$val_id][$languages[$i]['id']] = $values_upto[$val_id][$default_language_id];
                        }
                    } else {
                        $values_upto[$val_id][$languages[$i]['id']] = '';
                    }
                    if (trim($values_alt[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $values_alt[$val_id][$languages[$i]['id']] = $values_alt[$val_id][$default_language_id];
                    }
                    if (trim($values_prefix[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $values_prefix[$val_id][$languages[$i]['id']] = $values_prefix[$val_id][$default_language_id];
                    }
                    if (trim($values_postfix[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $values_postfix[$val_id][$languages[$i]['id']] = $values_postfix[$val_id][$default_language_id];
                    }
                    if (trim($values_seo[$val_id][$languages[$i]['id']]) == '') {
                        $values_seo[$val_id][$languages[$i]['id']] = Seo::make_slug(tep_not_null($values_alt[$val_id][$languages[$i]['id']]) ? $values_alt[$val_id][$languages[$i]['id']] : $values[$val_id][$languages[$i]['id']]);
                    }
                    if (trim($values_seo[$val_id][$languages[$i]['id']]) == '') {
                        $values_seo[$val_id][$languages[$i]['id']] = $values_id;
                    }
                    if (trim($values_seo[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $values_seo[$val_id][$languages[$i]['id']] = $values_seo[$val_id][$default_language_id];
                    }
                    if (trim($values_color[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $values_color[$val_id][$languages[$i]['id']] = $values_color[$val_id][$default_language_id];
                    }
                    if (trim($maps_id[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $maps_id[$val_id][$languages[$i]['id']] = $maps_id[$val_id][$default_language_id];
                    }
                    if (trim($sort_order[$val_id][$languages[$i]['id']]) == '' || $same_all_languages) {
                        $sort_order[$val_id][$languages[$i]['id']] = $sort_order[$val_id][$default_language_id];
                    }
                    $sql_data_array = ['values_text' => $values[$val_id][$languages[$i]['id']], 'values_number' => round((float) $values[$val_id][$languages[$i]['id']], (int) $decimals), 'values_number_upto' => round((float) $values_upto[$val_id][$languages[$i]['id']], (int) $decimals), 'values_alt' => $values_alt[$val_id][$languages[$i]['id']], 'values_seo_page_name' => $values_seo[$val_id][$languages[$i]['id']], 'values_color' => $values_color[$val_id][$languages[$i]['id']], 'sort_order' => $sort_order[$val_id][$languages[$i]['id']], 'maps_id' => $maps_id[$val_id][$languages[$i]['id']], 'values_prefix' => $values_prefix[$val_id][$languages[$i]['id']], 'values_postfix' => $values_postfix[$val_id][$languages[$i]['id']]];
                    $properties_values = \common\models\Properties_Values::find_one(['values_id' => $values_id, 'properties_id' => $properties_id, 'language_id' => $languages[$i]['id']]);
                    $sql_data_array['values_image'] = \common\helpers\Image::prepare_saving_image($properties_values->values_image ?? '', $values_image[$val_id][$languages[$i]['id']] ?? '', $values_image_loaded[$val_id][$languages[$i]['id']] ?? '', 'properties' . DIRECTORY_SEPARATOR . $properties_id, $values_image_delete[$val_id][$languages[$i]['id']] ?? '');
                    $check = tep_db_fetch_array(tep_db_query('select count(*) as properties_values_exists from ' . TABLE_PROPERTIES_VALUES . " where values_id = '" . (int) $values_id . "' and properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $languages[$i]['id'] . "'"));
                    if ($check['properties_values_exists']) {
                        tep_db_perform(TABLE_PROPERTIES_VALUES, $sql_data_array, 'update', "values_id = '" . (int) $values_id . "' and properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $languages[$i]['id'] . "'");
                    } else {
                        $insert_sql_data = ['values_id' => $values_id, 'properties_id' => $properties_id, 'language_id' => $languages[$i]['id']];
                        $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
                        tep_db_perform(TABLE_PROPERTIES_VALUES, $sql_data_array);
                    }
                }
                if ($same_all_languages) {
                    $check_image = tep_db_fetch_array(tep_db_query('SELECT values_image FROM ' . TABLE_PROPERTIES_VALUES . " WHERE values_id = '" . (int) $values_id . "' and properties_id = '" . (int) $properties_id . "' and language_id = '" . (int) $default_language_id . "' limit 1"));
                    tep_db_query('UPDATE ' . TABLE_PROPERTIES_VALUES . " SET values_image='" . $check_image['values_image'] . "' WHERE values_id = '" . (int) $values_id . "' and properties_id = '" . (int) $properties_id . "' and language_id != '" . (int) $default_language_id . "' ");
                }
                $all_values_id[] = $values_id;
            }
        }
        $properties_values_query = tep_db_query('select values_id from ' . TABLE_PROPERTIES_VALUES . " where properties_id = '" . (int) $properties_id . "' and values_id not in ('" . implode("','", $all_values_id) . "')");
        while ($properties_values = tep_db_fetch_array($properties_values_query)) {
            tep_db_query('delete from ' . TABLE_PROPERTIES_VALUES . " where properties_id = '" . (int) $properties_id . "' and values_id = '" . (int) $properties_values['values_id'] . "'");
            tep_db_query('delete from ' . TABLE_PROPERTIES_TO_PRODUCTS . " where properties_id = '" . (int) $properties_id . "' and values_id = '" . (int) $properties_values['values_id'] . "'");
        }
        \common\helpers\Properties::check_filter_table($properties_id);
        \common\helpers\Products_Group_Sort_Cache::update();
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
            $this->view->properties_tree = \common\helpers\Properties::get_properties_tree('0', '&nbsp;&nbsp;&nbsp;&nbsp;', '', false);
            return $this->render('properties_box.tpl', ['properties_id' => $properties_id]);
        } else if ($properties_type == 'category') {
            return $this->redirect(Yii::$app->url_manager->create_url(['properties/category', 'pID' => $properties_id]));
        } else {
            return $this->redirect(Yii::$app->url_manager->create_url(['properties/edit', 'pID' => $properties_id]));
        }
    }
    public function action_sort_order()
    {
        $categories_sorted = Yii::$app->request->post('category', []);
        foreach ($categories_sorted as $sort_order => $properties_id) {
            tep_db_query('update ' . TABLE_PROPERTIES . " set sort_order = '" . (int) $sort_order . "' where properties_id = '" . (int) $properties_id . "'");
        }
        $properties_sorted = Yii::$app->request->post('property', []);
        foreach ($properties_sorted as $sort_order => $properties_id) {
            tep_db_query('update ' . TABLE_PROPERTIES . " set sort_order = '" . (int) ($sort_order + count($categories_sorted)) . "' where properties_id = '" . (int) $properties_id . "'");
        }
        \common\helpers\Products_Group_Sort_Cache::update();
    }
    public function action_confirmdelete()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $this->layout = false;
        $properties_id = Yii::$app->request->post('properties_id');
        if ($properties_id > 0) {
            $properties = tep_db_fetch_array(tep_db_query('select p.properties_id, if(length(pd.properties_name_alt) > 0, pd.properties_name_alt, pd.properties_name) as properties_name, p.date_added, p.last_modified from ' . TABLE_PROPERTIES . ' p, ' . TABLE_PROPERTIES_DESCRIPTION . " pd where p.properties_id = pd.properties_id and pd.language_id = '" . (int) $languages_id . "' and p.properties_id = '" . (int) $properties_id . "'"));
            $p_info = new \Object_Info($properties, false);
            echo tep_draw_form('properties', FILENAME_PROPERTIES, \common\helpers\Output::get_all_get_params(['pID', 'action']) . 'dID=' . $p_info->properties_id . '&action=deleteconfirm', 'post', 'id="item_delete" onSubmit="return propertyDelete();"');
            echo '<div class="or_box_head">' . $p_info->properties_name . '</div>';
            echo TEXT_DELETE_INTRO . '<br>';
            echo '<div class="btn-toolbar btn-toolbar-order">';
            echo '<button type="submit" class="btn btn-primary btn-no-margin">' . IMAGE_CONFIRM . '</button>';
            echo '<button class="btn btn-cancel" onClick="return resetStatement(' . (int) $properties_id . ')">' . IMAGE_CANCEL . '</button>';
            echo tep_draw_hidden_field('properties_id', $properties_id);
            echo '</div></form>';
        }
    }
    public function action_delete()
    {
        global $language;
        $properties_id = Yii::$app->request->post('properties_id', 0);
        if ($properties_id > 0) {
            \common\helpers\Properties::remove_property($properties_id);
            echo 'reset';
        }
    }
    /**
     * Autocomplette
     */
    public function action_units()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $search = '1';
        if (!empty($term)) {
            $search = "properties_units_title like '%" . tep_db_input($term) . "%'";
        }
        $response = [];
        $units_query = tep_db_query('select properties_units_title from ' . TABLE_PROPERTIES_UNITS . ' where ' . $search . ' group by properties_units_title order by properties_units_title');
        while ($units = tep_db_fetch_array($units_query)) {
            $response[] = $units['properties_units_title'];
        }
        echo json_encode($response);
    }
    public function action_update_property_flag()
    {
        $this->layout = false;
        $property_id = Yii::$app->request->post('id', 0);
        $property_flag_name = Yii::$app->request->post('name', 0);
        $allowed_flag_names = ['display_product', 'display_listing', 'display_filter', 'display_search', 'display_compare', 'products_groups'];
        $flag = !!Yii::$app->request->post('flag', 0) ? 1 : 0;
        if ($property_id && in_array($property_flag_name, $allowed_flag_names)) {
            tep_db_query('UPDATE ' . TABLE_PROPERTIES . ' ' . "SET {$property_flag_name}='{$flag}', last_modified=NOW() " . "WHERE properties_id='" . intval($property_id) . "' ");
            \common\helpers\Properties::check_filter_table($property_id);
        }
        echo 'ok';
    }
}