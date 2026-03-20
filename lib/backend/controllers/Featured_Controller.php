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

use common\helpers\Html;
use Yii;
class Featured_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_CATALOG_FEATURED'];
    private static $date_options = ['active_on', 'start_between', 'end_between'];
    private static $by = [['name' => 'TEXT_ANY', 'value' => '', 'selected' => ''], ['name' => 'PRODUCTS_ID', 'value' => 'featured.products_id', 'selected' => ''], ['name' => 'PRODUCTS_MODEL', 'value' => 'products_model', 'selected' => ''], ['name' => 'PRODUCTS_NAME', 'value' => 'products_name', 'selected' => ''], ['name' => 'PRODUCTS_UPC', 'value' => 'products_upc', 'selected' => ''], ['name' => 'PRODUCTS_EAN', 'value' => 'products_ean', 'selected' => ''], ['name' => 'PRODUCTS_ISBN', 'value' => 'products_isbn', 'selected' => '']];
    private static $filter_fields = ['search' => '', 'date' => '', 'featured_type_id' => 'intval', 'inactive' => 'intval', 'dfrom' => ['list' => ['\common\helpers\Date', 'prepareInputDate']], 'dto' => ['list' => ['\common\helpers\Date', 'prepareInputDate']]];
    public function action_index()
    {
        $this->selected_menu = ['marketing', 'featured'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('featured/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $this->top_buttons[] = '<a href="' . \Yii::$app->url_manager->create_url(['featured/featurededit']) . '" class="btn btn-primary">' . IMAGE_INSERT . '</a>';
        $this->view->featured_table = [['title' => ' ', 'not_important' => 2], ['title' => ' ', 'not_important' => 2], ['title' => Html::checkbox('select_all', false, ['id' => 'select_all']), 'not_important' => 2], ['title' => HEADING_TYPE, 'not_important' => 0], ['title' => TEXT_INFO_DATE_ADDED, 'not_important' => 0], ['title' => TABLE_HEADING_PRODUCTS, 'not_important' => 0], ['title' => TEXT_START_DATE, 'not_important' => 0], ['title' => TEXT_END_DATE, 'not_important' => 0], ['title' => TABLE_HEADING_STATUS, 'not_important' => 1]];
        $this->view->sort_columns = '3,4,5,6,7,8';
        $languages_id = \Yii::$app->settings->get('languages_id');
        $featured_types_arr = \common\models\Featured_Types::find()->where(['language_id' => $languages_id])->select('featured_type_name, featured_type_id')->as_array()->index_by('featured_type_id')->column();
        if (!is_array($featured_types_arr)) {
            $featured_types_arr = [];
        }
        $featured_types_arr[0] = BOX_CATALOG_FEATURED;
        $featured_types_arr[-1] = TEXT_ALL;
        ksort($featured_types_arr);
        $this->view->types = $featured_types_arr;
        $this->view->filters = new \stdClass();
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $gets = Yii::$app->request->get();
        $by = self::$by;
        foreach ($by as $key => $value) {
            $by[$key]['name'] = defined($by[$key]['name']) ? constant($by[$key]['name']) : strtolower(str_replace('_', ' ', $by[$key]['name']));
            if (isset($gets['by']) && $value['value'] == $gets['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        foreach (self::$date_options as $opt) {
            $this->view->filters->date_options[$opt] = defined('TEXT_' . strtoupper($opt)) ? constant('TEXT_' . strtoupper($opt)) : strtoupper($opt);
        }
        foreach (self::$filter_fields as $v => $f) {
            if (!empty($gets[$v])) {
                if (is_callable($f)) {
                    $this->view->filters->{$v} = call_user_func($f, $gets[$v]);
                } elseif (is_array($f) && !empty($f['filter']) && is_callable($f['filter'])) {
                    $this->view->filters->{$v} = call_user_func($f['filter'], $gets[$v]);
                } else {
                    $this->view->filters->{$v} = $gets[$v];
                }
            } else {
                $this->view->filters->{$v} = '';
            }
        }
        return $this->render('index', ['selected_type_id' => (int) \Yii::$app->request->get('featured_type_id', -1)]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $filter = Yii::$app->request->get('filter', []);
        $filter_arr = [];
        parse_str($filter, $filter_arr);
        $featured_type_id = (int) ($filter_arr['featured_type_id'] ?? 0);
        $currencies = Yii::$container->get('currencies');
        $response_list = [];
        if ($length == -1) {
            $length = 10000;
        }
        $query_numrows = 0;
        $form_filter = Yii::$app->request->get('filter');
        $gets = [];
        parse_str($form_filter, $gets);
        if (isset($gets['date']) && in_array($gets['date'], self::$date_options)) {
            $date = $gets['date'];
        } else {
            $date = 'active_on';
        }
        if (isset($gets['by']) && in_array($gets['by'], \yii\helpers\Array_Helper::get_column(self::$by, 'value'))) {
            $by = $gets['by'];
        } else {
            $by = '';
        }
        $list_query = \common\models\Featured::find()->join_with(['backendProductDescription', 'featuredType'])->select(\common\models\Featured::table_name() . '.*');
        $inactive = false;
        foreach (self::$filter_fields as $v => $f) {
            if (isset($gets[$v]) && $gets[$v] != '') {
                if (is_callable($f)) {
                    if (is_array($gets[$v])) {
                        foreach ($gets[$v] as $k => $vv) {
                            $gets[$v][$k] = call_user_func($f, $vv);
                        }
                        $val = $gets[$v];
                    } else {
                        $val = call_user_func($f, $gets[$v]);
                    }
                } elseif (is_array($f) && !empty($f['list']) && is_callable($f['list'])) {
                    $val = call_user_func($f['list'], $gets[$v]);
                } else {
                    $val = $gets[$v];
                }
                switch ($v) {
                    case 'inactive':
                        $inactive = true;
                        break;
                    case 'featured_type_id':
                        if ($val >= 0) {
                            $list_query->and_where([\common\models\Featured::table_name() . '.featured_type_id' => $val]);
                        }
                        break;
                    case 'dfrom':
                        if (in_array($date, ['start_between'])) {
                            $list_query->start_after($val);
                        } elseif (in_array($date, ['active_on'])) {
                            $list_query->end_after($val);
                        } else {
                            //end between
                            $list_query->end_after($val);
                        }
                        break;
                    case 'dto':
                        if (in_array($date, ['start_between'])) {
                            $list_query->start_before($val);
                        } elseif (in_array($date, ['active_on'])) {
                            $list_query->start_before($val);
                        } else {
                            //end between
                            $list_query->end_before($val);
                        }
                        break;
                    case 'search':
                        if ($by == '') {
                            //all
                            $tmp = [];
                            foreach (\yii\helpers\Array_Helper::get_column(self::$by, 'value') as $field) {
                                if (!empty($field) && is_string($field)) {
                                    $tmp[] = ['like', $field, $val];
                                }
                            }
                            if (!empty($tmp)) {
                                $list_query->and_where(array_merge(['or'], $tmp));
                            }
                        } else {
                            $list_query->and_where(['like', $by, $val]);
                        }
                        break;
                }
            }
        }
        if (!$inactive) {
            $list_query->and_where('status=1');
        }
        $gets = Yii::$app->request->get();
        if (!empty($gets['search']['value'])) {
            $val = $gets['search']['value'];
            $tmp = [];
            foreach (\yii\helpers\Array_Helper::get_column(self::$by, 'value') as $field) {
                if (!empty($field) && is_string($field)) {
                    $tmp[] = ['like', $field, $val];
                }
            }
            if (!empty($tmp)) {
                $list_query->and_where(array_merge(['or'], $tmp));
            }
        }
        $can_sort = false;
        $current_sort = [];
        if (!empty($gets['order']) && is_array($gets['order'])) {
            foreach ($gets['order'] as $sort) {
                $dir = 'asc';
                if (!empty($sort['dir']) && $sort['dir'] == 'desc') {
                    $dir = 'desc';
                }
                switch ($sort['column']) {
                    case 0:
                        $can_sort = true;
                        $current_sort[] = 'sort_order, featured_date_added';
                        $list_query->add_order_by('sort_order, featured_date_added');
                    // no break
                    case 3:
                        $current_sort[] = 'featured_type_name ' . $dir;
                        $list_query->add_order_by(' featured_type_name ' . $dir);
                        break;
                    case 4:
                        $current_sort[] = 'featured_date_added ' . $dir;
                        $list_query->add_order_by(' featured_date_added ' . $dir);
                        break;
                    case 5:
                        $current_sort[] = 'products_name ' . $dir;
                        $list_query->add_order_by(' products_name ' . $dir);
                        break;
                    case 6:
                        $current_sort[] = 'start_date ' . $dir;
                        $list_query->add_order_by(' start_date ' . $dir);
                        break;
                    case 7:
                        $current_sort[] = 'expires_date ' . $dir;
                        $list_query->add_order_by(' expires_date ' . $dir);
                        break;
                    case 8:
                        $current_sort[] = 'status ' . $dir;
                        $list_query->add_order_by(' status ' . $dir);
                        break;
                    default:
                        $current_sort[] = 'featured_date_added desc';
                        $list_query->add_order_by(' featured_date_added desc ');
                        break;
                }
            }
            $list_query->add_order_by(' products_name ');
        } else {
            $current_sort[] = 'sort_order, featured_date_added';
            $list_query->add_order_by('sort_order, featured_date_added');
        }
        $response_list = [];
        $current_page_number = $start / $length + 1;
        $query_numrows = $list_query->count();
        if ($query_numrows < $start) {
            $start = 0;
        }
        $list_query->offset($start)->limit($length);
        $list_query->add_select('products_name, featured_type_name');
        //echo $listQuery->createCommand()->rawSql; die;
        $featureds = $list_query->as_array()->all();
        foreach ($featureds as $featured) {
            $row = [];
            $row[] = $featured['sort_order'];
            if ($can_sort) {
                $row[] = '<div class="handle_cat_list"><span class="handle" style="top: -15px; "><i class="icon-hand-paper-o"></i></span>' . '<input class="cell_id" type="hidden" value="' . $featured['featured_id'] . '">' . '</div>';
            } else {
                $row[] = '<input class="cell_id" type="hidden" value="' . $featured['featured_id'] . '">' . '<input class="current_sort" type="hidden" value="' . implode(', ', $current_sort) . '">';
            }
            $row[] = Html::checkbox('bulkProcess[]', false, ['value' => $featured['featured_id']]) . Html::hidden_input('featured_' . $featured['featured_id'], $featured['featured_id'], ['class' => 'cell_identify']) . (!$featured['status'] ? Html::hidden_input('featured_st' . $featured['featured_id'], 'dis_module', ['class' => 'tr-status-class']) : '');
            $row[] = !isset($featured['featured_type_name']) ? $featured['featured_type_id'] == 0 ? BOX_CATALOG_FEATURED : '' : $featured['featured_type_name'];
            if ($featured['featured_date_added'] > '1980-01-01') {
                $row[] = \common\helpers\Date::date_short($featured['featured_date_added']);
            } else {
                $row[] = '';
            }
            $name = $featured['backendProductDescription']['products_name'] ?? '';
            foreach (['products_model', 'products_upc', 'products_ean', 'products_isbn'] as $value) {
                if (!empty($featured['product'][$value])) {
                    $name .= '<br>' . $featured['product'][$value];
                }
            }
            $row[] = $name;
            if ($featured['start_date'] > '1980-01-01') {
                $row[] = \common\helpers\Date::datetime_short($featured['start_date']);
            } else {
                $row[] = '';
            }
            if ($featured['expires_date'] > '1980-01-01') {
                $row[] = \common\helpers\Date::datetime_short($featured['expires_date']);
            } else {
                $row[] = '';
            }
            $row[] = Html::checkbox('specials_status' . $featured['featured_id'], $featured['status'], ['value' => $featured['featured_id'], 'class' => $length < CATALOG_SPEED_UP_DESIGN ? 'check_on_off' : 'check_on_off_check']);
            $response_list[] = $row;
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_itempreedit($item_id = null)
    {
        $this->layout = false;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/featured');
        if ($item_id === null) {
            $item_id = (int) Yii::$app->request->post('item_id', 0);
        }
        $back_params = [];
        parse_str(Yii::$app->request->post('bp'), $back_params);
        $back_params = array_filter($back_params);
        $s_info = $this->get_item_info($item_id);
        if ($s_info->featured_id == 0) {
            exit;
        }
        ?>
    <div class="or_box_head or_box_head_no_margin">
      <?php 
        echo $s_info->backend_product_description->products_name;
        ?><br>
      <?php 
        echo $s_info->featured_type->featured_type_name ?? null;
        ?>
    </div>
    <div class="row_or_wrapp">
      <div class="row_or">
        <div><?php 
        echo TEXT_INFO_DATE_ADDED;
        ?></div>
        <div><?php 
        echo \common\helpers\Date::date_format($s_info->featured_date_added, DATE_FORMAT_SHORT);
        ?></div>
      </div>
      <div class="row_or">
        <div><?php 
        echo TEXT_INFO_LAST_MODIFIED;
        ?></div>
        <div><?php 
        echo \common\helpers\Date::date_format($s_info->featured_last_modified, DATE_FORMAT_SHORT);
        ?></div>
      </div>
      <div class="row_or">
        <div><?php 
        echo TEXT_START_DATE;
        ?></div>
        <div><?php 
        echo \common\helpers\Date::datetime_short($s_info->start_date, DATE_FORMAT_SHORT);
        ?></div>
      </div>
      <div class="row_or">
        <div><?php 
        echo TEXT_INFO_EXPIRES_DATE;
        ?></div>
        <div><?php 
        echo \common\helpers\Date::datetime_short($s_info->expires_date, DATE_FORMAT_SHORT);
        ?></div>
      </div>
      <div class="row_or">
        <div><?php 
        echo TEXT_INFO_STATUS_CHANGE;
        ?></div>
        <div><?php 
        echo \common\helpers\Date::date_format($s_info->date_status_change, DATE_FORMAT_SHORT);
        ?></div>
      </div>
    </div>
    <div class="btn-toolbar btn-toolbar-order">
      <a class="btn btn-edit btn-no-margin" href="<?php 
        echo Yii::$app->url_manager->create_url(['featured/featurededit', 'id' => $s_info->featured_id, 'bp' => $back_params]);
        ?>"><?php 
        echo IMAGE_EDIT;
        ?></a><button class="btn btn-delete" onclick="return deleteItemConfirm(<?php 
        echo $item_id;
        ?>)"><?php 
        echo IMAGE_DELETE;
        ?></button>
    </div>
    </div>
    <?php 
    }
    /**
     * @deprecated
     */
    public function action_itemedit()
    {
        global $login_id;
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/featured');
        $item_id = (int) Yii::$app->request->post('item_id');
        $featured_type_id = (int) Yii::$app->request->post('featured_type_id');
        $expires_date = '';
        $status_checked_active = false;
        $products_name = '';
        if ($item_id === 0) {
            $header = IMAGE_INSERT;
        } else {
            $header = IMAGE_EDIT;
            $product_query = tep_db_query('select pd.products_name, s.* from ' . TABLE_PRODUCTS_DESCRIPTION . ' pd, ' . TABLE_FEATURED . " s where pd.language_id = '" . $languages_id . "' and pd.products_id = s.products_id and s.featured_id = '" . $item_id . "' and s.featured_type_id = '" . $featured_type_id . "' and pd.platform_id = '" . intval(\common\classes\platform::default_id()) . "' " . \common\helpers\Affiliate::where_if_exists('s'));
            $product = tep_db_fetch_array($product_query);
            if ((int) $product['status'] > 0) {
                $status_checked_active = true;
            }
            $products_name = $product['products_name'];
            $expires_date = \common\helpers\Date::date_short($product['expires_date']);
        }
        $this->layout = false;
        return $this->render('edit.tpl', ['header' => $header, 'item_id' => $item_id, 'expires_date' => $expires_date, 'status_checked_active' => $status_checked_active, 'product' => $products_name]);
    }
    public function action_submit()
    {
        \common\helpers\Translation::init('admin/featured');
        $item_id = (int) Yii::$app->request->post('item_id');
        $products_id = (int) Yii::$app->request->post('products_id');
        $featured_type_id = (int) Yii::$app->request->post('featured_type_id');
        $status = tep_db_prepare_input(Yii::$app->request->post('status', 0));
        $expires_date = \common\helpers\Date::prepare_input_date(Yii::$app->request->post('expires_date'), true);
        $start_date = \common\helpers\Date::prepare_input_date(Yii::$app->request->post('start_date'), true);
        $date_format = date_create();
        if (!$start_date || $start_date == '' || $start_date == 'NULL') {
            $start_date = $date_format ? $date_format->format(\common\helpers\Date::DATABASE_DATETIME_FORMAT) : '';
        }
        $current_datetime = $date_format ? $date_format->format(\common\helpers\Date::DATABASE_DATETIME_FORMAT) : '';
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $ret = ['result' => 0];
        $m = $item_id > 0 ? \common\models\Featured::find_one($item_id) : null;
        if (!$m) {
            $m = new \common\models\Featured();
            $m->featured_date_added = $current_datetime;
        }
        if ($m) {
            $ret = ['result' => 1];
            try {
                $m->products_id = $products_id;
                $m->featured_type_id = $featured_type_id;
                $m->status = $status;
                if (!empty($expires_date)) {
                    $m->expires_date = $expires_date;
                }
                if (!empty($start_date)) {
                    $m->start_date = $start_date;
                }
                $m->featured_last_modified = $current_datetime;
                $m->save();
                $ret['item_id'] = $m->featured_id;
            } catch (\Exception $e) {
                $ret = ['result' => 0];
                $ret['message'] = TEXT_MESSAGE_ERROR . "\n(" . $e->get_message() . ')';
            }
        }
        return $ret;
    }
    public function action_confirmitemdelete()
    {
        \common\helpers\Translation::init('admin/featured');
        $this->layout = false;
        $item_id = (int) Yii::$app->request->post('item_id');
        $s_info = $this->get_item_info($item_id);
        echo tep_draw_form('item_delete', 'featured', \common\helpers\Output::get_all_get_params(['action']) . 'action=update', 'post', 'id="item_delete" onSubmit="return deleteItem();"');
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_DELETE_FEATURED . '</div>';
        echo '<div class="col_desc">' . TEXT_INFO_DELETE_INTRO . '</div>';
        echo '<div class="col_desc"><strong>' . $s_info->backend_product_description->products_name . '</strong></div>';
        ?>
    <div class="btn-toolbar btn-toolbar-order">
    <?php 
        echo '<button class="btn btn-delete btn-no-margin">' . IMAGE_DELETE . '</button>';
        echo '<input type="button" class="btn btn-cancel" value="' . IMAGE_CANCEL . '" onClick="return resetStatement()">';
        echo tep_draw_hidden_field('item_id', $item_id);
        ?>
    </div>
    </form>
    <?php 
    }
    public function action_itemdelete()
    {
        $this->layout = false;
        $featured_id = (int) Yii::$app->request->post('item_id');
        $message_type = 'success';
        $message = TEXT_INFO_DELETED;
        tep_db_query('delete from ' . TABLE_FEATURED . " where featured_id = '" . tep_db_input($featured_id) . "'");
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


    <p class="btn-toolbar">
    <?php 
        echo '<input type="button" class="btn btn-primary" value="' . IMAGE_BACK . '" onClick="return resetStatement()">';
        ?>
    </p>
    <?php 
    }
    public function tep_set_featured_status($featured_id, $status)
    {
        if ($status == '1') {
            return tep_db_query('update ' . TABLE_FEATURED . " set status = '1', date_status_change = now() where featured_id = '" . (int) $featured_id . "'");
        } elseif ($status == '0') {
            return tep_db_query('update ' . TABLE_FEATURED . " set status = '0', date_status_change = now() where featured_id = '" . (int) $featured_id . "'");
        } else {
            return -1;
        }
    }
    public function get_item_info($item_id)
    {
        $list_query = \common\models\Featured::find()->join_with(['backendProductDescription', 'featuredType'])->select(\common\models\Featured::table_name() . '.*');
        $list_query->add_select('products_name, featured_type_name');
        $list_query->and_where(['featured_id' => $item_id]);
        $s_info = $list_query->one();
        if (empty($s_info)) {
            $s_info = new \Object_Info(['featuredType' => new \Object_Info(['featured_type_name' => '']), 'backendProductDescription' => new \Object_Info(['products_name' => '']), 'featured_date_added' => '', 'featured_last_modified' => '', 'expires_date' => '', 'date_status_change' => '', 'start_date' => '', 'featured_id' => 0]);
        }
        return $s_info;
    }
    public function action_featurededit()
    {
        $featureds_id = (int) Yii::$app->request->get('id');
        $products_id = (int) Yii::$app->request->get('products_id');
        $bp = Yii::$app->request->get('bp', []);
        $this->view->heading_title = BOX_CATALOG_FEATURED;
        $this->selected_menu = ['marketing', 'featured'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('featured/index'), 'title' => BOX_CATALOG_FEATURED];
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $s_info = null;
        $params = [];
        if (!empty($featureds_id) || !empty($products_id)) {
            $template = 'featurededit';
            if (!empty($featureds_id)) {
                $s_info = \common\models\Featured::find()->and_where(['featured_id' => $featureds_id])->with(['backendProductDescription'])->one();
                if (!empty($s_info->featured_id)) {
                    $p_info = $s_info->product;
                    unset($s_info->product);
                }
            } else {
                $s_info = new \common\models\Featured();
                $s_info->load_default_values();
            }
            if (empty($s_info->featured_id) && !empty($products_id)) {
                $p_info = \common\models\Products::find()->and_where(['products_id' => $products_id])->with(['backendDescription'])->one();
            }
            if (!empty($p_info)) {
                $params['sInfo'] = (object) \yii\helpers\Array_Helper::to_array($s_info);
                $params['pInfo'] = (object) \yii\helpers\Array_Helper::to_array($p_info);
                $params['backendProductDescription'] = \yii\helpers\Array_Helper::to_array($p_info->backend_description, ['products_name']);
                $languages_id = \Yii::$app->settings->get('languages_id');
                $featured_types_arr = \common\models\Featured_Types::find()->where(['language_id' => $languages_id])->select('featured_type_name, featured_type_id')->as_array()->index_by('featured_type_id')->column();
                if (!is_array($featured_types_arr)) {
                    $featured_types_arr = [];
                }
                $featured_types_arr[0] = BOX_CATALOG_FEATURED;
                ksort($featured_types_arr);
                $params['featured_types'] = $featured_types_arr;
            } else {
                $template = 'choose_product';
            }
        } else {
            $template = 'choose_product';
        }
        $params['back_url'] = \Yii::$app->url_manager->create_url(['featured'] + $bp);
        if ($template == 'choose_product') {
            $catalog = new \backend\components\Products_Catalog();
            return $catalog->make();
        }
        return $this->render($template, $params);
    }
    public function action_switch_status()
    {
        $id = Yii::$app->request->post('id');
        $status = Yii::$app->request->post('status');
        $this->tep_set_featured_status($id, $status == 'true' ? 1 : 0);
    }
    public function action_delete_selected()
    {
        $this->layout = false;
        $sp_ids = Yii::$app->request->post('bulkProcess', []);
        if (is_array($sp_ids) && !empty($sp_ids)) {
            $sp_ids = array_map('intval', $sp_ids);
            \common\models\Featured::delete_all(['featured_id' => $sp_ids]);
        }
    }
    public function action_sort_order()
    {
        /** @var $featured \common\models\Featured */
        $tmp_rec = null;
        $i = 0;
        $tmp_index = 0;
        $just_after_current = null;
        $sort = array_map('intval', Yii::$app->request->post('sort_data', []));
        foreach (\common\models\Featured::find()->order_by('sort_order, featured_date_added')->each() as $featured) {
            $f_id = $featured->featured_id;
            if ($f_id === $sort['current']) {
                $tmp_rec = $featured;
                continue;
            }
            if ($f_id === $sort['before']) {
                $tmp_index = $i + 1;
                $just_after_current = true;
            } elseif ($f_id === $sort['after'] || $sort['after'] === 0 && $just_after_current) {
                // next after moved item
                $tmp_index = $i;
                $i++;
                $just_after_current = null;
            }
            $featured->sort_order = $i++;
            $featured->save();
        }
        $tmp_rec->sort_order = $tmp_index;
        $tmp_rec->save();
    }
    public function action_save_current_sort()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        /** @var $featured \common\models\Featured */
        $order_by = trim(Yii::$app->request->post('order_by', ''));
        if (empty($order_by)) {
            return ['status' => 'error', 'message' => 'SortOrder is empty'];
        }
        $i = 0;
        foreach (\common\models\Featured::find()->join_with(['backendProductDescription', 'featuredType'])->select(\common\models\Featured::table_name() . '.*')->order_by($order_by)->each() as $featured) {
            $featured->sort_order = $i++;
            $featured->save();
        }
        return ['status' => 'success'];
    }
}