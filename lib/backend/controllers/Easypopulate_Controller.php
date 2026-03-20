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

use backend\models\EP;
use common\api\models\XML\Io_Core;
use common\classes\platform;
use Yii;
use yii\helpers\File_Helper;
use yii\i18n\Formatter;
class Easypopulate_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_CATALOG', 'BOX_CATALOG_EASYPOPULATE'];
    public $import_folder = 'import';
    /**
     *
     * @var EP/Directory
     */
    public $current_directory;
    public $selected_root_directory_id;
    public function __construct($id, $module = null)
    {
        parent::__construct($id, $module);
        $request_directory = Yii::$app->request->post('directory_id');
        if (empty($request_directory)) {
            $request_directory = Yii::$app->request->get('directory_id', 1);
        }
        foreach (EP\Directory::get_all() as $Directory) {
            if (empty($this->current_directory)) {
                $this->current_directory = $Directory;
                $this->selected_root_directory_id = $Directory->directory_id;
            }
            if ($request_directory == $Directory->directory_id) {
                $this->current_directory = $Directory;
                $this->selected_root_directory_id = $Directory->directory_id;
                break;
            }
        }
        if ($this->current_directory->parent_id) {
            $walk_directory = $this->current_directory;
            while ($walk_directory = $walk_directory->get_parent()) {
                $this->selected_root_directory_id = $walk_directory->directory_id;
            }
        }
        \common\helpers\Translation::init('admin/easypopulate');
    }
    public function action_index()
    {
        $message_stack = \Yii::$container->get('message_stack');
        $this->selected_menu = ['catalog', 'easypopulate'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('easypopulate/index'), 'title' => EP_HEDING_TITLE];
        if ($this->selected_root_directory_id == 5 && count(EP\Data_Sources::get_available_list()) > 0) {
            $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['easypopulate/create-data-source']) . '" class="create_item js-create-datasource"><i class="icon-file-text"></i>' . 'Create data source' . '</a>';
        }
        $this->view->heading_title = EP_HEDING_TITLE;
        if (Yii::$app->request->is_post) {
            $datasource = tep_db_prepare_input(Yii::$app->request->post('datasource'));
            foreach ($datasource as $ds_key => $ds_settings) {
                if (!class_exists('backend\models\EP\Datasource\\' . $ds_key)) {
                    continue;
                }
                $ds_settings = call_user_func_array(['backend\models\EP\Datasource\\' . $ds_key, 'beforeSettingSave'], [$ds_settings]);
                $settings = json_encode($ds_settings);
                tep_db_query('INSERT INTO ep_datasources (code, settings) ' . 'VALUES ' . " ('" . tep_db_input($ds_key) . "', '" . tep_db_input($settings) . "') " . "ON DUPLICATE KEY UPDATE settings='" . tep_db_input($settings) . "'");
            }
            $this->redirect(Yii::$app->url_manager->create_url(['easypopulate/index'] + Yii::$app->request->get()));
        }
        /*
        $warn_products   = '';
        $warn_caregories = '';
        $office_limit    = 31998;
        $tpd_r           = tep_db_query( "SELECT max(length(products_description)) as pd_len, max(length(products_head_desc_tag)) as hd_len, max(length(products_head_keywords_tag )) as hk_len FROM " . TABLE_PRODUCTS_DESCRIPTION );
        $tpd_a           = tep_db_fetch_array( $tpd_r );
        foreach( $tpd_a as $col => $max_length ) {
            if( (int) $max_length > (int) $office_limit ) $warn_products = TEXT_WARN_LONGTEXT_EDIT;
        }
        $tpd_r = tep_db_query( "SELECT max(length(categories_description)) as cd_len, max(length(categories_head_desc_tag)) as hd_len, max(length(categories_head_keywords_tag)) as hk_len FROM " . TABLE_CATEGORIES_DESCRIPTION );
        $tpd_a = tep_db_fetch_array( $tpd_r );
        foreach( $tpd_a as $col => $max_length ) {
            if( (int) $max_length > (int) $office_limit ) $warn_caregories = TEXT_WARN_LONGTEXT_EDIT;
        }
        */
        if (!is_dir(Yii::get_alias('@ep_files'))) {
            $message_stack->add(sprintf(ERROR_DATA_DIRECTORY_MISSING, Yii::get_alias('@ep_files')));
        } elseif (!is_writeable(Yii::get_alias('@ep_files'))) {
            $message_stack->add(sprintf(ERROR_DATA_DIRECTORY_NOT_WRITEABLE, Yii::get_alias('@ep_files')));
        }
        $this->view->import_folder = $this->current_directory->files_root(EP\Directory::TYPE_IMAGES);
        if (!file_exists($this->view->import_folder)) {
            @mkdir($this->view->import_folder, 0777, true);
        }
        \common\helpers\Translation::init('admin/categories');
        $message_stack_output = '';
        if ($message_stack->size() > 0) {
            $message_stack_output = $message_stack->output();
        }
        $providers = new EP\Providers();
        $import_providers = $providers->pull_down_variants('Import', ['items' => ['' => TEXT_OPTION_AUTO], 'options' => ['class' => 'form-control']]);
        $export_options = $providers->pull_down_variants('Export', ['selection' => '', 'items' => ['' => PULL_DOWN_DEFAULT], 'options' => ['class' => 'form-control', 'required' => 'true', 'options' => []]]);
        $download_format_down_data = ['selection' => 'CSV', 'items' => ['' => PULL_DOWN_DEFAULT, 'CSV' => TEXT_OPTION_EXPORT_CSV, 'XLSX' => 'XLSX', 'ZIP' => TEXT_OPTION_EXPORT_ZIP, 'XML_orders_new' => TEXT_OPTION_EXPORT_ORDERS_NEW_XML, 'XML' => TEXT_OPTION_EXPORT_ORDERS_NEW_XML, 'XML-ZIP' => TEXT_OPTION_EXPORT_ORDERS_NEW_XML . ' ' . TEXT_OPTION_EXPORT_ZIP]];
        $directories = [];
        foreach (EP\Directory::get_all_roots() as $Directory) {
            if ($Directory->parent_id != 0 || empty($Directory->name)) {
                continue;
            }
            /**
             * @var EP\Directory $Directory
             */
            $directories[] = ['id' => $Directory->directory_id, 'text' => $Directory->name, 'link' => Yii::$app->url_manager->create_url(['easypopulate/', 'directory_id' => $Directory->directory_id])];
        }
        $order_year_start = $order_year_end = date('Y');
        $order_start_from_r = tep_db_query('SELECT MIN(YEAR(date_purchased)) AS min_year FROM ' . TABLE_ORDERS);
        if (tep_db_num_rows($order_start_from_r) > 0) {
            $order_start_from = tep_db_fetch_array($order_start_from_r);
            $order_year_start = $order_start_from['min_year'];
        }
        $order_year_range = [];
        for ($i = $order_year_start; $i <= $order_year_end; $i++) {
            $order_year_range[(int) $i] = (int) $i;
        }
        $order_month_range = array_map(function ($i) {
            return $i == 0 ? TEXT_ALL : sprintf('%02s', $i);
        }, range(0, 12));
        $filter_defaults = ['project' => ['value' => '', 'items' => ['' => ''] + Io_Core::get()->get_project_list()], 'order' => ['date_type_range' => ['value' => 'presel'], 'year' => ['value' => date('Y'), 'items' => $order_year_range], 'month' => ['items' => $order_month_range, 'value' => ''], 'interval' => ['value' => '', 'items' => ['' => TEXT_ALL, '1' => TEXT_TODAY, 'week' => TEXT_WEEK, 'month' => TEXT_THIS_MONTH, 'year' => TEXT_THIS_YEAR, '3' => TEXT_LAST_THREE_DAYS, '7' => TEXT_LAST_SEVEN_DAYS, '14' => TEXT_LAST_FOURTEEN_DAYS, '30' => TEXT_LAST_THIRTY_DAYS]]]];
        $view_data = [
            'current_directory_id' => $this->current_directory->directory_id,
            'currentDirectory' => $this->current_directory,
            'selectedRootDirectoryId' => $this->selected_root_directory_id,
            'directories' => $directories,
            'message_stack_output' => $message_stack_output,
            'show_data_management' => true,
            'show_export_page' => $this->current_directory->directory_type == EP\Directory::TYPE_EXPORT,
            'show_import_page' => $this->current_directory->directory_type == EP\Directory::TYPE_IMPORT,
            'importProviders' => $import_providers,
            'selected_type' => Yii::$app->request->get('file_type', ''),
            'export_options' => $export_options,
            'easypopulate_command_action' => tep_href_link(FILENAME_EASYPOPULATE . '/command'),
            'upload_form_action_ajax' => Yii::$app->url_manager->create_url(['easypopulate/upload-file-ajax', 'directory_id' => $this->current_directory->directory_id]),
            'job_list_url' => Yii::$app->url_manager->create_url(['easypopulate/files-list']),
            'get_job_messages_popup_action' => Yii::$app->url_manager->create_url(['easypopulate/job-log-messages', 'directory_id' => $this->current_directory->directory_id]),
            'upload_max_part_size' => 900 * 1024,
            'download_format_down_data' => $download_format_down_data,
            'download_form_action' => Yii::$app->url_manager->create_url(['easypopulate/process-export', 'directory_id' => $this->current_directory->directory_id]),
            'get_fields_action' => tep_href_link(FILENAME_EASYPOPULATE . '/get-fields'),
            'refresh_filter_action' => tep_href_link(FILENAME_EASYPOPULATE . '/refresh-filters'),
            //'select_filter_categories' => tep_draw_pull_down_menu('filter[category_id]', \common\helpers\Categories::get_category_tree(0,'','','',false,true), 0, ''),
            'select_filter_categories_auto_complete_url' => \Yii::$app->url_manager->create_url(['easypopulate/get-categories-list']),
            'select_filter_products_auto_complete_url' => \Yii::$app->url_manager->create_url(['easypopulate/get-products-list']),
            'select_filter_properties' => tep_draw_pull_down_menu('filter[properties_id]', \common\helpers\Properties::get_properties_tree(0, '', '', false), 0, ''),
            'filter_defaults' => $filter_defaults,
            'dataSourcesHref' => Yii::$app->url_manager->create_url(['easypopulate/', 'datasources' => '']),
            'select_filter_platform_variants' => array_map(function ($item) {
                return ['id' => $item['id'], 'value' => $item['text']];
            }, array_merge([['id' => '0', 'text' => TEXT_ALL]], \common\classes\platform::get_list(true, true))),
            'select_filter_platform_variants' => array_map(function ($item) {
                return ['id' => $item['id'], 'value' => $item['text']];
            }, array_merge([['id' => '0', 'text' => TEXT_ALL]], platform::get_list(true, true))),
            'js_messages' => json_encode(['file_changed' => TEXT_FILE_CHANGED, 'file_upload' => TEXT_FILE_UPLOAD, 'file_uploaded' => TEXT_FILE_UPLOADED]),
            'datasource_run_form_action' => Yii::$app->url_manager->create_url(['easypopulate/run-data-source', 'directory_id' => $this->current_directory->directory_id]),
        ];
        return $this->render('index', $view_data);
    }
    public function action_create_directory()
    {
        $this->layout = false;
        if (Yii::$app->request->is_post) {
        } else {
            $directory_type_variants = ['import' => 'Import', 'export' => 'Export'];
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => 'Job messages', 'message' => $this->render('create-directory', ['directoryTypeVariants' => $directory_type_variants]), 'buttons' => ['cancel' => ['label' => TEXT_OK, 'className' => 'btn-primary']]]];
        }
    }
    public function action_create_data_source()
    {
        $this->layout = false;
        if (Yii::$app->request->is_post) {
            $new_datasource = Yii::$app->request->post('new_datasource');
            EP\Data_Sources::add($new_datasource);
        } else {
            $available_sources = [];
            foreach (EP\Data_Sources::get_available_list() as $ds) {
                $available_sources[$ds['class']] = $ds['name'];
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => 'Create data source', 'message' => $this->render('create-data-source', ['availableSourcesVariants' => $available_sources]), 'buttons' => ['confirm' => ['label' => TEXT_OK, 'className' => 'btn-primary']]]];
        }
    }
    public function action_configure_auto_datasource_directory()
    {
        $this->layout = false;
        $id = Yii::$app->request->get('by_id', 0);
        $id = Yii::$app->request->post('by_id', $id);
        $id = (int) $id;
        $directory = EP\Directory::load_by_id($id);
        if (!$directory) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => TEXT_DIRECTORY_CONFIGURE, 'message' => 'Directory not found']];
            return;
        }
        try {
            $data_source_obj = EP\Data_Sources::get_by_name($directory->directory);
        } catch (\Exception $ex) {
            \Yii::warning('Datasource not found ' . $directory->directory);
        }
        if (!$data_source_obj) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => TEXT_DIRECTORY_CONFIGURE, 'message' => 'Datasource not found']];
            return;
        }
        $data_source_class = substr(get_class($data_source_obj), strrpos(get_class($data_source_obj), '\\') + 1);
        $providers = new EP\Providers();
        $providers_list = $providers->pull_down_variants('Datasource', [], $data_source_class);
        $format_readers = ['selection' => '', 'items' => []];
        foreach (EP\Data_Sources::get_available_list() as $data_source) {
            if ($data_source_class != $data_source['class']) {
                continue;
            }
            $format_readers['items'][$data_source['class']] = $data_source['name'];
        }
        $launch_frequency = ['selection' => '-1', 'items' => [-1 => TEXT_DISABLED, 1 => TEXT_IMMEDIATELY, 0 => TEXT_DEFINED_TIME, 5 => TEXT_EVERY_5_MINUTES, 15 => TEXT_EVERY_15_MINUTES, 30 => TEXT_EVERY_30_MINUTES, 60 => TEXT_EVERY_HOUR, 120 => sprintf(TEXT_NN_HOURS, 2), 180 => sprintf(TEXT_NN_HOURS, 3), 240 => sprintf(TEXT_NN_HOURS, 4), 300 => sprintf(TEXT_NN_HOURS, 5), 360 => sprintf(TEXT_NN_HOURS, 6), 720 => sprintf(TEXT_NN_HOURS, 12), 1440 => TEXT_EVERY_DAY]];
        if (Yii::$app->request->is_post) {
            $directory_config_input = tep_db_prepare_input(Yii::$app->request->post('directory_config', []));
            $directory_config = [];
            foreach ($directory_config_input as $directory_file_config) {
                if (empty($directory_file_config['filename_pattern'])) {
                    $directory_file_config['filename_pattern'] = str_replace('\\', '_', $directory_file_config['job_provider']) . '_' . rand(1000, 9999);
                }
                $directory_file_config['run_time'] = date('H:i', strtotime('2000-01-01 ' . ($directory_file_config['run_time'] ?? null)));
                $directory_config[] = $directory_file_config;
            }
            $directory->directory_config = $directory_config;
            tep_db_query('UPDATE ' . TABLE_EP_DIRECTORIES . ' ' . "SET directory_config='" . tep_db_input(json_encode($directory_config)) . "' " . "WHERE directory_id='" . (int) $id . "'");
            $directory->apply_directory_config();
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['status' => 'ok'];
        } else {
            $directory_configs = [];
            foreach ($directory->directory_config as $directory_config) {
                $directory_config['run_time'] = date('g:i A', strtotime('2000-01-01 ' . $directory_config['run_time']));
                $directory_configs[] = $directory_config;
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => TEXT_DIRECTORY_CONFIGURE, 'message' => $this->render('configure-auto-datasource-directory', ['directoryConfigs' => $directory_configs, 'providersList' => $providers_list, 'formatReaders' => $format_readers, 'launchFrequency' => $launch_frequency, 'runTimeDefault' => date('g:i A', strtotime('+2 minutes'))])]];
        }
    }
    public function action_configure_datasource_settings()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $id = Yii::$app->request->get('by_id', 0);
        $id = Yii::$app->request->post('by_id', $id);
        $id = (int) $id;
        $directory = EP\Directory::load_by_id($id);
        if (is_object($directory)) {
            $ds = EP\Data_Sources::get_by_name($directory->directory);
            if (is_object($ds)) {
                if (Yii::$app->request->is_post) {
                    $datasource = Yii::$app->request->post('datasource', []);
                    try {
                        $ds->update(isset($datasource[$ds->code]) ? $datasource[$ds->code] : []);
                        Yii::$app->response->data = ['result' => 'ok'];
                    } catch (\InvalidArgumentException $ex) {
                        Yii::$app->response->data = ['result' => 'error', 'message' => $ex->get_message()];
                    }
                    return;
                }
                Yii::$app->response->data = ['dialog' => ['title' => 'Datasource "' . $directory->directory . '" configure', 'message' => '<form id="frmDatasourceConfig"><input type="hidden" name="by_id" value="' . $id . '">' . call_user_func_array([$this, 'render'], $ds->configure_view()) . '</form>']];
                return;
            }
        }
        Yii::$app->response->data = ['dialog' => ['error' => 'true', 'message' => 'Datasource not found']];
    }
    public function action_configure_auto_import_directory()
    {
        $this->layout = false;
        $id = Yii::$app->request->get('by_id', 0);
        $id = Yii::$app->request->post('by_id', $id);
        $id = (int) $id;
        $directory = EP\Directory::load_by_id($id);
        if (!$directory) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => TEXT_DIRECTORY_CONFIGURE, 'message' => 'Directory not found']];
        }
        $providers = new EP\Providers();
        $providers_list = $providers->pull_down_variants('Import');
        $format_readers = ['selection' => 'CSV', 'items' => [
            //'' => PULL_DOWN_DEFAULT,
            'CSV' => TEXT_OPTION_EXPORT_CSV,
            'XLSX' => 'XLSX',
            'ZIP' => TEXT_OPTION_EXPORT_ZIP,
            'XML_orders_new' => TEXT_OPTION_EXPORT_ORDERS_NEW_XML,
        ]];
        $launch_frequency = ['selection' => '-1', 'items' => [-1 => TEXT_DISABLED, 1 => TEXT_IMMEDIATELY, 0 => TEXT_DEFINED_TIME, 5 => TEXT_EVERY_5_MINUTES, 15 => TEXT_EVERY_15_MINUTES, 30 => TEXT_EVERY_30_MINUTES, 60 => TEXT_EVERY_HOUR, 120 => sprintf(TEXT_NN_HOURS, 2), 180 => sprintf(TEXT_NN_HOURS, 3), 240 => sprintf(TEXT_NN_HOURS, 4), 300 => sprintf(TEXT_NN_HOURS, 5), 360 => sprintf(TEXT_NN_HOURS, 6), 720 => sprintf(TEXT_NN_HOURS, 12), 1440 => TEXT_EVERY_DAY]];
        if (Yii::$app->request->is_post) {
            $directory_config_input = tep_db_prepare_input(Yii::$app->request->post('directory_config', []));
            $directory_config = [];
            foreach ($directory_config_input as $directory_file_config) {
                if (empty($directory_file_config['filename_pattern'])) {
                    continue;
                }
                $directory_file_config['run_time'] = date('H:i', strtotime('2000-01-01 ' . $directory_file_config['run_time']));
                $directory_config[] = $directory_file_config;
            }
            $directory->directory_config = $directory_config;
            tep_db_query('UPDATE ' . TABLE_EP_DIRECTORIES . ' ' . "SET directory_config='" . tep_db_input(json_encode($directory_config)) . "' " . "WHERE directory_id='" . (int) $id . "'");
            $directory->apply_directory_config();
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['status' => 'ok'];
        } else {
            $directory_configs = [];
            foreach ($directory->directory_config as $directory_config) {
                $directory_config['run_time'] = date('g:i A', strtotime('2000-01-01 ' . $directory_config['run_time']));
                $directory_configs[] = $directory_config;
            }
            $directory_files_suggest = [];
            $get_files_r = tep_db_query('SELECT DISTINCT file_name ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $directory->directory_id . "'");
            if (tep_db_num_rows($get_files_r) > 0) {
                while ($get_file = tep_db_fetch_array($get_files_r)) {
                    $directory_files_suggest[$get_file['file_name']] = $get_file['file_name'];
                    $masked = preg_replace('/\d+/', '*', $get_file['file_name']);
                    $directory_files_suggest[$masked] = $masked;
                    $masked2 = preg_replace('/\*.*\*/', '*', $masked);
                    $directory_files_suggest[$masked2] = $masked2;
                }
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => TEXT_DIRECTORY_CONFIGURE, 'message' => $this->render('configure-auto-import-directory', ['directoryFilesSuggestSource' => implode(':', $directory_files_suggest), 'directoryConfigs' => $directory_configs, 'providersList' => $providers_list, 'formatReaders' => $format_readers, 'launchFrequency' => $launch_frequency, 'runTimeDefault' => date('g:i A', strtotime('+2 minutes'))])]];
        }
    }
    public function action_configure_auto_processed_directory()
    {
        $this->layout = false;
        $id = Yii::$app->request->get('by_id', 0);
        $id = Yii::$app->request->post('by_id', $id);
        $id = (int) $id;
        $directory = EP\Directory::load_by_id($id);
        if (!$directory) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => TEXT_DIRECTORY_CONFIGURE, 'message' => 'Directory not found']];
        }
        $cleaning_term = ['selection' => '-1', 'items' => [-1 => TEXT_DISABLE_REMOVAL, '1 day' => TEXT_KEEP_1_DAY, '1 week' => TEXT_KEEP_1_WEEK, '2 week' => TEXT_KEEP_2_WEEKS, '1 month' => TEXT_KEEP_1_MONTH]];
        if (Yii::$app->request->is_post) {
            $directory->directory_config = tep_db_prepare_input(Yii::$app->request->post('directory_config', []));
            tep_db_query('UPDATE ' . TABLE_EP_DIRECTORIES . ' ' . "SET directory_config='" . tep_db_input(json_encode($directory->directory_config)) . "' " . "WHERE directory_id='" . (int) $id . "'");
            $directory->apply_directory_config();
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['status' => 'ok'];
        } else {
            $directory_configs = $directory->directory_config;
            if (!isset($directory_configs['cleaning_term'])) {
                $directory_configs['cleaning_term'] = '-1';
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->response->data = ['dialog' => ['title' => 'Configure directory', 'message' => $this->render('configure-auto-processed-directory', ['directoryConfigs' => $directory_configs, 'cleaningTerm' => $cleaning_term])]];
        }
    }
    public function action_refresh_filters()
    {
        $this->layout = false;
        $data = [
            //'select_filter_categories' => tep_draw_pull_down_menu('filter[category_id]', \common\helpers\Categories::get_category_tree(0,'','','',false,true), 0, ''),
            'select_filter_properties' => tep_draw_pull_down_menu('filter[properties_id]', \common\helpers\Properties::get_properties_tree(0, '', '', false), 0, ''),
        ];
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $data;
    }
    public function action_get_categories_list()
    {
        $this->layout = false;
        $all_data = \common\helpers\Categories::get_category_tree(0, '', '', '', false, true);
        $data = array_filter($all_data, function ($option) {
            $search_term = \Yii::$app->request->get('term', '');
            $search_term = tep_db_prepare_input($search_term);
            $option_value = html_entity_decode($option['text'], ENT_HTML5, 'UTF-8');
            return preg_match('/' . preg_quote($search_term, '/') . '/is', $option_value) || $option_value == $search_term;
        });
        $data = array_map(function ($option) {
            $option['value'] = html_entity_decode($option['text'], ENT_HTML5, 'UTF-8');
            $option['text'] = $option['value'];
            return $option;
        }, $data);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $data;
    }
    public function action_get_products_list()
    {
        $this->layout = false;
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        \Yii::$app->response->data = [];
        $languages_id = \Yii::$app->settings->get('languages_id');
        $search = Yii::$app->request->get('term', null);
        $exclude_pids = (array) Yii::$app->request->get('exclude_pids', []);
        $exclude_pids = array_map('intval', $exclude_pids);
        if (!empty($search)) {
            //$catalog = new \backend\components\ProductsCatalog();
            //$catalog->post['suggest'] = 1;
            //return $catalog->search($search);
            $p_q = (new \yii\db\Query())->select('p.products_id, p.products_status, p.products_model ')->add_select(['products_name' => new \yii\db\Expression(\backend\models\Product_Name_Decorator::instance()->listing_query_expression('pd', ''))])->from(['p' => TABLE_PRODUCTS])->left_join(TABLE_PRODUCTS_DESCRIPTION . ' pd', 'p.products_id = pd.products_id and pd.language_id =:lid and pd.platform_id = :pid', [':lid' => (int) $languages_id, ':pid' => intval(\common\classes\platform::default_id())])->and_filter_where(['NOT IN', 'p.products_id', $exclude_pids])->distinct()->order_by('p.sort_order ')->add_order_by(new \yii\db\Expression(\backend\models\Product_Name_Decorator::instance()->listing_query_expression('pd', '')))->limit(100);
            $p_q->and_where(['or', ['like', 'p.products_model', tep_db_input($search)], ['like', 'pd.products_name', tep_db_input($search)], ['like', 'pd.products_internal_name', tep_db_input($search)]]);
            $products_all = $p_q->all();
            if (is_array($products_all) && !empty($products_all)) {
                foreach ($products_all as $products) {
                    Yii::$app->response->data[] = ['id' => $products['products_id'], 'text' => $products['products_name'], 'model' => $products['products_model'], 'status' => $products['products_status']];
                }
            }
        }
    }
    public function action_export_columns()
    {
        $this->layout = false;
        $job_id = Yii::$app->request->get('by_id', 0);
        $job_id = Yii::$app->request->post('by_id', $job_id);
        if (Yii::$app->request->is_post) {
            $selected_columns = tep_db_prepare_input(Yii::$app->request->post('selected_fields', ''));
            if (!empty($selected_columns)) {
                $selected_columns = explode(',', $selected_columns);
            } else {
                $selected_columns = false;
            }
            if ($job_id) {
                $job = EP\Job::load_by_id($job_id);
                if ($job) {
                    $job->job_configure['export']['columns'] = $selected_columns;
                    tep_db_query('UPDATE ' . TABLE_EP_JOB . " SET job_configure='" . tep_db_input(json_encode($job->job_configure)) . "' WHERE job_id='" . (int) $job->job_id . "' ");
                }
            }
        }
        die;
    }
    public function action_get_fields()
    {
        $this->layout = false;
        $selected = false;
        $export_provider = tep_db_prepare_input(Yii::$app->request->post('export_provider', ''));
        $job_id = Yii::$app->request->post('by_id', 0);
        if ($job_id) {
            $job = EP\Job::load_by_id($job_id);
            if ($job) {
                $export_provider = $job->job_provider;
                if (isset($job->job_configure['export']['columns']) && is_array($job->job_configure['export']['columns'])) {
                    $selected = array_flip($job->job_configure['export']['columns']);
                }
            }
        }
        $providers = new EP\Providers();
        $columns = [];
        $default_config = $providers->get_provider_config($export_provider);
        $export_provider = $providers->get_provider_instance($export_provider, $default_config);
        if (is_object($export_provider) && $export_provider instanceof EP\Provider\Export_Interface) {
            $columns = $export_provider->get_columns(['adm_export_hidden']);
        }
        if (!is_array($selected)) {
            $selected = [];
            $get_selected_fields_r = tep_db_query('SELECT shop_field ' . 'FROM ' . TABLE_EP_PROFILES . ' ' . "WHERE ep_direction='export' AND ep_type='" . tep_db_input($export_provider) . "' ");
            if (tep_db_num_rows($get_selected_fields_r) > 0) {
                while ($_selected_field = tep_db_fetch_array($get_selected_fields_r)) {
                    if (!isset($columns[$_selected_field['shop_field']])) {
                        continue;
                    }
                    $selected[$_selected_field['shop_field']] = $_selected_field['shop_field'];
                }
            }
            if (count($selected) == 0) {
                $selected = false;
            }
        }
        $out_columns = [];
        foreach ($columns as $key => $column_title) {
            $out_columns[] = ['db_key' => $key, 'selected' => is_array($selected) ? isset($selected[$key]) : true, 'title' => $column_title];
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $out_columns;
    }
    public function action_process_export()
    {
        $this->layout = false;
        $export_filename = tep_db_prepare_input(Yii::$app->request->post('export_filename'));
        $export_provider = tep_db_prepare_input(Yii::$app->request->post('export_provider'));
        $format = tep_db_prepare_input(Yii::$app->request->post('format'));
        $feed_format = 'CSV';
        $container_format = 'CSV';
        if (strpos($format, '-') !== false) {
            list($feed_format, $container_format) = explode('-', $format, 2);
        } elseif ($format == 'ZIP') {
            $container_format = 'ZIP';
        } elseif (in_array($format, ['CSV', 'XML', 'XLSX', 'XML_orders_new'])) {
            $container_format = $format;
        }
        $selected_columns = tep_db_prepare_input(Yii::$app->request->post('selected_fields', ''));
        if (!empty($selected_columns)) {
            $selected_columns = explode(',', $selected_columns);
        } else {
            $selected_columns = false;
        }
        $filter = tep_db_prepare_input(Yii::$app->request->post('filter'));
        if (!is_array($filter)) {
            $filter = [];
        }
        $file_name_mark = '';
        if (!empty($filter['category_id'])) {
            $_filtered_categories = [(int) $filter['category_id']];
            \common\helpers\Categories::get_parent_categories($_filtered_categories, $_filtered_categories[0], false);
            $_filtered_categories = array_reverse($_filtered_categories);
            $file_name_mark = preg_replace('/[^\w\d]+/u', '_', implode('_', array_map(['\common\helpers\Categories', 'get_categories_name'], $_filtered_categories))) . '_';
        }
        if (!empty($filter['order']['date_from'])) {
            $value_time = date_create_from_format(DATE_FORMAT_DATEPICKER_PHP, \common\helpers\Date::check_input_date($filter['order']['date_from']));
            $filter['order']['date_from'] = '';
            if ($value_time) {
                $filter['order']['date_from'] = $value_time->format('Y-m-d');
            }
        }
        if (!empty($filter['order']['date_to'])) {
            $value_time = date_create_from_format(DATE_FORMAT_DATEPICKER_PHP, \common\helpers\Date::check_input_date($filter['order']['date_to']));
            $filter['order']['date_to'] = '';
            if ($value_time) {
                $filter['order']['date_to'] = $value_time->format('Y-m-d');
            }
        }
        $providers = new EP\Providers();
        $default_config = $providers->get_provider_config($export_provider);
        if ($this->current_directory->cron_enabled && Yii::$app->request->post('new_job', 0)) {
            $error = false;
            $export_filename = ltrim(File_Helper::normalize_path('/' . $export_filename), '/');
            if (empty($export_filename)) {
                $error = ERROR_EMPTY_FILENAME;
            } else if ($this->current_directory->find_job_by_filename($export_filename)) {
                $error = ERROR_FILENAME_NOT_UNIQUE;
            }
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            if ($error) {
                Yii::$app->response->data = ['status' => 'error', 'dialog' => ['title' => ICON_ERROR, 'message' => '<p>' . $error . '</p>']];
                return;
            }
            $new_job_data = ['directory_id' => $this->current_directory->directory_id, 'file_name' => $export_filename, 'direction' => $this->current_directory->directory_type, 'job_provider' => $export_provider, 'job_state' => 'configured', 'job_configure' => ['export' => ['columns' => $selected_columns, 'filter' => $filter, 'format' => $container_format]]];
            $export_providers = $providers->get_available_providers('Export');
            foreach ($export_providers as $export_provider) {
                if ($export_provider['key'] != $export_provider) {
                    continue;
                }
                $new_job_data['job_configure'] = array_merge($default_config, $new_job_data['job_configure']);
                if (isset($export_provider['export']) && isset($export_provider['export']['write_config'][$format])) {
                    if (!isset($new_job_data['job_configure']['export'])) {
                        $new_job_data['job_configure']['export'] = [];
                    }
                    $new_job_data['job_configure']['export']['write_config'] = $export_provider['export']['write_config'][$format];
                }
            }
            $new_job_data['job_configure'] = \json_encode($new_job_data['job_configure']);
            tep_db_perform(TABLE_EP_JOB, $new_job_data);
            Yii::$app->response->data = ['status' => 'ok'];
            return;
        }
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_clean();
        }
        $job_id = Yii::$app->request->post('by_id', 0);
        if ($job_id) {
            $messages = new \backend\models\EP\Messages(['job_id' => $job_id, 'output' => 'none']);
            $job = EP\Job::load_by_id($job_id);
            if (true) {
                if ($container_format == 'ZIP') {
                    $mime_type = 'application/zip';
                    $extension = 'zip';
                } elseif (strpos($container_format, 'XML') !== false) {
                    /*ZRADA - Adding headers for your format /**/
                    $mime_type = 'application/xml';
                    $extension = 'xml';
                } elseif ($format == 'XLSX') {
                    $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                    $extension = 'xlsx';
                } else {
                    $mime_type = 'application/vnd.ms-excel';
                    $extension = 'csv';
                }
                $export_provider = $job->job_provider;
                $filename = (strpos($export_provider, '\\') === false ? $export_provider : substr($export_provider, strpos($export_provider, '\\') + 1)) . '_' . $file_name_mark . strftime('%Y%b%d_%H%M') . '.' . $extension;
                $feed_filename = (strpos($export_provider, '\\') === false ? $export_provider : substr($export_provider, strpos($export_provider, '\\') + 1)) . '_' . $file_name_mark . strftime('%Y%b%d_%H%M') . '.csv';
                Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
                Yii::$app->response->set_download_headers($filename, $mime_type, false);
                Yii::$app->response->content = null;
                Yii::$app->response->send();
            }
            $job->file_name = 'php://output';
            $job->job_configure['export'] = ['columns' => false, 'filter' => [], 'format' => $container_format];
            if ($container_format == 'ZIP' && $job->job_provider != 'product\catalog') {
                $job->job_configure['export']['feed'] = ['feed_filename' => $feed_filename, 'format' => $feed_format];
            }
            $job->run($messages);
            die;
        }
        if (true) {
            if ($container_format == 'ZIP') {
                $mime_type = 'application/zip';
                $extension = 'zip';
            } elseif (strpos($container_format, 'XML') !== false) {
                /*ZRADA - Adding headers for your format /**/
                $mime_type = 'application/xml';
                $extension = 'xml';
            } elseif ($format == 'XLSX') {
                $mime_type = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
                $extension = 'xlsx';
            } else {
                $mime_type = 'application/vnd.ms-excel';
                $extension = 'csv';
            }
            $filename = (strpos($export_provider, '\\') === false ? $export_provider : substr($export_provider, strpos($export_provider, '\\') + 1)) . '_' . $file_name_mark . strftime('%Y%b%d_%H%M') . '.' . $extension;
            $feed_filename = (strpos($export_provider, '\\') === false ? $export_provider : substr($export_provider, strpos($export_provider, '\\') + 1)) . '_' . $file_name_mark . strftime('%Y%b%d_%H%M') . '.csv';
            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
            Yii::$app->response->set_download_headers($filename, $mime_type, false);
            Yii::$app->response->content = null;
            Yii::$app->response->send();
        }
        $messages = new EP\Messages();
        $export_job = new EP\Job_File();
        $export_job->directory_id = $this->current_directory->directory_id;
        $export_job->direction = 'export';
        $export_job->file_name = 'php://output';
        $export_job->job_provider = $export_provider;
        $export_job->job_configure = array_merge($default_config, ['export' => ['columns' => $selected_columns, 'filter' => $filter, 'format' => $container_format]]);
        $providers = new EP\Providers();
        $export_providers = $providers->get_available_providers('Export');
        foreach ($export_providers as $export_provider) {
            if ($export_provider['key'] != $export_provider) {
                continue;
            }
            if (isset($export_provider['export']) && isset($export_provider['export']['write_config'][$format])) {
                $export_job->job_configure['export']['write_config'] = $export_provider['export']['write_config'][$format];
            }
        }
        if ($container_format == 'ZIP' && $export_job->job_provider != 'product\catalog') {
            $export_job->job_configure['export']['feed'] = ['feed_filename' => $feed_filename, 'format' => $feed_format];
        }
        $export_job->run($messages);
        die;
    }
    public function action_upload_file_ajax()
    {
        include DIR_FS_ADMIN . 'plugins/jQuery-File-Upload/php/UploadHandler.php';
        $file_type = Yii::$app->request->post('file_type', '');
        $ep_files_dir = $this->current_directory->files_root(EP\Directory::TYPE_IMPORT);
        $upload_handler = new \Upload_Handler([
            'upload_dir' => $ep_files_dir,
            'accept_file_types' => '/.*/',
            //'/.+\.('.$ep_modules[$_GET['epID']]['epObj']->getAcceptedExtensions().')$/i',
            'param_name' => 'data_file',
            'access_control_allow_methods' => ['POST'],
        ]);
        $response = $upload_handler->get_response();
        if (isset($response['data_file']) && is_array($response['data_file']) && isset($response['data_file'][0])) {
            if (is_object($response['data_file'][0]) && isset($response['data_file'][0]->name)) {
                if (isset($response['data_file'][0]->url)) {
                    // upload finished
                    $job_id = $this->current_directory->touch_import_job($response['data_file'][0]->name, 'uploaded', $file_type);
                    $job = EP\Job::load_by_id($job_id);
                    if (is_object($job)) {
                        $job->try_auto_configure();
                    }
                } else {
                    // upload in progress
                    $this->current_directory->touch_import_job($response['data_file'][0]->name, 'upload', $file_type);
                }
            }
        }
        die;
    }
    public function action_files_list()
    {
        $this->layout = false;
        $records_total = 0;
        $records_filtered = 0;
        $start = (int) Yii::$app->request->get('start', 0);
        $length = (int) Yii::$app->request->get('length', 25);
        $this->current_directory->synchronize_files();
        $providers = new EP\Providers();
        $formatter = new Formatter();
        $list_directory_ids = [$this->current_directory->directory_id];
        $subdirectories = $this->current_directory->get_subdirectories();
        foreach ($subdirectories as $subdir) {
            $list_directory_ids[] = $subdir->directory_id;
        }
        $search_condition = '';
        $search_array = Yii::$app->request->get('search');
        if (is_array($search_array) && isset($search_array['value']) && !empty($search_array['value'])) {
            $search_word = tep_db_prepare_input($search_array['value']);
            $search_condition .= " AND file_name like '%" . tep_db_input(str_replace(' ', '%', $search_word)) . "%' ";
            $get_providers_r = tep_db_query('SELECT DISTINCT job_provider ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->current_directory->directory_id . "' ");
            $providers_in = [];
            if (tep_db_num_rows($get_providers_r) > 0) {
                while ($_provider = tep_db_fetch_array($get_providers_r)) {
                    $provider_name = $providers->get_provider_name($_provider['job_provider']);
                    if (preg_match('/' . str_replace('\s{1,}', '.*', preg_quote($search_word)) . '/i', $provider_name)) {
                        $providers_in[] = tep_db_input($_provider['job_provider']);
                    }
                }
            }
            if (count($providers_in) > 0) {
                $search_condition = " AND ( 1 {$search_condition} OR job_provider IN ('" . implode("','", $providers_in) . "') ) ";
            }
        }
        $dir_files = [];
        $get_db_files_r = tep_db_query('SELECT SQL_CALC_FOUND_ROWS job_id ' . 'FROM ' . TABLE_EP_JOB . ' ' . "WHERE directory_id='" . $this->current_directory->directory_id . "' " . " {$search_condition} " . 'ORDER BY file_time DESC, last_cron_run DESC ' . "LIMIT {$start}, {$length} ");
        $total_record_array = tep_db_fetch_array(tep_db_query('SELECT FOUND_ROWS() AS total_records'));
        $records_total += $total_record_array['total_records'];
        if (tep_db_num_rows($get_db_files_r) > 0) {
            $records_filtered += $total_record_array['total_records'];
            //tep_db_num_rows($get_db_files_r);
            while ($_db_file = tep_db_fetch_array($get_db_files_r)) {
                $dir_files[] = EP\Job::load_by_id($_db_file['job_id']);
            }
        }
        $directory_root = $this->current_directory->files_root();
        $files = [];
        // {{
        if ($level_up_directory = $this->current_directory->get_parent()) {
            $files[] = ['<div data-directory_id="' . $level_up_directory->directory_id . '"><span class="parent_cats"><i class="icon-circle"></i><i class="icon-circle"></i><i class="icon-circle"></i></span></div>', '--', '', '', '<div class="job-actions">' . ($this->current_directory->can_configure_datasource() ? '<a class="job-button js-action-link" href="javascript:void(0);" data-action="configure_datasource_settings" data-type="' . $this->current_directory->directory_type . '" data-directory_id="' . (int) $this->current_directory->directory_id . '"><i class="icon-wrench"></i></a>' : '') . ($this->current_directory->can_configure() ? '<a class="job-button js-action-link" href="javascript:void(0);" data-action="configure_dir" data-type="' . $this->current_directory->directory_type . '" data-directory_id="' . (int) $this->current_directory->directory_id . '"><i class="icon-cog"></i></a>' : '') . '</div>'];
        }
        $records_total += count($subdirectories);
        $records_filtered += count($subdirectories);
        foreach ($subdirectories as $subdir) {
            $files[] = ['<div class="cat_name cat_name_attr" data-directory_id="' . $subdir->directory_id . '">' . $subdir->directory . '</div>', '--', '', '', '<div class="job-actions">' . ($subdir->can_remove() ? '<a class="job-button" href="javascript:void(0);" onclick="return ep_directory_remove(' . (int) $subdir->directory_id . ');"><i class="icon-trash"></i></a>' : '') . ($subdir->can_configure_datasource() ? '<a class="job-button js-action-link" href="javascript:void(0);" data-action="configure_datasource_settings" data-type="' . $subdir->directory_type . '" data-directory_id="' . (int) $subdir->directory_id . '"><i class="icon-wrench"></i></a>' : '') . ($subdir->can_configure() ? '<a class="job-button js-action-link" href="javascript:void(0);" data-action="configure_dir" data-type="' . $subdir->directory_type . '" data-directory_id="' . (int) $subdir->directory_id . '"><i class="icon-cog"></i></a>' : '') . '</div>'];
        }
        // }}
        if ($this->current_directory->cron_enabled && in_array($this->current_directory->directory_type, ['import', 'processed', 'datasource'])) {
            foreach (array_keys($files) as $__idx) {
                $files[$__idx][5] = $files[$__idx][4];
                $files[$__idx][4] = '';
            }
        }
        foreach ($dir_files as $job) {
            /**
             * @var EP/Job $job
             */
            if ($job instanceof EP\Job_File) {
                $file_info = $job->get_file_info();
                $show_filename = $job->file_name;
                if (!empty($file_info['pathFilename'])) {
                    $show_filename = str_replace($directory_root, '', $file_info['pathFilename']);
                }
                if (!empty($file_info['fileSystemName']) && is_file($file_info['fileSystemName'])) {
                    $file_name_cell = '<div style="white-space: nowrap"><a href="' . Yii::$app->url_manager->create_url(['easypopulate/download', 'id' => $job->job_id]) . '" target="_blank"><i class="' . (strpos($job->direction, 'import') === 0 ? 'icon-upload' : 'icon-download fieldRequired') . '"></i></a> ' . $show_filename . '</div>';
                } else {
                    $file_name_cell = '<div style="white-space: nowrap"><i class="icon-download fieldRequired"></i> ' . $show_filename . '</div>';
                }
            } else {
                $file_name_cell = '<div style="white-space: nowrap"> ' . $job->file_name . '</div>';
                $file_info = false;
            }
            $show_job_provider = $providers->get_provider_name($job->job_provider);
            if ($this->current_directory->directory_type == 'import') {
                $show_job_provider = '<a href="javascript:void(0)" class="js-change-job-type" onclick="uploader(\'need_choose_file_type\', {\'id\':\'' . $job->job_id . '\'});">' . $show_job_provider . '</a>';
            }
            $file_row = [$file_name_cell, $show_job_provider, is_array($file_info) && $file_info['fileSize'] ? $formatter->as_short_size($file_info['fileSize'], 3) : '--', ($file_info['fileTime'] ?? null) > 0 ? \common\helpers\Date::datetime_short(date('Y-m-d H:i:s', $file_info['fileTime'])) : ($job->last_cron_run > 2000 ? \common\helpers\Date::datetime_short($job->last_cron_run) : '--'), '<div class="job-actions">' . ($job->can_remove() ? '<a class="job-button" href="javascript:void(0);" onclick="return ep_file_remove(' . (int) $job->job_id . ');"><i class="icon-trash"></i></a>' : '') . ($job->can_configure_export() ? '<a class="job-button" href="javascript:void(0);" onclick="return ep_command(\'configure_export_columns\', ' . (int) $job->job_id . ');"><i class="icon-reorder"></i></a>' : '') . ($job->can_configure_import() ? '<a class="job-button" href="javascript:void(0);" onclick="return ep_command(\'configure\', ' . (int) $job->job_id . ');"><i class="icon-reorder"></i></a>' : '') . ($job->can_setup_run_frequency() ? '<a class="job-button" href="javascript:void(0);" onclick="return ep_command(\'run_frequency\', ' . (int) $job->job_id . ');"><i class="icon-time" style="color:' . ($job->run_frequency == -1 ? 'red' : 'green') . '"></i></a>' : '') . ($job->can_run() ? '<a class="job-button" href="javascript:void(0);" onclick="return ep_command(\'' . $job->direction . '\', ' . (int) $job->job_id . ');"><i class="icon-play"></i></a>' : '') . ($job->have_messages() ? '<a class="job-button" href="javascript:void(0);" onclick="return showJobMessages(' . (int) $job->job_id . ');"><i class="icon-file-text"></i></a>' : '') . '</div>'];
            if ($this->current_directory->cron_enabled && in_array($this->current_directory->directory_type, ['import', 'processed', 'datasource'])) {
                $file_row[5] = $file_row[4];
                $file_row[4] = $job->job_state;
                if ($job->job_state == EP\Job::PROCESS_STATE_IN_PROGRESS) {
                    $file_row[4] .= ' ' . $job->process_progress . '%';
                }
            }
            $files[] = $file_row;
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['data' => $files, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered];
    }
    public function action_download()
    {
        $this->layout = false;
        $job_id = Yii::$app->request->get('id', 0);
        $job = EP\Job::load_by_id($job_id);
        if ($job && $job instanceof EP\Job_File && is_file($job->get_file_system_name())) {
            for ($i = 0; $i < ob_get_level(); $i++) {
                ob_end_clean();
            }
            $filename = basename($job->file_name);
            $mime_type = File_Helper::get_mime_type_by_extension($job->file_name);
            if ($mime_type == 'text/plain') {
                $mime_type = 'application/vnd.ms-excel';
            }
            header('Content-Type: ' . $mime_type);
            header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
            if (preg_match('@MSIE ([0-9].[0-9]{1,2})@', $_SERVER['HTTP_USER_AGENT'], $log_version)) {
                header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
                header('Pragma: public');
            } else {
                header('Pragma: no-cache');
            }
            readfile($job->get_file_system_name());
        }
        die;
    }
    public function action_remove_directory()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $directory_id = intval(Yii::$app->request->post('id', 0));
        $directory = EP\Directory::load_by_id($directory_id);
        if ($directory && $directory->delete()) {
            Yii::$app->response->data = ['status' => 'ok'];
        } else {
            Yii::$app->response->data = ['status' => 'error'];
        }
    }
    public function action_remove_ep_file()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $file_id = intval(Yii::$app->request->post('id', 0));
        $job = EP\Job::load_by_id($file_id);
        if ($job && $job->delete()) {
            Yii::$app->response->data = ['status' => 'ok'];
        } else {
            Yii::$app->response->data = ['status' => 'error'];
        }
    }
    public function action_command()
    {
        $cmd = Yii::$app->request->post('cmd', '');
        if (!empty($cmd) && method_exists($this, $cmd)) {
            return call_user_func([$this, $cmd]);
        }
    }
    public function action_choose_provider()
    {
        $this->layout = false;
        $id = intval(Yii::$app->request->post('id'));
        $update_type = tep_db_prepare_input(Yii::$app->request->post('file_type'));
        $job = EP\Job::load_by_id((int) $id);
        if ($job) {
            $job->try_auto_configure($update_type);
        }
        return '';
    }
    public function action_import_configure()
    {
        $providers = new EP\Providers();
        // load ep_job
        $job_record = false;
        $open_full_match = false;
        $job_by_filename = Yii::$app->request->post('by_file_name', '');
        if (!empty($job_by_filename)) {
            $job_record = $this->current_directory->find_job_by_filename($job_by_filename);
        } else {
            $job_by_id = Yii::$app->request->post('by_id', '');
            if (!empty($job_by_id)) {
                $job_record = EP\Job::load_by_id($job_by_id);
                $open_full_match = true;
            }
        }
        if (!is_object($job_record) || !$job_record->can_configure_import()) {
            die;
        }
        $process_filename = Yii::$app->request->post('process_filename', '');
        $process_filename_navigate = Yii::$app->request->post('navigate', '');
        $command_params = ['id' => $job_record->job_id, 'filename' => $job_record->file_name, 'process_filename' => $job_record->file_name, 'navigation' => false];
        if (!empty($job_record->job_provider) && $job_record->job_provider != 'auto') {
            $check_valid = $providers->get_provider_instance($job_record->job_provider);
            if (!is_object($check_valid)) {
                $job_record->job_provider = '';
            }
        }
        $multi_sheets = [];
        $is_multi_sheets = false;
        if (defined('EP_MULTI_SHEETS') && !empty(EP_MULTI_SHEETS)) {
            $multi_sheets = array_map('strtolower', explode(',', EP_MULTI_SHEETS));
        }
        $file_system_name = $job_record->get_file_system_name();
        if (is_array($job_record->job_configure) && isset($job_record->job_configure['import']) && !empty($job_record->job_configure['import']['format'])) {
            $reader_class = $job_record->job_configure['import']['format'];
            $reader_config = array_merge(['class' => 'backend\models\EP\Reader\\' . $reader_class, 'filename' => $file_system_name], isset($this->job_configure['import']) && is_array($this->job_configure['import']) ? $this->job_configure['import'] : []);
            if (!empty($reader_config['class']) && class_exists($reader_config['class'])) {
                $reader = Yii::create_object($reader_config);
            }
        } elseif ($job_record->direction == 'datasource') {
            //datasource import options
            //isset($this->job_configure['import']) && is_array($this->job_configure['import'])?$this->job_configure['import']:[];
        } elseif (preg_match('/\.zip/i', $file_system_name)) {
            $reader = new EP\Reader\ZIP(['filename' => $file_system_name]);
        } elseif (preg_match('/\.xml/i', $file_system_name)) {
            $reader = new EP\Reader\XML_orders_new(['filename' => $file_system_name]);
        } elseif (preg_match('/\.xlsx/i', $file_system_name)) {
            $reader = new EP\Reader\XLSX(['filename' => $file_system_name]);
            if (in_array('xlsx', $multi_sheets)) {
                $is_multi_sheets = true;
            }
            $reader->filename = $file_system_name;
            //2check why doesn't work else not from baseobject
        } elseif (preg_match('/\.xls/i', $file_system_name)) {
            $reader = new EP\Reader\XLS(['filename' => $file_system_name]);
            $reader->filename = $file_system_name;
            //2check why doesn't work else
        } else {
            $reader = new EP\Reader\CSV(['filename' => $file_system_name]);
        }
        if ($job_record instanceof EP\Job_Zip_File) {
            $uploaded_file_columns = $job_record->get_archived_file_columns();
        } else if ($is_multi_sheets && $job_record instanceof EP\Job_Sheets_File) {
            $uploaded_file_columns = $job_record->get_archived_file_columns();
        } elseif (is_object($reader)) {
            $uploaded_file_columns = $reader->read_columns();
        } else {
            //import options only w/o columns mapping
            $uploaded_file_columns = [];
        }
        if (empty($job_record->job_provider) || $job_record->job_provider == 'auto') {
            $possible_providers = $job_record->try_auto_configure();
            if (empty($job_record->job_provider) || $job_record->job_provider == 'auto') {
                $command_params['selected_provider'] = '';
                if (count($possible_providers) > 0) {
                    $command_params['selected_provider'] = current(array_keys($possible_providers));
                }
                echo '<script>window.parent.uploader(\'need_choose_file_type\', ' . json_encode($command_params) . ')</script>';
                die;
            }
        }
        if ($job_record instanceof EP\Job_Zip_File || $is_multi_sheets && $job_record instanceof EP\Job_Sheets_File) {
            $file_list = array_keys($uploaded_file_columns);
            $sequence_feed_idx = array_search('process_sequence.csv', $file_list);
            if ($sequence_feed_idx !== false) {
                unset($file_list[$sequence_feed_idx]);
                $file_list = array_values($file_list);
            }
            $current_file_pointer = $process_filename ? array_search($process_filename, $file_list) : 0;
            if ($current_file_pointer === false) {
                $current_file_pointer = 0;
            }
            $inner_filename = $file_list[$current_file_pointer];
            if ($process_filename_navigate == 'next' && isset($file_list[$current_file_pointer + 1])) {
                $inner_filename = $file_list[$current_file_pointer + 1];
            } elseif ($process_filename_navigate == 'prev' && isset($file_list[$current_file_pointer - 1])) {
                $inner_filename = $file_list[$current_file_pointer - 1];
            }
            $_file_columns = $uploaded_file_columns[$inner_filename];
            $job_remap = isset($job_record->job_configure['containerFilesSetting'][$inner_filename]['remap_columns']) && is_array($job_record->job_configure['containerFilesSetting'][$inner_filename]['remap_columns']) ? $job_record->job_configure['containerFilesSetting'][$inner_filename]['remap_columns'] : [];
            $job_configure = isset($job_record->job_configure['containerFilesSetting'][$inner_filename]) && is_array($job_record->job_configure['containerFilesSetting'][$inner_filename]) ? $job_record->job_configure['containerFilesSetting'][$inner_filename] : [];
            $file_columns = $_file_columns['columns'];
            $command_params['filename'] = $job_record->file_name . '\\' . $inner_filename;
            $command_params['process_filename'] = $inner_filename;
            $command_params['file_columns'] = $file_columns;
            $command_params['navigation'] = count($uploaded_file_columns) > 1 ? true : false;
            $possible_providers = $providers->best_match($file_columns);
            reset($possible_providers);
            $command_params['matched_providers'] = array_keys($possible_providers);
            $first_provider = $providers->get_provider_instance($command_params['matched_providers'][0], $job_configure);
            //$command_params['provider_name'] = $firstProvider;
            $command_params['provider_columns'] = array_merge([''], $first_provider->get_columns());
            if (count($job_remap) == 0) {
                $p_map = array_flip($first_provider->get_columns());
                foreach ($file_columns as $file_column) {
                    $job_remap[$file_column] = isset($p_map[$file_column]) ? $p_map[$file_column] : '';
                }
            }
            if (method_exists($first_provider, 'importNewColumns')) {
                $command_params['provider_columns'] = array_merge($command_params['provider_columns'], $first_provider->import_new_columns($file_columns, $job_remap));
            }
            $command_params['remap_columns'] = $job_remap;
            if (method_exists($first_provider, 'importOptions')) {
                $command_params['import_options'] = $first_provider->import_options();
            }
            echo '<script>window.parent.uploader(/*3*/\'need_choose_import_map\',' . json_encode($command_params) . ')</script>';
        } else {
            if ($job_record->job_provider == 'orders\orders' || isset($job_record->job_configure['import']['format']) && stripos($job_record->job_configure['import']['format'], 'XML') !== false) {
                die;
            }
            $job_remap = isset($job_record->job_configure['remap_columns']) && is_array($job_record->job_configure['remap_columns']) ? $job_record->job_configure['remap_columns'] : [];
            $file_columns = $uploaded_file_columns;
            $command_params['file_columns'] = $file_columns;
            $possible_providers = $providers->best_match($file_columns);
            if (empty($job_record->job_provider) && count($possible_providers) == 0 && count($file_columns) > 0) {
                echo '<script>window.parent.uploader(\'wrong_file_type\')</script>';
                die;
            }
            if (!empty($job_record->job_provider)) {
                $command_params['matched_providers'][0] = $job_record->job_provider;
            } else {
                reset($possible_providers);
                $command_params['matched_providers'] = array_keys($possible_providers);
            }
            $first_provider = $providers->get_provider_instance($command_params['matched_providers'][0], $job_record->job_configure);
            if (method_exists($first_provider, 'getColumns')) {
                $command_params['provider_columns'] = array_merge([''], $first_provider->get_columns());
            }
            if (count($job_remap) == 0 && method_exists($first_provider, 'getColumns')) {
                $p_map = array_flip($first_provider->get_columns());
                foreach ($file_columns as $file_column) {
                    $job_remap[$file_column] = isset($p_map[$file_column]) ? $p_map[$file_column] : '';
                }
            }
            if (method_exists($first_provider, 'importNewColumns')) {
                $command_params['provider_columns'] = array_merge($command_params['provider_columns'], $first_provider->import_new_columns($file_columns, $job_remap));
            }
            $command_params['remap_columns'] = $job_remap;
            if (method_exists($first_provider, 'importOptions')) {
                $command_params['import_options'] = $first_provider->import_options();
            }
            echo '<script>window.parent.uploader(/*3*/\'need_choose_import_map\',' . json_encode($command_params) . ')</script>';
        }
        die;
        if (empty($job_record->job_provider) || $job_record->job_provider == 'auto') {
            // guess
            /**
             * @var $reader EP\Reader\ReaderInterface
             */
            if (count($file_columns) == 0) {
                echo '<script>window.parent.uploader(\'wrong_file_type\')</script>';
                die;
            }
            $possible_providers = $providers->best_match($file_columns);
            reset($possible_providers);
            if (count($possible_providers) == 0) {
                echo '<script>window.parent.uploader(\'need_choose_file_type\', ' . json_encode($command_params) . ')</script>';
                die;
            } elseif (current($possible_providers) == 1) {
                $file_provider = current(array_keys($possible_providers));
                $job_record->job_provider = $file_provider;
                tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='configured', job_provider='" . tep_db_input($file_provider) . "' " . "WHERE job_id='" . $job_record->job_id . "' ");
                echo '<script>window.parent.uploader(\'reload_file_list\')</script>';
            } else {
                // not sure, something match, but not 100%
                $command_params['matched_providers'] = array_keys($possible_providers);
                tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_provider='" . tep_db_input($command_params['matched_providers'][0]) . "' " . "WHERE job_id='" . $job_record->job_id . "' ");
                $command_params['file_columns'] = $file_columns;
                $first_provider = $providers->get_provider_instance($command_params['matched_providers'][0]);
                //$command_params['provider_name'] = $firstProvider;
                $command_params['provider_columns'] = array_merge([''], $first_provider->get_columns());
                $command_params['remap_columns'] = [];
                $p_map = array_flip($first_provider->get_columns());
                foreach ($file_columns as $file_column) {
                    $command_params['remap_columns'][$file_column] = isset($p_map[$file_column]) ? $p_map[$file_column] : '';
                }
                echo '<script>window.parent.uploader(/*1*/\'need_choose_import_map\',' . json_encode($command_params) . ')</script>';
                die;
            }
        }
        $job_configure = $job_record->job_configure;
        $provider_obj = $providers->get_provider_instance($job_record->job_provider);
        if ($provider_obj->get_column_match_score($file_columns) != 1 || $open_full_match) {
            $command_params['file_columns'] = $file_columns;
            $command_params['provider_columns'] = array_merge(['' => ''], $provider_obj->get_columns());
            if (isset($job_configure['remap_columns']) && is_array($job_configure['remap_columns'])) {
                $command_params['remap_columns'] = $job_configure['remap_columns'];
            } else {
                $command_params['remap_columns'] = [];
                $p_map = array_flip($provider_obj->get_columns());
                foreach ($file_columns as $file_column) {
                    $command_params['remap_columns'][$file_column] = isset($p_map[$file_column]) ? $p_map[$file_column] : '';
                }
            }
            echo '<script>window.parent.uploader(/*2*/\'need_choose_import_map\',' . json_encode($command_params) . ')</script>';
            die;
        }
        // guess job_provider for auto
        // check columns map - init dialog for map missing
        // suggest start import
        die;
    }
    public function action_confirm_mapping()
    {
        $this->layout = false;
        $result = ['status' => 'ok'];
        $job_id = intval(Yii::$app->request->post('id', 0));
        $process_filename = Yii::$app->request->post('process_filename', '');
        $map = Yii::$app->request->post('map', []);
        $import_config = Yii::$app->request->post('import_config', []);
        $job_record = EP\Job::load_by_id((int) $job_id);
        if (!is_object($job_record)) {
            $result['status'] = 'error';
            $result['message'] = 'Job not found';
        } elseif (empty($job_record->job_provider) || $job_record->job_provider == 'auto') {
            $result['status'] = 'error';
            $result['message'] = 'Need select job type';
        } else {
            $process_provider = $job_record->job_provider;
            if ($job_record instanceof EP\Job_Zip_File || $job_record instanceof EP\Job_Sheets_File) {
                if (isset($job_record->job_configure['containerFilesSetting']) && isset($job_record->job_configure['containerFilesSetting'][$process_filename])) {
                    $process_provider = $job_record->job_configure['containerFilesSetting'][$process_filename]['job_provider'];
                }
            }
            $providers = new EP\Providers();
            $provider = $providers->get_provider_instance($process_provider);
            if ($provider instanceof EP\Provider\Provider_Abstract) {
                $provider_columns = $provider->get_columns();
                $remap_columns = array_flip($provider_columns);
                if (is_array($map) && count($map) > 0) {
                    $__map_columns = [];
                    foreach ($map as $file_column_name => $import_field_name) {
                        if (isset($provider_columns[$import_field_name])) {
                            $__map_columns[$file_column_name] = $import_field_name;
                        }
                    }
                    if (count($__map_columns) > 0) {
                        $remap_columns = $__map_columns;
                    }
                }
                if ($job_record instanceof EP\Job_Zip_File || $job_record instanceof EP\Job_Sheets_File) {
                    if (isset($job_record->job_configure['containerFilesSetting']) && isset($job_record->job_configure['containerFilesSetting'][$process_filename])) {
                        $job_record->job_configure['containerFilesSetting'][$process_filename]['remap_columns'] = $remap_columns;
                        $job_record->job_configure['containerFilesSetting'][$process_filename]['import_config'] = $import_config;
                    }
                } else {
                    $job_record->job_configure['remap_columns'] = $remap_columns;
                    $job_record->job_configure['import_config'] = $import_config;
                }
                tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='configured', job_configure='" . tep_db_input(json_encode($job_record->job_configure)) . "' " . "WHERE job_id='" . $job_record->job_id . "' ");
            } elseif ($job_record->can_configure_import()) {
                //datasource - import options only
                $job_record->job_configure['import_config'] = $import_config;
                tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET job_state='configured', job_configure='" . tep_db_input(json_encode($job_record->job_configure)) . "' " . "WHERE job_id='" . $job_record->job_id . "' ");
            } else {
                $result['status'] = 'error';
                $result['message'] = 'Wrong job type';
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = $result;
    }
    public function action_import()
    {
        $this->layout = false;
        for ($i = 0; $i < ob_get_level(); $i++) {
            ob_end_clean();
        }
        header('X-Accel-Buffering: no');
        $job_id = intval(Yii::$app->request->post('by_id', 0));
        $job_record = EP\Job::load_by_id($job_id);
        if (!is_object($job_record)) {
            $result['status'] = 'error';
            $result['message'] = 'Job not found';
        } else {
            $messages = new EP\Messages();
            $messages->set_ep_file_id($job_record->job_id);
            try {
                $job_record->run($messages);
            } catch (\Exception $ex) {
                Yii::error('EasyPopulate manual import exception: ' . $ex->get_message() . "\n" . $ex->get_trace_as_string());
                $messages->info($ex->get_message());
            }
            $messages->command('reload_file_list');
        }
        die;
    }
    public function action_run_data_source()
    {
        $this->layout = false;
        $job_id = intval(Yii::$app->request->post('by_id', 0));
        $messages = new EP\Messages();
        $messages->command('start_import');
        $messages->command('set_title', 'Job process');
        /**
         * @var $job_record EP\JobDatasource
         */
        $job_record = EP\Job::load_by_id($job_id);
        if (!is_object($job_record) || !is_a($job_record, '\backend\models\EP\JobDatasource') || !$job_record->can_run()) {
            $messages->info('Job can not be runned');
            $messages->progress(100);
            $messages->command('reload_file_list');
            die;
        }
        if ($job_record->can_run_in_browser()) {
            $messages = new EP\Messages();
            $messages->set_ep_file_id($job_record->job_id);
            $now = strtotime('now');
            try {
                $job_record->set_job_start_time($now);
                $messages->progress(0);
                tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . "SET last_cron_run='" . date('Y-m-d H:i:s', $now) . "' " . "WHERE job_id='" . $job_record->job_id . "'");
                $job_record->run($messages);
            } catch (\Exception $ex) {
                $messages->info($ex->get_message());
            }
            $job_record->job_finished();
            $messages->info('Done');
            $messages->command('reload_file_list');
        } else {
            $job_record->run_asap();
            $messages->info('Job start scheduled');
            $messages->progress(100);
            $messages->command('reload_file_list');
        }
        die;
    }
    public function action_job_log_messages()
    {
        $this->layout = false;
        $id = Yii::$app->request->get('id');
        $messages = [];
        $message_string = '';
        $get_job_messages_r = tep_db_query('SELECT message_text ' . 'FROM ' . TABLE_EP_LOG_MESSAGES . ' ' . "WHERE job_id='" . (int) $id . "' " . 'ORDER BY ep_log_message_id ' . '/*LIMIT 3000*/');
        if (tep_db_num_rows($get_job_messages_r) > 0) {
            while ($message = tep_db_fetch_array($get_job_messages_r)) {
                $message_string .= $message['message_text'] . '<br>';
                //$messages[] = $message;
            }
        }
        Yii::$app->response->format = \yii\web\Response::FORMAT_HTML;
        return $this->render('log-messages', ['messages' => $messages, 'message_string' => $message_string]);
        /*Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
          Yii::$app->response->data = [
              'dialog' => [
                  'title'=>'Job messages',
                  'message' => $this->render('log-messages',['messages'=>$messages,'message_string'=>$message_string]),
                  'buttons' => [
                      'cancel' => [
                          'label' => TEXT_OK,
                          'className' => 'btn-primary',
                      ]
                  ]
              ]
          ];*/
    }
    public function action_job_frequency()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $by_id = Yii::$app->request->get('by_id', 0);
        $by_id = Yii::$app->request->post('by_id', $by_id);
        $run_frequency = Yii::$app->request->post('run_frequency', -1);
        $run_time = Yii::$app->request->post('run_time', '00:00');
        $freq_period = Yii::$app->request->post('freq_period', 'job');
        if (Yii::$app->request->is_post) {
            $time_APM = strtotime('2000-01-01 ' . $run_time);
            tep_db_query('UPDATE ' . TABLE_EP_JOB . ' ' . 'SET ' . ((int) $run_frequency == 0 ? "last_cron_run=IF(run_time='" . tep_db_input(date('H:i', $time_APM)) . "',last_cron_run,NULL), " : '') . " run_frequency='" . (int) $run_frequency . "', run_time='" . tep_db_input(date('H:i', $time_APM)) . "' " . "WHERE job_id='" . (int) $by_id . "' ");
            if ($freq_period == 'directory') {
                $job_obj = EP\Job::load_by_id($by_id);
                if ($job_obj) {
                    $directory_obj = $job_obj->get_directory();
                    $directory_obj->update_job_directory_config($by_id, ['run_frequency' => (int) $run_frequency, 'run_time' => date('H:i', $time_APM)]);
                }
            }
            Yii::$app->response->data = ['status' => 'ok'];
            return;
        }
        $freq_period = 'directory';
        $job_obj = EP\Job::load_by_id($by_id);
        if ($job_obj) {
            $run_frequency = $job_obj->run_frequency;
            $run_time = $job_obj->run_time;
            $configs = $job_obj->get_directory()->get_job_config_template($job_obj->job_id);
            if (!empty($configs)) {
                $first_config = reset($configs);
                if ($first_config['run_time'] != $run_time || $first_config['run_frequency'] != $run_frequency) {
                    $freq_period = 'job';
                }
            }
        }
        $run_frequency_variants = [-1 => TEXT_DISABLED, 1 => TEXT_IMMEDIATELY, 0 => TEXT_DEFINED_TIME, 5 => TEXT_EVERY_5_MINUTES, 15 => TEXT_EVERY_15_MINUTES, 30 => TEXT_EVERY_30_MINUTES, 60 => TEXT_EVERY_HOUR, 120 => sprintf(TEXT_NN_HOURS, 2), 180 => sprintf(TEXT_NN_HOURS, 3), 240 => sprintf(TEXT_NN_HOURS, 4), 300 => sprintf(TEXT_NN_HOURS, 5), 360 => sprintf(TEXT_NN_HOURS, 6), 720 => sprintf(TEXT_NN_HOURS, 12), 1440 => TEXT_EVERY_DAY];
        $time_APM = strtotime('2000-01-01 ' . $run_time);
        Yii::$app->response->data = ['dialog' => ['title' => 'Job run frequency', 'message' => $this->render('popup-job-frequency', ['run_frequency' => $run_frequency, 'runFrequencyVariants' => $run_frequency_variants, 'freq_period' => $freq_period, 'run_time' => date('g:i A', strtotime('2000-01-01 ' . $run_time))]), 'buttons' => ['confirm' => ['label' => TEXT_OK, 'className' => 'btn-primary']]]];
    }
    public function action_datasource_action()
    {
        $directory_id = Yii::$app->request->get('id', 0);
        if ($directory = EP\Directory::find_by_id($directory_id)) {
            $datasource = $directory->get_datasource();
            if ($datasource && method_exists($datasource, 'datasourceActions')) {
                return $datasource->datasource_actions($directory, $datasource);
            }
        }
    }
    public function action_empty()
    {
        if (\Yii::$app->request->post('products')) {
            $query = tep_db_query('select * from ' . TABLE_CATEGORIES);
            while ($data = tep_db_fetch_array($query)) {
                @unlink(DIR_FS_CATALOG_IMAGES . $data['categories_image']);
            }
            \common\classes\Images::clean_image_reference();
            $product_images_dir_path = \common\classes\Images::get_fs_catalog_images_path() . 'products' . DIRECTORY_SEPARATOR;
            if (is_dir($product_images_dir_path)) {
                $images_dir_handle = opendir($product_images_dir_path);
                while (($product_image_directory = readdir($images_dir_handle)) !== false) {
                    if (!is_numeric($product_image_directory) || intval($product_image_directory) != $product_image_directory) {
                        continue;
                    }
                    $remove_image_directory = $product_images_dir_path . DIRECTORY_SEPARATOR . $product_image_directory;
                    if (is_file($remove_image_directory)) {
                        continue;
                    }
                    //??
                    try {
                        File_Helper::remove_directory($remove_image_directory);
                    } catch (\Exception $ex) {
                    }
                }
                closedir($images_dir_handle);
            }
            \common\helpers\Product::trunk_products();
            \common\helpers\Categories::trunk_categories();
            tep_db_query('TRUNCATE TABLE ' . TABLE_FILTERS);
            tep_db_query('TRUNCATE TABLE ' . \common\models\Warehouses_Products::table_name());
            $query = tep_db_query('select * from ' . TABLE_MANUFACTURERS);
            while ($data = tep_db_fetch_array($query)) {
                @unlink(DIR_FS_CATALOG_IMAGES . $data['manufacturers_image']);
            }
            tep_db_query('TRUNCATE TABLE ' . TABLE_MANUFACTURERS);
            tep_db_query('TRUNCATE TABLE ' . TABLE_MANUFACTURERS_INFO);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_CATEGORIES);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_CATEGORIES_DESCRIPTION);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_TO_PROPERTIES_CATEGORIES);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_DESCRIPTION);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_TO_PRODUCTS);
            tep_db_query('TRUNCATE TABLE ' . TABLE_PROPERTIES_VALUES);
            if (defined('TABLE_PRODUCTS_IMAGES_EXTERNAL_URL')) {
                tep_db_query('TRUNCATE TABLE ' . TABLE_PRODUCTS_IMAGES_EXTERNAL_URL);
            }
            tep_db_query('TRUNCATE TABLE ' . TABLE_DOCUMENT_TYPES);
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_products');
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_categories');
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_mapping');
            tep_db_query('INSERT IGNORE INTO ' . TABLE_PLATFORMS_CATEGORIES . ' (platform_id, categories_id) ' . 'SELECT platform_id, 0 FROM ' . TABLE_PLATFORMS);
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_products');
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_products_flags');
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_categories');
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_kv_storage');
            tep_db_query('TRUNCATE TABLE ep_holbi_soap_kw_id_storage');
            $schema_check = Yii::$app->get('db')->schema->get_table_schema('gapi_search');
            if ($schema_check) {
                tep_db_query('TRUNCATE TABLE gapi_search');
            }
            $schema_check = Yii::$app->get('db')->schema->get_table_schema('gapi_search_to_products');
            if ($schema_check) {
                tep_db_query('TRUNCATE TABLE gapi_search_to_products');
            }
            $schema_check = Yii::$app->get('db')->schema->get_table_schema('products_groups');
            if ($schema_check) {
                tep_db_query('TRUNCATE TABLE products_groups');
            }
        }
        if (\Yii::$app->request->post('orders') == 1) {
            \common\helpers\Order::trunk_orders();
            \common\models\Orders_Products_Allocate::delete_all();
            foreach (['amazon_payment_orders', 'klarna_order_reference', 'orders_payment', 'orders_transactions', 'orders_transactions_children', 'tracking_numbers', 'tracking_numbers_to_orders_products', 'ep_holbi_soap_link_orders', 'ep_holbi_soap_kv_storage', 'ga'] as $truncate_table) {
                $schema_check = Yii::$app->get_db()->schema->get_table_schema($truncate_table);
                if ($schema_check) {
                    Yii::$app->get_db()->create_command('TRUNCATE TABLE ' . $truncate_table)->execute();
                }
            }
        }
        if (\Yii::$app->request->post('customers') == 1) {
            \common\helpers\Customer::trunk_customers();
            foreach (['products_notify', 'virtual_gift_card_basket', 'wedding_registry', 'wedding_registry_inviting', 'wedding_registry_products', 'ep_holbi_soap_link_customers', 'ep_holbi_soap_kv_storage', 'gdpr_check', 'guest_check', 'personal_catalog'] as $truncate_table) {
                $schema_check = Yii::$app->get_db()->schema->get_table_schema($truncate_table);
                if ($schema_check) {
                    Yii::$app->get_db()->create_command('TRUNCATE TABLE ' . $truncate_table)->execute();
                }
            }
        }
        $message_stack = \Yii::$container->get('message_stack');
        $message_stack->add_session(ICON_SUCCESS, 'header', 'success');
        return $this->redirect(Yii::$app->url_manager->create_url('easypopulate/'));
    }
    private static function nocompress($v, $d = 0)
    {
        return $v;
    }
    private function preset_encode_decode($data, $encode)
    {
        if (function_exists('bzcompress')) {
            $_comp = 'bzcompress';
            $_uncomp = 'bzdecompress';
        } elseif (function_exists('gzcompress')) {
            $_comp = 'gzcompress';
            $_uncomp = 'gzuncompress';
        } else {
            $_comp = $_uncomp = ['self', 'nocompress'];
        }
        if ((int) $encode > 0) {
            return @base64_encode(call_user_func($_comp, json_encode($data), 9));
        } else {
            $return = [];
            try {
                $return = @json_decode(call_user_func($_uncomp, base64_decode($data)), true);
            } catch (\Exception $exc) {
            }
            return is_array($return) ? $return : [];
        }
    }
    public function action_preset_load()
    {
        $this->layout = false;
        if (Yii::$app->request->is_post) {
            $return = ['status' => 'error'];
            $type = trim(Yii::$app->request->post('type', ''));
            if ($type != '') {
                $export_preset_record = \common\models\Data_Storage::find_one(['pointer' => 'easypopulate_export_preset']);
                if ($export_preset_record instanceof \common\models\Data_Storage) {
                    $return = ['status' => 'ok', 'presetArray' => []];
                    $preset_array = $this->preset_encode_decode($export_preset_record->data, false);
                    if (isset($preset_array[$type])) {
                        foreach ($preset_array[$type] as $preset => $preset_list) {
                            $return['presetArray'][$preset] = implode(';', $preset_list);
                        }
                        ksort($return['presetArray'], SORT_STRING);
                    }
                }
            }
            echo json_encode($return);
        }
        die;
    }
    public function action_preset_save()
    {
        $this->layout = false;
        if (Yii::$app->request->is_post) {
            $return = ['status' => 'error'];
            $type = trim(Yii::$app->request->post('type', ''));
            $preset = trim(Yii::$app->request->post('preset', ''));
            $selection = Yii::$app->request->post('selection', '');
            $selection = is_array($selection) ? $selection : [];
            if ($type != '' and $preset != '' and count($selection) > 0) {
                $preset_array = [];
                $export_preset_record = \common\models\Data_Storage::find_one(['pointer' => 'easypopulate_export_preset']);
                if (!$export_preset_record instanceof \common\models\Data_Storage) {
                    $export_preset_record = new \common\models\Data_Storage();
                    $export_preset_record->pointer = 'easypopulate_export_preset';
                } else {
                    $preset_array = $this->preset_encode_decode($export_preset_record->data, false);
                }
                $preset_array[$type][$preset] = $selection;
                $export_preset_record->data = $this->preset_encode_decode($preset_array, true);
                if (strlen($export_preset_record->data) <= 65530) {
                    $export_preset_record->date_modified = date('Y-m-d H:i:s', strtotime('+100 years'));
                    $export_preset_record->detach_behavior('date_modified_now');
                    try {
                        $export_preset_record->save();
                        $return = ['status' => 'ok'];
                    } catch (\Exception $exc) {
                        $return = ['status' => 'error', 'message' => $exc->get_message()];
                    }
                } else {
                    $return = ['status' => 'error', 'message' => TEXT_EASYPOPULATE_EXPORT_MAXLENGTH];
                }
            }
            echo json_encode($return);
        }
        die;
    }
    public function action_preset_delete()
    {
        $this->layout = false;
        if (Yii::$app->request->is_post) {
            $return = ['status' => 'error'];
            $type = trim(Yii::$app->request->post('type', ''));
            $preset = trim(Yii::$app->request->post('preset', ''));
            if ($type != '' and $preset != '') {
                $export_preset_record = \common\models\Data_Storage::find_one(['pointer' => 'easypopulate_export_preset']);
                if ($export_preset_record instanceof \common\models\Data_Storage) {
                    $preset_array = $this->preset_encode_decode($export_preset_record->data, false);
                    if (isset($preset_array[$type][$preset])) {
                        unset($preset_array[$type][$preset]);
                        $export_preset_record->data = $this->preset_encode_decode($preset_array, true);
                        $export_preset_record->date_modified = date('Y-m-d H:i:s', strtotime('+100 years'));
                        $export_preset_record->detach_behavior('date_modified_now');
                        try {
                            $export_preset_record->save();
                            $return = ['status' => 'ok'];
                        } catch (\Exception $exc) {
                            $return = ['status' => 'error', 'message' => $exc->get_message()];
                        }
                    }
                }
            }
            echo json_encode($return);
        }
        die;
    }
    public function action_io_project()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $form_data = [];
        if (Yii::$app->request->is_get) {
            $project_id = intval(Yii::$app->request->get('project_id', 0));
            $form_data = tep_db_fetch_array(tep_db_query("SELECT * FROM io_project WHERE project_id = '{$project_id}'"));
        } elseif (Yii::$app->request->is_post) {
            $project_id = intval(Yii::$app->request->post('project_id', 0));
            $table_data = ['project_code' => Yii::$app->request->post('project_code', ''), 'description' => Yii::$app->request->post('description', '')];
            $is_local = Yii::$app->request->post('is_local');
            if (!is_null($is_local)) {
                $table_data['is_local'] = $is_local ? 1 : 0;
            }
            if ($project_id) {
                tep_db_perform('io_project', $table_data, 'update', "project_id = '{$project_id}'");
            } else {
                tep_db_perform('io_project', $table_data);
                $project_id = tep_db_insert_id();
            }
            $form_data = tep_db_fetch_array(tep_db_query("SELECT * FROM io_project WHERE project_id = '{$project_id}'"));
        }
        Yii::$app->response->data = ['formData' => is_array($form_data) ? $form_data : []];
    }
    public function action_io_projects_list()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $projects = [];
        $get_projects_r = tep_db_query('SELECT * FROM io_project WHERE 1 ORDER BY 1');
        $records_total = tep_db_num_rows($get_projects_r);
        $records_filtered = $records_total;
        if (tep_db_num_rows($get_projects_r) > 0) {
            while ($project = tep_db_fetch_array($get_projects_r)) {
                $projects[] = [$project['project_code'], '<div class="job-actions">' . '<a class="job-button js-project-edit" href="javascript:void(0);" data-project_id="' . (int) $project['project_id'] . '"><i class="icon-edit"></i></a>' . '</div>'];
            }
        }
        Yii::$app->response->data = ['data' => $projects, 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered];
    }
}