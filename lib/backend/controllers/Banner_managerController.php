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

use common\classes\Images;
use common\classes\platform as Platform;
use common\helpers\Affiliate;
use common\helpers\Image;
use common\helpers\Language;
use common\models\Banners;
use common\models\Banners_Groups;
use common\models\Banners_Groups_Images;
use common\models\Banners_Groups_Sizes;
use common\models\Banners_Languages;
use common\models\Banners_To_Platform;
use Yii;
use yii\db\Expression;
use yii\helpers\Array_Helper;
use yii\helpers\File_Helper;
use yii\helpers\Html;
class Banner_manager_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_TOOLS_BANNER_MANAGER'];
    public $banner_extension;
    public $dir_ok = false;
    private function banner_image_extension()
    {
        if (function_exists('imagetypes')) {
            if (imagetypes() & IMG_PNG) {
                return 'png';
            } elseif (imagetypes() & IMG_JPG) {
                return 'jpg';
            } elseif (imagetypes() & IMG_GIF) {
                return 'gif';
            }
        } elseif (function_exists('imagecreatefrompng') && function_exists('imagepng')) {
            return 'png';
        } elseif (function_exists('imagecreatefromjpeg') && function_exists('imagejpeg')) {
            return 'jpg';
        } elseif (function_exists('imagecreatefromgif') && function_exists('imagegif')) {
            return 'gif';
        }
        return false;
    }
    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);
        \common\helpers\Translation::init('admin/main');
        \common\helpers\Translation::init('admin/banner_manager');
        $this->banner_extension = $this->banner_image_extension();
        if (function_exists('imagecreate') && tep_not_null($this->banner_extension)) {
            if (is_dir(DIR_WS_IMAGES . 'graphs')) {
                if (is_writeable(DIR_WS_IMAGES . 'graphs')) {
                    $this->dir_ok = true;
                } else {
                    $this->view->error_message = ERROR_GRAPHS_DIRECTORY_NOT_WRITEABLE;
                    $this->view->error_message_type = 'danger';
                }
            } else {
                $this->view->error_message = ERROR_GRAPHS_DIRECTORY_DOES_NOT_EXIST;
                $this->view->error_message_type = 'danger';
            }
        }
    }
    public function action_index()
    {
        $this->selected_menu = ['marketing', 'banner_manager'];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('banner_manager/banneredit') . '" class="btn btn-primary btn-new-banner"><i class="icon-file-text"></i>' . IMAGE_NEW_BANNER . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('banner_manager/banner-groups-edit') . '" class="btn btn-primary btn-new-group"><i class="icon-file-text"></i>' . NEW_BANNER_GROUP . '</a>';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('marketing/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $platform = Yii::$app->request->get('platform');
        $banners_group = Yii::$app->request->get('banners_group', '');
        $group_id = Yii::$app->request->get('group_id', 0);
        $row_id = Yii::$app->request->get('row_id', 0);
        $search_title = Yii::$app->request->get('search_title', '');
        $search_group = Yii::$app->request->get('search_group', '');
        $search_file = Yii::$app->request->get('search_file', '');
        $search_text = Yii::$app->request->get('search_text', '');
        $search_status = Yii::$app->request->get('search_status', '');
        if (Platform::is_multi()) {
            $platform_id = Yii::$app->request->get('platform_id', 0);
        } else {
            $platform_id = Platform::default_id();
        }
        $tmp = [];
        $tmp[] = ['title' => TAB_IMAGES, 'not_important' => 0, 'class' => 'image-heading-cell'];
        $tmp[] = ['title' => TEXT_TITLE, 'not_important' => 0, 'class' => 'title-heading-cell'];
        $tmp[] = ['title' => TABLE_HEADING_GROUPS, 'not_important' => 0, 'class' => 'group-heading-cell'];
        if (Platform::is_multi()) {
            $tmp[] = ['title' => TABLE_HEAD_PLATFORM_NAME, 'not_important' => 0, 'class' => 'status-heading-cell'];
        }
        if (Platform::is_multi()) {
            $tmp[] = ['title' => TABLE_HEAD_PLATFORM_BANNER_ASSIGN, 'not_important' => 0, 'class' => 'status-heading-cell'];
        } else {
            $tmp[] = ['title' => TABLE_HEADING_STATUS, 'not_important' => 0, 'class' => 'status-heading-cell'];
        }
        $this->view->filters = new \stdClass();
        $this->view->filters->platform = [];
        if (isset($platform) && is_array($platform)) {
            foreach ($platform as $_platform_id) {
                if ((int) $_platform_id > 0) {
                    $this->view->filters->platform[] = (int) $_platform_id;
                }
            }
        }
        $banners_groups_arr = Banners_Groups::find(['id' => $group_id])->as_array()->one();
        $group_name = $banners_groups_arr['banners_group'];
        $platform_name = '';
        $platforms = Platform::get_list(false, true);
        foreach ($platforms as $platform) {
            if ($platform['id'] == $platform_id) {
                $platform_name = $platform['text'];
                break;
            }
        }
        $this->view->banner_table = $tmp;
        return $this->render('index', ['isMultiPlatforms' => Platform::is_multi(), 'platforms' => $platforms, 'platform_id' => $platform_id, 'group_id' => $group_id, 'row_id' => $row_id, 'group_name' => $group_id == '-1' || $group_id == -1 ? BANNERS_WITHOUT_GROUP : $group_name, 'platform_name' => $platform_id == '-1' || $platform_id == -1 ? BANNERS_WITHOUT_PLATFORM : $platform_name, 'search_title' => $search_title, 'search_file' => $search_file, 'search_text' => $search_text, 'search_status' => $search_status, 'search_group' => $search_group]);
    }
    public function action_getimage($banner_id)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $banner = Banners_Languages::find()->select(['banners_image', 'banners_title'])->where(['banners_id' => (int) $banner_id, 'language_id' => (int) $languages_id])->as_array()->one();
        $image = $this->get_image($banner);
        if ($image) {
            return $image;
        }
        $banners = Banners_Languages::find()->select(['banners_title', 'banners_title'])->where(['banners_id' => (int) $banner_id])->as_array()->all();
        if (is_array($banners)) {
            foreach ($banners as $banner) {
                $image = $this->get_image($banner);
                if ($image) {
                    return $image;
                }
            }
        }
        return '';
    }
    public function get_image($banner)
    {
        if (!isset($banner) || !isset($banner['banners_image'])) {
            return false;
        }
        if (isset($banner['banners_image']) && is_file(Images::get_fs_catalog_images_path() . $banner['banners_image'])) {
            $type = explode('/', mime_content_type(Images::get_fs_catalog_images_path() . $banner['banners_image']));
            if ($type[0] == 'image') {
                return tep_image(HTTP_CATALOG_SERVER . DIR_WS_CATALOG_IMAGES . $banner['banners_image'], $banner['banners_title']);
            } else {
                return '<span class="ico-video"></span>';
            }
        } elseif (isset($banner['banners_image']) && is_file(DIR_FS_CATALOG . $banner['banners_image'])) {
            $type = explode('/', mime_content_type(DIR_FS_CATALOG . $banner['banners_image']));
            if ($type[0] == 'image') {
                return tep_image(HTTP_CATALOG_SERVER . DIR_WS_CATALOG_IMAGES . $banner['banners_image'], $banner['banners_title']);
            } else {
                return '<span class="ico-video"></span>';
            }
        }
        return false;
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        $_search = Yii::$app->request->get('search', 10);
        $search = Array_Helper::get_value($_search, ['value'], '');
        $response_list = [];
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $output);
        $group_id = $output['group_id'];
        if (Platform::is_multi()) {
            $platform_id = $output['platform_id'];
        } else {
            $platform_id = Platform::default_id();
        }
        $search_title = $output['search_title'] ?? null;
        $search_file = $output['search_file'] ?? null;
        $search_text = $output['search_text'] ?? null;
        $search_status = $output['search_status'] ?? null;
        $empty_groups = $output['empty_groups'] ?? null;
        $search_group = $output['search_group'] ?? null;
        if ($group_id || $search_title || $search_file || $search_text || $search) {
            if ($group_id == '-1' || $group_id == -1) {
                $group_id = 0;
            }
            $banners_query = Banners::find()->alias('b')->select(['b.banners_id', 'b.status', 'b.sort_order', 'bl.banners_title', 'bl.banners_image', 'bg.banners_group', 'bl.banners_html_text', 'bl.banners_image'])->left_join(Banners_Groups::table_name() . ' bg', 'b.group_id = bg.id');
            if ($platform_id != '-1' && $platform_id != -1) {
                $banners_query->left_join(Banners_To_Platform::table_name() . ' b2p', 'b.banners_id = b2p.banners_id');
            }
            $banners_query->left_join(Banners_Languages::table_name() . ' bl', 'b.banners_id = bl.banners_id and bl.language_id = ' . (int) $languages_id);
            if ($group_id || $group_id === 0) {
                $banners_query->where(['b.group_id' => $group_id]);
            }
            if ($search) {
                $banners_query->and_where(['or', ['like', 'bl.banners_title', $search], ['like', 'bl.banners_image', $search], ['like', 'bl.banners_html_text', $search]]);
            }
            if ($search_title) {
                $banners_query->and_where(['like', 'bl.banners_title', $search_title]);
            }
            if ($search_file) {
                $banners_query->and_where(['like', 'bl.banners_image', $search_file]);
            }
            if ($search_text) {
                $banners_query->and_where(['like', 'bl.banners_html_text', $search_text]);
            }
            if ($search_status) {
                $banners_query->and_where(['b.status' => $search_status == 'on' ? 1 : 0]);
            }
            if ($platform_id == '-1' || $platform_id == -1) {
                $banners_query->and_where('NOT EXISTS(SELECT 1 FROM ' . Banners_To_Platform::table_name() . ' b2p WHERE b2p.banners_id=b.banners_id)');
            } elseif ($platform_id) {
                $banners_query->and_where(['b2p.platform_id' => $platform_id]);
            }
            $banners_query->order_by('b.sort_order, bl.banners_title');
            $all_banners_count = $banners_query->count();
            $banners_arr = $banners_query->limit($length)->offset($start)->as_array()->all();
            foreach ($banners_arr as $banners) {
                $tmp = [];
                $tmp['id'] = $banners['banners_id'];
                $tmp['name'] = 'banners_id';
                $tmp['image'] = $this->action_getimage($banners['banners_id']);
                if ($search_title) {
                    $tmp['title'] = str_ireplace($search_title, '<span class="keywords">' . $search_title . '</span>', strip_tags($banners['banners_title']));
                } else {
                    $tmp['title'] = $banners['banners_title'];
                }
                if ($search_file) {
                    $tmp['file'] = str_ireplace($search_file, '<span class="keywords">' . $search_file . '</span>', strip_tags($banners['banners_image']));
                } else {
                    $tmp['file'] = $banners['banners_image'];
                }
                if ($search_text) {
                    $tmp['text'] = str_ireplace($search_text, '<span class="keywords">' . $search_text . '</span>', strip_tags($banners['banners_html_text']));
                } else {
                    $tmp['text'] = $banners['banners_html_text'];
                }
                $tmp['group'] = $banners['banners_group'];
                if (Platform::is_multi()) {
                    $platforms = '';
                    $public_checkbox = '';
                    $banners_to_platform = Banners_To_Platform::find()->where(['banners_id' => $banners['banners_id']])->as_array()->all();
                    $banner_statuses = [];
                    foreach ($banners_to_platform as $status) {
                        $sub_row_key = $status['banners_id'] . '^' . $status['platform_id'];
                        $banner_statuses[$sub_row_key] = 1;
                    }
                    foreach (Platform::get_list(false, true) as $platform_variant) {
                        $sub_row_key = $banners['banners_id'] . '^' . $platform_variant['id'];
                        $sub_row_disabled = !isset($banner_statuses[$sub_row_key]);
                        $_row_key = $banners['banners_id'] . '-' . $platform_variant['id'];
                        if ($platform_variant['is_marketplace'] == 0) {
                            $platforms .= '<div id="banner-' . $_row_key . '"' . ($sub_row_disabled ? ' class="platform-disable"' : '') . '>' . $platform_variant['text'] . '</div>';
                            $public_checkbox .= '<div>' . Html::checkbox('platform[' . $banners['banners_id'] . '][' . $platform_variant['id'] . ']', !$sub_row_disabled, ['value' => $_row_key, 'class' => 'check_on_off']) . '</div>';
                        }
                    }
                    $tmp['platform-name'] = $platforms;
                    $tmp['platform-status'] = $public_checkbox;
                }
                $tmp['status'] = '<input type="checkbox" value=' . $banners['banners_id'] . ' name="status" class="check_on_off"' . ($banners['status'] == '1' ? ' checked="checked"' : '') . '>';
                $response_list[] = $this->banner_row($tmp);
            }
        } elseif (!$platform_id && Platform::is_multi()) {
            foreach (Platform::get_list(false, true) as $platform) {
                $tmp = [];
                $tmp['id'] = $platform['id'];
                $tmp['name'] = 'platform_id';
                $tmp['platform-name'] = '<div class="platform-name">' . $platform['text'] . '</div>';
                $tmp['count'] = Banners_To_Platform::find()->where(['platform_id' => $platform['id']])->count();
                $response_list[] = $this->banner_row($tmp);
            }
            $tmp = [];
            $tmp['platform-name'] = '<div class="platform-name">' . BANNERS_WITHOUT_PLATFORM . '</div>';
            $tmp['id'] = '-1';
            $tmp['name'] = 'platform_id';
            $tmp['count'] = Banners::find()->alias('b')->where('NOT EXISTS(SELECT 1 FROM ' . Banners_To_Platform::table_name() . ' b2p WHERE b2p.banners_id=b.banners_id)')->count();
            $response_list[] = $this->banner_row($tmp);
        } else {
            $banners_groups = Banners_Groups::find()->as_array()->all();
            $response_list_tmp = [];
            foreach ($banners_groups as $banners_group) {
                if ($search_group && !str_contains(strtolower($banners_group['banners_group']), strtolower($search_group))) {
                    continue;
                }
                $tmp = [];
                $tmp['group'] = $banners_group['banners_group'];
                $tmp['id'] = $banners_group['id'];
                $tmp['name'] = 'group_id';
                if ($platform_id == '-1') {
                    $tmp['count'] = Banners::find()->alias('b')->where(['group_id' => $banners_group['id']])->and_where('NOT EXISTS(SELECT 1 FROM ' . Banners_To_Platform::table_name() . ' b2p WHERE b2p.banners_id=b.banners_id)')->count();
                } else {
                    $tmp['count'] = Banners::find()->alias('b')->inner_join(Banners_To_Platform::table_name() . ' b2p', 'b.banners_id = b2p.banners_id and b2p.platform_id = ' . $platform_id)->where(['b.group_id' => $banners_group['id']])->count();
                }
                if (!$empty_groups && !$tmp['count']) {
                    continue;
                }
                $response_list_tmp[] = $tmp;
            }
            usort($response_list_tmp, function ($a, $b) {
                return $a['count'] > $b['count'] ? -1 : 1;
            });
            foreach ($response_list_tmp as $item) {
                $response_list[] = $this->banner_row($item);
            }
            $tmp = [];
            $tmp['group'] = BANNERS_WITHOUT_GROUP;
            $tmp['id'] = '-1';
            $tmp['name'] = 'group_id';
            if ($platform_id == '-1') {
                $tmp['count'] = Banners::find()->alias('b')->where(['group_id' => 0])->and_where('NOT EXISTS(SELECT 1 FROM ' . Banners_To_Platform::table_name() . ' b2p WHERE b2p.banners_id=b.banners_id)')->count();
            } else {
                $tmp['count'] = Banners::find()->alias('b')->inner_join(Banners_To_Platform::table_name() . ' b2p', 'b.banners_id = b2p.banners_id and b2p.platform_id = ' . $platform_id)->where(['group_id' => 0])->count();
            }
            if ($empty_groups && !$tmp['count']) {
                $response_list[] = $this->banner_row($tmp);
            }
        }
        if (!isset($all_banners_count)) {
            $all_banners_count = count($response_list);
        }
        $response = ['draw' => $draw, 'recordsTotal' => $all_banners_count, 'recordsFiltered' => $all_banners_count, 'data' => $response_list];
        echo json_encode($response);
    }
    public function banner_row($data)
    {
        $row = [];
        $row[] = '<div class="batch-cell"><input type="checkbox" name="' . $data['name'] . '" value="' . $data['id'] . '"/></div>';
        $row[] = '<div class="sort-cell" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '"></div>';
        $row[] = '<div class="image-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '">' . ($data['image'] ?? '') . '</div>';
        $row[] = '<div class="title-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '">' . ($data['title'] ?? '') . '</div>';
        $row[] = '<div class="group-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '">' . ($data['group'] ?? '') . '</div>';
        $row[] = '<div class="file-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '">' . ($data['file'] ?? '') . '</div>';
        $row[] = '<div class="text-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '">' . ($data['text'] ?? '') . '</div>';
        $row[] = '<div class="platform-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '"><div class="platforms-cell">' . ($data['platform-name'] ?? '') . '</div></div>';
        $row[] = '<div class="platform-cell"><div class="platforms-cell-checkbox">' . ($data['platform-status'] ?? '') . '</div></div>';
        $row[] = '<div class="status-cell">' . ($data['status'] ?? '') . '</div>';
        $row[] = '<div class="count-cell double-click" data-id="' . $data['id'] . '" data-name="' . $data['name'] . '">' . ($data['count'] ?? '') . '</div>';
        return $row;
    }
    public function get_banner($b_id)
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        return Banners::find()->alias('b')->select(['b.banners_id', 'b.status', 'b.sort_order', 'bl.banners_title', 'b.date_added', 'b.date_scheduled', 'b.expires_date', 'b.expires_impressions', 'b.date_status_change', 'bl.banners_image', 'bg.banners_group', 'bl.banners_html_text', 'bl.banners_image'])->left_join(Banners_Groups::table_name() . ' bg', 'b.group_id = bg.id')->left_join(Banners_Languages::table_name() . ' bl', 'b.banners_id = bl.banners_id and language_id = ' . (int) $languages_id)->where(['b.banners_id' => $b_id])->as_array()->one();
    }
    public function action_view()
    {
        $id = Yii::$app->request->get('id');
        $name = Yii::$app->request->get('name');
        $platform_id = Yii::$app->request->get('platform_id');
        $row_id = Yii::$app->request->get('platform_id');
        $this->layout = false;
        if (!$id || !$name) {
            return '';
        }
        switch ($name) {
            case 'banners_id':
                $b_info = $this->get_banner($id);
                $b_platform = '';
                $banners_platform = tep_db_query('select platform_name from ' . TABLE_PLATFORMS . ' p left join ' . TABLE_BANNERS_TO_PLATFORM . " bt on p.platform_id = bt.platform_id where bt.banners_id ='" . $id . "' ");
                if (tep_db_num_rows($banners_platform) > 0) {
                    while ($banners_platform_result = tep_db_fetch_array($banners_platform)) {
                        $b_platform .= '<div class="platform_res">' . $banners_platform_result['platform_name'] . '</div>';
                    }
                }
                return $this->render('bar-banner', ['b_platform' => $b_platform, 'bInfo' => $b_info, 'image' => $this->action_getimage($id)]);
            case 'group_id':
                $title = Banners_Groups::find_one(['id' => $id])->banners_group;
                $count = 0;
                if ($id == '-1') {
                    $id = 0;
                }
                if ($platform_id == '-1') {
                    $count = Banners::find()->alias('b')->where(['b.group_id' => $id])->and_where('NOT EXISTS(SELECT 1 FROM ' . Banners_To_Platform::table_name() . ' b2p WHERE b2p.banners_id=b.banners_id)')->count();
                } else {
                    $count = Banners::find()->alias('b')->inner_join(Banners_To_Platform::table_name() . ' b2p', 'b.banners_id = b2p.banners_id and b2p.platform_id = ' . $platform_id)->where(['group_id' => $id])->count();
                }
                return $this->render('bar-group', ['name' => $id == '-1' ? BANNERS_WITHOUT_GROUP : $title, 'count' => $count, 'platform_id' => $platform_id, 'group_id' => $id, 'row_id' => $row_id]);
            case 'platform_id':
                if ($id == '-1') {
                    $count = Banners::find()->alias('b')->where('NOT EXISTS(SELECT 1 FROM ' . Banners_To_Platform::table_name() . ' b2p WHERE b2p.banners_id=b.banners_id)')->count();
                } else {
                    $count = Banners_To_Platform::find()->where(['platform_id' => $id])->count();
                }
                return $this->render('bar-platform', ['name' => $id == '-1' ? BANNERS_WITHOUT_PLATFORM : Platform::name($id), 'count' => $count]);
        }
    }
    public function action_banner_duplicate()
    {
        $id = (int) \Yii::$app->request->post('banners_id', 0);
        $ret = false;
        if (!$id) {
            return json_encode(['error' => 'no ID']);
        }
        $from_banner = \common\models\Banners::find_one($id);
        if (!$from_banner) {
            return json_encode(['error' => 'no banner with banner_id = ' . $id]);
        }
        $new_banner = new \common\models\Banners();
        try {
            $new_banner->attributes = $from_banner->attributes;
            $new_banner->banners_id = null;
            $new_banner->is_new_record = true;
            $new_banner->status = 0;
            $new_banner->group_id = $from_banner->group_id;
            $new_banner->date_added = new Expression('NOW()');
            $new_banner->save(false);
            $banners_id = $new_banner->banners_id;
        } catch (\Exception $ex) {
            \Yii::warning($ex->get_message() . ' #### ' . print_r($new_banner, 1), 'TLDEBU_banner_save_error');
            return json_encode(['error' => BANNER_NOT_COPIED]);
        }
        if (!$banners_id) {
            return json_encode(['error' => BANNER_NOT_COPIED]);
        }
        $from_b_ls = Banners_Languages::findall(['banners_id' => $from_banner->banners_id]);
        if (!empty($from_b_ls) && is_array($from_b_ls)) {
            foreach ($from_b_ls as $from_bl) {
                $to_bl = new Banners_Languages();
                try {
                    $to_bl->attributes = $from_bl->attributes;
                    $to_bl->blang_id = null;
                    $to_bl->is_new_record = true;
                    $to_bl->banners_image = Images::move_image($from_bl->banners_image, 'banners' . DIRECTORY_SEPARATOR . $banners_id);
                    $to_bl->banners_id = $banners_id;
                    $to_bl->save(false);
                    Images::create_webp($to_bl->banners_image);
                } catch (\Exception $ex) {
                    \Yii::warning($ex->get_message() . ' #### ' . print_r($to_bl, 1), 'TLDEBU_banner_lang_save_error');
                }
            }
        }
        $from_b_ls = Banners_Groups_Images::findall(['banners_id' => $from_banner->banners_id]);
        if (!empty($from_b_ls) && is_array($from_b_ls)) {
            foreach ($from_b_ls as $from_bl) {
                $to_bl = new Banners_Groups_Images();
                try {
                    $to_bl->attributes = $from_bl->attributes;
                    $to_bl->id = null;
                    $to_bl->is_new_record = true;
                    $to_bl->image = Images::move_image($from_bl->image, 'banners' . DIRECTORY_SEPARATOR . $banners_id);
                    $to_bl->banners_id = $banners_id;
                    $to_bl->save(false);
                    Images::create_webp($to_bl->image);
                } catch (\Exception $ex) {
                    \Yii::warning($ex->get_message() . ' #### ' . print_r($to_bl, 1), 'TLDEBU_banner_lang_save_error');
                }
            }
        }
        $from_b_ls = Banners_To_Platform::findall(['banners_id' => $from_banner->banners_id]);
        if (!empty($from_b_ls) && is_array($from_b_ls)) {
            foreach ($from_b_ls as $from_bl) {
                $to_bl = new Banners_To_Platform();
                try {
                    $to_bl->banners_id = $banners_id;
                    $to_bl->platform_id = $from_bl->platform_id;
                    $to_bl->save(false);
                } catch (\Exception $ex) {
                    \Yii::warning($ex->get_message() . ' #### ' . print_r($to_bl, 1), 'TLDEBU_banner_platform_save_error');
                }
            }
        }
        return json_encode(['success' => BANNER_COPIED, 'banners_id' => $banners_id]);
    }
    public function action_submit()
    {
        global $login_id;
        $request = Yii::$app->request->post();
        \common\helpers\Translation::init('admin/banner_manager');
        $banners_id = (int) Yii::$app->request->post('banners_id', 0);
        $platforms = Platform::get_list(false, true);
        $sql_data_array = [];
        $expires_date = 'null';
        if (!empty(\Yii::$app->request->post('expires_date'))) {
            $expires_date = \common\helpers\Date::prepare_input_date(\Yii::$app->request->post('expires_date'), true);
        }
        $sql_data_array['expires_date'] = $expires_date;
        $expires_impressions = tep_db_prepare_input(\Yii::$app->request->post('expires_impressions'));
        if (tep_not_null($expires_impressions)) {
            $sql_data_array['expires_impressions'] = $expires_impressions;
        }
        $date_scheduled = 'null';
        if (!empty(\Yii::$app->request->post('date_scheduled'))) {
            $date_scheduled = \common\helpers\Date::prepare_input_date(\Yii::$app->request->post('date_scheduled'), true);
        }
        $sql_data_array['date_scheduled'] = $date_scheduled;
        $sql_data_array['status'] = isset($request['status']) ? 1 : 0;
        if (Affiliate::is_logged()) {
            $sql_data_array['affiliate_id'] = $login_id;
        }
        $sql_data_array['sort_order'] = tep_db_prepare_input($request['sort_order']);
        $sql_data_array['nofollow'] = isset($request['nofollow']) && $request['nofollow'] ? 1 : 0;
        $sql_data_array['group_id'] = tep_db_prepare_input($request['group_id']);
        if ($banners_id == 0) {
            tep_db_perform(Banners::table_name(), array_merge($sql_data_array, ['date_added' => 'now()']));
            $banners_id = tep_db_insert_id();
            Yii::$app->request->set_body_params(['banners_id' => $banners_id]);
            $success_message = defined('SUCCESS_BANNER_INSERTED') ? SUCCESS_BANNER_INSERTED : 'Inserted';
        } else {
            $sql_data_array['banners_id'] = $banners_id;
            $check = tep_db_fetch_array(tep_db_query('SELECT COUNT(*) AS c FROM ' . Banners::table_name() . " WHERE banners_id='" . (int) $banners_id . "'"));
            if ($check['c'] == 0) {
                tep_db_perform(Banners::table_name(), array_merge($sql_data_array, ['date_added' => 'now()']));
            } else {
                tep_db_perform(Banners::table_name(), array_merge($sql_data_array, ['date_status_change' => 'now()']), 'update', "banners_id = '" . (int) $banners_id . "'");
            }
            $success_message = defined('SUCCESS_BANNER_UPDATED') ? SUCCESS_BANNER_UPDATED : 'Updated';
        }
        foreach ($platforms as $_platform_info) {
            if (isset($request['platform_status'][$_platform_info['id']])) {
                tep_db_query('REPLACE INTO ' . TABLE_BANNERS_TO_PLATFORM . " (banners_id, platform_id) VALUES('" . (int) $banners_id . "', '" . (int) $_platform_info['id'] . "')");
            } else {
                tep_db_query('DELETE FROM  ' . TABLE_BANNERS_TO_PLATFORM . " WHERE banners_id='" . (int) $banners_id . "' AND platform_id='" . (int) $_platform_info['id'] . "'");
            }
        }
        $languages = \common\helpers\Language::get_languages();
        $old_image = [];
        $delete_old_image = [];
        foreach ($languages as $language) {
            $language_id = $language['id'];
            $banner_language = Banners_Languages::find_one(['banners_id' => $banners_id, 'language_id' => $language_id]);
            if (!$banner_language) {
                $banner_language = new Banners_Languages();
                $banner_language->banners_id = $banners_id;
                $banner_language->language_id = $language_id;
            }
            $old_image[$language_id] = $banner_language->banners_image;
            $banner_language->banners_title = $request['banners_title'][$language_id] ?? '';
            $banner_language->banners_url = $request['banners_url'][$language_id] ?? '';
            $banner_language->target = $request['target'][$language_id] ?? 0;
            $banner_language->banner_display = $request['banner_display'][$language_id] ?? 0;
            $banner_language->text_position = $request['text_position'][$language_id] ?? 0;
            $banner_language->banners_html_text = $request['banners_html_text'][$language_id] ?? 0;
            $banner_language->banners_image = Image::prepare_saving_image($banner_language->banners_image, $request['banners_image'][$language_id] ?? '', $request['banners_image_upload'][$language_id] ?? '', 'banners' . DIRECTORY_SEPARATOR . $banners_id, $request['banners_image_delete'][$language_id] ?? '');
            $delete_old_image[$language_id] = false;
            if (isset($request['banners_image_delete'][$language_id]) && $request['banners_image_delete'][$language_id] == 1 && $banner_language->banners_image) {
                $delete_old_image[$language_id] = true;
            }
            if ($banner_language->banners_title || $banner_language->banners_url || $banner_language->banners_html_text || $banner_language->banners_image) {
                $banner_language->save();
            }
            if ($banner_language->errors && count($banner_language->errors) > 0) {
                return json_encode(['error' => 'db error']);
            }
        }
        $wrong = Banners_To_Platform::find()->alias('b2p')->where('NOT EXISTS(SELECT 1 FROM ' . Banners::table_name() . ' b WHERE b2p.banners_id=b.banners_id)')->as_array()->all();
        foreach ($wrong as $row) {
            Banners_To_Platform::delete_all(['banners_id' => $row['banners_id']]);
        }
        self::save_group_images($banners_id, $old_image, $delete_old_image);
        foreach (\common\helpers\Hooks::get_list('banner_manager/submit') as $filename) {
            include $filename;
        }
        return json_encode(['text' => $success_message, 'html' => $this->action_banneredit()]);
    }
    public function action_delete_confirm()
    {
        $ids = Yii::$app->request->get('bID', []);
        $this->layout = false;
        $b_info = [];
        foreach ($ids as $id) {
            $b_info[] = $this->get_banner($id);
        }
        return $this->render('bar-banner-delete', ['bInfo' => $b_info, 'ids' => $ids]);
    }
    public function action_delete()
    {
        $this->layout = false;
        $banners_ids = Yii::$app->request->post('bID', []);
        $delete_image = Yii::$app->request->post('delete_image', false);
        $this->delete_banners($banners_ids, $delete_image);
        return json_encode(['success' => SUCCESS_BANNER_REMOVED]);
    }
    public function delete_banners($banners_ids, $delete_images)
    {
        foreach ($banners_ids as $banners_id) {
            if ($delete_images) {
                try {
                    File_Helper::remove_directory(DIR_FS_CATALOG_IMAGES . 'banners/' . $banners_id);
                } catch (\Exception $ex) {
                    \Yii::warning($ex->get_message() . ' #### banner image delete error. id = ' . $banners_id);
                }
            }
            tep_db_query('delete from ' . TABLE_BANNERS_HISTORY . " where banners_id = '" . (int) $banners_id . "'");
            Banners::delete_all(['banners_id' => (int) $banners_id]);
            Banners_Languages::delete_all(['banners_id' => (int) $banners_id]);
            Banners_To_Platform::delete_all(['banners_id' => (int) $banners_id]);
            if (function_exists('imagecreate') && tep_not_null($this->banner_extension)) {
                if (is_file(DIR_WS_IMAGES . 'graphs/banner_infobox-' . $banners_id . '.' . $this->banner_extension)) {
                    if (is_writeable(DIR_WS_IMAGES . 'graphs/banner_infobox-' . $banners_id . '.' . $this->banner_extension)) {
                        unlink(DIR_WS_IMAGES . 'graphs/banner_infobox-' . $banners_id . '.' . $this->banner_extension);
                    }
                }
                if (is_file(DIR_WS_IMAGES . 'graphs/banner_yearly-' . $banners_id . '.' . $this->banner_extension)) {
                    if (is_writeable(DIR_WS_IMAGES . 'graphs/banner_yearly-' . $banners_id . '.' . $this->banner_extension)) {
                        unlink(DIR_WS_IMAGES . 'graphs/banner_yearly-' . $banners_id . '.' . $this->banner_extension);
                    }
                }
                if (is_file(DIR_WS_IMAGES . 'graphs/banner_monthly-' . $banners_id . '.' . $this->banner_extension)) {
                    if (is_writeable(DIR_WS_IMAGES . 'graphs/banner_monthly-' . $banners_id . '.' . $this->banner_extension)) {
                        unlink(DIR_WS_IMAGES . 'graphs/banner_monthly-' . $banners_id . '.' . $this->banner_extension);
                    }
                }
                if (is_file(DIR_WS_IMAGES . 'graphs/banner_daily-' . $banners_id . '.' . $this->banner_extension)) {
                    if (is_writeable(DIR_WS_IMAGES . 'graphs/banner_daily-' . $banners_id . '.' . $this->banner_extension)) {
                        unlink(DIR_WS_IMAGES . 'graphs/banner_daily-' . $banners_id . '.' . $this->banner_extension);
                    }
                }
            }
            self::delete_banner_group_images($banners_id);
        }
    }
    public function action_switch_status()
    {
        $ids = Yii::$app->request->post('ids');
        $status = Yii::$app->request->post('status');
        foreach ($ids as $id) {
            $banner = Banners::find_one(['banners_id' => (int) $id]);
            $banner->status = $status == 'true' ? 1 : 0;
            $banner->date_status_change = new Expression('NOW()');
            $banner->save(false);
        }
    }
    public function action_switch_status_platform()
    {
        $ids = Yii::$app->request->post('ids', []);
        $status = Yii::$app->request->post('status');
        foreach ($ids as $id) {
            if (strpos($id, '-') !== false) {
                list($bid, $pid) = explode('-', $id, 2);
                if ($status == 'true') {
                    tep_db_query('REPLACE INTO ' . TABLE_BANNERS_TO_PLATFORM . " (banners_id, platform_id) VALUES('" . (int) $bid . "', '" . (int) $pid . "')");
                } else {
                    tep_db_query('DELETE FROM  ' . TABLE_BANNERS_TO_PLATFORM . " WHERE banners_id='" . (int) $bid . "' AND platform_id='" . (int) $pid . "'");
                }
            } else if ($status == 'true') {
                tep_db_query('REPLACE INTO ' . TABLE_BANNERS_TO_PLATFORM . " (banners_id, platform_id) VALUES('" . (int) $id . "', '" . (int) Platform::first_id() . "')");
            } else {
                tep_db_query('DELETE FROM  ' . TABLE_BANNERS_TO_PLATFORM . " WHERE banners_id='" . (int) $id . "' AND platform_id='" . (int) Platform::first_id() . "'");
            }
        }
    }
    public function action_banneredit()
    {
        if (Yii::$app->request->get('popup')) {
            $this->layout = false;
        }
        if (Yii::$app->request->is_post) {
            $banners_id = (int) Yii::$app->request->get_body_param('banners_id');
        } else {
            $banners_id = (int) Yii::$app->request->get('banners_id');
        }
        $platform_id = (int) Yii::$app->request->get('platform_id', 0);
        $group_id = (int) Yii::$app->request->get('group_id', 0);
        $row_id = (int) Yii::$app->request->get('row_id', 0);
        $popup = (int) Yii::$app->request->get('popup', false);
        $this->top_buttons[] = '<span class="btn btn-confirm">' . IMAGE_SAVE . '</span>';
        if (!$banners_id) {
            $banner_query = tep_db_fetch_array(tep_db_query('select MAX(banners_id) as max_id from ' . Banners::table_name()));
            $banners_id = $banner_query['max_id'] + 1;
            if (!$popup) {
                return $this->redirect(Yii::$app->url_manager->create_url(['banner_manager/banneredit', 'banners_id' => $banners_id, 'platform_id' => $platform_id, 'group_id' => $group_id, 'row_id' => $row_id]));
            }
        }
        if ($banners_id > 0) {
            $banner_query = tep_db_query('select * from ' . Banners::table_name() . ' where banners_id = ' . $banners_id);
            $banner = tep_db_fetch_array($banner_query);
        }
        $c_info = new \Object_Info($banner);
        $groups_array = [['id' => '', 'text' => '']];
        $groups = Banners_Groups::find()->as_array()->all();
        foreach ($groups as $group) {
            $groups_array[] = ['id' => $group['id'], 'text' => $group['banners_group']];
        }
        $banner_statuses = [];
        $platform_statuses = [];
        $get_statuses_r = tep_db_query('SELECT banners_id, platform_id FROM ' . TABLE_BANNERS_TO_PLATFORM . " WHERE banners_id='" . (int) $banners_id . "'");
        while ($get_status = tep_db_fetch_array($get_statuses_r)) {
            $sub_row_key = $get_status['platform_id'];
            $banner_statuses[$sub_row_key] = 1;
        }
        $banners_data = [];
        $c_description = [];
        $main_desc = [];
        $languages = \common\helpers\Language::get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $languages[$i]['logo'] = $languages[$i]['image'];
            $c_description[$i]['code'] = $languages[$i]['code'];
            $banner_description_query = tep_db_query('select * from ' . Banners::table_name() . ' b ' . ' left join ' . TABLE_BANNERS_LANGUAGES . " bl on b.banners_id = bl.banners_id and bl.language_id = '" . (int) $languages[$i]['id'] . "' " . "where   b.banners_id = '" . $banners_id . "'  " . ' and b.affiliate_id=0 ');
            if (tep_db_num_rows($banner_description_query) > 0) {
                $banner_data = tep_db_fetch_array($banner_description_query);
            }
            \common\helpers\Php8::null_props($banner_data, ['banners_title', 'banners_url', 'banner_display', 'target', 'svg', 'banners_html_text', 'banners_image', 'text_position', 'banners_group', 'banner_type', 'date_scheduled', 'expires_date', 'sort_order', 'nofollow']);
            $c_description[$i]['language_id'] = $languages[$i]['id'];
            $c_description[$i]['banners_title'] = tep_draw_input_field('banners_title[' . $languages[$i]['id'] . ']', $banner_data['banners_title'], 'class="form-control banner-title"');
            $c_description[$i]['banners_url'] = tep_draw_input_field('banners_url[' . $languages[$i]['id'] . ']', $banner_data['banners_url'], 'class="form-control"');
            $c_description[$i]['bannerUrl'] = 'banners_url[' . $languages[$i]['id'] . ']';
            $c_description[$i]['target'] = tep_draw_checkbox_field('target[' . $languages[$i]['id'] . ']', 0, $banner_data['target'] == 1, '', 'class="form-control"');
            $c_description[$i]['banner_display'] = $banner_data['banner_display'];
            $c_description[$i]['banner_display_name'] = 'banner_display[' . $languages[$i]['id'] . ']';
            $c_description[$i]['svg'] = $banner_data['svg'];
            $c_description[$i]['svg_url'] = Yii::$app->url_manager->create_url(['banner_manager/banner-editor', 'language_id' => $languages[$i]['id'], 'banners_id' => $banners_id]);
            $c_description[$i]['banners_html_text'] = tep_draw_textarea_field('banners_html_text[' . $languages[$i]['id'] . ']', 'soft', '40', '15', $banner_data['banners_html_text'], 'class="form-control ck-editor"');
            $c_description[$i]['banners_image'] = '<div class="banner_image">' . '<div class="upload" data-name="banners_image[' . $languages[$i]['id'] . ']" data-value="' . \common\helpers\Output::output_string($banner_data['banners_image']) . '"></div>' . '</div>';
            $c_description[$i]['name'] = 'banners_image[' . $languages[$i]['id'] . ']';
            //$cDescription[$i]['value'] = $banner_data['banners_image'];
            $c_description[$i]['upload'] = 'banners_image_upload[' . $languages[$i]['id'] . ']';
            $c_description[$i]['delete'] = 'banners_image_delete[' . $languages[$i]['id'] . ']';
            $c_description[$i]['name_video'] = 'banners_video[' . $languages[$i]['id'] . ']';
            //$cDescription[$i]['value_video'] = $banner_data['banners_image'];
            $c_description[$i]['upload_video'] = 'banners_video_upload[' . $languages[$i]['id'] . ']';
            $c_description[$i]['delete_video'] = 'banners_video_delete[' . $languages[$i]['id'] . ']';
            if ($banner_data['banner_display'] == 4) {
                $c_description[$i]['value'] = '';
                $c_description[$i]['value_video'] = $banner_data['banners_image'];
            } else {
                $c_description[$i]['value'] = $banner_data['banners_image'];
                $c_description[$i]['value_video'] = '';
            }
            if (is_file(Images::get_fs_catalog_images_path() . $c_description[$i]['value'])) {
                $type = explode('/', mime_content_type(Images::get_fs_catalog_images_path() . $c_description[$i]['value']));
                $c_description[$i]['type'] = $type[0];
            } elseif (is_file(DIR_FS_CATALOG . $c_description[$i]['value'])) {
                $type = explode('/', mime_content_type(DIR_FS_CATALOG . $c_description[$i]['value']));
                $c_description[$i]['type'] = $type[0];
            }
            $c_description[$i]['text_position'] = $banner_data['text_position'];
            $c_description[$i]['text_position_name'] = 'text_position[' . $languages[$i]['id'] . ']';
            $main_desc['banners_group'] = tep_draw_pull_down_menu('group_id', $groups_array, $group_id ?: $banner_data['group_id'], 'class="form-control"');
            $main_desc['date_scheduled'] = '<input type="text" name="date_scheduled" value="' . \common\helpers\Date::format_date_time_js($banner_data && $banner_data['date_scheduled'] > 0 ? $banner_data['date_scheduled'] : '') . '" class="form-control datepicker">';
            $main_desc['expires_date'] = '<input type="text" name="expires_date" value="' . \common\helpers\Date::format_date_time_js($banner_data && $banner_data['expires_date'] > 0 ? $banner_data['expires_date'] : '') . '" class="form-control datepicker">';
            $main_desc['status'] = tep_draw_checkbox_field('status', '1', isset($banner_data['status']) && $banner_data['status'] ? true : false, '', 'class="check_on_off"');
            $main_desc['sort_order'] = tep_draw_input_field('sort_order', $banner_data ? $banner_data['sort_order'] : '', 'class="form-control"');
            $main_desc['nofollow'] = tep_draw_checkbox_field('nofollow', '', $banner_data ? $banner_data['nofollow'] : '', 'class="form-control"');
            $banners_data = $main_desc;
        }
        $banners_data['lang'] = $c_description;
        if (Platform::is_multi()) {
            foreach (Platform::get_list(false, true) as $_platform_info) {
                $status = isset($banner_statuses[$_platform_info['id']]) ? true : false;
                if ($_platform_info['id'] == $platform_id) {
                    $status = true;
                }
                $platform_statuses[$_platform_info['id']] = tep_draw_checkbox_field('platform_status[' . $_platform_info['id'] . ']', '1', $status, '', 'class="check_on_off platform-status" data-platform-id="' . $_platform_info['id'] . '"');
            }
        } else {
            $platform_statuses = Html::hidden_input('platform_status[' . Platform::first_id() . ']', 1);
        }
        $banners_data['platform_statuses'] = $platform_statuses;
        $this->selected_menu = ['marketing', 'banner_manager'];
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
        }
        $text_new_or_edit = $banners_id == 0 ? TEXT_BANNER_INSERT : TEXT_BANNER_EDIT;
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('banner_manager/index'), 'title' => $text_new_or_edit];
        foreach (\common\helpers\Hooks::get_list('banner_manager/banneredit') as $filename) {
            include $filename;
        }
        $render_data = [
            'banners_id' => $banners_id,
            'cInfo' => $c_info,
            'languages' => $languages,
            //'cDescription' => $cDescription,
            //'mainDesc'=>$mainDesc,
            'banners_data' => $banners_data,
            'platforms' => Platform::get_list(false, true),
            'first_platform_id' => Platform::first_id(),
            'isMultiPlatforms' => Platform::is_multi(),
            'tr' => \common\helpers\Translation::translations_for_js(['IMAGE_SAVE', 'IMAGE_CANCEL', 'NOT_SAVE', 'CHANGED_DATA_ON_PAGE', 'GO_TO_BANNER_EDITOR']),
            'setLanguage' => (int) Yii::$app->request->get('language_id', false),
            'backUrl' => Yii::$app->url_manager->create_url(['banner_manager', 'platform_id' => $platform_id, 'group_id' => $group_id, 'row_id' => $row_id]),
            'platform_id' => $platform_id,
            'group_id' => $group_id,
            'row_id' => $row_id,
            'popup' => $popup,
        ];
        return $this->render('banneredit.tpl', $render_data);
    }
    public function action_gallery()
    {
        $htm = '';
        $files = scandir(DIR_FS_CATALOG . 'images/banners/thumbnails');
        foreach ($files as $item) {
            $s = strtolower(substr($item, -3));
            if ($s == 'gif' || $s == 'png' || $s == 'jpg' || $s == 'peg') {
                $htm .= '<div class="item item-general" data-src="' . DIR_WS_CATALOG . 'images/banners/' . $item . '"><div class="image"><img src="' . DIR_WS_CATALOG . 'images/banners/thumbnails/' . $item . '" title="' . $item . '" alt="' . $item . '"></div><div class="name" data-path="images/">' . $item . '</div></div>';
            }
        }
        return $htm;
    }
    public function action_upload()
    {
        if (isset($_FILES['file'])) {
            $path = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'banners';
            if (!file_exists($path)) {
                mkdir($path, 0777);
                @chmod($path, 0777);
            }
            $path_thumbnails = DIR_FS_CATALOG . 'images' . DIRECTORY_SEPARATOR . 'banners' . DIRECTORY_SEPARATOR . 'thumbnails';
            if (!file_exists($path_thumbnails)) {
                mkdir($path_thumbnails, 0777);
                @chmod($path_thumbnails, 0777);
            }
            $i = 0;
            $response = [];
            while ($_FILES['file']['name'][$i]) {
                $file_name = $_FILES['file']['name'][$i];
                $copy_file = $file_name;
                $j = 1;
                $dot_pos = strrpos($copy_file, '.');
                $end = substr($copy_file, $dot_pos);
                $temp_name = $copy_file;
                while (is_file($path . DIRECTORY_SEPARATOR . $temp_name)) {
                    $temp_name = substr($copy_file, 0, $dot_pos) . '-' . $j . $end;
                    $temp_name = str_replace(' ', '_', $temp_name);
                    $j++;
                }
                $uploadfile = $path . DIRECTORY_SEPARATOR . $temp_name;
                $thumbnail = $path_thumbnails . DIRECTORY_SEPARATOR . $temp_name;
                if (!is_writeable(dirname($uploadfile))) {
                    $response[] = ['status' => 'error', 'text' => sprintf(ERROR_DATA_DIRECTORY_NOT_WRITEABLE, self::basename(\Yii::get_alias('@webroot')) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR), 'file' => $_FILES['file']['name'][$i]];
                } elseif (!is_uploaded_file($_FILES['file']['tmp_name'][$i]) || filesize($_FILES['file']['tmp_name'][$i]) == 0) {
                    $response[] = ['status' => 'error', 'text' => WARNING_NO_FILE_UPLOADED, 'file' => $_FILES['file']['name'][$i]];
                } elseif (is_file($uploadfile)) {
                    $response[] = ['status' => 'error', 'text' => FILE_ALREADY_EXIST, 'file' => $_FILES['file']['name'][$i]];
                } elseif (move_uploaded_file($_FILES['file']['tmp_name'][$i], $uploadfile)) {
                    Images::tep_image_resize($uploadfile, $thumbnail, 200, 200);
                    $response[] = ['status' => 'ok', 'text' => TEXT_MESSEAGE_SUCCESS_ADDED, 'file' => $temp_name, 'src' => DIR_WS_CATALOG . 'images/banners/' . $temp_name];
                } else {
                    $response[] = ['status' => 'error', 'text' => 'error', 'file' => $_FILES['file']['name'][$i]];
                }
                $i++;
            }
        }
        return json_encode($response);
    }
    public function action_product_images()
    {
        $products_id = (int) Yii::$app->request->get('id');
        $images = Images::get_image_list($products_id);
        return json_encode($images);
    }
    public function action_banner_groups()
    {
        $this->selected_menu = ['marketing', 'banner_manager'];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('banner_manager/banner-groups-edit') . '" class="btn btn-confirm new-group">' . NEW_GROUP . '</a>';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('banner_manager/banner-groups'), 'title' => BOX_BANNER_GROUPS];
        $this->view->heading_title = BOX_BANNER_GROUPS;
        return $this->render('banner-groups.tpl', []);
    }
    public function action_banner_groups_list()
    {
        $draw = Yii::$app->request->get('draw', 1);
        $length = Yii::$app->request->get('length', 25);
        $search = Yii::$app->request->get('search');
        $start = Yii::$app->request->get('start', 0);
        $order = Yii::$app->request->get('order', 0);
        if ($length == -1) {
            $length = 10000;
        }
        if (!$search['value']) {
            $search['value'] = '';
        }
        $groups = Banners_Groups::find()->select('banners_group')->distinct()->where(['like', 'banners_group', $search['value']])->limit($length)->offset($start)->order_by(['banners_group' => $order[0]['dir'] == 'asc' ? SORT_ASC : SORT_DESC])->all();
        $response_list = [];
        foreach ($groups as $group) {
            $response_list[] = ['<div class="group" data-group-name="' . $group->banners_group . '">' . $group->banners_group . '</div>'];
        }
        $count_groups = Banners_Groups::find()->select('banners_group')->distinct()->count();
        $response = ['draw' => $draw, 'recordsTotal' => $count_groups, 'recordsFiltered' => $count_groups, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_banner_groups_edit()
    {
        $group_id = Yii::$app->request->get('group_id', 0);
        $row_id = Yii::$app->request->get('row_id', 0);
        $platform_id = Yii::$app->request->get('platform_id', 0);
        if (!$group_id) {
            $max_id = Banners_Groups::find()->max('id');
            $group_id = $max_id + 1;
            return $this->redirect(Yii::$app->url_manager->create_url(['banner_manager/banner-groups-edit', 'platform_id' => $platform_id, 'group_id' => $group_id, 'row_id' => $row_id]));
        }
        $banners_groups = Banners_Groups::find_one(['id' => $group_id]);
        if ($banners_groups) {
            $group_name = $banners_groups->banners_group;
        } else {
            $group_name = '';
        }
        $this->selected_menu = ['marketing', 'banner_manager'];
        $this->top_buttons[] = '<span class="btn btn-confirm save-group">' . IMAGE_SAVE . '</span>';
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('banner_manager/banner-groups'), 'title' => BOX_BANNER_GROUPS . ': ' . $group_name];
        $this->view->heading_title = 'Banner group: ' . $group_name;
        $group_sizes = [];
        if ($group_id) {
            $group_sizes = Banners_Groups_Sizes::find()->where(['group_id' => $group_id])->as_array()->all();
        }
        if (Yii::$app->request->is_ajax) {
            $this->layout = false;
        }
        return $this->render('banner-groups-edit.tpl', ['groupName' => $group_name, 'groupSizes' => $group_sizes, 'group_id' => $group_id, 'row_id' => $row_id, 'platform_id' => $platform_id]);
    }
    public function action_banner_group_settings()
    {
        $group_id = Yii::$app->request->get('group_id', 0);
        $group_settings = [];
        if ($group_id) {
            $group_settings = Banners_Groups_Sizes::find()->where(['group_id' => $group_id])->as_array()->all();
        }
        return json_encode($group_settings);
    }
    public function action_banner_groups_save()
    {
        $post = Yii::$app->request->post();
        $ids = [];
        if ($post['group_id']) {
            $banners_groups = Banners_Groups::find_one($post['group_id']);
            if (!$banners_groups) {
                $banners_groups = new Banners_Groups();
            }
            $banners_groups->banners_group = $post['banners_group'] ?? '';
            $banners_groups->save();
            $group_sizes = Banners_Groups_Sizes::find()->select('id')->where(['group_id' => $post['group_id']])->as_array()->all();
            foreach ($group_sizes as $item) {
                $ids[$item['id']] = $item['id'];
            }
        }
        $count = 0;
        if (!empty($post['id'])) {
            foreach ($post['id'] as $id) {
                $save_data = ['group_id' => $post['group_id'], 'width_from' => (int) $post['width_from'][$count] ? $post['width_from'][$count] : 0, 'width_to' => (int) $post['width_to'][$count] ? $post['width_to'][$count] : 0, 'image_width' => (int) $post['image_width'][$count] ? $post['image_width'][$count] : 0, 'image_height' => (int) $post['image_height'][$count] ? $post['image_height'][$count] : 0];
                if ($ids[$id] ?? null) {
                    unset($ids[$id]);
                    $banners_groups_sizes = Banners_Groups_Sizes::find_one($id);
                    if ($banners_groups_sizes) {
                        $banners_groups_sizes->attributes = $save_data;
                        $banners_groups_sizes->save();
                    }
                } else {
                    $banners_groups_sizes = new Banners_Groups_Sizes();
                    $banners_groups_sizes->attributes = $save_data;
                    $banners_groups_sizes->save();
                }
                $count++;
            }
            if (is_array($ids) && count($ids) > 0) {
                foreach ($ids as $id) {
                    if ($id != 0) {
                        $banners_groups_sizes = Banners_Groups_Sizes::find_one($id);
                        $banners_groups_sizes->delete();
                    }
                }
            }
        }
        return json_encode(['text' => MESSAGE_SAVED]);
    }
    public function action_banner_groups_delete_confirm()
    {
        $this->layout = false;
        $group_id = \Yii::$app->request->get('group_id', 0);
        $language_id = \Yii::$app->settings->get('languages_id');
        $banners = Banners::find()->alias('b')->select(['b.*'])->add_select(['bl.banners_title'])->left_join(Banners_Languages::table_name() . ' bl', 'b.banners_id = bl.banners_id and bl.language_id = ' . $language_id)->and_where('b.group_id = "' . $group_id . '"')->order_by('bl.banners_title')->as_array()->all();
        $banners_group = Banners_Groups::find_one(['id' => $group_id])->banners_group;
        return $this->render('bar-group-delete', ['group_id' => $group_id, 'banners' => $banners, 'banners_group' => $banners_group]);
    }
    public function action_banner_groups_delete()
    {
        $group_id = Yii::$app->request->post('group_id');
        $delete_image = Yii::$app->request->post('delete_image');
        Banners_Groups::delete_all(['id' => $group_id]);
        Banners_Groups_Sizes::delete_all(['group_id' => $group_id]);
        $banners = Banners::find()->where(['group_id' => $group_id])->as_array()->all();
        $banners_ids = [];
        foreach ($banners as $banner) {
            $banners_ids[] = $banner['banners_id'];
        }
        $this->delete_banners($banners_ids, $delete_image);
        $response = ['status' => 'ok', 'success' => TEXT_REMOVED];
        return json_encode($response);
    }
    public function action_banner_group_images()
    {
        $this->layout = false;
        $group_id = Yii::$app->request->get('group_id');
        $banners_id = Yii::$app->request->get('banners_id');
        $group_sizes = Banners_Groups_Sizes::find()->where(['group_id' => $group_id])->as_array()->all();
        $group_images = Banners_Groups_Images::find()->where(['banners_id' => $banners_id])->as_array()->all();
        $group_images_lang = [];
        foreach ($group_images as $lang_images) {
            $group_images_lang[$lang_images['language_id']][$lang_images['image_width']] = $lang_images;
        }
        $response = [];
        $languages = \common\helpers\Language::get_languages();
        foreach ($languages as $language) {
            $size_images = [];
            foreach ($group_sizes as $size) {
                $type = 'image';
                $image = '';
                if ($image = Array_Helper::get_value($group_images_lang, [$language['id'], $size['image_width'], 'image'])) {
                    if (is_file(Images::get_fs_catalog_images_path() . $image)) {
                        $type = explode('/', mime_content_type(Images::get_fs_catalog_images_path() . $image));
                        $type = $type[0];
                    } elseif (is_file(DIR_FS_CATALOG . $image)) {
                        $type = explode('/', mime_content_type(DIR_FS_CATALOG . $image));
                        $type = $type[0];
                    }
                }
                $size_images[$size['image_width']] = ['width_from' => $size['width_from'], 'width_to' => $size['width_to'], 'image_width' => $size['image_width'], 'image_height' => $size['image_height'], 'image' => $image ? DIR_WS_IMAGES . $image : '', 'type' => $type, 'svg' => $group_images_lang[$language['id']][$size['image_width']]['svg'] ?? null, 'fit' => $group_images_lang[$language['id']][$size['image_width']]['fit'] ?? null, 'position' => $group_images_lang[$language['id']][$size['image_width']]['position'] ?? null, 'svg_url' => Yii::$app->url_manager->create_url(['banner_manager/banner-editor', 'language_id' => $language['id'], 'banners_id' => $banners_id, 'banner_group' => $size['image_width']])];
            }
            $response[$language['id']] = ['img' => $this->render('banner-group-images.tpl', ['group_id' => $group_id, 'sizeImages' => $size_images, 'language_id' => $language['id']]), 'svg' => $this->render('banner-group-svg.tpl', ['group_id' => $group_id, 'sizeImages' => $size_images, 'language_id' => $language['id']])];
        }
        return json_encode($response);
    }
    public static function save_group_images($banners_id, $old_image = '', $delete_old_image = [])
    {
        $group_image = Yii::$app->request->post('group_image', []);
        $group_image_upload = Yii::$app->request->post('group_image_upload', []);
        $group_image_delete = Yii::$app->request->post('group_image_delete', []);
        $group_id = Yii::$app->request->post('group_id', 0);
        $positions = Yii::$app->request->post('position', []);
        $fits = Yii::$app->request->post('fit', []);
        $languages = Language::get_languages();
        $group_sizes = Banners_Groups_Sizes::find()->where(['group_id' => $group_id])->as_array()->all();
        foreach ($languages as $language) {
            $main_image = Banners_Languages::find()->select('banners_image')->where(['banners_id' => $banners_id, 'language_id' => $language['id']])->as_array()->one();
            foreach ($group_sizes as $group_size) {
                if (!isset($group_image[$language['id']])) {
                    continue;
                }
                $image = str_replace(DIR_WS_IMAGES, '', $group_image[$language['id']][$group_size['image_width']] ?? '');
                $image_upload = $group_image_upload[$language['id']][$group_size['image_width']] ?? '';
                $image_delete = (bool) $group_image_delete[$language['id']][$group_size['image_width']] ?? false;
                $position = $positions[$language['id']][$group_size['image_width']] ?? '';
                $fit = $fits[$language['id']][$group_size['image_width']] ?? '';
                $banners_groups_images = Banners_Groups_Images::find_one(['banners_id' => $banners_id, 'language_id' => $language['id'], 'image_width' => $group_size['image_width']]);
                $new_img = Image::prepare_saving_image($banners_groups_images->image ?? '', $image, $image_upload, 'banners' . DIRECTORY_SEPARATOR . $banners_id, $image_delete, false, ['width' => $group_size['image_width'], 'height' => $group_size['image_height'], 'fit' => $fit, 'parentImage' => $main_image['banners_image'] ?? '', 'parentOldImage' => $old_image[$language['id']]]);
                if (!$banners_groups_images) {
                    $banners_groups_images = new Banners_Groups_Images();
                }
                $banners_groups_images->attributes = ['banners_id' => (int) $banners_id, 'language_id' => (int) $language['id'], 'image_width' => (int) $group_size['image_width'], 'image' => $new_img ? $new_img : '', 'fit' => $fit, 'position' => $position];
                $banners_groups_images->save();
            }
        }
    }
    public static function delete_banner_group_images($banners_id)
    {
        $remove_images = Banners_Groups_Images::find()->where(['banners_id' => $banners_id])->as_array()->all();
        Banners_Groups_Images::delete_all(['banners_id' => $banners_id]);
        foreach ($remove_images as $remove_image) {
            $count = Banners_Groups_Images::find()->and_where(['image' => $remove_image['image']])->count();
            if ($count == 0 && is_file(DIR_FS_CATALOG_IMAGES . $remove_image['image'])) {
                unlink(DIR_FS_CATALOG_IMAGES . $remove_image['image']);
            }
        }
    }
    public function action_group_banners()
    {
        $language_id = \Yii::$app->settings->get('languages_id');
        $banners_group = \Yii::$app->request->get('banners_group');
        $banners = Banners::find()->alias('b')->select(['b.*'])->add_select(['bl.banners_title'])->left_join(Banners_Languages::table_name() . ' bl', 'b.banners_id = bl.banners_id and bl.language_id = ' . $language_id)->left_join(Banners_Groups::table_name() . ' bg', 'bg.id = b.group_id')->and_where(['or', ['bg.banners_group' => $banners_group], ['b.group_id' => $banners_group]])->order_by('bl.banners_title')->as_array()->all();
        $response_list = [];
        foreach ($banners as $banner) {
            $platforms = Banners_To_Platform::find()->alias('b2p')->select(['p.platform_name'])->left_join(\common\models\Platforms::table_name() . ' p', 'p.platform_id = b2p.platform_id')->where(['b2p.banners_id' => $banner['banners_id']])->as_array()->all();
            $response_list[] = ['url' => \Yii::$app->url_manager->create_url(['banner_manager/banneredit', 'banners_id' => $banner['banners_id']]), 'banners_id' => $banner['banners_id'], 'image' => $this->action_getimage($banner['banners_id']), 'banners_title' => $banner['banners_title'], 'status' => $banner['status'], 'platforms' => $platforms];
        }
        echo json_encode($response_list);
    }
    public function action_sort()
    {
        $ids = \Yii::$app->request->post('ids', []);
        $count = 0;
        foreach ($ids as $id) {
            $banner = Banners::find_one(['banners_id' => $id]);
            $banner->sort_order = $count;
            $banner->save(false);
            $count++;
        }
    }
}