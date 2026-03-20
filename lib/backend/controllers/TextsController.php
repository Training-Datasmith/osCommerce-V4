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

use backend\models\Admin;
use Yii;
/**
 * default controller to handle user requests.
 */
class Texts_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_DESIGN_CONTROLS', 'BOX_TRANSLATION_TEXTS'];
    public function action_index()
    {
        $this->view->heading_title = HEADING_TITLE;
        $this->selected_menu = ['design_controls', 'texts'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('texts/index'), 'title' => HEADING_TITLE];
        $this->view->languages_table = [['title' => TABLE_HEADING_LANGUAGE_KEY, 'not_important' => 0], ['title' => TABLE_HEADING_LANGUAGE_ENTITY, 'not_important' => 0]];
        $admin = \common\models\Admin::find_one($_SESSION['login_id']);
        if ($admin->frontend_translation) {
            $this->top_buttons[] = '<div class="top-bar-text"><a href="' . tep_catalog_href_link('') . '" target="_blank">' . TEXT_EDIT_TRANSLATIONS_FRONTEND . '</a></div>';
        }
        //update all absent hashes
        tep_db_query("UPDATE translation SET hash=MD5(CONCAT(translation_key,'-',translation_entity)) WHERE (hash IS NULL OR LENGTH(hash)=0)");
        $languages = \common\helpers\Language::get_languages(true);
        $this->view->searchable_language = [];
        $this->view->shown_language = [0, 1];
        $admin = new Admin();
        $a_info = $admin->get_additional_info();
        if (!isset($a_info['shown_language'])) {
            $a_info['shown_language'] = [];
            if (is_array($languages)) {
                foreach ($languages as $_k => $_v) {
                    $a_info['shown_language'][] = $_v['id'];
                }
            }
            $admin->save_additional_info($a_info);
        }
        if (!isset($a_info['searchable_language'])) {
            $a_info['searchable_language'] = [];
            if (is_array($languages)) {
                foreach ($languages as $_k => $_v) {
                    $a_info['searchable_language'][] = $_v['id'];
                }
            }
            $admin->save_additional_info($a_info);
        }
        if (!isset($a_info['key_entity'])) {
            $a_info['key_entity'] = [0, 1];
            $admin->save_additional_info($a_info);
        }
        $this->view->key_entity = $a_info['key_entity'];
        $_c = 0;
        $a_info['shown_language'] = $a_info['shown_language'] ?? [\common\helpers\Language::get_default_language_id()];
        foreach ($languages as $key => $value) {
            if (in_array($value['id'], $a_info['shown_language'])) {
                $translated = tep_db_fetch_array(tep_db_query('select sum(translated) as translated, count(translated) as total from ' . TABLE_TRANSLATION . " where language_id = '{$value['id']}'"));
                $this->view->languages_table[] = ['title' => $value['image'] . '&nbsp;' . ucfirst($value['code']) . ' - ' . sprintf(TEXT_TRANSLATION_PERCENT, $translated['translated'], $translated['total'], round($translated['translated'] / ($translated['total'] ? $translated['total'] : 1) * 100, 2) . '%'), 'not_important' => 0];
                $_c++;
                $languages[$key]['data'] = $_c + 1;
                $languages[$key]['shown_language'] = 1;
            } else {
                $languages[$key]['shown_language'] = 0;
            }
            if (in_array($value['id'], $a_info['searchable_language'])) {
                $languages[$key]['searchable_language'] = 1;
            } else {
                $languages[$key]['searchable_language'] = 0;
            }
        }
        $this->view->checkable_list = '0,1';
        // . implode(',', range(2, $_c + 1 ));
        $this->view->filters = new \stdClass();
        $by = [['name' => TEXT_ANY, 'value' => '', 'selected' => ''], ['name' => TEXT_ENTITY, 'value' => 'translation_entity', 'selected' => ''], ['name' => TEXT_KEY, 'value' => 'translation_key', 'selected' => ''], ['name' => TEXT_VALUE, 'value' => 'translation_value', 'selected' => '']];
        foreach ($by as $key => $value) {
            if (isset($_GET['by']) && $value['value'] == $_GET['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        $search = '';
        if (isset($_GET['search'])) {
            $search = filter_var(Yii::$app->request->get('search', ''), FILTER_SANITIZE_STRING);
        }
        $this->view->filters->search = $search;
        $this->view->filters->sensitive = (int) Yii::$app->request->get('sensitive', 0);
        $this->view->filters->skip_admin = (int) \Yii::$app->request->get('skip_admin', 0);
        $this->view->filters->untranslated = (int) \Yii::$app->request->get('untranslated', 0);
        $this->view->filters->unverified = (int) \Yii::$app->request->get('unverified', 0);
        $filter_entity = [];
        $filter_entity[] = ['id' => 'ALL', 'text' => SELECT_ALL];
        $translation_query = tep_db_query('select translation_entity from ' . TABLE_TRANSLATION . ' where 1 group by translation_entity');
        while ($translation = tep_db_fetch_array($translation_query)) {
            $filter_entity[] = ['id' => $translation['translation_entity'], 'text' => $translation['translation_entity']];
        }
        $this->view->filter_entity = '';
        $this->view->filters->row = (int) Yii::$app->request->get('row', 0);
        $messages = Yii::$app->session->get_all_flashes();
        Yii::$app->session->remove_all_flashes();
        $admin_tr = \common\models\Admin::find_one($_SESSION['login_id']);
        return $this->render('index', ['messages' => $messages, 'languages' => $languages, 'frontend_translation' => $admin_tr->frontend_translation]);
    }
    public function action_list()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        $draw = Yii::$app->request->get('draw', 1);
        $start = Yii::$app->request->get('start', 0);
        $length = Yii::$app->request->get('length', 10);
        if ($length == -1) {
            $length = 10000;
        }
        $filter = [];
        $form_filter = Yii::$app->request->get('filter');
        parse_str($form_filter, $filter);
        $admin = new Admin();
        $a_info = $admin->get_additional_info();
        $search = '';
        $languages = \common\helpers\Language::get_languages(true);
        $_def_id = \common\helpers\Language::get_default_language_id();
        $a_info['shown_language'] = $a_info['shown_language'] ?? [$_def_id];
        $a_info['searchable_language'] = $a_info['searchable_language'] ?? [$_def_id];
        $search_in_language = '';
        $sensitive = '';
        if (isset($filter['sensitive'])) {
            $sensitive = ' BINARY ';
        }
        if ($filter['search'] && tep_not_null($filter['search'])) {
            $keywords = tep_db_input(tep_db_prepare_input($filter['search']));
            switch ($filter['by']) {
                case 'translation_entity':
                    $search .= " and t1.translation_entity = '" . $keywords . "' ";
                    break;
                case 'translation_key':
                    $search .= ' and t1.translation_key like ' . $sensitive . " '%" . $keywords . "%' ";
                    break;
                case 'translation_value':
                    $search .= ' and t1.translation_value like ' . $sensitive . " '%" . $keywords . "%' ";
                    break;
                default:
                    $search .= ' and (t1.translation_key like ' . $sensitive . " '%" . $keywords . "%' or t1.translation_entity like " . $sensitive . " '%" . $keywords . "%' or t1.translation_value like " . $sensitive . " '%" . $keywords . "%') ";
                    break;
            }
        }
        if (tep_not_null($_GET['search']['value'])) {
            $search = ' and ( 1 ' . $search . ' and (t1.translation_key like ' . $sensitive . " '%" . tep_db_input($_GET['search']['value']) . "%' or t1.translation_entity like " . $sensitive . " '%" . tep_db_input($_GET['search']['value']) . "%' or t1.translation_value like " . $sensitive . " '%" . tep_db_input($_GET['search']['value']) . "%')) ";
        }
        if (isset($filter['skip_admin'])) {
            $search .= " and t1.translation_entity not like  'admin/%' ";
            $search .= " and t1.translation_entity not like  'configuration' ";
        }
        if (isset($filter['untranslated'])) {
            $search .= ' and t1.translated=0 ';
        }
        if (isset($filter['unverified'])) {
            $search .= ' and t1.checked=0 ';
        }
        $response_list = [];
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 't.translation_key ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                case 1:
                    $order_by = 't.translation_entity ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    break;
                default:
                    if ($_GET['order'][0]['column'] > 1) {
                        $_prefix = $languages[$_GET['order'][0]['column'] - 2]['code'];
                        if ($languages[$_GET['order'][0]['column'] - 2]['id'] == $_def_id) {
                            $_prefix = 't';
                        }
                        $order_by = $_prefix . '.translation_value ' . tep_db_prepare_input($_GET['order'][0]['dir']);
                    } else {
                        $order_by = 't.translation_key';
                    }
                    break;
            }
        } else {
            $order_by = 'translation_key';
        }
        $select = 'select t.translation_key, t.translation_entity, t.translation_value as def_value, ';
        $from = ' from ' . TABLE_TRANSLATION . ' t ';
        $search_in = [0];
        if (is_array($languages)) {
            foreach ($languages as $_lang) {
                if (tep_not_null($search)) {
                    if (in_array($_lang['id'], $a_info['searchable_language'])) {
                        $search_in[] = $_lang['id'];
                    }
                }
                if (!in_array($_lang['id'], $a_info['shown_language'])) {
                    continue;
                }
                if ($_lang['id'] != $_def_id) {
                    $from .= ' left join ' . TABLE_TRANSLATION . ' ' . $_lang['code'] . ' on t.translation_key = ' . $_lang['code'] . '.translation_key and ' . $_lang['code'] . ".language_id = '" . $_lang['id'] . "' and t.translation_entity = " . $_lang['code'] . '.translation_entity ';
                    $select .= ' ' . $_lang['code'] . '.translation_value as ' . $_lang['code'] . '_value, ';
                }
            }
            $select = substr($select, 0, -2);
        }
        if (tep_not_null($search)) {
            //$keywords = tep_db_input(tep_db_prepare_input($search_in_language));
            if (count($search_in) < 2) {
                $search_in[] = $languages_id;
            }
            $search = ' and t.hash in( select distinct t1.hash from ' . TABLE_TRANSLATION . ' t1 where  t1.language_id in (' . implode(',', $search_in) . ') ' . $search . ' )';
            /*} else {
            
                          $search = " and t.hash in( select distinct t1.hash from " . TABLE_TRANSLATION . " t1 where  t1.translation_key like '%" . $keywords . "%' or t1.translation_entity like '%" . $keywords . "%' )";
                        }*/
            $where = ' where 1 ';
            //" where t.language_id = " . $_def_id;
        } else {
            $where = ' where 1 ';
            //" where t.language_id = " . $_def_id;
        }
        $orders_status_query_raw = $select . $from . $where . ' ' . $search . ' group by t.translation_key, t.translation_entity order by ' . $order_by;
        $orders_status_query = tep_db_query($orders_status_query_raw);
        $query_numrows = tep_db_num_rows($orders_status_query);
        $orders_status_query_raw .= ' limit ' . $start . ', ' . $length;
        $orders_status_query = tep_db_query($orders_status_query_raw);
        while ($orders_status = tep_db_fetch_array($orders_status_query)) {
            $_prepare = [$orders_status['translation_key'] . tep_draw_hidden_field('translation_key', $orders_status['translation_key'], 'class="cell_identify"') . tep_draw_hidden_field('translation_entity', $orders_status['translation_entity'], 'class="cell_type"'), $orders_status['translation_entity'] . tep_draw_hidden_field('translation_key', $orders_status['translation_key'], 'class="cell_identify"') . tep_draw_hidden_field('translation_entity', $orders_status['translation_entity'], 'class="cell_type"')];
            foreach ($languages as $_lang) {
                //if (!$_lang['shown_language']) continue;
                if (!in_array($_lang['id'], $a_info['shown_language'])) {
                    continue;
                }
                $full_desc = $_lang['id'] == $_def_id ? $orders_status['def_value'] : $orders_status[$_lang['code'] . '_value'];
                if ($_lang['id'] == $_def_id) {
                    $full_desc = \common\helpers\Translation::get_translation_value($orders_status['translation_key'], $orders_status['translation_entity'], $_lang['id']);
                }
                $short_desc = preg_replace('/<.*?>/', ' ', $full_desc);
                if (mb_strlen($short_desc) > 128) {
                    $short_desc = mb_substr($short_desc, 0, 122) . '...';
                }
                $_prepare[] = '
<div class="ls-review-rev">
<div class="for-before">
  ' . (tep_not_null($full_desc) ? '<div class="ord-total-info ord-total-info-translate"><div class="ord-box-img"></div><div>' . $full_desc . '</div></div>' : '') . '
</div>
' . (\common\helpers\Translation::is_translated($orders_status['translation_key'], $orders_status['translation_entity'], $_lang['id']) ? '<div class="translated"></div>' : '') . '
' . $short_desc . '</div>
' . tep_draw_hidden_field('translation_key', $orders_status['translation_key'], 'class="cell_identify"') . tep_draw_hidden_field('translation_entity', $orders_status['translation_entity'], 'class="cell_type"');
            }
            $response_list[] = $_prepare;
            //,
        }
        $response = ['draw' => $draw, 'recordsTotal' => $query_numrows, 'recordsFiltered' => $query_numrows, 'data' => $response_list];
        echo json_encode($response, JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    public function action_actions()
    {
        \common\helpers\Translation::init('admin/texts');
        $this->layout = false;
        $translation_key = Yii::$app->request->post('translation_key');
        $translation_entity = Yii::$app->request->post('translation_entity');
        $selected_entity = Yii::$app->request->post('selected_entity', '');
        $row = Yii::$app->request->post('row', '0');
        $_search = Yii::$app->request->post('search', '');
        $_search = explode('&', urldecode($_search));
        $params = ['translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'row' => $row];
        if (is_array($_search) && count($_search)) {
            foreach ($_search as $_item) {
                if (strpos($_item, '[') === false) {
                    $e = explode('=', $_item);
                    $params[$e[0]] = $e[1];
                }
            }
        }
        if ($selected_entity != $translation_entity && $selected_entity != '') {
            $selected_entity = $translation_entity;
        }
        if (!empty($translation_key)) {
            echo '<div class="or_box_head or_box_head_transl">' . $translation_key . '</div>';
            $languages = \common\helpers\Language::get_languages(true);
            $admin = new Admin();
            $a_info = $admin->get_additional_info();
            //$translation_query = tep_db_query("select translation_entity from " . TABLE_TRANSLATION . " where translation_key='" . $translation_key . "' group by translation_entity");
            //while ($translation = tep_db_fetch_array($translation_query)) {
            //echo '<div class="or_box_head">' . $translation['translation_entity'] . '</div>';
            //$translation_entity = $translation['translation_entity'];
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                if (!in_array($languages[$i]['id'], $a_info['shown_language'])) {
                    continue;
                }
                echo '<div class="col_desc">' . $languages[$i]['image'] . '&nbsp;' . \common\helpers\Translation::get_translation_value($translation_key, $translation_entity, $languages[$i]['id']) . '</div>';
            }
            //}
            echo '<div class="btn-toolbar btn-toolbar-order">';
            if (tep_not_null($selected_entity)) {
                $params['selected_entity'] = $selected_entity;
                //echo '<a class="btn btn-edit btn-no-margin" href="' . Yii::$app->urlManager->createUrl(['texts/edit', 'selected_entity' => $selected_entity, 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'row' => $row]) . '">' . IMAGE_EDIT . '</a>';
            } else {
                //echo '<a class="btn btn-edit btn-no-margin" href="' . Yii::$app->urlManager->createUrl(['texts/edit', 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'row' => $row]) . '">' . IMAGE_EDIT . '</a>';
            }
            echo '<a class="btn btn-edit btn-no-margin" href="' . \yii\helpers\Url::to(['texts/edit'] + $params) . '">' . IMAGE_EDIT . '</a>';
            echo '<button class="btn btn-delete" onclick="translateDelete(\'' . $translation_key . '\', \'' . $translation_entity . '\')">' . IMAGE_DELETE . '</button>';
            echo '<button class="btn btn-no-margin btn-process-order" onclick="translateChangeKey(\'' . $translation_key . '\', \'' . $translation_entity . '\')">' . TEXT_CHANGE_KEY . '</button>';
            echo '<button class="btn btn-no-margin btn-process-order" onclick="translateChangeEntity(\'' . $translation_key . '\', \'' . $translation_entity . '\')">' . TEXT_CHANGE_ENTITY . '</button>';
            echo '</div>';
        }
    }
    public function action_add()
    {
        \common\helpers\Translation::init('admin/texts');
        $this->view->heading_title = HEADING_TITLE;
        $this->selected_menu = ['design_controls', 'texts'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('texts/index'), 'title' => HEADING_TITLE];
        $values = [];
        $languages = \common\helpers\Language::get_languages(true);
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $values[$i]['flag'] = $languages[$i]['image'];
            $values[$i]['name'] = 'translation_value[' . $languages[$i]['id'] . ']';
            $values[$i]['text'] = '';
        }
        return $this->render('add', ['values' => $values]);
    }
    public function action_entity_list()
    {
        $term = tep_db_prepare_input(Yii::$app->request->get('term'));
        $search = '1';
        if (!empty($term)) {
            $search = "translation_entity like '%" . tep_db_input($term) . "%'";
        }
        $filter_entity = [];
        $translation_query = tep_db_query('select translation_entity from ' . TABLE_TRANSLATION . ' where ' . $search . ' group by translation_entity');
        while ($translation = tep_db_fetch_array($translation_query)) {
            $filter_entity[] = $translation['translation_entity'];
        }
        echo json_encode($filter_entity);
    }
    public function action_edit_search()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/texts');
        $admin = new Admin();
        $a_info = $admin->get_additional_info();
        $add_search = '';
        if (Yii::$app->request->is_post) {
            //$translation_key = Yii::$app->request->post('translation_key');
            //$translation_entity = Yii::$app->request->post('translation_entity');
            //$selected_entity = Yii::$app->request->post('selected_entity', '');
            $row = Yii::$app->request->post('row', 0);
            $by = Yii::$app->request->post('by', '');
            $search = tep_db_prepare_input(Yii::$app->request->post('search', ''));
            $sensitive = Yii::$app->request->post('sensitive', 0);
            $skip_admin = Yii::$app->request->post('skip_admin', 0);
            if ($skip_admin) {
                $add_search .= " and t.translation_entity not like  'admin/%' ";
                $add_search .= " and t.translation_entity not like  'configuration' ";
            }
            $untranslated = Yii::$app->request->post('untranslated', 0);
            if ($untranslated) {
                $add_search .= ' and t1.translated=0 ';
            }
            $unverified = Yii::$app->request->post('unverified', 0);
            if ($unverified) {
                $add_search .= ' and t1.checked=0 ';
            }
            $this->layout = false;
            $total_query = tep_db_query('select t.translation_key, t.translation_entity,  min(translated) as translated from ' . TABLE_TRANSLATION . ' t where t.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') and t.hash in (select distinct t1.hash from ' . TABLE_TRANSLATION . ' t1 where t1.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') ' . (tep_not_null($selected_entity) ? "and t1.translation_entity = '" . tep_db_input($selected_entity) . "'" : '') . ' ' . (tep_not_null($by) && tep_not_null($search) ? ' and t1.' . ($by == 'translation_entity' ? "translation_entity = '" . tep_db_input($search) . "' " : "{$by} like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%'") : (tep_not_null($search) ? ' and (t1.translation_entity like ' . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_key like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_value like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%')" : '')) . ") {$add_search} group by t.translation_key, t.translation_entity order by t.translation_key, t.translation_entity limit 1");
            if (tep_db_num_rows($total_query)) {
                $row = tep_db_fetch_array($total_query);
                $params = ['texts/edit', 'translation_key' => $row['translation_key'], 'translation_entity' => $row['translation_entity'], 'row' => 0, 'by' => $by, 'search' => $search];
                if ($sensitive) {
                    $params['sensitive'] = 1;
                }
                if ($skip_admin) {
                    $params['skip_admin'] = 1;
                }
                if ($unverified) {
                    $params['unverified'] = 1;
                }
                if ($untranslated) {
                    $params['untranslated'] = 1;
                }
                return $this->redirect(\yii\helpers\Url::to($params));
            } else {
                \common\helpers\Translation::init('admin/js');
                Yii::$app->session->set_flash('warning', DATATABLE_EMPTY_TABLE);
            }
        } else {
            \common\helpers\Translation::init('admin/js');
            Yii::$app->session->set_flash('warning', DATATABLE_EMPTY_TABLE);
        }
        $params = ['texts/edit', 'row' => 0, 'by' => $by, 'search' => $search, 'empty' => 1];
        if ($sensitive) {
            $params['sensitive'] = 1;
        }
        if ($skip_admin) {
            $params['skip_admin'] = 1;
        }
        if ($unverified) {
            $params['unverified'] = 1;
        }
        if ($untranslated) {
            $params['untranslated'] = 1;
        }
        return $this->redirect(\yii\helpers\Url::to($params));
    }
    public function action_edit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/texts');
        $this->view->heading_title = HEADING_TITLE;
        $admin = new Admin();
        $a_info = $admin->get_additional_info();
        if (!isset($a_info['key_entity'])) {
            $a_info['key_entity'] = [];
        }
        //$this->selectedMenu = array('settings', 'translation', 'texts');
        $this->selected_menu = ['design_controls', 'texts'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('texts/index'), 'title' => HEADING_TITLE];
        $this->top_buttons[] = '<span class="btn btn-confirm" onclick="$(\'#save_item_form\').trigger(\'submit\')">' . IMAGE_SAVE . '</span>';
        $languages = \common\helpers\Language::get_languages(true);
        $this->view->searchable_language = [];
        $this->view->shown_language = [0, \common\helpers\Language::get_default_language_id()];
        $save = false;
        if (!isset($a_info['searchable_language'])) {
            $a_info['searchable_language'] = [];
            if (is_array($languages)) {
                foreach ($languages as $_k => $_v) {
                    $a_info['searchable_language'][] = $_v['id'];
                }
            }
            $save = true;
        }
        if (!isset($a_info['shown_language'])) {
            $a_info['shown_language'] = $this->view->shown_language;
            $save = true;
        }
        if ($save) {
            $admin->save_additional_info($a_info);
        }
        $this->view->key_entity = $a_info['key_entity'];
        $_c = 0;
        foreach ($languages as $key => $value) {
            if (in_array($value['id'], $a_info['shown_language'])) {
                $this->view->languages_table[] = ['title' => $value['image'] . '&nbsp;' . ucfirst($value['code']), 'not_important' => 0];
                $_c++;
                $languages[$key]['data'] = $_c + 1;
                $languages[$key]['shown_language'] = 1;
            } else {
                $languages[$key]['shown_language'] = 0;
            }
            if (in_array($value['id'], $a_info['searchable_language'])) {
                $languages[$key]['searchable_language'] = 1;
            } else {
                $languages[$key]['searchable_language'] = 0;
            }
        }
        $this->view->filters = new \stdClass();
        $by = [['name' => TEXT_ANY, 'value' => '', 'selected' => ''], ['name' => TEXT_ENTITY, 'value' => 'translation_entity', 'selected' => ''], ['name' => TEXT_KEY, 'value' => 'translation_key', 'selected' => ''], ['name' => TEXT_VALUE, 'value' => 'translation_value', 'selected' => '']];
        foreach ($by as $key => $value) {
            if (isset($_GET['by']) && $value['value'] == $_GET['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        $add_search = $search = '';
        if (isset($_GET['search'])) {
            $search = $_GET['search'];
        }
        $this->view->filters->search = $search;
        $this->view->filters->sensitive = (int) \Yii::$app->request->get('sensitive');
        $this->view->filters->skip_admin = (int) \Yii::$app->request->get('skip_admin', 0);
        $unverified = $this->view->filters->unverified = (int) \Yii::$app->request->get('unverified', 0);
        $untranslated = $this->view->filters->untranslated = (int) \Yii::$app->request->get('untranslated', 0);
        $empty = 0;
        if (Yii::$app->request->is_post) {
            $translation_key = Yii::$app->request->post('translation_key');
            $translation_entity = Yii::$app->request->post('translation_entity');
            $selected_entity = Yii::$app->request->post('selected_entity', '');
            // $id = Yii::$app->request->post('id', 0);
            $row = Yii::$app->request->post('row', 0);
            $by = Yii::$app->request->get('by', '');
            $search = Yii::$app->request->get('search', '');
            $sensitive = Yii::$app->request->get('sensitive', 0);
            $skip_admin = Yii::$app->request->get('skip_admin', 0);
            $this->layout = false;
        } else {
            $translation_key = Yii::$app->request->get('translation_key');
            $translation_entity = Yii::$app->request->get('translation_entity');
            $selected_entity = Yii::$app->request->get('selected_entity', '');
            // $id = Yii::$app->request->get('id', 0);
            $row = Yii::$app->request->get('row', 0);
            $by = Yii::$app->request->get('by', '');
            $search = Yii::$app->request->get('search', '');
            $sensitive = Yii::$app->request->get('sensitive', 0);
            $skip_admin = Yii::$app->request->get('skip_admin', 0);
            $empty = Yii::$app->request->get('empty', 0);
        }
        if ($skip_admin) {
            $add_search .= " and t.translation_entity not like  'admin/%' ";
            $add_search .= " and t.translation_entity not like  'configuration' ";
        }
        if ($unverified) {
            $add_search .= ' and t.checked=0 ';
        }
        if ($untranslated) {
            $add_search .= ' and t.translated=0 ';
        }
        $messages = Yii::$app->session->get_all_flashes();
        Yii::$app->session->remove_all_flashes();
        if ($empty) {
            return $this->render('edit', ['empty' => 1, 'search' => $search, 'languages' => $languages, 'messages' => $messages]);
        }
        $nextut = Yii::$app->request->get('nextut', 0);
        $total_query = tep_db_query('select t.translation_key, t.translation_entity,  min(translated) as translated from ' . TABLE_TRANSLATION . ' t where t.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') and t.hash in (select distinct t1.hash from ' . TABLE_TRANSLATION . ' t1 where t1.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') ' . (tep_not_null($selected_entity) ? "and t1.translation_entity = '" . $selected_entity . "'" : '') . ' ' . (tep_not_null($by) && tep_not_null($search) ? ' and t1.' . ($by == 'translation_entity' ? "translation_entity = '" . tep_db_input($search) . "' " : "{$by} like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%'") : (tep_not_null($search) ? ' and (t1.translation_entity like ' . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_key like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_value like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%')" : '')) . ") {$add_search} group by t.translation_key, t.translation_entity order by t.translation_key, t.translation_entity");
        $cursor = 0;
        $_next_ut = 0;
        $total_num = tep_db_num_rows($total_query);
        $have_untranslated = false;
        if ($total_num) {
            $spl = new \SplFixedArray($total_num + 1);
            $i = 0;
            $found = false;
            $setled = false;
            while ($_row = tep_db_fetch_array($total_query)) {
                if ($found) {
                    break;
                }
                $spl[$i] = $_row;
                if ($_row['translation_key'] == $translation_key && $_row['translation_entity'] == $translation_entity) {
                    $cursor = $i;
                    $have_untranslated = $_row['translated'];
                    $setled = true;
                }
                if ($nextut && $cursor < $i && $setled && !$_row['translated'] && !$_next_ut && !$found) {
                    $_next_ut = $i;
                    $found = true;
                }
                $i++;
            }
        }
        if ($_next_ut) {
            $_next = $spl->offsetGet($_next_ut);
            if ($selected_entity != '') {
                $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'selected_entity' => $selected_entity, 'row' => $row]);
            } else {
                $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'row' => $row]);
            }
            if (tep_not_null($search)) {
                $next_url .= '&search=' . $search;
            }
            if (tep_not_null($by)) {
                $next_url .= '&by=' . $by;
            }
            if ($sensitive) {
                $next_url .= '&sensitive=1';
            }
            if ($skip_admin) {
                $next_url .= '&skip_admin=1';
            }
            if ($unverified) {
                $next_url .= '&unverified=1';
            }
            if ($untranslated) {
                $next_url .= '&untranslated=1';
            }
            return $this->redirect($next_url);
            exit;
        } elseif (!$_next_ut && $nextut) {
            $message = 'That is the last record ' . ($selected_entity != '' ? 'in "' . $selected_entity . '" entity' : '') . (tep_not_null($search) ? ' in search ' : '');
            Yii::$app->session->set_flash('info', $message);
        }
        $prev_url = '';
        $next_url = '';
        try {
            if ($cursor < $total_num - 1 && $total_num) {
                $_next = $spl->offsetGet($cursor + 1);
                if ($selected_entity != '') {
                    $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'selected_entity' => $selected_entity, 'row' => $row]);
                } else {
                    $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'row' => $row]);
                }
                if (tep_not_null($search)) {
                    $next_url .= '&search=' . urlencode($search);
                }
                if (tep_not_null($by)) {
                    $next_url .= '&by=' . $by;
                }
                if ($sensitive) {
                    $next_url .= '&sensitive=1';
                }
                if ($skip_admin) {
                    $next_url .= '&skip_admin=1';
                }
                if ($unverified) {
                    $next_url .= '&unverified=1';
                }
                if ($untranslated) {
                    $next_url .= '&untranslated=1';
                }
            }
            if ($cursor > 0 && $total_num) {
                $_prev = $spl->offsetGet($cursor - 1);
                if ($selected_entity != '') {
                    $prev_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_prev['translation_key'], 'translation_entity' => $_prev['translation_entity'], 'selected_entity' => $selected_entity, 'row' => $row]);
                } else {
                    $prev_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_prev['translation_key'], 'translation_entity' => $_prev['translation_entity'], 'row' => $row]);
                }
                if (tep_not_null($search)) {
                    $prev_url .= '&search=' . urlencode($search);
                }
                if (tep_not_null($by)) {
                    $prev_url .= '&by=' . $by;
                }
                if ($sensitive) {
                    $prev_url .= '&sensitive=1';
                }
                if ($skip_admin) {
                    $prev_url .= '&skip_admin=1';
                }
                if ($unverified) {
                    $prev_url .= '&unverified=1';
                }
                if ($untranslated) {
                    $prev_url .= '&untranslated=1';
                }
            }
        } catch (\RuntimeException $e) {
            error_log($e->get_message());
        }
        $values = [];
        //$languages = \common\helpers\Language::get_languages(true);
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $_t = !\common\helpers\Translation::is_translated($translation_key, $translation_entity, $languages[$i]['id']) ? 'untranslated' : '';
            if (is_array($a_info['shown_language']) && !in_array($languages[$i]['id'], $a_info['shown_language'])) {
                continue;
            }
            $values[$i]['flag'] = $languages[$i]['image'];
            $values[$i]['translated_checkbox'] = \yii\helpers\Html::checkbox('translated[' . $languages[$i]['id'] . ']', \common\helpers\Translation::is_translated($translation_key, $translation_entity, $languages[$i]['id']), ['title' => TEXT_SET_TRANSLATED, 'class' => 'uniform']);
            $values[$i]['checked_checkbox'] = \yii\helpers\Html::checkbox('checked[' . $languages[$i]['id'] . ']', \common\helpers\Translation::is_checked($translation_key, $translation_entity, $languages[$i]['id']), ['title' => TEXT_CHECKED, 'class' => 'uniform']);
            $values[$i]['class'] = 'form-control ' . $_t;
            $values[$i]['div_class'] = $_t;
            $values[$i]['name'] = 'translation_value[' . $languages[$i]['id'] . ']';
            $values[$i]['text'] = \common\helpers\Translation::get_translation_value($translation_key, $translation_entity, $languages[$i]['id']);
        }
        $properties = [
            'values' => $values,
            'translation_key' => $translation_key,
            'translation_entity' => $translation_entity,
            'selected_entity' => $selected_entity,
            'next_url' => $next_url,
            'prev_url' => $prev_url,
            // 'id' => $id,
            'row' => $row,
            'messages' => $messages,
            'get_params' => Yii::$app->request->get_query_params(),
            'sensitive' => $sensitive,
            'skip_admin' => $skip_admin,
            'unverified' => $unverified,
            'untranslated' => $untranslated,
            'have_untranslated' => $have_untranslated,
            'search' => $search,
            'languages' => $languages,
            'empty' => 0,
        ];
        if (\Yii::$app->request->get('popup')) {
            $this->layout = 'iframe.tpl';
            return $this->render('edit-popup', $properties);
        }
        return $this->render('edit', $properties);
    }
    public function action_submit()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/texts');
        $translation_key = tep_db_prepare_input(Yii::$app->request->post('translation_key'), false);
        $translation_entity = tep_db_prepare_input(Yii::$app->request->post('translation_entity'), false);
        $replace_key = tep_db_prepare_input(Yii::$app->request->post('replace_key'), false);
        $replace_value = tep_db_prepare_input(Yii::$app->request->post('replace_value'), false);
        $translation_value = tep_db_prepare_input(Yii::$app->request->post('translation_value'), false);
        $translated = tep_db_prepare_input(Yii::$app->request->post('translated'), false);
        $checked = tep_db_prepare_input(Yii::$app->request->post('checked'), false);
        $gonext = Yii::$app->request->post('gonext', 0);
        $id = Yii::$app->request->post('id', 0);
        $by = Yii::$app->request->get('by', '');
        $search = Yii::$app->request->get('search', '');
        $sensitive = Yii::$app->request->get('sensitive', 0);
        $skip_admin = Yii::$app->request->get('skip_admin', 0);
        $unverified = Yii::$app->request->get('unverified', 0);
        $untranslated = Yii::$app->request->get('untranslated', 0);
        $to_main = Yii::$app->request->post('to_main', 0);
        $_row = Yii::$app->request->post('row', 0);
        $languages = \common\helpers\Language::get_languages(true);
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            if (isset($languages[$i]['id']) && !tep_not_null($translation_value[$languages[$i]['id']] ?? null)) {
                continue;
            }
            $updated = false;
            if ($replace_key == 'on') {
                \common\helpers\Translation::replace_translation_value_by_key($translation_key, $translation_entity, $languages[$i]['id'], $translation_value[$languages[$i]['id']]);
                $updated = true;
            }
            if ($replace_value == 'on') {
                \common\helpers\Translation::replace_translation_value_by_old_value($translation_key, $translation_entity, $languages[$i]['id'], $translation_value[$languages[$i]['id']]);
                $updated = true;
            }
            if (!$updated) {
                \common\helpers\Translation::set_translation_value($translation_key, $translation_entity, $languages[$i]['id'], $translation_value[$languages[$i]['id']]);
            }
            \common\helpers\Translation::set_translated($translation_key, $translation_entity, $languages[$i]['id'], isset($translated[$languages[$i]['id']]) ? 1 : 0);
            \common\helpers\Translation::set_checked($translation_key, $translation_entity, $languages[$i]['id'], isset($checked[$languages[$i]['id']]) ? 1 : 0);
        }
        \common\helpers\Translation::reset_cache();
        $message_type = 'success';
        if ($to_main) {
            $message = TEXT_MESSEAGE_SUCCESS_ADDED;
        } else {
            $message = TEXT_MESSEAGE_SUCCESS;
        }
        Yii::$app->session->set_flash($message_type, $message);
        $selected_entity = Yii::$app->request->post('selected_entity', '');
        if ($gonext) {
            $admin = new Admin();
            $a_info = $admin->get_additional_info();
            $add_search = '';
            if ($skip_admin) {
                $add_search .= " and t.translation_entity not like  'admin/%' ";
                $add_search .= " and t.translation_entity not like  'configuration' ";
            }
            if ($unverified) {
                $add_search .= ' and t.checked=0 ';
            }
            if ($untranslated) {
                $add_search .= ' and t.translated=0 ';
            }
            //$total_query = tep_db_query("select t.translation_key, t.translation_entity from " . TABLE_TRANSLATION. " t where language_id = '". (int)$languages_id."' and t.hash in (select distinct t1.hash from " . TABLE_TRANSLATION . " t1 where  1 " . (tep_not_null($selected_entity)? "and t1.translation_entity = '" . $selected_entity . "'" : '') . " " . (tep_not_null($by) && tep_not_null($search) ? " and t1.$by like '%" . tep_db_input($search) . "%'" : (tep_not_null($search) ? " and (t1.translation_entity like '%" . tep_db_input($search) . "%' or t1.translation_key like '%" . tep_db_input($search) . "%' or t1.translation_value like '%" . tep_db_input($search) . "%')" : '')). ") order by t.translation_key, t.translation_entity");//group by t.translation_key, t.translation_entity
            $total_query = tep_db_query('select t.translation_key, t.translation_entity from ' . TABLE_TRANSLATION . ' t where t.hash in (select distinct t1.hash from ' . TABLE_TRANSLATION . ' t1 where t1.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') ' . (tep_not_null($selected_entity) ? "and t1.translation_entity = '" . tep_db_input($selected_entity) . "'" : '') . ' ' . (tep_not_null($by) && tep_not_null($search) ? " and t1.{$by} like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%'" : (tep_not_null($search) ? ' and (t1.translation_entity like ' . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_key like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_value like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%')" : '')) . ") {$add_search} group by t.translation_key, t.translation_entity order by t.translation_key, t.translation_entity");
            $total_num = tep_db_num_rows($total_query);
            if ($total_num) {
                $spl = new \SplFixedArray($total_num + 1);
                $i = 0;
                $cursor = 0;
                while ($row = tep_db_fetch_array($total_query)) {
                    $spl[$i] = $row;
                    if ($row['translation_key'] == $translation_key && $row['translation_entity'] == $translation_entity) {
                        $cursor = $i;
                    }
                    $i++;
                }
            }
            //var_dump($cursor);die;
            try {
                if ($cursor < $total_num - 1 && $total_num) {
                    $new_row = $spl->offsetGet($cursor + 1);
                    if ($selected_entity != '') {
                        $url = \yii\helpers\Url::to(['edit', 'translation_key' => $new_row['translation_key'], 'translation_entity' => $new_row['translation_entity'], 'selected_entity' => $selected_entity]);
                    } else {
                        $url = \yii\helpers\Url::to(['edit', 'translation_key' => $new_row['translation_key'], 'translation_entity' => $new_row['translation_entity']]);
                    }
                    if (tep_not_null($search)) {
                        $url .= '&search=' . urlencode($search);
                    }
                    if (tep_not_null($by)) {
                        $url .= '&by=' . $by;
                    }
                    if ($_row) {
                        $url .= '&row=' . $_row;
                    }
                    if ($sensitive) {
                        $url .= '&sensitive=1';
                    }
                    if ($skip_admin) {
                        $url .= '&skip_admin=1';
                    }
                    if ($unverified) {
                        $url .= '&unverified=1';
                    }
                    if ($untranslated) {
                        $url .= '&untranslated=1';
                    }
                    return $this->redirect($url);
                    exit;
                } else {
                    $gonext = false;
                    $message_type = 'info';
                    $message = 'That is the last record ' . ($selected_entity != '' ? 'in "' . $selected_entity . '" entity' : '') . (tep_not_null($search) ? ' in search ' : '');
                    Yii::$app->session->set_flash($message_type, $message);
                }
            } catch (\RuntimeException $e) {
                error_log($e->get_message());
            }
        }
        if ($selected_entity != '') {
            $url = \yii\helpers\Url::to(['edit', 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'selected_entity' => $selected_entity]);
        } else {
            $url = \yii\helpers\Url::to(['edit', 'translation_key' => $translation_key, 'translation_entity' => $translation_entity]);
        }
        if (tep_not_null($search)) {
            $url .= '&search=' . urlencode($search);
        }
        if (tep_not_null($by)) {
            $url .= '&by=' . $by;
        }
        if ($_row) {
            $url .= '&row=' . $_row;
        }
        if ($sensitive) {
            $url .= '&sensitive=1';
        }
        if ($skip_admin) {
            $url .= '&skip_admin=1';
        }
        if ($unverified) {
            $url .= '&unverified=1';
        }
        if ($untranslated) {
            $url .= '&untranslated=1';
        }
        if ($to_main) {
            // new record
            return $this->redirect(['texts/']);
        } else {
            return $this->redirect($url);
        }
        exit;
    }
    public function action_delete()
    {
        $translation_key = Yii::$app->request->post('translation_key');
        $translation_entity = Yii::$app->request->post('translation_entity');
        tep_db_query('delete from ' . TABLE_TRANSLATION . " where translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "'");
        \common\helpers\Translation::reset_cache();
    }
    public function action_set_shown()
    {
        $id = Yii::$app->request->post('id', 0);
        $status = Yii::$app->request->post('status');
        $status = strtolower($status) == 'true' ? 1 : 0;
        if ($id) {
            $admin = new Admin();
            $info = $admin->get_additional_info();
            if (!isset($info['shown_language'])) {
                $info['shown_language'] = [];
            }
            if ($status) {
                $info['shown_language'][] = $id;
                $info['shown_language'] = array_unique($info['shown_language']);
            } else {
                $info['shown_language'] = array_flip($info['shown_language']);
                unset($info['shown_language'][$id]);
                $info['shown_language'] = array_flip($info['shown_language']);
            }
            $admin->save_additional_info($info);
            //tep_db_query("update " . TABLE_LANGUAGES . " set shown_language = '" . $status . "' where languages_id = '" . (int)$id . "'");
        }
        echo 'ok';
        exit;
    }
    public function action_set_searchable()
    {
        $id = Yii::$app->request->post('id', 0);
        $status = Yii::$app->request->post('status');
        $status = strtolower($status) == 'true' ? 1 : 0;
        if ($id) {
            $admin = new Admin();
            $info = $admin->get_additional_info();
            if (!isset($info['searchable_language'])) {
                $info['searchable_language'] = [];
            }
            if ($status) {
                $info['searchable_language'][] = $id;
                $info['searchable_language'] = array_unique($info['searchable_language']);
            } else {
                $info['searchable_language'] = array_flip($info['searchable_language']);
                unset($info['searchable_language'][$id]);
                $info['searchable_language'] = array_flip($info['searchable_language']);
            }
            $admin->save_additional_info($info);
            //tep_db_query("update " . TABLE_LANGUAGES . " set searchable_language = '" . $status . "' where languages_id = '" . (int)$id . "'");
        }
        echo 'ok';
        exit;
    }
    public function action_set_keyentity()
    {
        $id = Yii::$app->request->post('id');
        $status = Yii::$app->request->post('status');
        $status = strtolower($status) == 'true' ? 1 : 0;
        $admin = new Admin();
        $info = $admin->get_additional_info();
        if (!isset($info['key_entity'])) {
            $info['key_entity'] = [];
        }
        if ($status) {
            $info['key_entity'][] = $id;
            $info['key_entity'] = array_unique($info['key_entity']);
        } else {
            $info['key_entity'] = array_flip($info['key_entity']);
            unset($info['key_entity'][$id]);
            $info['key_entity'] = array_flip($info['key_entity']);
        }
        $admin->save_additional_info($info);
        echo 'ok';
        exit;
    }
    public function action_export()
    {
        \common\helpers\Translation::init('admin/texts');
        \common\helpers\Translation::init('admin/easypopulate');
        $this->layout = false;
        $languages = \common\helpers\Language::get_languages(true);
        return $this->render('export', ['languages' => $languages]);
    }
    public function action_export_start()
    {
        $this->layout = false;
        $params = Yii::$app->request->post('params');
        parse_str($params, $filter);
        $sensitive = '';
        if (isset($filter['sensitive'])) {
            $sensitive = ' BINARY ';
        }
        $search = '';
        if ($filter['search'] && tep_not_null($filter['search'])) {
            $keywords = tep_db_input(tep_db_prepare_input($filter['search']));
            switch ($filter['by']) {
                case 'translation_entity':
                    $search .= " and t.translation_entity = '" . $keywords . "' ";
                    break;
                case 'translation_key':
                    $search .= ' and t.translation_key like ' . $sensitive . " '%" . $keywords . "%' ";
                    break;
                case 'translation_value':
                    $search .= ' and t.translation_value like ' . $sensitive . " '%" . $keywords . "%' ";
                    break;
                default:
                    $search .= ' and (t.translation_key like ' . $sensitive . " '%" . $keywords . "%' or t.translation_entity like " . $sensitive . " '%" . $keywords . "%' or t.translation_value like " . $sensitive . " '%" . $keywords . "%') ";
                    break;
            }
        }
        if (!empty($filter['skip_admin'])) {
            $search .= " and t.translation_entity not like  'admin/%' ";
            $search .= " and t.translation_entity not like  'configuration' ";
        }
        if (!empty($filter['unverified'])) {
            $search .= ' and t.checked=0 ';
        }
        if (!empty($filter['untranslated'])) {
            $search .= ' and t.translated=0 ';
        }
        $sl = Yii::$app->request->post('sl');
        if (count($sl) == 0) {
            $this->redirect(['texts/']);
        }
        \common\helpers\Translation::init('admin/texts');
        $format = (string) Yii::$app->request->post('format', 'XLSX');
        if ($format == 'XLSX') {
            $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            $extension = 'xlsx';
            $CSV = new \backend\models\EP\Writer\XLSX(['filename' => 'php://output']);
        } else {
            $mime_type = 'application/vnd.ms-excel';
            $extension = 'csv';
            $CSV = new \backend\models\EP\Writer\CSV(['filename' => 'php://output']);
        }
        $filename = 'Keys_' . strftime('%Y%b%d_%H%M') . '.' . $extension;
        Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
        Yii::$app->response->set_download_headers($filename, $mime_type, false);
        Yii::$app->response->content = null;
        Yii::$app->response->send();
        $_def_id = \common\helpers\Language::get_default_language_id();
        $languages = \common\helpers\Language::get_languages(true);
        $write_data = [];
        foreach ($languages as $_lang) {
            if ($_lang['id'] == $_def_id && in_array($_lang['id'], $sl)) {
                $write_data['def_value'] = $_lang['code'];
                $write_data['def_tsl'] = $_lang['code'] . '_TSL';
            }
        }
        foreach ($languages as $_lang) {
            if ($_lang['id'] != $_def_id && in_array($_lang['id'], $sl)) {
                $write_data[$_lang['code'] . '_value'] = $_lang['code'];
                $write_data[$_lang['code'] . '_tsl'] = $_lang['code'] . '_TSL';
            }
        }
        $write_data['translation_key'] = TABLE_HEADING_LANGUAGE_KEY;
        $write_data['translation_entity'] = TABLE_HEADING_LANGUAGE_ENTITY;
        $write_data['hash'] = 'HASH';
        $CSV->set_columns($write_data);
        if (in_array($_def_id, $sl)) {
            $select = 'select t.translation_value as def_value, t.translated as def_tsl, ';
        } else {
            $select = 'select  ';
        }
        $from = ' from ' . TABLE_TRANSLATION . ' t ';
        foreach ($languages as $_lang) {
            if ($_lang['id'] != $_def_id && in_array($_lang['id'], $sl)) {
                $from .= ' left join ' . TABLE_TRANSLATION . ' ' . $_lang['code'] . ' on t.translation_key = ' . $_lang['code'] . '.translation_key and ' . $_lang['code'] . ".language_id = '" . $_lang['id'] . "' and t.translation_entity = " . $_lang['code'] . '.translation_entity ';
                $select .= ' ' . $_lang['code'] . '.translation_value as ' . $_lang['code'] . '_value, ' . $_lang['code'] . '.translated as ' . $_lang['code'] . '_tsl, ';
            }
        }
        $select = substr($select, 0, -2);
        $select .= ', t.translation_key, t.translation_entity, t.hash ';
        $where = " where t.translation_value!='' and t.language_id = " . $_def_id . $search;
        $order_by = 'translation_key';
        $main_sql = $select . $from . $where . ' ' . $search . ' group by t.translation_key, t.translation_entity order by ' . $order_by;
        $query = tep_db_query($main_sql);
        while ($data = tep_db_fetch_array($query)) {
            //            $data = preg_replace("/\r?\n/", '\n', $data);
            //            $data = preg_replace("/\t/", '\t', $data);
            $CSV->write($data);
        }
        $CSV->close();
        die;
    }
    public function action_import()
    {
        \common\helpers\Translation::init('admin/texts');
        $this->layout = false;
        return $this->render('import');
    }
    public function action_import_start()
    {
        $override = (int) Yii::$app->request->post('override');
        $addnew = (int) Yii::$app->request->post('addnew');
        $languages = \common\helpers\Language::get_languages(true);
        $filename = $_FILES['usrfl']['tmp_name'];
        if (substr($_FILES['usrfl']['name'], -5) == '.xlsx') {
            $reader = new \backend\models\EP\Reader\XLSX(['filename' => $filename]);
        } else {
            $reader = new \backend\models\EP\Reader\CSV(['filename' => $filename]);
        }
        while ($data = $reader->read()) {
            if (!array_key_exists('HASH', $data) || empty($data['HASH'])) {
                continue;
            }
            foreach ($languages as $_lang) {
                if (!isset($data[$_lang['code']])) {
                    //check and add empty value if not exist
                    continue;
                }
                $check_hash_query = tep_db_query('SELECT * FROM ' . TABLE_TRANSLATION . " WHERE language_id='" . (int) $_lang['id'] . "' and hash = '" . tep_db_input($data['HASH']) . "'");
                if (tep_db_num_rows($check_hash_query) > 0) {
                    if ($override) {
                        tep_db_query('update ' . TABLE_TRANSLATION . " set translation_value = '" . tep_db_input($data[$_lang['code']]) . "', translated = '" . tep_db_input($data[$_lang['code'] . '_TSL']) . "' where language_id = '" . (int) $_lang['id'] . "' and hash = '" . tep_db_input($data['HASH']) . "'");
                    }
                } elseif (isset($data['Entity']) && isset($data['Key'])) {
                    $check_hash_query = tep_db_query('SELECT * FROM ' . TABLE_TRANSLATION . " WHERE language_id='" . (int) $_lang['id'] . "' and translation_key = '" . tep_db_input($data['Key']) . "' and translation_entity = '" . tep_db_input($data['Entity']) . "'");
                    if (tep_db_num_rows($check_hash_query) > 0) {
                        if ($override) {
                            tep_db_query('update ' . TABLE_TRANSLATION . " set translation_value = '" . tep_db_input($data[$_lang['code']]) . "', translated = '" . tep_db_input($data[$_lang['code'] . '_TSL']) . "' where language_id = '" . (int) $_lang['id'] . "' and hash = '" . tep_db_input($data['HASH']) . "'");
                        }
                    } elseif ($addnew && !empty($data['Key']) && !empty($data['Entity'])) {
                        $hash = md5($data['Key'] . '-' . $data['Entity']);
                        $sql_data_array = ['language_id' => (int) $_lang['id'], 'translation_key' => $data['Key'], 'translation_entity' => $data['Entity'], 'translation_value' => $data[$_lang['code']], 'hash' => $hash, 'translated' => $data[$_lang['code'] . '_TSL']];
                        tep_db_perform(TABLE_TRANSLATION, $sql_data_array);
                    }
                }
            }
        }
        \common\helpers\Translation::reset_cache();
        $this->redirect(['texts/']);
    }
    public function action_change_key()
    {
        \common\helpers\Translation::init('admin/texts');
        $this->layout = false;
        $translation_key = Yii::$app->request->post('translation_key');
        $translation_entity = Yii::$app->request->post('translation_entity');
        return $this->render('change-key', ['translation_key' => $translation_key, 'translation_entity' => $translation_entity]);
    }
    public function action_change_entity()
    {
        \common\helpers\Translation::init('admin/texts');
        $this->layout = false;
        $translation_key = Yii::$app->request->post('translation_key');
        $translation_entity = Yii::$app->request->post('translation_entity');
        return $this->render('change-entity', ['translation_key' => $translation_key, 'translation_entity' => $translation_entity]);
    }
    public function action_apply_changes()
    {
        $translation_key = tep_db_prepare_input(Yii::$app->request->post('translation_key'), false);
        $translation_entity = tep_db_prepare_input(Yii::$app->request->post('translation_entity'), false);
        $new_entity = Yii::$app->request->post('new_entity', '');
        $new_key = Yii::$app->request->post('new_key', '');
        if (!empty($new_entity)) {
            $hash = md5($translation_key . '-' . $new_entity);
            $languages = \common\helpers\Language::get_languages(true);
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $sql_data_array = ['translation_entity' => $new_entity, 'hash' => $hash];
                tep_db_perform(TABLE_TRANSLATION, $sql_data_array, 'update', "language_id = '" . (int) $languages[$i]['id'] . "' and translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "'");
            }
        } elseif (!empty($new_key)) {
            $hash = md5($new_key . '-' . $translation_entity);
            $languages = \common\helpers\Language::get_languages(true);
            for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
                $sql_data_array = ['translation_key' => $new_key, 'hash' => $hash];
                tep_db_perform(TABLE_TRANSLATION, $sql_data_array, 'update', "language_id = '" . (int) $languages[$i]['id'] . "' and translation_key = '" . tep_db_input($translation_key) . "' and translation_entity = '" . tep_db_input($translation_entity) . "'");
            }
        }
        \common\helpers\Translation::reset_cache();
    }
    public function action_switch_frontend_translation()
    {
        $admin = \common\models\Admin::find_one($_SESSION['login_id']);
        $admin->frontend_translation = (int) \Yii::$app->request->get('status', 0);
        $admin->save(false);
    }
    public function action_frontend_translation()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/texts');
        $this->view->heading_title = HEADING_TITLE;
        $admin = new Admin();
        $a_info = $admin->get_additional_info();
        //$this->selectedMenu = array('settings', 'translation', 'texts');
        $this->selected_menu = ['design_controls', 'texts'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('texts/index'), 'title' => HEADING_TITLE];
        $languages = \common\helpers\Language::get_languages(true);
        $this->view->searchable_language = [];
        $this->view->shown_language = [0, \common\helpers\Language::get_default_language_id()];
        $save = false;
        if (!isset($a_info['searchable_language'])) {
            $a_info['searchable_language'] = [];
            if (is_array($languages)) {
                foreach ($languages as $_k => $_v) {
                    $a_info['searchable_language'][] = $_v['id'];
                }
            }
            $save = true;
        }
        if (!isset($a_info['shown_language'])) {
            $a_info['shown_language'] = $this->view->shown_language;
            $save = true;
        }
        if ($save) {
            $admin->save_additional_info($a_info);
        }
        $this->view->key_entity = $a_info['key_entity'];
        $_c = 0;
        foreach ($languages as $key => $value) {
            if (in_array($value['id'], $a_info['shown_language'])) {
                $this->view->languages_table[] = ['title' => $value['image'] . '&nbsp;' . ucfirst($value['code']), 'not_important' => 0];
                $_c++;
                $languages[$key]['data'] = $_c + 1;
                $languages[$key]['shown_language'] = 1;
            } else {
                $languages[$key]['shown_language'] = 0;
            }
            if (in_array($value['id'], $a_info['searchable_language'])) {
                $languages[$key]['searchable_language'] = 1;
            } else {
                $languages[$key]['searchable_language'] = 0;
            }
        }
        $this->view->filters = new \stdClass();
        $by = [['name' => TEXT_ANY, 'value' => '', 'selected' => ''], ['name' => TEXT_ENTITY, 'value' => 'translation_entity', 'selected' => ''], ['name' => TEXT_KEY, 'value' => 'translation_key', 'selected' => ''], ['name' => TEXT_VALUE, 'value' => 'translation_value', 'selected' => '']];
        foreach ($by as $key => $value) {
            if (isset($_GET['by']) && $value['value'] == $_GET['by']) {
                $by[$key]['selected'] = 'selected';
            }
        }
        $this->view->filters->by = $by;
        $search = '';
        if (isset($_GET['search'])) {
            $search = $_GET['search'];
        }
        $this->view->filters->search = $search;
        $this->view->filters->sensitive = (int) $_GET['sensitive'];
        $empty = 0;
        if (Yii::$app->request->is_post) {
            $translation_key = Yii::$app->request->post('translation_key');
            $translation_entity = Yii::$app->request->post('translation_entity');
            $selected_entity = Yii::$app->request->post('selected_entity', '');
            // $id = Yii::$app->request->post('id', 0);
            $row = Yii::$app->request->post('row', 0);
            $by = Yii::$app->request->get('by', '');
            $search = Yii::$app->request->get('search', '');
            $sensitive = Yii::$app->request->get('sensitive', 0);
            $this->layout = false;
        } else {
            $translation_key = Yii::$app->request->get('translation_key');
            $translation_entity = Yii::$app->request->get('translation_entity');
            $selected_entity = Yii::$app->request->get('selected_entity', '');
            // $id = Yii::$app->request->get('id', 0);
            $row = Yii::$app->request->get('row', 0);
            $by = Yii::$app->request->get('by', '');
            $search = Yii::$app->request->get('search', '');
            $sensitive = Yii::$app->request->get('sensitive', 0);
            $empty = Yii::$app->request->get('empty', 0);
        }
        $messages = Yii::$app->session->get_all_flashes();
        Yii::$app->session->remove_all_flashes();
        if ($empty) {
            return $this->render('edit', ['empty' => 1, 'search' => $search, 'languages' => $languages, 'messages' => $messages]);
        }
        $nextut = Yii::$app->request->get('nextut', 0);
        $total_query = tep_db_query('select t.translation_key, t.translation_entity,  min(translated) as translated from ' . TABLE_TRANSLATION . ' t where t.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') and t.hash in (select distinct t1.hash from ' . TABLE_TRANSLATION . ' t1 where t1.language_id in (' . (count($a_info['shown_language']) ? implode(',', $a_info['shown_language']) : (int) $languages_id) . ') ' . (tep_not_null($selected_entity) ? "and t1.translation_entity = '" . $selected_entity . "'" : '') . ' ' . (tep_not_null($by) && tep_not_null($search) ? ' and t1.' . ($by == 'translation_entity' ? "translation_entity = '" . tep_db_input($search) . "' " : "{$by} like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%'") : (tep_not_null($search) ? ' and (t1.translation_entity like ' . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_key like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%' or t1.translation_value like " . ($sensitive ? ' BINARY ' : '') . " '%" . tep_db_input($search) . "%')" : '')) . ') group by t.translation_key, t.translation_entity order by t.translation_key, t.translation_entity');
        $_next_ut = 0;
        $total_num = tep_db_num_rows($total_query);
        $have_untranslated = false;
        if ($total_num) {
            $spl = new \SplFixedArray($total_num + 1);
            $i = 0;
            $cursor = 0;
            $found = false;
            $setled = false;
            while ($_row = tep_db_fetch_array($total_query)) {
                if ($found) {
                    break;
                }
                $spl[$i] = $_row;
                if ($_row['translation_key'] == $translation_key && $_row['translation_entity'] == $translation_entity) {
                    $cursor = $i;
                    $have_untranslated = $_row['translated'];
                    $setled = true;
                }
                if ($nextut && $cursor < $i && $setled && !$_row['translated'] && !$_next_ut && !$found) {
                    $_next_ut = $i;
                    $found = true;
                }
                $i++;
            }
        }
        if ($_next_ut) {
            $_next = $spl->offsetGet($_next_ut);
            if ($selected_entity != '') {
                $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'selected_entity' => $selected_entity, 'row' => $row]);
            } else {
                $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'row' => $row]);
            }
            if (tep_not_null($search)) {
                $next_url .= '&search=' . $search;
            }
            if (tep_not_null($by)) {
                $next_url .= '&by=' . $by;
            }
            if ($sensitive) {
                $next_url .= '&sensitive=1';
            }
            return $this->redirect($next_url);
            exit;
        } elseif (!$_next_ut && $nextut) {
            $message = 'That is the last record ' . ($selected_entity != '' ? 'in "' . $selected_entity . '" entity' : '') . (tep_not_null($search) ? ' in search ' : '');
            Yii::$app->session->set_flash('info', $message);
        }
        $prev_url = '';
        $next_url = '';
        try {
            if ($cursor < $total_num - 1 && $total_num) {
                $_next = $spl->offsetGet($cursor + 1);
                if ($selected_entity != '') {
                    $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'selected_entity' => $selected_entity, 'row' => $row]);
                } else {
                    $next_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_next['translation_key'], 'translation_entity' => $_next['translation_entity'], 'row' => $row]);
                }
                if (tep_not_null($search)) {
                    $next_url .= '&search=' . urlencode($search);
                }
                if (tep_not_null($by)) {
                    $next_url .= '&by=' . $by;
                }
                if ($sensitive) {
                    $next_url .= '&sensitive=1';
                }
            }
            if ($cursor > 0 && $total_num) {
                $_prev = $spl->offsetGet($cursor - 1);
                if ($selected_entity != '') {
                    $prev_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_prev['translation_key'], 'translation_entity' => $_prev['translation_entity'], 'selected_entity' => $selected_entity, 'row' => $row]);
                } else {
                    $prev_url = \yii\helpers\Url::to(['edit', 'translation_key' => $_prev['translation_key'], 'translation_entity' => $_prev['translation_entity'], 'row' => $row]);
                }
                if (tep_not_null($search)) {
                    $prev_url .= '&search=' . urlencode($search);
                }
                if (tep_not_null($by)) {
                    $prev_url .= '&by=' . $by;
                }
                if ($sensitive) {
                    $prev_url .= '&sensitive=1';
                }
            }
        } catch (\RuntimeException $e) {
            error_log($e->get_message());
        }
        $values = [];
        //$languages = \common\helpers\Language::get_languages(true);
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
            $_t = !\common\helpers\Translation::is_translated($translation_key, $translation_entity, $languages[$i]['id']) ? 'untranslated' : '';
            if (is_array($a_info['shown_language']) && !in_array($languages[$i]['id'], $a_info['shown_language'])) {
                continue;
            }
            $values[$i]['flag'] = $languages[$i]['image'];
            $values[$i]['translated_checkbox'] = \yii\helpers\Html::checkbox('translated[' . $languages[$i]['id'] . ']', \common\helpers\Translation::is_translated($translation_key, $translation_entity, $languages[$i]['id']), ['title' => TEXT_SET_TRANSLATED, 'class' => 'uniform']);
            $values[$i]['checked_checkbox'] = \yii\helpers\Html::checkbox('checked[' . $languages[$i]['id'] . ']', \common\helpers\Translation::is_checked($translation_key, $translation_entity, $languages[$i]['id']), ['title' => TEXT_CHECKED, 'class' => 'uniform']);
            $values[$i]['class'] = 'form-control ' . $_t;
            $values[$i]['div_class'] = $_t;
            $values[$i]['name'] = 'translation_value[' . $languages[$i]['id'] . ']';
            $values[$i]['text'] = \common\helpers\Translation::get_translation_value($translation_key, $translation_entity, $languages[$i]['id']);
        }
        $this->layout = false;
        return $this->render('frontend-translation', ['values' => $values, 'translation_key' => $translation_key, 'translation_entity' => $translation_entity, 'selected_entity' => $selected_entity, 'next_url' => $next_url, 'prev_url' => $prev_url, 'row' => $row, 'messages' => $messages, 'get_params' => Yii::$app->request->get_query_params(), 'sensitive' => $sensitive, 'have_untranslated' => $have_untranslated, 'search' => $search, 'languages' => $languages, 'empty' => 0]);
    }
}