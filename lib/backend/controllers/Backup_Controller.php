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

use Yii;
use yii\helpers\Html;
use yii\helpers\Url;
class Backup_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_TOOLS', 'BOX_TOOLS_BACKUP'];
    public $dir_ok = false;
    public $contents = [];
    public $dir = false;
    public $exec_gzip_available = false;
    public $exec_zip_available = false;
    private function tep_remove($source)
    {
        global $tep_remove_error;
        $message_stack = \Yii::$container->get('message_stack');
        if (isset($tep_remove_error)) {
            $tep_remove_error = false;
        }
        if (is_dir($source)) {
            $dir = dir($source);
            while ($file = $dir->read()) {
                if ($file != '.' && $file != '..') {
                    if (is_writeable($source . '/' . $file)) {
                        $this->tep_remove($source . '/' . $file);
                    } else {
                        $message_stack->add(sprintf(ERROR_FILE_NOT_REMOVEABLE, $source . '/' . $file));
                        $tep_remove_error = true;
                    }
                }
            }
            $dir->close();
            if (is_writeable($source)) {
                rmdir($source);
            } else {
                $message_stack->add(sprintf(ERROR_DIRECTORY_NOT_REMOVEABLE, $source));
                $tep_remove_error = true;
            }
        } else if (is_writeable($source)) {
            unlink($source);
        } else {
            $message_stack->add(sprintf(ERROR_FILE_NOT_REMOVEABLE, $source));
            $tep_remove_error = true;
        }
    }
    public function __construct($id, $module = null)
    {
        $message_stack = \Yii::$container->get('message_stack');
        \common\helpers\Translation::init('admin/backup');
        if ($this->is_exec_available()) {
            exec(LOCAL_EXE_GZIP, $output, $return_var);
            if (!$return_var) {
                $this->exec_gzip_available = true;
            }
        }
        if ($this->is_exec_available()) {
            exec(LOCAL_EXE_ZIP, $output, $return_var);
            if (!$return_var) {
                $this->exec_zip_available = true;
            }
        }
        if (is_dir(DIR_FS_BACKUP)) {
            if (is_writeable(DIR_FS_BACKUP)) {
                $this->dir_ok = true;
                $this->dir = dir(DIR_FS_BACKUP);
            } else {
                $message_stack->add(ERROR_BACKUP_DIRECTORY_NOT_WRITEABLE);
            }
        } else {
            $message_stack->add(ERROR_BACKUP_DIRECTORY_DOES_NOT_EXIST);
        }
        parent::__construct($id, $module);
    }
    public function action_index()
    {
        $message_stack = \Yii::$container->get('message_stack');
        $this->selected_menu = ['settings', 'tools', 'backup'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('backup/index'), 'title' => HEADING_TITLE];
        if ($this->dir) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('backup/backup') . '" class="btn btn-primary backup"><i class="icon-file-text"></i>' . IMAGE_BACKUP . '</a>';
        }
        if ($this->dir) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('backup/restorelocal') . '" class="btn btn-primary restore"><i class="icon-file-text"></i>' . IMAGE_RESTORE . '</a>';
        }
        $this->view->heading_title = HEADING_TITLE;
        $this->view->backup_table = [['title' => TABLE_HEADING_TITLE, 'not_important' => 0], ['title' => TABLE_HEADING_FILE_DATE, 'not_important' => 0], ['title' => TABLE_HEADING_FILE_SIZE, 'not_important' => 0]];
        /*if ($messageStack->size() > 0) {
              $this->view->errorMessage = $messageStack->output(true);
              $this->view->errorMessageType = $messageStack->messageType;
          }*/
        $params = ['backupPath' => TEXT_BACKUP_DIRECTORY . ' ' . DIR_FS_BACKUP];
        if (defined('DB_LAST_RESTORE')) {
            $params['forget'] = TEXT_LAST_RESTORATION . ' ' . DB_LAST_RESTORE . ' <a href="' . tep_href_link(FILENAME_BACKUP . '/forget', '') . '">' . TEXT_FORGET . '</a>';
        }
        return $this->render('index', $params);
    }
    public function get_contents()
    {
        if ($this->dir_ok == true && $this->dir) {
            if (isset($_GET['search']['value']) && tep_not_null($_GET['search']['value'])) {
                $keywords = tep_db_input(tep_db_prepare_input($_GET['search']['value']));
            }
            while ($file = $this->dir->read()) {
                if (!is_dir(DIR_FS_BACKUP . $file) && $file != '.htaccess') {
                    if (empty($keywords)) {
                        $this->contents[] = $file;
                    } elseif (strpos($file, $keywords) !== false) {
                        $this->contents[] = $file;
                    }
                }
            }
            usort($this->contents, function ($a, $b) {
                return filemtime(DIR_FS_BACKUP . $a) > filemtime(DIR_FS_BACKUP . $b) ? -1 : 1;
            });
            $this->dir->close();
        }
    }
    public function get_current_backup($entry)
    {
        $file_array['file'] = $entry;
        $file_array['date'] = date(PHP_DATE_TIME_FORMAT, filemtime(DIR_FS_BACKUP . $entry));
        $file_array['size'] = number_format(filesize(DIR_FS_BACKUP . $entry)) . ' bytes';
        switch (substr($entry, -3)) {
            case 'zip':
                $file_array['compression'] = 'ZIP';
                break;
            case '.gz':
                $file_array['compression'] = 'GZIP';
                break;
            default:
                $file_array['compression'] = TEXT_NO_EXTENSION;
                break;
        }
        /*
                  if (isset($buInfo) && is_object($buInfo) && ($entry == $buInfo->file)) {
                    echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">' . "\n";
                    $onclick_link = 'file=' . $buInfo->file . '&action=restore';
                  } else {
                    echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)">' . "\n";
                    $onclick_link = 'file=' . $entry;
                  }
        */
        return new \Object_Info($file_array);
    }
    private function get_file_comments($file_name)
    {
        $file_name = trim($file_name, '.gz');
        $comments_location = DIR_FS_BACKUP . 'comments' . DIRECTORY_SEPARATOR;
        if (is_file($comments_location . $file_name . '.txt')) {
            $file_name = file_get_contents($comments_location . $file_name . '.txt');
            $file_name = str_replace("\n", '<br>', \common\helpers\Output::strip_tags($file_name));
        }
        return $file_name;
    }
    public function action_list()
    {
        $draw = Yii::$app->request->get('draw');
        $start = Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length');
        if ($length == -1) {
            $length = 10000;
        }
        $response_list = [];
        if ($this->dir_ok == true) {
            $this->get_contents();
            for ($i = $start, $n = count($this->contents); $i < $n && $i < $start + $length; $i++) {
                $entry = $this->contents[$i];
                $response_list[] = [Html::a('<i style="font-size: 1.2em" class="icon-download"></i>', tep_href_link(FILENAME_BACKUP . '/download', tep_session_name() . '=' . tep_session_id() . '&file=' . $entry)) . ' ' . $this->get_file_comments($entry) . '<input class="cell_identify" type="hidden" value="' . $entry . '">', date(PHP_DATE_TIME_FORMAT, filemtime(DIR_FS_BACKUP . $entry)), number_format(filesize(DIR_FS_BACKUP . $entry)) . 'bytes'];
            }
        }
        $response = ['draw' => $draw, 'recordsTotal' => count($this->contents), 'recordsFiltered' => count($this->contents), 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_backup()
    {
        $backup_html = '<div class="or_box_head">' . TEXT_INFO_HEADING_NEW_BACKUP . '</div>';
        $backup_html .= '<div class="col_desc">' . TEXT_INFO_NEW_BACKUP . '</div>';
        $backup_html .= tep_draw_form('backup', 'backup/backupnow', tep_session_name() . '=' . tep_session_id());
        $backup_html .= '<br>' . TEXT_COMMENTS . ':';
        $backup_html .= '<br>' . '<textarea name="comments"></textarea>';
        $connection = Yii::$app->get_db();
        $tables = $connection->create_command('SHOW TABLES FROM `' . DB_DATABASE . '`;')->query_column();
        sort($tables, SORT_STRING);
        $backup_html .= '<br><br>' . tep_draw_checkbox_field('all_tables', 'yes', true, '', 'onchange="selectTables(this);"') . ' ' . ' All tables';
        $backup_html .= '<div class="export_table_list" style="display: none;">';
        $backup_html .= '<table><thead><tr><th></th><th>' . TEXT_SELECTED_TABLES . '</th></tr></thead>';
        $backup_html .= '<tbody>';
        $backup_html .= '<tr><td><input type="checkbox" onclick="$(\'input.db_table\').prop(\'checked\',this.checked?\'checked\':null)"></td><td><u><nobr>' . TEXT_CHECK_ALL . '</nobr> / <nobr>' . TEXT_UNCHECK_ALL . '</nobr></u></td></tr>';
        foreach ($tables as $table) {
            $backup_html .= '<tr><td>' . tep_draw_checkbox_field('tables[]', $table, false, '', 'class="db_table"') . '</td><td>' . $table . '</td></tr>';
        }
        $backup_html .= '</tbody></table></div>';
        $backup_html .= '<br><br>' . tep_draw_radio_field('compress', 'no', true) . ' ' . TEXT_INFO_USE_NO_COMPRESSION;
        if ($this->exec_gzip_available || extension_loaded('zlib')) {
            $backup_html .= '<br>' . tep_draw_radio_field('compress', 'gzip') . ' ' . TEXT_INFO_USE_GZIP;
        }
        if ($this->exec_zip_available || extension_loaded('zip')) {
            $backup_html .= tep_draw_radio_field('compress', 'zip') . ' ' . TEXT_INFO_USE_ZIP;
        }
        $backup_html .= '<br><br>';
        if ($this->dir_ok == true) {
            $backup_html .= tep_draw_checkbox_field('download', 'yes') . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '*<br>*' . TEXT_INFO_BEST_THROUGH_HTTPS;
        } else {
            $backup_html .= tep_draw_radio_field('download', 'yes', true) . ' ' . TEXT_INFO_DOWNLOAD_ONLY . '*<br>*' . TEXT_INFO_BEST_THROUGH_HTTPS;
        }
        $backup_html .= '<input type="submit" class="btn btn-primary" value="' . IMAGE_BACKUP . '">&nbsp;' . '<button class="btn btn-cancel"  onClick="return resetStatement();">' . IMAGE_CANCEL . '</button>';
        $backup_html .= '<script type="text/javascript">function selectTables(obj) { if ($(obj).prop("checked")) { $(".export_table_list").hide() } else { $(".export_table_list").show() } }</script>';
        $backup_html .= '</form>';
        echo $backup_html;
    }
    public function action_backupnow()
    {
        $message_stack = \Yii::$container->get('message_stack');
        set_time_limit(0);
        $backup_file = 'db_' . DB_DATABASE . '-' . date('YmdHis') . '.sql';
        $comments_txt = TEXT_BACKUP_FILE . ': ' . $backup_file . "\n";
        $all_tables = Yii::$app->request->post('all_tables', 'no');
        if ($all_tables == 'yes') {
            $tables_list = '';
            $comments_txt .= TEXT_EXPORTED_ALL_TABLES . ".\n";
        } else {
            $tables = Yii::$app->request->post('tables');
            if (is_array($tables) && count($tables) > 0) {
                $tables_list = ' ' . implode(' ', $tables);
                $comments_txt .= TEXT_EXPORTED_TABLES . ': ' . implode(', ', $tables) . "\n";
            } else {
                $message_stack->add_session('Select specific tables', 'header', 'error');
                return $this->redirect(Url::to_route('backup/'));
            }
        }
        $comments = Yii::$app->request->post('comments');
        if (!empty($comments)) {
            $comments_txt .= TEXT_ADDED_COMMENTS . ': ' . $comments;
        }
        exec('mysqldump -h' . DB_SERVER . ' -u' . DB_SERVER_USERNAME . ' -p' . DB_SERVER_PASSWORD . ' ' . DB_DATABASE . $tables_list . ' > ' . DIR_FS_BACKUP . $backup_file);
        $comments_location = DIR_FS_BACKUP . 'comments' . DIRECTORY_SEPARATOR;
        if (!file_exists($comments_location)) {
            mkdir($comments_location, 0777, true);
        }
        file_put_contents($comments_location . $backup_file . '.txt', $comments_txt);
        if (isset($_POST['download']) && $_POST['download'] == 'yes') {
            switch ($_POST['compress']) {
                case 'gzip':
                    if ($this->exec_gzip_available == true) {
                        exec(LOCAL_EXE_GZIP . ' ' . DIR_FS_BACKUP . $backup_file);
                        $backup_file .= '.gz';
                    } elseif (extension_loaded('zlib')) {
                        $this->gz_compress_file(DIR_FS_BACKUP . $backup_file);
                        unlink(DIR_FS_BACKUP . $backup_file);
                        $backup_file .= '.gz';
                    }
                    break;
                case 'zip':
                    if ($this->exec_zip_available == true) {
                        exec(LOCAL_EXE_ZIP . ' -j ' . DIR_FS_BACKUP . $backup_file . '.zip ' . DIR_FS_BACKUP . $backup_file);
                        unlink(DIR_FS_BACKUP . $backup_file);
                        $backup_file .= '.zip';
                    } elseif (extension_loaded('zip')) {
                        $zip = new \Zip_Archive();
                        if ($zip->open(DIR_FS_BACKUP . $backup_file . '.zip') === true) {
                            $zip->add_file(DIR_FS_BACKUP . $backup_file, $backup_file);
                            $zip->close();
                            unlink(DIR_FS_BACKUP . $backup_file);
                            $backup_file .= '.zip';
                        }
                    }
            }
            header('Cache-Control: none');
            header('Pragma: none');
            header('Content-type: application/x-octet-stream');
            header('Content-disposition: attachment; filename=' . $backup_file);
            readfile(DIR_FS_BACKUP . $backup_file);
            unlink(DIR_FS_BACKUP . $backup_file);
            exit;
        } else {
            switch ($_POST['compress']) {
                case 'gzip':
                    if ($this->exec_gzip_available == true) {
                        exec(LOCAL_EXE_GZIP . ' ' . DIR_FS_BACKUP . $backup_file);
                    } elseif (extension_loaded('zlib')) {
                        $this->gz_compress_file(DIR_FS_BACKUP . $backup_file);
                        unlink(DIR_FS_BACKUP . $backup_file);
                    }
                    break;
                case 'zip':
                    if ($this->exec_zip_available == true) {
                        exec(LOCAL_EXE_ZIP . ' -j ' . DIR_FS_BACKUP . $backup_file . '.zip ' . DIR_FS_BACKUP . $backup_file);
                        unlink(DIR_FS_BACKUP . $backup_file);
                    } elseif (extension_loaded('zip')) {
                        $zip = new \Zip_Archive();
                        if ($zip->open(DIR_FS_BACKUP . $backup_file . '.zip', \ZIPARCHIVE::CREATE) === true) {
                            $zip->add_file(DIR_FS_BACKUP . $backup_file, $backup_file);
                            $zip->close();
                            unlink(DIR_FS_BACKUP . $backup_file);
                        }
                    }
            }
            $message_stack->add_session(SUCCESS_DATABASE_SAVED, 'header', 'success');
        }
        return $this->redirect(Url::to_route('backup/'));
    }
    public function action_download()
    {
        $this->layout = false;
        $message_stack = \Yii::$container->get('message_stack');
        $backup_file = basename(Yii::$app->request->get('file', ''));
        $extension = substr($backup_file, -3);
        if ($extension == 'zip' || $extension == '.gz' || $extension == 'sql') {
            if (is_file(DIR_FS_BACKUP . $backup_file)) {
                Yii::$app->response->send_file(DIR_FS_BACKUP . $backup_file, $backup_file, ['mimeType' => 'application/x-octet-stream']);
            }
        } else {
            $message_stack->add(ERROR_DOWNLOAD_LINK_NOT_ACCEPTABLE);
        }
    }
    public function action_restore()
    {
        $file = Yii::$app->request->get('file');
        if ($file) {
            $bu_info = $this->get_current_backup($file);
            echo '<div class="or_box_head">' . $bu_info->date . '</div>';
            echo \common\helpers\Output::break_string(sprintf(TEXT_INFO_RESTORE, DIR_FS_BACKUP . ($bu_info->compression != TEXT_NO_EXTENSION ? substr($bu_info->file, 0, strrpos($bu_info->file, '.')) : $bu_info->file), $bu_info->compression != TEXT_NO_EXTENSION ? TEXT_INFO_UNPACK : ''), 35, ' ');
            echo '<br><a href="' . tep_href_link(FILENAME_BACKUP . '/restorenow', 'file=' . $bu_info->file . '&action=restorenow') . '" class="btn btn-primary">' . IMAGE_RESTORE . '</a>&nbsp;<button class="btn btn-cancel" onClick="return resetStatement()">' . IMAGE_CANCEL . '</button>';
        }
    }
    public function action_view()
    {
        $file = Yii::$app->request->get('file');
        if ($file) {
            $bu_info = $this->get_current_backup($file);
            echo '<div class="or_box_head">' . $bu_info->date . '</div>';
            echo '<div class="col_desc">' . TEXT_INFO_DATE . ' ' . $bu_info->date . '</div>';
            echo '<div class="col_desc">' . TEXT_INFO_SIZE . ' ' . $bu_info->size . '</div>';
            echo '<div class="col_desc">' . TEXT_INFO_COMPRESSION . ' ' . $bu_info->compression . '</div>';
            echo '<button class="btn btn-primary" onclick="actionFile(\'' . $bu_info->file . '\', \'restore\');">' . IMAGE_RESTORE . '</button> <button class="btn btn-delete" onclick="actionFile(\'' . $bu_info->file . '\', \'delete\');">' . IMAGE_DELETE . '</button>';
        }
    }
    public function action_delete()
    {
        $file = Yii::$app->request->get('file');
        if ($file) {
            $bu_info = $this->get_current_backup($file);
            echo '<div class="or_box_head">' . $bu_info->date . '</div>';
            echo tep_draw_form('delete', 'backup/deleteconfirm', 'file=' . $bu_info->file);
            echo '<div class="col_desc">' . TEXT_DELETE_INTRO . '</div>';
            echo '<div class="col_desc">' . $bu_info->file . '</div>';
            echo '<br><input type="submit" class="btn btn-delete" value="' . IMAGE_DELETE . '"> <button class="btn btn-cancel" onclick="return resetStatement()">' . IMAGE_CANCEL . '</button>';
            echo '</form>';
        }
    }
    public function action_deleteconfirm()
    {
        global $tep_remove_error;
        $message_stack = \Yii::$container->get('message_stack');
        if (strstr($_GET['file'], '..')) {
            return $this->redirect(Url::to_route('backup/'));
        }
        if (is_file(DIR_FS_BACKUP . '/' . $_GET['file'])) {
            $this->tep_remove(DIR_FS_BACKUP . '/' . $_GET['file']);
            if (is_file(DIR_FS_BACKUP . 'comments' . DIRECTORY_SEPARATOR . $_GET['file'] . '.txt')) {
                $this->tep_remove(DIR_FS_BACKUP . 'comments' . DIRECTORY_SEPARATOR . $_GET['file'] . '.txt');
            }
        }
        if (!$tep_remove_error) {
            $message_stack->add_session(SUCCESS_BACKUP_DELETED, 'header', 'success');
        }
        return $this->redirect(Url::to_route('backup/'));
    }
    public function action_restorenow()
    {
        $message_stack = \Yii::$container->get('message_stack');
        set_time_limit(0);
        $action = Yii::$app->request->get('action', '');
        if ($action == 'restorenow') {
            $read_from = Yii::$app->request->get('file', '');
            if (file_exists(DIR_FS_BACKUP . $read_from)) {
                $restore_file = DIR_FS_BACKUP . $read_from;
                $extension = substr($read_from, -3);
                if ($extension == 'sql' || $extension == '.gz' || $extension == 'zip') {
                    switch ($extension) {
                        case 'sql':
                            $restore_from = $restore_file;
                            $remove_raw = false;
                            break;
                        case '.gz':
                            $restore_from = substr($restore_file, 0, -3);
                            if ($this->exec_gzip_available == true) {
                                exec(LOCAL_EXE_GUNZIP . ' ' . $restore_file . ' -c > ' . $restore_from);
                                $remove_raw = true;
                            } elseif (extension_loaded('zlib')) {
                                $this->gz_un_compress_file($restore_file, $restore_from);
                                $remove_raw = true;
                            }
                            break;
                        case 'zip':
                            $restore_from = substr($restore_file, 0, -4);
                            if ($this->exec_zip_available == true) {
                                exec(LOCAL_EXE_UNZIP . ' ' . $restore_file . ' -d ' . DIR_FS_BACKUP);
                                $remove_raw = true;
                            } elseif (extension_loaded('zip')) {
                                $zip = new \Zip_Archive();
                                if ($zip->open($restore_file) === true) {
                                    $zip->extract_to(DIR_FS_BACKUP);
                                    $zip->close();
                                    $remove_raw = true;
                                }
                            }
                    }
                    if (isset($restore_from) && file_exists($restore_from) && filesize($restore_from) > 15000) {
                        exec('mysql -h' . DB_SERVER . ' -u' . DB_SERVER_USERNAME . ' -p' . DB_SERVER_PASSWORD . ' ' . DB_DATABASE . ' < ' . $restore_from);
                        tep_db_query('delete from ' . TABLE_CONFIGURATION . " where configuration_key = 'DB_LAST_RESTORE'");
                        tep_db_query('insert into ' . TABLE_CONFIGURATION . " values ('', 'Last Database Restore', 'DB_LAST_RESTORE', '" . tep_db_input($read_from) . "', 'Last database restore file', '6', '', '', now(), '', '')");
                        if (isset($remove_raw) && $remove_raw == true) {
                            unlink($restore_from);
                        }
                        $message_stack->add_session(SUCCESS_DATABASE_RESTORED, 'header', 'success');
                        return $this->redirect(Url::to_route('backup/'));
                        //$fd = fopen($restore_from, 'rb');
                        //$restore_query = fread($fd, filesize($restore_from));
                        //fclose($fd);
                    }
                }
            }
        } elseif ($action == 'restorelocalnow') {
            $sql_file = new \upload('sql_file');
            if ($sql_file->parse() == true) {
                $restore_query = fread(fopen($sql_file->tmp_filename, 'r'), filesize($sql_file->tmp_filename));
                $read_from = $sql_file->filename;
            }
        }
        if (isset($restore_query)) {
            $sql_array = [];
            $sql_length = strlen($restore_query);
            $pos = strpos($restore_query, ';');
            for ($i = $pos; $i < $sql_length; $i++) {
                if ($restore_query[0] == '#') {
                    $restore_query = ltrim(substr($restore_query, strpos($restore_query, "\n")));
                    $sql_length = strlen($restore_query);
                    $i = strpos($restore_query, ';') - 1;
                    continue;
                }
                if ($restore_query[$i + 1] == "\n") {
                    for ($j = $i + 2; $j < $sql_length; $j++) {
                        if (trim($restore_query[$j]) != '') {
                            $next = substr($restore_query, $j, 6);
                            if ($next[0] == '#') {
                                // find out where the break position is so we can remove this line (#comment line)
                                for ($k = $j; $k < $sql_length; $k++) {
                                    if ($restore_query[$k] == "\n") {
                                        break;
                                    }
                                }
                                $query = substr($restore_query, 0, $i + 1);
                                $restore_query = substr($restore_query, $k);
                                // join the query before the comment appeared, with the rest of the dump
                                $restore_query = $query . $restore_query;
                                $sql_length = strlen($restore_query);
                                $i = strpos($restore_query, ';') - 1;
                                continue 2;
                            }
                            break;
                        }
                    }
                    if ($next == '') {
                        // get the last insert query
                        $next = 'insert';
                    }
                    if (preg_match('/create/i', $next) || preg_match('/insert/i', $next) || preg_match('/drop t/i', $next)) {
                        $next = '';
                        $sql_array[] = substr($restore_query, 0, $i);
                        $restore_query = ltrim(substr($restore_query, $i + 1));
                        $sql_length = strlen($restore_query);
                        $i = strpos($restore_query, ';') - 1;
                    }
                }
            }
            tep_db_query('drop table if exists address_book, address_format, banners, banners_history, categories, categories_description, configuration, configuration_group, counter, counter_history, countries, currencies, customers, customers_basket, customers_basket_attributes, customers_info, languages, manufacturers, manufacturers_info, orders, orders_products, orders_status, orders_status_history, orders_products_attributes, orders_products_download, products, products_attributes, products_attributes_download, prodcts_description, products_options, products_options_values, products_options_values_to_products_options, products_to_categories, reviews, reviews_description, sessions, specials, tax_class, tax_rates, geo_zones, whos_online, zones, zones_to_geo_zones');
            for ($i = 0, $n = sizeof($sql_array); $i < $n; $i++) {
                tep_db_query($sql_array[$i]);
            }
            tep_db_query('delete from ' . TABLE_CONFIGURATION . " where configuration_key = 'DB_LAST_RESTORE'");
            tep_db_query('insert into ' . TABLE_CONFIGURATION . " values ('', 'Last Database Restore', 'DB_LAST_RESTORE', '" . tep_db_input($read_from) . "', 'Last database restore file', '6', '', '', now(), '', '')");
            if (isset($remove_raw) && $remove_raw == true) {
                unlink($restore_from);
            }
            $message_stack->add_session(SUCCESS_DATABASE_RESTORED, 'header', 'success');
        }
        return $this->redirect(Url::to_route('backup/'));
    }
    public function action_forget()
    {
        $message_stack = \Yii::$container->get('message_stack');
        tep_db_query('delete from ' . TABLE_CONFIGURATION . " where configuration_key = 'DB_LAST_RESTORE'");
        $message_stack->add_session(SUCCESS_LAST_RESTORE_CLEARED, 'header', 'success');
        return $this->redirect(Url::to_route('backup/'));
    }
    public function action_restorelocal()
    {
        echo '<div class="or_box_head">' . TEXT_INFO_HEADING_RESTORE_LOCAL . '</div>';
        echo tep_draw_form('restore', FILENAME_BACKUP . '/restorenow', 'action=restorelocalnow', 'post', 'enctype="multipart/form-data"');
        echo TEXT_INFO_RESTORE_LOCAL . '<br><br>' . TEXT_INFO_BEST_THROUGH_HTTPS;
        echo '<br>' . tep_draw_file_field('sql_file');
        echo TEXT_INFO_RESTORE_LOCAL_RAW_FILE;
        echo '<br><input type="submit" value="' . IMAGE_RESTORE . '" class="btn btn-primary">&nbsp;<button class="btn btn-cancel" onclick="return resetStatement()">' . IMAGE_CANCEL . '</button>';
        echo '</form>';
    }
    public function is_exec_available()
    {
        $available = true;
        if (ini_get('safe_mode')) {
            $available = false;
        } else {
            $d = ini_get('disable_functions');
            $s = ini_get('suhosin.executor.func.blacklist');
            if ("{$d}{$s}") {
                $array = preg_split('/,\s*/', "{$d},{$s}");
                if (in_array('exec', $array)) {
                    $available = false;
                }
            }
        }
        return $available;
    }
    public function gz_compress_file($source, $level = 9)
    {
        $dest = $source . '.gz';
        $mode = 'wb' . $level;
        $error = false;
        if ($fp_out = gzopen($dest, $mode)) {
            if ($fp_in = fopen($source, 'rb')) {
                while (!feof($fp_in)) {
                    gzwrite($fp_out, fread($fp_in, 4096));
                }
                fclose($fp_in);
            } else {
                $error = true;
            }
            gzclose($fp_out);
        } else {
            $error = true;
        }
        if ($error) {
            return false;
        } else {
            return $dest;
        }
    }
    public function gz_un_compress_file($src_name, $dst_name)
    {
        $sfp = gzopen($src_name, 'rb');
        $fp = fopen($dst_name, 'w');
        while (!gzeof($sfp)) {
            $string = gzread($sfp, 4096);
            fwrite($fp, $string, strlen($string));
        }
        gzclose($sfp);
        fclose($fp);
    }
}