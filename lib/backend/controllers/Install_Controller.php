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
/**
 * default controller to handle user requests.
 */
class Install_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_INSTALL'];
    private $deploy_log = [];
    private $do_migrations;
    private $do_system;
    private $do_smarty;
    private $do_theme;
    private $do_hooks;
    private $do_menu;
    private $show_ignore_field = false;
    private $dst_file_ignore = [];
    private $ext_class = null;
    public function __construct($id, $module = null)
    {
        \common\helpers\Translation::init('admin/install');
        parent::__construct($id, $module);
    }
    private function check_system_requires()
    {
        if (!(PHP_VERSION_ID >= 70400)) {
            echo 'Further system upgrade requires a PHP version ">= 7.4.0". You are running ' . PHP_VERSION . '.';
            die;
        }
        ini_set('memory_limit', '512M');
        // for large updates
    }
    private static function is_known_require_module($filename)
    {
        return $filename === 'php_version_74';
    }
    private function basename($param, $suffix = null, $charset = 'utf-8')
    {
        if ($suffix) {
            $tmpstr = ltrim(mb_substr($param, mb_strrpos($param, DIRECTORY_SEPARATOR, 0, $charset), null, $charset), DIRECTORY_SEPARATOR);
            if (mb_strpos($param, $suffix, null, $charset) + mb_strlen($suffix, $charset) == mb_strlen($param, $charset)) {
                return str_ireplace($suffix, '', $tmpstr);
            } else {
                return ltrim(mb_substr($param, mb_strrpos($param, DIRECTORY_SEPARATOR, 0, $charset), null, $charset), DIRECTORY_SEPARATOR);
            }
        } else {
            return ltrim(mb_substr($param, mb_strrpos($param, DIRECTORY_SEPARATOR, 0, $charset), null, $charset), DIRECTORY_SEPARATOR);
        }
    }
    private function del_tree($dir)
    {
        if (is_dir($dir)) {
            $files = array_diff(scandir($dir), ['.', '..']);
            foreach ($files as $file) {
                is_dir("{$dir}/{$file}") ? $this->del_tree("{$dir}/{$file}") : @unlink("{$dir}/{$file}");
            }
        }
        return @rmdir($dir);
    }
    private function build_xml_tree($parent_id, $query_response)
    {
        $tree = [];
        foreach ($query_response as $response) {
            if ($response['parent_id'] == $parent_id) {
                if ($response['box_type'] == 1) {
                    $response['child'] = $this->build_xml_tree($response['box_id'], $query_response);
                }
                unset($response['box_id']);
                unset($response['parent_id']);
                $tree[] = $response;
            }
        }
        return $tree;
    }
    private function reset_re_cache_flags()
    {
        $this->do_migrations = false;
        $this->do_system = false;
        $this->do_smarty = false;
        $this->do_theme = false;
        $this->do_hooks = false;
        $this->do_menu = false;
    }
    private function run_system_re_cache($echo = false)
    {
        set_time_limit(0);
        @ignore_user_abort(true);
        $runtime_path = Yii::get_alias('@runtime');
        $all_runtime_directories = [];
        $all_runtime_directories[] = $runtime_path;
        $runtime_dir_name = str_replace(Yii::get_alias('@backend'), '', Yii::get_alias('@runtime'));
        $other_apps_aliases = ['@frontend', '@console'];
        foreach ($other_apps_aliases as $_apps_alias) {
            $_app_runtime_dir = Yii::get_alias($_apps_alias . $runtime_dir_name, false);
            if (!$_app_runtime_dir || !is_dir($_app_runtime_dir)) {
                continue;
            }
            $all_runtime_directories[] = $_app_runtime_dir;
        }
        if ($this->do_migrations) {
            if ($echo) {
                echo TEXT_APPLY_MIGRATIONS . "<br>\n";
            }
            $old_app = \Yii::$app;
            new \yii\console\Application(['id' => 'Command runner', 'basePath' => '@site_root', 'components' => ['db' => $old_app->db, 'cache' => ['class' => 'yii\caching\FileCache', 'cachePath' => '@frontend/runtime/cache'], 'log' => ['targets' => [['class' => 'yii\log\FileTarget', 'levels' => ['error', 'warning']]]], 'errorHandler' => ['class' => '\common\classes\TlErrorHandlerConsole']]]);
            \Yii::$app->run_action('migrate/up', ['migrationPath' => '@console/migrations/', 'interactive' => false, 'compact' => true]);
            \Yii::$app = $old_app;
        }
        if ($this->do_system) {
            if ($echo) {
                echo TEXT_CLEAN_CACHE . "<br>\n";
            }
            Yii::$app->get_cache()->flush();
            if (function_exists('opcache_reset')) {
                opcache_reset();
                if ($echo) {
                    echo TEXT_CACHE_FLUSHED . "<br>\n";
                }
            }
        }
        if ($this->do_smarty) {
            if ($echo) {
                echo TEXT_CLEAN_SMARTY . "<br>\n";
            }
            foreach ($all_runtime_directories as $runtime_directory) {
                $smarty_path = $runtime_directory . DIRECTORY_SEPARATOR . 'Smarty' . DIRECTORY_SEPARATOR . 'compile' . DIRECTORY_SEPARATOR . '*.*';
                array_map('unlink', glob($smarty_path));
            }
            $themes_path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR;
            $dir = scandir($themes_path);
            foreach ($dir as $theme) {
                if (file_exists($themes_path . $theme . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR)) {
                    \yii\helpers\File_Helper::remove_directory($themes_path . $theme . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);
                }
            }
        }
        if ($this->do_menu) {
            \common\helpers\Menu_Helper::reset_admin_menu();
        }
        if ($this->do_hooks) {
            \common\helpers\Hooks::reset_hooks();
        }
        if ($this->do_theme) {
            \backend\design\Style::flush_cache_all();
        }
        $this->reset_re_cache_flags();
    }
    private function get_file_with_dependencies($get_by, $filter)
    {
        $status = false;
        $filename = '';
        if ($request = curl_init()) {
            $storage_url = \Yii::$app->params['appStorage.url'];
            $storage_key = $this->get_storage_key();
            $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
            curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server/product');
            // for testing
            curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
            if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                // Added in cURL 7.41.0
                curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
            }
            curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
            curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
            $post_field_array = ['get_by' => $get_by, 'filter' => $filter];
            $post_field_array = json_encode($post_field_array);
            curl_setopt($request, CURLOPT_POSTFIELDS, $post_field_array);
            //$result = curl_exec($request);
            $result = json_decode(curl_exec($request), true);
            $response = curl_getinfo($request);
            curl_close($request);
            if ($response['http_code'] == 200 && isset($result['content'])) {
                $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                $filename = $result['filename'] ?? '';
                if (!file_exists($path . $filename)) {
                    $content = base64_decode($result['content']);
                    $size = $result['size'] ?? 0;
                    if (strlen($content) == $size) {
                        file_put_contents($path . $filename, $content);
                        $status = true;
                    }
                } else {
                    $status = true;
                }
                $zip = new \Zip_Archive();
                if ($zip->open($path . $filename) === true) {
                    $json = $zip->get_from_name('distribution.json');
                    $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
                    $zip->close();
                    if (!empty($json)) {
                        $distribution = json_decode($json);
                        if (isset($distribution->require->modules) && is_array($distribution->require->modules)) {
                            foreach ($distribution->require->modules as $subfile) {
                                $record = \common\models\Installer::find()->where(['filename' => $subfile])->one();
                                if (!$record instanceof \common\models\Installer) {
                                    $status = $status && $this->get_file_with_dependencies('file', $subfile);
                                }
                            }
                        }
                    }
                } else {
                    $status = false;
                }
            }
        }
        if ($status) {
            return $filename;
        }
        return $status;
    }
    private function install_file_with_dependencies($filename, $settings = [], $echo = false)
    {
        $this->deploy_log[] = TEXT_CHECKING . ' ' . $filename;
        $selected_platform_id = $settings['platform_id'] ?? 0;
        $locale = $settings['locale'] ?? 0;
        $platform_names = [];
        $to_assign = [];
        if ($selected_platform_id > 0) {
            $to_assign[] = $selected_platform_id;
            $p_row = \common\models\Platforms::find()->select(['platform_name'])->where(['is_virtual' => 0, 'is_marketplace' => 0, 'platform_id' => $selected_platform_id])->as_array()->one();
            $platform_names[$selected_platform_id] = $p_row['platform_name'] ?? '';
        }
        if ($selected_platform_id < 0) {
            foreach (\common\models\Platforms::find()->select(['platform_id', 'platform_name'])->where(['is_virtual' => 0, 'is_marketplace' => 0])->as_array()->all() as $p_row) {
                $to_assign[] = $p_row['platform_id'];
                $platform_names[$p_row['platform_id']] = $p_row['platform_name'];
            }
        }
        $force = $settings['force'] ?? 0;
        $selected_acl = $settings['acl'] ?? 0;
        $status = false;
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR;
        $zip = new \Zip_Archive();
        if ($zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . $filename) === true) {
            $json = $zip->get_from_name('distribution.json');
            $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
            $zip->close();
            if (!empty($json)) {
                $distribution = json_decode($json);
                $status = true;
                if (isset($distribution->require->version)) {
                    $conf = \common\models\Configuration::find()->where(['configuration_key' => 'MIGRATIONS_DB_REVISION'])->one();
                    if ($conf instanceof \common\models\Configuration) {
                        $version = $conf->configuration_value;
                    } else {
                        $version = '';
                    }
                    $version_applicable = $distribution->require->version_applicable ?? 'equal';
                    switch ($version_applicable) {
                        case 'equal':
                            if ($version != $distribution->require->version) {
                                $status = false;
                                $this->deploy_log[] = 'Version required ' . $distribution->require->version;
                            }
                            break;
                        case 'greater-equal':
                            if (intval($version) < intval($distribution->require->version)) {
                                $status = false;
                                $this->deploy_log[] = 'Version must be greater or equal to ' . $distribution->require->version;
                            }
                            break;
                        case 'less-equal':
                            if (intval($version) > intval($distribution->require->version)) {
                                $status = false;
                                $this->deploy_log[] = 'Version must be less or equal to ' . $distribution->require->version;
                            }
                            break;
                        default:
                            break;
                    }
                }
                if (isset($distribution->require->modules) && is_array($distribution->require->modules)) {
                    foreach ($distribution->require->modules as $subfile) {
                        if (self::is_known_require_module($subfile)) {
                            continue;
                        }
                        $record = \common\models\Installer::find()->where(['filename' => $subfile])->one();
                        if (!$record instanceof \common\models\Installer) {
                            $status = $status && $this->install_file_with_dependencies($subfile, $settings, $echo);
                        }
                    }
                }
                if (isset($distribution->require->classes) && is_array($distribution->require->classes)) {
                    foreach ($distribution->require->classes as $classversion) {
                        $record_query = \common\models\Installer::find()->where(['archive_class' => $classversion->name]);
                        $cv = '';
                        if (isset($classversion->min)) {
                            list($major, $minor, $patch) = array_pad(explode('.', (string) $classversion->min), 3, 0);
                            $archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
                            $record_query->and_where(['>=', 'archive_version', $archive_version]);
                            $cv .= ', v.' . $classversion->min . ' or greater';
                        }
                        if (isset($classversion->max)) {
                            list($major, $minor, $patch) = array_pad(explode('.', (string) $classversion->max), 3, 0);
                            $archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
                            $record_query->and_where(['<=', 'archive_version', $archive_version]);
                            $cv .= ', v.' . $classversion->max . ' or less';
                        }
                        $record = $record_query->one();
                        if (!$record instanceof \common\models\Installer) {
                            $this->deploy_log[] = 'Class ' . $classversion->name . $cv . ' must be installed';
                            $status = false;
                        }
                        unset($record);
                    }
                }
                if ($status) {
                    $module_dir = '';
                    $set_param = '';
                    switch ($distribution->type) {
                        case 'extension':
                            // Extension
                            if (isset($distribution->class)) {
                                $this->ext_class = $distribution->class;
                                $path_p = $path . 'lib' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'extensions';
                                $check = \common\models\Installer::find()->select(['max(archive_version) as version'])->where(['archive_type' => (string) $distribution->type])->and_where(['archive_class' => (string) $distribution->class])->as_array()->one();
                                if (isset($check['version'])) {
                                    $major = floor($check['version']);
                                    $minor = floor(($check['version'] - $major) * 100);
                                    $patch = ($check['version'] - $major - $minor / 100) * 10000;
                                    $json_file = 'v-' . $major . '-' . $minor . '-' . $patch . '.json';
                                    $zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . $filename);
                                    $json_string = $zip->get_from_name($json_file);
                                    $zip->close();
                                    if ($json_string !== false) {
                                        $checklist = json_decode($json_string);
                                        $path_c = $path_p . DIRECTORY_SEPARATOR . $distribution->class . DIRECTORY_SEPARATOR;
                                        foreach ($checklist as $checkfile => $checksum) {
                                            $dst = str_replace('|', DIRECTORY_SEPARATOR, $checkfile);
                                            if (empty($checksum) && !is_dir($path_c . $dst)) {
                                                $status = false;
                                                $this->deploy_log[] = "Directory {$dst} not found.";
                                            }
                                            if (!empty($checksum) && is_file($path_c . $dst)) {
                                                $crc = crc32(file_get_contents($path_c . $dst));
                                                if ($crc != $checksum) {
                                                    $status = false;
                                                    $this->deploy_log[] = "File {$dst} modified.";
                                                }
                                            } elseif (!empty($checksum)) {
                                                $status = false;
                                                $this->deploy_log[] = "File {$dst} not found.";
                                            }
                                        }
                                    }
                                }
                                if ($status) {
                                    $status = $this->check_file_dst($distribution->src, $filename, $path_p, $echo, $force);
                                }
                                if ($status) {
                                    $this->run_file_dst($distribution->src, $filename, $path_p, $echo);
                                    $class = '\common\extensions\\' . (string) $distribution->class . '\\' . (string) $distribution->class;
                                    $this->do_install_class($class, 0, $selected_acl);
                                    $this->do_hooks = true;
                                    $this->do_menu = false;
                                    $this->do_install_record($filename, (string) $distribution->type, (string) $distribution->class, (string) $distribution->version, $distribution->src);
                                    $this->do_system = true;
                                }
                            }
                            break;
                        case 'design':
                            // Theme
                            $theme = new \common\models\Themes();
                            $theme->load_default_values();
                            $theme_name = \common\classes\design::page_name($distribution->name);
                            $theme->theme_name = $theme_name;
                            $theme->title = $distribution->name;
                            $theme->install = 1;
                            $theme->is_default = 0;
                            $theme->sort_order = 0;
                            $theme->parent_theme = '';
                            if ($theme->save()) {
                                \backend\design\Theme::import($theme_name, $path . 'uploads' . DIRECTORY_SEPARATOR . $filename);
                                $old_data = ['id' => $theme->id];
                                if ($theme->id > 0) {
                                    foreach ($to_assign as $to_id) {
                                        $old_data['platforms_to_themes'][$to_id] = \common\models\Platforms_To_Themes::find()->where(['platform_id' => $to_id])->as_array()->all();
                                        \common\models\Platforms_To_Themes::delete_all(['platform_id' => $to_id]);
                                        $p2t = new \common\models\Platforms_To_Themes();
                                        $p2t->load_default_values();
                                        $p2t->platform_id = $to_id;
                                        $p2t->theme_id = $theme->id;
                                        $p2t->is_default = 1;
                                        $p2t->save(false);
                                    }
                                }
                                $this->do_system = true;
                                $this->do_smarty = true;
                                //$this->doTheme = true;
                                $this->do_install_record($filename, (string) $distribution->type, (string) ($distribution->class ?? ''), (string) $distribution->version, $old_data);
                                $status = true;
                            } else {
                                $status = false;
                            }
                            break;
                        case 'translate':
                            // Translations
                            $languages = \common\helpers\Language::get_languages(true);
                            $override = $addnew = true;
                            $zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . $filename);
                            $localejson = $zip->get_from_name('locale.json');
                            $localejson = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $localejson);
                            $old_data = [];
                            if (!empty($json)) {
                                $localejson = json_decode($localejson, JSON_OBJECT_AS_ARRAY);
                                $lang = \common\models\Languages::find()->where(['code' => (string) $localejson['code']])->one();
                                if ($lang instanceof \common\models\Languages) {
                                    if ($locale == 1) {
                                        // update language settings
                                        $update_language_id = $lang->languages_id;
                                        if ($update_language_id > 0 && isset($localejson['formats']) && is_array($localejson['formats'])) {
                                            foreach ($localejson['formats'] as $configuration_key => $configuration_value) {
                                                $l_formats = \common\models\Languages_Formats::find()->where(['configuration_key' => $configuration_key])->and_where(['language_id' => $update_language_id])->one();
                                                if ($l_formats instanceof \common\models\Languages_Formats) {
                                                    $l_formats->configuration_value = $configuration_value;
                                                    $l_formats->save(false);
                                                } else {
                                                    $l_formats = new \common\models\Languages_Formats();
                                                    $l_formats->load_default_values();
                                                    $l_formats->configuration_key = $configuration_key;
                                                    $l_formats->configuration_value = $configuration_value;
                                                    $l_formats->language_id = $update_language_id;
                                                    $l_formats->save(false);
                                                }
                                            }
                                        }
                                    }
                                    $insert_id = $lang->languages_id;
                                } else {
                                    // install new language and settings
                                    $max = tep_db_fetch_array(tep_db_query('select max(sort_order)+1 as sort_order from languages where 1'));
                                    $sql_array = ['name' => $localejson['name'], 'code' => strtolower((string) $localejson['code']), 'image_svg' => $localejson['icon'], 'locale' => (string) $localejson['locale'], 'sort_order' => $max['sort_order'], 'languages_status' => 0];
                                    $lang = new \common\models\Languages();
                                    $lang->load_default_values();
                                    $lang->set_attributes($sql_array, false);
                                    if ($lang->save(false)) {
                                        $insert_id = $lang->languages_id;
                                        if ($insert_id > 0 && isset($localejson['formats']) && is_array($localejson['formats'])) {
                                            foreach ($localejson['formats'] as $configuration_key => $configuration_value) {
                                                $l_formats = new \common\models\Languages_Formats();
                                                $l_formats->load_default_values();
                                                $l_formats->configuration_key = $configuration_key;
                                                $l_formats->configuration_value = $configuration_value;
                                                $l_formats->language_id = $insert_id;
                                                $l_formats->save(false);
                                            }
                                            $old_data[] = ['action' => 'deletelanguage', 'language_id' => $insert_id];
                                        }
                                    }
                                    $languages = \common\helpers\Language::get_languages(true);
                                }
                                // update or create from default language
                                \common\helpers\Language::copy_language((int) \common\helpers\Language::get_default_language_id(), (int) $insert_id);
                            }
                            foreach ((array) $distribution->files as $file) {
                                $csv_string = $zip->get_from_name($file);
                                $bom = substr($csv_string, 0, 2);
                                if ($bom === chr(0xff) . chr(0xfe) || $bom === chr(0xfe) . chr(0xff)) {
                                    $encoding = 'UTF-16';
                                } else {
                                    $encoding = mb_detect_encoding($csv_string, 'auto', true);
                                }
                                if ($encoding) {
                                    $csv_string = iconv($encoding, 'UTF-8', $csv_string);
                                } else {
                                    $csv_string = iconv('CP850', 'UTF-8', $csv_string);
                                }
                                $Data = str_getcsv($csv_string, "\n");
                                $uploaded_keys = false;
                                foreach ($Data as &$data) {
                                    $data = str_getcsv($data, "\t");
                                    if ($uploaded_keys === false) {
                                        $uploaded_keys = array_flip($data);
                                        continue;
                                    }
                                    if (isset($data[$uploaded_keys['HASH']]) && !empty($data[$uploaded_keys['HASH']])) {
                                        foreach ($languages as $_lang) {
                                            if (isset($uploaded_keys[$_lang['code']])) {
                                                $check_hash_query = tep_db_query('SELECT * FROM ' . TABLE_TRANSLATION . " WHERE language_id='" . (int) $_lang['id'] . "' and hash = '" . tep_db_input($data[$uploaded_keys['HASH']]) . "'");
                                                if (tep_db_num_rows($check_hash_query) > 0) {
                                                    if ($override) {
                                                        $check_hash = tep_db_fetch_array($check_hash_query);
                                                        $old_data[] = ['action' => 'update', 'translation_value' => $check_hash['translation_value'], 'translated' => $check_hash['translated'], 'language_id' => $check_hash['language_id'], 'hash' => $check_hash['hash']];
                                                        tep_db_query('update ' . TABLE_TRANSLATION . " set translation_value = '" . tep_db_input($data[$uploaded_keys[$_lang['code']]]) . "', translated = '" . tep_db_input($data[$uploaded_keys[$_lang['code'] . '_TSL']]) . "' where language_id = '" . (int) $_lang['id'] . "' and hash = '" . tep_db_input($data[$uploaded_keys['HASH']]) . "'");
                                                    }
                                                } elseif (isset($data[$uploaded_keys['Entity']]) && isset($data[$uploaded_keys['Key']])) {
                                                    $check_hash_query = tep_db_query('SELECT * FROM ' . TABLE_TRANSLATION . " WHERE language_id='" . (int) $_lang['id'] . "' and translation_key = '" . tep_db_input($data[$uploaded_keys['Key']]) . "' and translation_entity = '" . tep_db_input($data[$uploaded_keys['Entity']]) . "'");
                                                    if (tep_db_num_rows($check_hash_query) > 0) {
                                                        if ($override) {
                                                            $check_hash = tep_db_fetch_array($check_hash_query);
                                                            $old_data[] = ['action' => 'update', 'translation_value' => $check_hash['translation_value'], 'translated' => $check_hash['translated'], 'language_id' => $check_hash['language_id'], 'hash' => $check_hash['hash']];
                                                            tep_db_query('update ' . TABLE_TRANSLATION . " set translation_value = '" . tep_db_input($data[$uploaded_keys[$_lang['code']]]) . "', translated = '" . tep_db_input($data[$uploaded_keys[$_lang['code'] . '_TSL']]) . "' where language_id = '" . (int) $_lang['id'] . "' and hash = '" . tep_db_input($data[$uploaded_keys['HASH']]) . "'");
                                                        }
                                                    } elseif ($addnew && !empty($data[$uploaded_keys['Key']]) && !empty($data[$uploaded_keys['Entity']])) {
                                                        $hash = md5($data[$uploaded_keys['Key']] . '-' . $data[$uploaded_keys['Entity']]);
                                                        $sql_data_array = ['language_id' => (int) $_lang['id'], 'translation_key' => $data[$uploaded_keys['Key']], 'translation_entity' => $data[$uploaded_keys['Entity']], 'translation_value' => $data[$uploaded_keys[$_lang['code']]], 'hash' => $hash, 'translated' => $data[$uploaded_keys[$_lang['code'] . '_TSL']]];
                                                        $old_data[] = ['action' => 'delete', 'language_id' => (int) $_lang['id'], 'hash' => $hash];
                                                        tep_db_perform(TABLE_TRANSLATION, $sql_data_array);
                                                    }
                                                }
                                            } else {
                                                //check and add empty value if not exist
                                            }
                                        }
                                    }
                                }
                            }
                            $this->do_install_record($filename, (string) $distribution->type, (string) ($distribution->class ?? ''), (string) $distribution->version, $old_data);
                            $this->do_system = true;
                            $this->do_theme = true;
                            $zip->close();
                            break;
                        case 'payment':
                            // Payment
                            $module_dir = 'orderPayment';
                            $set_param = 'payment';
                        // no break
                        case 'shipping':
                            // Shipping
                            if (empty($module_dir)) {
                                $module_dir = 'orderShipping';
                                $set_param = 'shipping';
                            }
                        // no break
                        case 'totals':
                            // Order structure
                            if (empty($module_dir)) {
                                $module_dir = 'orderTotal';
                                $set_param = 'ordertotal';
                            }
                        // no break
                        case 'analytic':
                            // Google analytic
                            if (empty($module_dir)) {
                                $module_dir = 'analytic';
                            }
                        // no break
                        case 'label':
                            // Shipping label
                            if (empty($module_dir)) {
                                $module_dir = 'label';
                                $set_param = 'label';
                            }
                            $path_p = $path . 'lib' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module_dir;
                            $status = $this->check_file_dst($distribution->src, $filename, $path_p, $echo, $force);
                            if ($status) {
                                $this->run_file_dst($distribution->src, $filename, $path_p, $echo);
                                if (isset($distribution->class)) {
                                    $class = '\common\modules\\' . $module_dir . '\\' . (string) $distribution->class;
                                    foreach ($to_assign as $to_id) {
                                        $this->do_install_class($class, $to_id);
                                        $this->do_recalc_module_sort('add', $class, $distribution->type, $to_id);
                                        if (!empty($set_param)) {
                                            $this->deploy_log[] = 'This ' . $distribution->type . ' installed automatically.' . 'Dont forget <a target="_blank" href="' . Yii::$app->url_manager->create_url(['modules/edit', 'set' => $set_param, 'module' => (string) $distribution->class, 'platform_id' => $to_id]) . '">check settings for platform ' . $platform_names[$to_id] . '</a>.';
                                        }
                                    }
                                }
                                $this->do_install_record($filename, (string) $distribution->type, (string) ($distribution->class ?? ''), (string) $distribution->version, $distribution->src);
                                $this->do_system = true;
                            }
                            break;
                        case 'samples':
                            // Sample data
                            $status = true;
                            \common\helpers\Translation::init('admin/easypopulate');
                            \common\helpers\Translation::init('admin/main');
                            try {
                                copy($path . 'uploads' . DIRECTORY_SEPARATOR . $filename, $path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . $filename);
                                ob_start();
                                $messages = new \backend\models\EP\Messages(['output' => 'null']);
                                $import_job = new \backend\models\EP\Job_Zip_File([
                                    'directory_id' => 2,
                                    //manual import
                                    'file_name' => $filename,
                                    'direction' => 'import',
                                    'job_provider' => 'auto',
                                ]);
                                $import_job->try_auto_configure();
                                $import_job->run($messages);
                                ob_flush();
                                if ($zip->open($path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . $filename) === true) {
                                    $catalog_categories = [];
                                    $zip->extract_to($path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR, 'catalog_categories.csv');
                                    $reader = new \backend\models\EP\Reader\CSV(['filename' => $path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . 'catalog_categories.csv']);
                                    while ($Columns = $reader->read()) {
                                        $catalog_categories[] = $Columns['Categories SEO page name (URL) en'];
                                    }
                                    @unlink($path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . 'catalog_categories.csv');
                                    unset($reader);
                                    $catalog_products = [];
                                    $zip->extract_to($path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR, 'catalog_products.csv');
                                    $reader = new \backend\models\EP\Reader\CSV(['filename' => $path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . 'catalog_products.csv']);
                                    while ($Columns = $reader->read()) {
                                        $catalog_products[] = $Columns['Products Model'];
                                    }
                                    @unlink($path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . 'catalog_products.csv');
                                    unset($reader);
                                    $zip->close();
                                    foreach ($to_assign as $to_id) {
                                        foreach ($catalog_categories as $catalog_cat) {
                                            $cat = \common\models\Categories_Description::find()->where(['categories_seo_page_name' => $catalog_cat])->one();
                                            if ($cat instanceof \common\models\Categories_Description) {
                                                tep_db_query("INSERT IGNORE INTO platforms_categories (platform_id, categories_id) VALUES ({$to_id}, " . $cat->categories_id . ');');
                                            }
                                        }
                                        foreach (\common\models\Products::find()->where(['IN', 'products_model', $catalog_products])->all() as $product) {
                                            tep_db_query("INSERT IGNORE INTO platforms_products (platform_id, products_id) VALUES ({$to_id}, " . $product->products_id . ');');
                                            \common\helpers\Product::do_cache($product->products_id);
                                        }
                                    }
                                }
                                $old_data = ['catalog_categories' => $catalog_categories, 'catalog_products' => $catalog_products];
                                $this->do_install_record($filename, (string) $distribution->type, (string) ($distribution->class ?? ''), (string) $distribution->version, $old_data);
                                @unlink($path . 'ep_files' . DIRECTORY_SEPARATOR . 'manual_import' . DIRECTORY_SEPARATOR . $filename);
                            } catch (\Exception $ex) {
                                //echo "err:".$ex->getMessage()."\n".$ex->getTraceAsString()."\n";die();
                                $status = false;
                                $this->send_echo("<font color='red'>Exception: " . $ex->get_message() . ".</font><br>\n");
                            }
                            break;
                        case 'system':
                        case 'update':
                            // System update
                            $status = $this->check_file_dst($distribution->src, $filename, $path, $force ? false : $echo, $force);
                            if ($status) {
                                $this->run_file_dst($distribution->src, $filename, $path, $echo);
                                $this->do_install_record($filename, (string) $distribution->type, (string) ($distribution->class ?? ''), (string) $distribution->version, $distribution->src);
                                $this->do_migrations = true;
                                $this->do_system = true;
                                $this->do_smarty = true;
                                $this->do_theme = true;
                                $this->do_hooks = true;
                                $this->do_menu = true;
                                if ($distribution->type == 'update') {
                                    \common\models\Configuration::update_all(['configuration_value' => (string) $distribution->version], ['configuration_key' => 'MIGRATIONS_DB_REVISION']);
                                }
                            }
                            break;
                        case 'configuration':
                            $old_data = [];
                            $zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . $filename);
                            foreach ((array) $distribution->files as $file) {
                                $csv_string = $zip->get_from_name($file);
                                $bom = substr($csv_string, 0, 2);
                                if ($bom === chr(0xff) . chr(0xfe) || $bom === chr(0xfe) . chr(0xff)) {
                                    $encoding = 'UTF-16';
                                } else {
                                    $encoding = mb_detect_encoding($csv_string, 'auto', true);
                                }
                                if ($encoding) {
                                    $csv_string = iconv($encoding, 'UTF-8', $csv_string);
                                } else {
                                    $csv_string = iconv('CP850', 'UTF-8', $csv_string);
                                }
                                $Data = str_getcsv($csv_string, "\n");
                                $uploaded_keys = false;
                                foreach ($Data as &$data) {
                                    $data = str_getcsv($data, "\t");
                                    if ($uploaded_keys === false) {
                                        $uploaded_keys = array_flip($data);
                                        continue;
                                    }
                                    // Key Group Operation Value
                                    if (isset($data[$uploaded_keys['Key']]) && !empty($data[$uploaded_keys['Key']]) && isset($data[$uploaded_keys['Operation']]) && !empty($data[$uploaded_keys['Operation']])) {
                                        switch ($data[$uploaded_keys['Operation']]) {
                                            case 'add':
                                                $conf = \common\models\Configuration::find()->where(['configuration_key' => (string) $data[$uploaded_keys['Key']]])->one();
                                                if ($conf instanceof \common\models\Configuration) {
                                                    $old_data[] = ['action' => 'update', 'configuration_key' => $conf->configuration_key, 'configuration_value' => $conf->configuration_value, 'configuration_group_id' => $conf->configuration_group_id];
                                                } else {
                                                    $old_data[] = ['action' => 'delete', 'configuration_key' => (string) $data[$uploaded_keys['Key']]];
                                                    $conf = new \common\models\Configuration();
                                                    $conf->load_default_values();
                                                    $conf->configuration_key = (string) $data[$uploaded_keys['Key']];
                                                }
                                                $conf->configuration_value = (string) $data[$uploaded_keys['Value']];
                                                $conf->configuration_group_id = (string) $data[$uploaded_keys['Group']];
                                                $conf->save(false);
                                                break;
                                            case 'delete':
                                                $conf = \common\models\Configuration::find()->where(['configuration_key' => (string) $data[$uploaded_keys['Key']]])->one();
                                                if ($conf instanceof \common\models\Configuration) {
                                                    $old_data[] = ['action' => 'add', 'configuration_title' => $conf->configuration_title, 'configuration_key' => $conf->configuration_key, 'configuration_value' => $conf->configuration_value, 'configuration_description' => $conf->configuration_description, 'configuration_group_id' => $conf->configuration_group_id, 'sort_order' => $conf->sort_order, 'last_modified' => $conf->last_modified, 'date_added' => $conf->date_added, 'use_function' => $conf->use_function, 'set_function' => $conf->set_function];
                                                    $conf->delete();
                                                }
                                                break;
                                            case 'modify':
                                                $conf = \common\models\Configuration::find()->where(['configuration_key' => (string) $data[$uploaded_keys['Key']]])->one();
                                                if ($conf instanceof \common\models\Configuration) {
                                                    $old_data[] = ['action' => 'update', 'configuration_key' => $conf->configuration_key, 'configuration_value' => $conf->configuration_value, 'configuration_group_id' => $conf->configuration_group_id];
                                                    $conf->configuration_value = (string) $data[$uploaded_keys['Value']];
                                                    $conf->configuration_group_id = (string) $data[$uploaded_keys['Group']];
                                                    $conf->save(false);
                                                }
                                                break;
                                        }
                                    }
                                }
                            }
                            $this->do_install_record($filename, (string) $distribution->type, (string) ($distribution->class ?? ''), (string) $distribution->version, $old_data);
                            $this->do_system = true;
                            $zip->close();
                            break;
                        default:
                            $status = false;
                            break;
                    }
                }
            }
        }
        if ($status) {
            $this->deploy_log[] = $filename . ' ' . TEXT_PACK_INSTALLED . '.';
        } else {
            $this->deploy_log[] = $filename . ' ' . TEXT_PACK_ABORTED . '.';
        }
        return $status;
    }
    private function check_file_dst($rules, $zip_file, $path_p, $echo = false, $force = 0)
    {
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR;
        $checked = true;
        $force_backup = false;
        if ($force == 1) {
            $zip_force = new \Zip_Archive();
            if ($zip_force->open($path . 'uploads' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . 'force.' . $zip_file, \Zip_Archive::CREATE) === true) {
                $force_backup = true;
            }
        }
        foreach ((array) $rules as $src) {
            switch ($src->action) {
                case 'add':
                case 'modify':
                case 'copy':
                case 'delete':
                    break;
                default:
                    //                    if ($echo) echo "<font color='red'>".TEXT_ACTION_ERROR.".</font><br>\n";
                    if ($echo) {
                        $this->send_echo_for_update(TEXT_ACTION_ERROR, 'error');
                    }
                    $this->deploy_log[] = "<font color='red'>" . TEXT_ACTION_ERROR . ".</font><br>\n";
                    $checked = false;
                    break;
            }
            switch ($src->type) {
                case 'dir':
                case 'file':
                    break;
                default:
                    //                    if ($echo) echo "<font color='red'>".TEXT_TYPE_ERROR.".</font><br>\n";
                    if ($echo) {
                        $this->send_echo_for_update(TEXT_TYPE_ERROR, 'error');
                    }
                    $this->deploy_log[] = "<font color='red'>" . TEXT_TYPE_ERROR . ".</font><br>\n";
                    $checked = false;
                    break;
            }
            if (isset($src->crc32)) {
                $dst = str_replace('|', DIRECTORY_SEPARATOR, $src->path);
                if (!is_file($path_p . DIRECTORY_SEPARATOR . $dst)) {
                    //                    if ($echo) echo "<font color='red'>File $dst not found.</font><br>\n";
                    if ($echo) {
                        $this->send_echo_for_update("File \"{$dst}\" not found.", 'error');
                    }
                    $this->deploy_log[] = "<font color='red'>File {$dst} not found.</font><br>\n";
                    $checked = false;
                } else {
                    $old_item_crc = crc32(file_get_contents($path_p . DIRECTORY_SEPARATOR . $dst));
                    if ($src->crc32 != $old_item_crc) {
                        //                        if ($echo) echo "<font color='red'>" . TEXT_FILE . " " . $dst . " " . TEXT_CHECKSUM_ERROR . ".</font>".($this->show_ignore_field ? '<label><input type="checkbox" name="dst_file_ignore[]" class="dst_file_ignore" value="'.$dst.'">Ignore</label>' : '')."<br>\n";
                        if ($echo) {
                            $this->send_echo_for_update(TEXT_FILE . ' ' . $dst . ' ' . TEXT_CHECKSUM_ERROR . ($this->show_ignore_field ? '<label style="color: #000"><input type="checkbox" name="dst_file_ignore[]" class="dst_file_ignore" value="' . $dst . '">Ignore</label>' : ''), 'warning');
                        }
                        $this->deploy_log[] = "<font color='red'>" . TEXT_FILE . ' ' . $dst . ' ' . TEXT_CHECKSUM_ERROR . ".</font><br>\n";
                        $checked = false;
                        if ($force_backup) {
                            $zip_force->add_file($path_p . DIRECTORY_SEPARATOR . $dst, $dst);
                        }
                    } else {
                        //                        if ($echo) echo "<font color='green'>" . TEXT_FILE . " " . $dst . " " . TEXT_CHECKSUM_PASSED . ".</font><br>\n";
                        if ($echo) {
                            $this->send_echo_for_update(TEXT_FILE . ' ' . $dst . ' ' . TEXT_CHECKSUM_PASSED, 'success');
                        }
                        $this->deploy_log[] = "<font color='green'>" . TEXT_FILE . ' ' . $dst . ' ' . TEXT_CHECKSUM_PASSED . ".</font><br>\n";
                    }
                }
            }
        }
        if ($force == 1) {
            if ($force_backup) {
                $zip_force->close();
            }
            unset($zip_force);
            $checked = true;
        }
        if ($checked) {
            $zip = new \Zip_Archive();
            if ($zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $zip_file, \Zip_Archive::CREATE) === true) {
                // $zipFile must contain path to new backup file
                foreach ($rules as $src) {
                    $dst = str_replace('|', DIRECTORY_SEPARATOR, $src->path);
                    switch ($src->action) {
                        case 'copy':
                            if ($src->type == 'dir' && is_dir($path_p . DIRECTORY_SEPARATOR . $dst)) {
                                $zip->add_empty_dir($dst);
                                $scanner = new \common\classes\Dir_Scanner($path_p . DIRECTORY_SEPARATOR . $dst);
                                $result = $scanner->run();
                                foreach ($result as $path_sub => $crc) {
                                    $dst_sub = str_replace('|', DIRECTORY_SEPARATOR, $path_sub);
                                    if (is_dir($path_p . DIRECTORY_SEPARATOR . $dst . DIRECTORY_SEPARATOR . $dst_sub)) {
                                        $zip->add_empty_dir($dst . DIRECTORY_SEPARATOR . $dst_sub);
                                    } elseif (is_file($path_p . DIRECTORY_SEPARATOR . $dst . DIRECTORY_SEPARATOR . $dst_sub)) {
                                        $zip->add_file($path_p . DIRECTORY_SEPARATOR . $dst . DIRECTORY_SEPARATOR . $dst_sub, $dst . DIRECTORY_SEPARATOR . $dst_sub);
                                    }
                                }
                                unset($scanner);
                            }
                            break;
                        case 'add':
                        case 'modify':
                        case 'delete':
                            if ($src->type == 'file' && is_file($path_p . DIRECTORY_SEPARATOR . $dst)) {
                                $zip->add_file($path_p . DIRECTORY_SEPARATOR . $dst, $dst);
                            }
                            break;
                        default:
                            break;
                    }
                }
                $zip->close();
            }
            unset($zip);
        }
        return $checked;
    }
    private function run_file_dst($rules, $zip_file, $path_p, $echo = false)
    {
        //        $pathP = \yii\helpers\BaseFileHelper::normalizePath($pathP, '/');
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR;
        $zip = new \Zip_Archive();
        if ($zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . $zip_file) === true) {
            foreach ($rules as $src) {
                $dst = str_replace('|', '/', $src->path);
                // don't use DIRECTORY_SEPARATOR here
                if (in_array($dst, $this->dst_file_ignore)) {
                    if ($echo) {
                        echo "<font color='red'>{$dst} ignored.</font><br>\n";
                    }
                    continue;
                }
                switch ($src->action) {
                    case 'add':
                        if ($src->type == 'dir') {
                            if (!is_dir($path_p . DIRECTORY_SEPARATOR . $dst)) {
                                @mkdir($path_p . DIRECTORY_SEPARATOR . $dst);
                                if ($echo) {
                                    echo "<font color='blue'>" . TEXT_DIRECTORY . " {$dst} " . TEXT_ADDED . ".</font><br>\n";
                                }
                            }
                        }
                        if ($src->type == 'file') {
                            if (!$zip->extract_to($path_p, $dst)) {
                                $error_msg = sprintf('Error extracting %s: %s', $dst, $zip->get_status_string());
                                \Yii::warning($error_msg);
                                if ($echo) {
                                    echo "<font color='red'>{$error_msg}</font><br>\n";
                                }
                            } else if ($echo) {
                                echo "<font color='blue'>" . TEXT_FILE . " {$dst} " . TEXT_ADDED . ".</font><br>\n";
                            }
                        }
                        break;
                    case 'modify':
                        if ($src->type == 'file') {
                            $file_name = $path_p . DIRECTORY_SEPARATOR . $dst;
                            @rename($file_name, $file_name . '_old');
                            // to avoid 'Failed to open stream: Permission denied' under Windows
                            @unlink($file_name . '_old');
                            if (!$zip->extract_to($path_p, $dst)) {
                                $error_msg = sprintf('Error extracting %s: %s', $dst, $zip->get_status_string());
                                \Yii::warning($error_msg);
                                if ($echo) {
                                    echo "<font color='red'>{$error_msg}</font><br>\n";
                                }
                            } else if ($echo) {
                                echo "<font color='blue'>" . TEXT_FILE . " {$dst} " . TEXT_MODIFIED . ".</font><br>\n";
                            }
                        }
                        break;
                    case 'copy':
                        if ($src->type == 'dir') {
                            for ($i = 0; $i < $zip->num_files; $i++) {
                                $entry = $zip->get_name_index($i);
                                if (strpos($entry, $dst) === 0) {
                                    if (!$zip->extract_to($path_p, $entry)) {
                                        $error_msg = sprintf('Error extracting %s: %s', $entry, $zip->get_status_string());
                                        \Yii::warning($error_msg);
                                        if ($echo) {
                                            echo "<font color='red'>{$error_msg}</font><br>\n";
                                        }
                                    }
                                }
                            }
                            if ($echo) {
                                echo "<font color='blue'>" . TEXT_DIRECTORY . " {$dst} " . TEXT_COPIED . ".</font><br>\n";
                            }
                        }
                        break;
                    case 'delete':
                        $fn = $path_p . DIRECTORY_SEPARATOR . $dst;
                        if ($src->type == 'dir' && is_dir($fn)) {
                            if (!@rmdir($fn)) {
                                $error_msg = "Can't remove dir {$fn}: " . error_get_last()['message'] ?? 'unknown';
                                \Yii::warning($error_msg . 'Dir contains: ' . implode("\n", glob($fn . '/*')) . "\n" . implode("\n", glob($fn . '/.*')));
                                if ($echo) {
                                    echo "<font color='red'>{$error_msg}</font><br>\n";
                                }
                            } else if ($echo) {
                                echo "<font color='blue'>" . TEXT_DIRECTORY . " {$dst} " . TEXT_DELETED . ".</font><br>\n";
                            }
                        }
                        if ($src->type == 'file' && is_file($fn)) {
                            if (!@unlink($fn)) {
                                $error_msg = "Can't remove file {$fn}: " . error_get_last()['message'] ?? 'unknown';
                                \Yii::warning($error_msg);
                                if ($echo) {
                                    echo "<font color='red'>{$error_msg}</font><br>\n";
                                }
                            } else if ($echo) {
                                echo "<font color='blue'>" . TEXT_FILE . " {$dst} " . TEXT_DELETED . ".</font><br>\n";
                            }
                        }
                        break;
                    default:
                        break;
                }
            }
            if (!@$zip->close()) {
                $error_msg = sprintf('Error closing zip: %s', $zip->get_status_string());
                \Yii::warning($error_msg);
            }
        }
        unset($zip);
    }
    private function revert_file_dst($rules, $zip_file, $path_p, $echo = false)
    {
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR;
        $zip = new \Zip_Archive();
        $can_use_zip_for_revert = $zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $zip_file) === true;
        // $zipFile must contain deleted files
        $rules = array_reverse($rules);
        foreach ($rules as $src) {
            $dst = str_replace('|', DIRECTORY_SEPARATOR, $src->path);
            switch ($src->action) {
                case 'add':
                    if ($src->type == 'dir') {
                        //delete
                        if (is_dir($path_p . DIRECTORY_SEPARATOR . $dst)) {
                            @rmdir($path_p . DIRECTORY_SEPARATOR . $dst . DIRECTORY_SEPARATOR);
                            if ($echo) {
                                echo TEXT_DIRECTORY . " {$dst} deleted.<br>\n";
                            }
                        }
                    }
                    if ($src->type == 'file') {
                        //delete
                        @unlink($path_p . DIRECTORY_SEPARATOR . $dst);
                        if ($echo) {
                            echo TEXT_FILE . " {$dst} deleted.<br>\n";
                        }
                    }
                    break;
                case 'modify':
                    if ($src->type == 'file') {
                        //restore from backup
                        @unlink($path_p . DIRECTORY_SEPARATOR . $dst);
                        if ($can_use_zip_for_revert) {
                            $zip->extract_to($path_p, $dst);
                            if ($echo) {
                                echo TEXT_FILE . " {$dst} restored.<br>\n";
                            }
                        }
                    }
                    break;
                case 'copy':
                    if ($src->type == 'dir') {
                        //delete
                        $this->del_tree($path_p . DIRECTORY_SEPARATOR . $dst . DIRECTORY_SEPARATOR);
                        if ($echo) {
                            echo TEXT_DIRECTORY . " {$dst} deleted.<br>\n";
                        }
                        if ($can_use_zip_for_revert) {
                            for ($i = 0; $i < $zip->num_files; $i++) {
                                $entry = $zip->get_name_index($i);
                                if (strpos($entry, $dst) === 0) {
                                    $zip->extract_to($path_p, $entry);
                                }
                            }
                            if ($echo) {
                                echo TEXT_DIRECTORY . " {$dst} copied.<br>\n";
                            }
                        }
                    }
                    break;
                case 'delete':
                    if ($src->type == 'dir' && !is_dir($path_p . DIRECTORY_SEPARATOR . $dst)) {
                        //add
                        @mkdir($path_p . DIRECTORY_SEPARATOR . $dst);
                        if ($echo) {
                            echo TEXT_DIRECTORY . " {$dst} added.<br>\n";
                        }
                    }
                    if ($src->type == 'file' && !is_file($path_p . DIRECTORY_SEPARATOR . $dst)) {
                        //restore from backup
                        if ($can_use_zip_for_revert) {
                            $zip->extract_to($path_p, $dst);
                            if ($echo) {
                                echo TEXT_FILE . " {$dst} added.<br>\n";
                            }
                        }
                    }
                    break;
                default:
                    break;
            }
        }
        if ($can_use_zip_for_revert) {
            $zip->close();
            @unlink($path . 'uploads' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $zip_file);
        }
        unset($zip);
    }
    public function action_install_class()
    {
        $class = Yii::$app->request->get('class');
        $platform_id = Yii::$app->request->get('platform_id');
        $acl = Yii::$app->request->get('acl');
        $this->do_install_class_internal($class, $platform_id, $acl);
    }
    private function do_install_class($class, $selected_platform_id = 0, $acl = 0)
    {
        $url = \Yii::$app->url_manager->create_absolute_url(['install/install-class', 'class' => $class, 'platform_id' => $selected_platform_id, 'acl' => $acl]);
        $res = \common\helpers\Curl::run_safe($url, 'GET', null, null, ['verify' => false, CURLOPT_COOKIE => 'tlAdminID=' . \Yii::$app->session->id]);
        if (!($res['success'] ?? false)) {
            \Yii::warning(sprintf('Internal install class "%s" is failed (safe method will be used): %s', $class, $res['error'] ?? var_export($res, true)));
            $this->do_install_class_internal($class, $selected_platform_id, $acl);
        }
    }
    private function do_install_class_internal($class, $selected_platform_id = 0, $acl = 0)
    {
        if (class_exists($class)) {
            $module = new $class();
            $export_settings = [];
            if (method_exists($module, 'remove')) {
                if (method_exists($module, 'keys')) {
                    $keys = $module->keys();
                    $rows = \common\models\Platforms_Configuration::find()->select(['configuration_value', 'configuration_key'])->where(['platform_id' => $selected_platform_id])->and_where(['IN', 'configuration_key', $keys])->all();
                    foreach ($rows as $row) {
                        $export_settings['keys'][$row['configuration_key']] = $row['configuration_value'];
                    }
                    if (method_exists($module, 'get_extra_params')) {
                        $extra_params = $module->get_extra_params($selected_platform_id);
                        if (count($extra_params) > 0) {
                            $export_settings['extra_params'] = $extra_params;
                        }
                    }
                }
                // $module->remove($selected_platform_id);
            }
            if (method_exists($module, 'install')) {
                if ($acl > 0) {
                    if (isset($module->is_extension)) {
                        switch ($acl) {
                            case 'all':
                                $access_levels = [];
                                foreach (\common\models\Access_Levels::find()->select(['access_levels_id'])->as_array()->all() as $al) {
                                    $access_levels[] = $al['access_levels_id'];
                                }
                                $module->assign_to_access_levels = $access_levels;
                                break;
                            case 'my':
                                global $access_levels_id;
                                $module->assign_to_access_levels = $access_levels_id;
                                break;
                            default:
                                $module->assign_to_access_levels = 0;
                                break;
                        }
                    }
                }
                $module->install($selected_platform_id);
                if (method_exists($module, 'save_config') && is_array($export_settings['keys'] ?? null)) {
                    $module->save_config($selected_platform_id, $export_settings['keys']);
                    if (method_exists($module, 'set_extra_params') && isset($export_settings['extra_params'])) {
                        $module->set_extra_params($selected_platform_id, $export_settings['extra_params']);
                    }
                }
                if (method_exists($module, 'enable_module')) {
                    $module->enable_module($selected_platform_id, true);
                }
            }
            unset($export_settings);
        }
    }
    private function do_uninstall_class($class, $selected_platform_id = 0, $prev_ver = null)
    {
        if (class_exists($class)) {
            $module = new $class();
            if (is_null($prev_ver)) {
                if (method_exists($module, 'remove')) {
                    $module->remove($selected_platform_id);
                }
            } else if (method_exists($module, 'downgrade')) {
                $module->downgrade($prev_ver);
            }
        }
    }
    private function do_recalc_module_sort($action, $module, $type, $selected_platform_id)
    {
        switch ($type) {
            case 'payment':
                $module_key = 'MODULE_PAYMENT_INSTALLED';
                break;
            case 'shipping':
                $module_key = 'MODULE_SHIPPING_INSTALLED';
                break;
            case 'totals':
                $module_key = 'MODULE_ORDER_TOTAL_INSTALLED';
                break;
            case 'label':
                $module_key = 'MODULE_LABEL_INSTALLED';
                break;
            default:
                $module = '';
                break;
        }
        if (!empty($module)) {
            $module = \common\helpers\Output::mb_basename($module);
            $conf = \common\models\Platforms_Configuration::find_one(['configuration_key' => tep_db_input($module_key), 'platform_id' => intval($selected_platform_id)]);
            if (!empty($conf)) {
                $sorted = explode(';', $conf->configuration_value);
                if ($action == 'add') {
                    if (!in_array($module . '.php', $sorted)) {
                        $sorted[] = $module . '.php';
                    }
                }
                if ($action == 'delete') {
                    if ($key = array_search($module . '.php', $sorted) !== false) {
                        unset($sorted[$key]);
                    }
                }
                $new_sort = implode(';', $sorted);
                if ($new_sort != $conf->configuration_value) {
                    $conf->configuration_value = $new_sort;
                    $conf->save(false);
                }
            }
        }
    }
    private function do_install_record($filename, $type, $class, $version, $data)
    {
        $record = new \common\models\Installer();
        $record->data = serialize($data);
        $record->filename = $filename;
        $record->date_added = date('Y-m-d H:i:s');
        $record->archive_type = $type;
        $record->archive_class = $class;
        list($major, $minor, $patch) = array_pad(explode('.', $version), 3, '0');
        $record->archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
        return $record->save(false);
    }
    private function get_storage_key()
    {
        global $login_id;
        $admin = \common\models\Admin::find_one($login_id);
        //$storageKey = \Yii::$app->params['appStorage.key'];
        return $admin->storage_key ?? '';
    }
    public function action_index()
    {
        \common\helpers\Translation::init('admin/easypopulate');
        defined('TEXT_CLEANUP_INTRO') or define('TEXT_CLEANUP_INTRO', 'Are you sure you want to cleanup? All backups and unused archives will be deleted. Also deletion will make it impossible to revert to the previous version.');
        $this->selected_menu = ['settings', 'logging'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('install/'), 'title' => BOX_HEADING_INSTALL];
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url(['install/add-storage-key']) . '" class="create_item create_item_popup">' . TEXT_STORE_KEY . '</a>';
        $this->top_buttons[] = '<a href="' . Yii::$app->url_manager->create_url('install/reset-storage-key') . '" onclick="return confirm(\'' . TEXT_RESET_STORAGE_KEY . '\')" class="create_item"><i class="icon-refresh"></i>' . TEXT_RESET . '</a>';
        $this->view->heading_title = BOX_HEADING_INSTALL;
        $messages = [];
        if (Yii::$app->request->is_post) {
            if (isset($_FILES['data_file']['name'])) {
                $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                $uploadfile = $path . $this->basename($_FILES['data_file']['name']);
                $ext = substr(basename($uploadfile), strrpos(basename($uploadfile), '.') + 1);
                if ($ext != 'zip') {
                    $messages[] = 'Wrong file format';
                } elseif (!is_writeable(dirname($uploadfile))) {
                    $messages[] = 'Directory "' . $path . '" not writeable';
                } elseif (!is_uploaded_file($_FILES['data_file']['tmp_name']) || filesize($_FILES['data_file']['tmp_name']) == 0) {
                    $messages[] = 'File upload error';
                } elseif (move_uploaded_file($_FILES['data_file']['tmp_name'], $uploadfile)) {
                    $messages[] = 'File successfully uploaded';
                } else {
                    $messages[] = 'Cant upload file';
                }
            }
        }
        $this->view->filters = new \stdClass();
        $this->view->filters->search = Yii::$app->request->get('search', '');
        $this->view->filters->type = Yii::$app->request->get('type', '');
        $selected_root_directory_id = Yii::$app->request->get('set', 'selection');
        $directories = [];
        $directories[] = ['id' => 'selection', 'text' => TEXT_SELECTION, 'link' => Yii::$app->url_manager->create_url(['install/', 'set' => 'selection'])];
        $directories[] = ['id' => 'library', 'text' => TEXT_MY_LIB, 'link' => Yii::$app->url_manager->create_url(['install/', 'set' => 'library'])];
        $directories[] = ['id' => 'modules', 'text' => TEXT_INSTALLED, 'link' => Yii::$app->url_manager->create_url(['install/', 'set' => 'modules'])];
        /*$directories[] = [
              'id' => 'settings',
              'text' => TEXT_MY_SETTINGS,
              'link' => Yii::$app->urlManager->createUrl(['install/','set'=> 'settings']),
          ];*/
        $directories[] = ['id' => 'updates', 'text' => TEXT_SYSTEM_UPDATE, 'link' => Yii::$app->url_manager->create_url(['install/', 'set' => 'updates'])];
        $storage_url = \Yii::$app->params['appStorage.url'];
        $storage_key = $this->get_storage_key();
        $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
        if (!isset(\Yii::$app->params['secKey.global']) or \Yii::$app->params['secKey.global'] != $sec_key_global) {
            $message = defined('MESSAGE_KEY_DOMAIN_WANING') ? constant('MESSAGE_KEY_DOMAIN_WANING') : 'Warning: Security keys were generated for a different domain! Update required. Please change \'security store key\' to the actual value: %s.';
            if (strpos($message, '%s') === false) {
                $message .= ' (%s)';
            }
            $message = sprintf($message, $sec_key_global);
            $messages[] = $message;
        } elseif (empty($storage_key)) {
            $show_empty_key_intro = true;
            if ($request = curl_init()) {
                curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server');
                // for testing
                curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
                if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                    // Added in cURL 7.41.0
                    curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
                }
                curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
                curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
                $return = curl_exec($request);
                $response = curl_getinfo($request);
                curl_close($request);
                if ($response['http_code'] == 406) {
                    $result = json_decode($return, true);
                    if (isset($result['code']) && $result['code'] == 428) {
                        $owner_name = $result['message'];
                        $message = defined('MESSAGE_KEY_DOMAIN_INFO2') ? constant('MESSAGE_KEY_DOMAIN_INFO2') : 'This shop is already registered to %3$s and is not shared key to all administrators. You need to connect it using your own credentials.<br>
                                If your already registered with us and %3$s, approved your storage key, please insert \'storage\' key value. If you do not remember your \'storage\' key - please login at <a target="_blank" href="%1$s">application shop</a> with your credentials and copy it from there.<br>
                                If you not registered with us, please visit <a target="_blank" href="%1$s">application shop</a>, register your account and put there your \'security store key\'.<br>
                                You \'secutiry store key\' for this shop is [%2$s].<br>
                                After registration, wait for confirmation by %3$s. If this approve take a lot, you may e-mail him directly. After confirmation insert the received \'storage\' key (<a href="javascript:void(0);" onclick="$(\'.create_item_popup\').click();">use button on this page</a>) value.';
                        $messages[] = sprintf($message, $storage_url . 'account?return', $sec_key_global, $owner_name);
                        $show_empty_key_intro = false;
                    }
                }
            }
            if ($show_empty_key_intro) {
                $message = defined('MESSAGE_KEY_DOMAIN_INFO') ? constant('MESSAGE_KEY_DOMAIN_INFO') : 'It is looks like your store is not connected to our <a target="_blank" href="%1$s">application shop</a>.<br>If your already registered with us, please insert \'storage\' key value. If you do not remember your \'storage\' key - please login at <a target="_blank" href="%1$s">application shop</a> with your credentials and copy it from there.<br>If you not registered with us, please visit <a target="_blank" href="%1$s">application shop</a>, register your account and put there your \'security store key\'.<br>You \'secutiry store key\' for this shop is [%2$s].<br>After registration insert the received \'storage\' key (<a href="javascript:void(0);" onclick="$(\'.create_item_popup\').click();">use button on this page</a>) value.';
                $messages[] = sprintf($message, $storage_url . 'account?return', $sec_key_global);
            }
        } else if ($request = curl_init()) {
            curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server');
            // for testing
            curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
            if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                // Added in cURL 7.41.0
                curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
            }
            curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
            curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
            curl_exec($request);
            $response = curl_getinfo($request);
            curl_close($request);
            if ($response['http_code'] != 200) {
                $message = defined('MESSAGE_KEY_DOMAIN_ERROR') ? constant('MESSAGE_KEY_DOMAIN_ERROR') : 'Error: Your \'storage\' key is wrong. Please login at <a target="_blank" href="%1$s">application shop</a> with your credentials and copy it from there. You \'secutiry store key\' for this shop is [%2$s].';
                $messages[] = sprintf($message, $storage_url . 'account?return', $sec_key_global);
            }
        }
        $success = '';
        $types = [];
        if (($selected_root_directory_id == 'library' || $selected_root_directory_id == 'selection') && count($messages) == 0) {
            $message = defined('MESSAGE_KEY_DOMAIN_OK') ? constant('MESSAGE_KEY_DOMAIN_OK') : 'Your store successfully connected to our <a target="_blank" href="%1$s">application shop</a>. You \'secutiry store key\' for this shop is [%2$s].';
            $success = sprintf($message, $storage_url, $sec_key_global);
        }
        if (!empty($storage_url)) {
            $context = null;
            if (\common\helpers\System::is_development()) {
                $context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]]);
            }
            $response = @file_get_contents($storage_url . 'app-api-types.json', false, $context);
            $result = json_decode($response, true);
        }
        if (isset($result['types'])) {
            $types = $result['types'];
        }
        $platforms = [0 => TEXT_NONE, -1 => TEXT_ALL_PLATFORMS] + \yii\helpers\Array_Helper::map(\common\models\Platforms::find()->select(['platform_id', 'platform_name'])->where(['is_virtual' => 0, 'is_marketplace' => 0])->as_array()->all(), 'platform_id', 'platform_name');
        return $this->render('index', ['messages' => $messages, 'success' => $success, 'directories' => $directories, 'selectedRootDirectoryId' => $selected_root_directory_id, 'job_list_url' => Yii::$app->url_manager->create_url(['install/files-list']), 'store_list_url' => Yii::$app->url_manager->create_url(['install/store-list']), 'types' => $types, 'platforms' => $platforms]);
    }
    public function action_add_storage_key()
    {
        $this->layout = false;
        return $this->render('add-storage-key.tpl', ['storageKey' => $this->get_storage_key()]);
    }
    public function action_reset_storage_key()
    {
        global $login_id;
        $admin = \common\models\Admin::find_one($login_id);
        if ($admin instanceof \common\models\Admin) {
            $admin->storage_key = '';
            $admin->save(false);
        }
        return $this->redirect(Yii::$app->url_manager->create_url('install/'));
    }
    public function action_submit_storage_key()
    {
        $storekey = Yii::$app->request->post('storekey', '');
        $button = Yii::$app->request->post('button', '');
        if ($button == 'all') {
            \common\models\Admin::update_all(['storage_key' => $storekey]);
        } else {
            global $login_id;
            $admin = \common\models\Admin::find_one($login_id);
            if ($admin instanceof \common\models\Admin) {
                $admin->storage_key = $storekey;
                $admin->save(false);
            }
        }
        return $this->redirect(Yii::$app->url_manager->create_url('install/'));
    }
    public function action_store_list()
    {
        $start = (int) Yii::$app->request->post('start', 0);
        $length = (int) Yii::$app->request->post('length', 9);
        $type = \Yii::$app->request->post('type', '');
        $search = \Yii::$app->request->post('search', '');
        $sort = \Yii::$app->request->post('sort_by', '');
        $this->layout = false;
        $records_total = 0;
        $records_filtered = 0;
        $items = [];
        global $login_id;
        $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
        $storage_url = \Yii::$app->params['appStorage.url'];
        $storage_key = $this->get_storage_key();
        if (!isset(\Yii::$app->params['secKey.global']) or \Yii::$app->params['secKey.global'] != $sec_key_global) {
            // wrong security store key
        } elseif (empty($storage_key) || empty($storage_url)) {
            // wrong storage key or url
        } else {
            \common\models\Install_List_Cache::delete_all('date_added <= :date_added', [':date_added' => date('Y-m-d H:i:s', strtotime('- 1 hour'))]);
            $result = false;
            $cache = \common\models\Install_List_Cache::find()->where(['admin_id' => $login_id])->and_where(['offset' => $start])->and_where(['limit' => $length])->and_where(['type' => $type])->and_where(['search' => $search])->and_where(['sort' => $sort])->one();
            if ($cache instanceof \common\models\Install_List_Cache) {
                $result = json_decode(stripslashes($cache->return), true);
            } elseif ($request = curl_init()) {
                curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server/products');
                // for testing
                curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
                if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                    // Added in cURL 7.41.0
                    curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
                }
                curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
                curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
                $post_field = ['offset' => $start, 'limit' => $length, 'type' => $type, 'search' => $search, 'sort' => $sort];
                $post_field_array = json_encode($post_field);
                curl_setopt($request, CURLOPT_POSTFIELDS, $post_field_array);
                $return = curl_exec($request);
                $response = curl_getinfo($request);
                curl_close($request);
                if ($response['http_code'] == 200) {
                    if ($sort != 'installed') {
                        $cache = new \common\models\Install_List_Cache();
                        $cache->load_default_values();
                        $cache->set_attributes($post_field, false);
                        $cache->admin_id = $login_id;
                        $cache->return = tep_db_input($return);
                        $cache->date_added = date('Y-m-d H:i:s');
                        $cache->save(false);
                    }
                    $result = json_decode($return, true);
                }
            }
            if (isset($result['products'])) {
                $records_total = $result['total'];
                $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
                foreach ($result['products'] as $product) {
                    $deployed = 0;
                    // Install or Discover
                    if (!empty($product['filename']) && file_exists($path . $product['filename'])) {
                        $deployed = 1;
                        // Downloaded (Not installed)
                    }
                    $archive_version = (float) $product['archive_version'];
                    $archive_type = (string) $product['archive_type'];
                    $archive_class = (string) $product['archive_class'];
                    $check = \common\models\Installer::find()->select(['max(archive_version) as version'])->where(['archive_type' => $archive_type])->and_where(['archive_class' => $archive_class])->as_array()->one();
                    if (isset($check['version']) && $check['version'] == $archive_version) {
                        $deployed = 2;
                        // Installed
                    }
                    if (isset($check['version']) && $check['version'] < $archive_version) {
                        $deployed = 3;
                        // Update
                    }
                    $records_filtered++;
                    $product['deployed'] = $deployed;
                    $items[] = $product;
                }
            }
        }
        $pages = [];
        if ($records_total > $records_filtered) {
            for ($p = 0; $p < ceil($records_total / $length); $p++) {
                $pages[] = $p;
            }
        }
        return $this->render('store-list', ['items' => $items, 'module_list_url' => Yii::$app->url_manager->create_url(['install/', 'set' => 'modules']), 'pages' => $pages, 'start' => $start, 'length' => $length]);
    }
    public function action_upload_file_info()
    {
        $this->layout = false;
        $id = (int) Yii::$app->request->get('id', 0);
        if ($id > 0) {
            if ($request = curl_init()) {
                $storage_url = \Yii::$app->params['appStorage.url'];
                $storage_key = $this->get_storage_key();
                $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
                curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server/product-info');
                // for testing
                curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
                if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                    // Added in cURL 7.41.0
                    curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
                }
                curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
                curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
                $post_field_array = ['id' => $id];
                $post_field_array = json_encode($post_field_array);
                curl_setopt($request, CURLOPT_POSTFIELDS, $post_field_array);
                //$result = curl_exec($request);
                $result = json_decode(curl_exec($request), true);
                $response = curl_getinfo($request);
                curl_close($request);
                if ($response['http_code'] == 200) {
                    $packages_selected_list = $result['packagesSelectedList'];
                    $ready_for_install = $result['readyForInstall'];
                    $platform_selection = $result['platformSelection'];
                    $acl_selection = $result['aclSelection'];
                    $packages_depended_list = $result['packagesDependedList'];
                    \common\helpers\Translation::init('admin/modules');
                    $platforms = [0 => TEXT_NONE, -1 => TEXT_ALL_PLATFORMS] + \yii\helpers\Array_Helper::map(\common\models\Platforms::find()->select(['platform_id', 'platform_name'])->where(['is_virtual' => 0, 'is_marketplace' => 0])->as_array()->all(), 'platform_id', 'platform_name');
                    return $this->render('upload-file-info', ['id' => $id, 'packagesSelectedList' => $packages_selected_list, 'readyForInstall' => $ready_for_install, 'platformSelection' => $platform_selection, 'aclSelection' => $acl_selection, 'packagesDependedList' => $packages_depended_list, 'platforms' => $platforms]);
                }
            }
        }
        echo 'Failed to download application.';
    }
    public function action_upload_file()
    {
        $this->layout = false;
        $status = 'fail';
        $id = (int) Yii::$app->request->post('id', 0);
        $this->deploy_log = [];
        if ($id > 0) {
            $this->reset_re_cache_flags();
            if ($file = $this->get_file_with_dependencies('id', $id)) {
                $platform_id = (int) Yii::$app->request->post('platform', 0);
                $acl = (string) Yii::$app->request->post('acl', '');
                $ready_for_install = (int) Yii::$app->request->post('readyForInstall', 0);
                if ($ready_for_install) {
                    if ($this->install_file_with_dependencies($file, ['platform_id' => $platform_id, 'acl' => $acl])) {
                        $status = 'success';
                    }
                    $depended = (array) Yii::$app->request->post('depended', []);
                    if (is_array($depended)) {
                        foreach ($depended as $depid) {
                            if ($subfile = $this->get_file_with_dependencies('id', $depid)) {
                                $this->install_file_with_dependencies($subfile, ['platform_id' => $platform_id, 'acl' => $acl]);
                            }
                        }
                    }
                }
                $this->run_system_re_cache();
            }
        }
        $upload_info = implode('<br>', $this->deploy_log);
        if ($status == 'success') {
            $packages_synergy_list = [];
            if ($request = curl_init()) {
                $storage_url = \Yii::$app->params['appStorage.url'];
                $storage_key = $this->get_storage_key();
                $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
                curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server/product-synergy');
                // for testing
                curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
                if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                    // Added in cURL 7.41.0
                    curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
                }
                curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
                curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
                $post_field_array = ['id' => $id];
                $post_field_array = json_encode($post_field_array);
                curl_setopt($request, CURLOPT_POSTFIELDS, $post_field_array);
                //$result = curl_exec($request);
                $result = json_decode(curl_exec($request), true);
                $response = curl_getinfo($request);
                curl_close($request);
                if ($response['http_code'] == 200) {
                    $packages_synergy_list = $result['packagesSynergyList'];
                }
            }
            $ext_class = $this->ext_class;
            unset($this->ext_class);
            return $this->render('upload-file-success', ['message' => APP_INSTALL_OK, 'uploadInfo' => $upload_info, 'packagesSynergyList' => $packages_synergy_list, 'extClass' => $ext_class ?? null]);
        } else {
            echo $upload_info . '<br>';
            echo APP_INSTALL_FAIL;
        }
        /*Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
          Yii::$app->response->data = [
              'status' => $status,
          ];*/
    }
    public function action_files_list()
    {
        $this->layout = false;
        $formatter = new \yii\i18n\Formatter();
        $version = defined('MIGRATIONS_DB_REVISION') ? MIGRATIONS_DB_REVISION : '';
        $records_total = 0;
        $records_filtered = 0;
        $start = (int) Yii::$app->request->get('start', 0);
        $length = (int) Yii::$app->request->get('length', 25);
        $search_word = '';
        $search_array = Yii::$app->request->get('search');
        if (is_array($search_array) && isset($search_array['value']) && !empty($search_array['value'])) {
            $search_word = tep_db_prepare_input($search_array['value']);
        }
        $files = [];
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        if ($dir = @dir($path)) {
            while ($file = $dir->read()) {
                if (!empty($search_word)) {
                    if (false === stripos($file, $search_word)) {
                        continue;
                    }
                }
                $ext = substr($file, strrpos($file, '.') + 1);
                if ($ext == 'zip') {
                    $deployed = false;
                    $can_deploy = true;
                    $type = 'unknown';
                    $dclass = '';
                    $dtype = '';
                    $app_name = '';
                    $req = '';
                    $choose_platform = 0;
                    $can_revert = false;
                    $can_delete = true;
                    $zip = new \Zip_Archive();
                    if ($zip->open($path . $file) === true) {
                        $json = $zip->get_from_name('distribution.json');
                        $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
                        if (!empty($json)) {
                            $distribution = json_decode($json);
                            $dtype = (string) ($distribution->type ?? '');
                            $dclass = (string) ($distribution->class ?? '');
                            $app_name = (string) ($distribution->name ?? '');
                            $type = '<div class="ord-location">' . $distribution->type . '<div class="ord-total-info ord-location-info"><div class="ord-box-img"></div><b>' . \yii\helpers\Html::encode($distribution->name) . '</b>' . \yii\helpers\Html::encode($distribution->description) . '<br>Vesion: ' . \yii\helpers\Html::encode($distribution->version) . '<br>' . '</div></div>';
                            if (isset($distribution->require->version)) {
                                $version_applicable = $distribution->require->version_applicable ?? 'equal';
                                switch ($version_applicable) {
                                    case 'equal':
                                        if ($version == $distribution->require->version) {
                                            $req .= '<p style="color:green">Version: ' . \yii\helpers\Html::encode($distribution->require->version) . '</p>';
                                        } else {
                                            $req .= '<p style="color:red">Version: ' . \yii\helpers\Html::encode($distribution->require->version) . '</p>';
                                            $can_deploy = false;
                                        }
                                        break;
                                    case 'greater-equal':
                                        if (intval($version) >= intval($distribution->require->version)) {
                                            $req .= '<p style="color:green">Version: ' . \yii\helpers\Html::encode($distribution->require->version) . ' or greater</p>';
                                        } else {
                                            $req .= '<p style="color:red">Version: ' . \yii\helpers\Html::encode($distribution->require->version) . ' or greater</p>';
                                            $can_deploy = false;
                                        }
                                        break;
                                    case 'less-equal':
                                        if (intval($version) <= intval($distribution->require->version)) {
                                            $req .= '<p style="color:green">Version: ' . \yii\helpers\Html::encode($distribution->require->version) . ' or less</p>';
                                        } else {
                                            $req .= '<p style="color:red">Version: ' . \yii\helpers\Html::encode($distribution->require->version) . ' or less</p>';
                                            $can_deploy = false;
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                            if (isset($distribution->require->modules) && is_array($distribution->require->modules)) {
                                foreach ($distribution->require->modules as $subfile) {
                                    $record = \common\models\Installer::find()->where(['filename' => $subfile])->one();
                                    if ($record instanceof \common\models\Installer || self::is_known_require_module($subfile)) {
                                        $req .= '<p style="color:green">' . $subfile . '</p>';
                                    } elseif (is_file($path . $subfile)) {
                                        $req .= '<p style="color:yellow">' . $subfile . '</p>';
                                    } else {
                                        $req .= '<p style="color:red">' . $subfile . '</p>';
                                        $can_deploy = false;
                                    }
                                    unset($record);
                                }
                            }
                            if (isset($distribution->require->classes) && is_array($distribution->require->classes)) {
                                foreach ($distribution->require->classes as $classversion) {
                                    $record_query = \common\models\Installer::find()->where(['archive_class' => $classversion->name]);
                                    $cv = '';
                                    if (isset($classversion->min)) {
                                        list($major, $minor, $patch) = array_pad(explode('.', (string) $classversion->min), 3, 0);
                                        $archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
                                        $record_query->and_where(['>=', 'archive_version', $archive_version]);
                                        $cv .= ', v.' . $classversion->min . ' or greater';
                                    }
                                    if (isset($classversion->max)) {
                                        list($major, $minor, $patch) = array_pad(explode('.', (string) $classversion->max), 3, 0);
                                        $archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
                                        $record_query->and_where(['<=', 'archive_version', $archive_version]);
                                        $cv .= ', v.' . $classversion->max . ' or less';
                                    }
                                    $record = $record_query->one();
                                    if ($record instanceof \common\models\Installer) {
                                        $req .= '<p style="color:green">' . $classversion->name . $cv . '</p>';
                                    } else {
                                        $req .= '<p style="color:red">' . $classversion->name . $cv . '</p>';
                                        $can_deploy = false;
                                    }
                                    unset($record);
                                }
                            }
                            if (isset($distribution->require->platform) && (string) $distribution->require->platform == 'True') {
                                $choose_platform = 1;
                            }
                        }
                        if ($dtype == 'translate') {
                            $choose_platform = 0;
                            $json = $zip->get_from_name('locale.json');
                            $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
                            if (!empty($json)) {
                                $locale = json_decode($json);
                                $lang = \common\models\Languages::find()->and_where(['code' => (string) $locale->code])->one();
                                if ($lang instanceof \common\models\Languages) {
                                    $choose_platform = 3;
                                } else {
                                    $choose_platform = 2;
                                }
                            }
                        }
                        //$zip->extractTo($path);
                        $zip->close();
                    }
                    $record = \common\models\Installer::find()->where(['filename' => $file])->one();
                    if ($record instanceof \common\models\Installer) {
                        $deployed = true;
                    }
                    $file_name_cell = '<div style="white-space: nowrap"><a href="' . Yii::$app->url_manager->create_url(['install/download-file', 'name' => $file]) . '" target="_blank"><i class="' . 'icon-upload' . '"></i></a> ' . $file . '</div>';
                    switch ($dtype) {
                        case 'extension':
                        case 'design':
                        case 'translate':
                        case 'analytic':
                        case 'payment':
                        case 'shipping':
                        case 'totals':
                        case 'label':
                        case 'samples':
                        case 'configuration':
                        case 'system':
                            //                            $canDeploy = true;
                            if ($deployed) {
                                $can_revert = true;
                                $can_delete = false;
                            }
                            break;
                        case 'update':
                            //                            $canDeploy = true;
                            if ($deployed) {
                                if ((string) $distribution->version == MIGRATIONS_DB_REVISION) {
                                    $can_revert = true;
                                    $can_delete = false;
                                }
                            }
                            break;
                        default:
                            $can_deploy = false;
                            break;
                    }
                    if ($can_deploy) {
                        list($major, $minor, $patch) = array_pad(explode('.', (string) $distribution->version), 3, 0);
                        $archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
                        $check = \common\models\Installer::find()->select(['max(archive_version) as version'])->where(['archive_type' => $dtype])->and_where(['archive_class' => $dclass])->as_array()->one();
                        if (isset($check['version']) && $check['version'] > $archive_version) {
                            $can_deploy = false;
                        }
                        if (isset($check['version']) && $check['version'] != $archive_version) {
                            $can_revert = false;
                        }
                    }
                    $file_row = [
                        \common\helpers\Date::datetime_short(date('Y-m-d H:i:s', filemtime($path . $file))),
                        $file_name_cell,
                        //$formatter->asShortSize(filesize($path . $file), 3),
                        $app_name,
                        $type,
                        $req,
                        $deployed ? '<span style="color:green;">deployed</span>' : '<span style="white-space: nowrap;color:red;">not deployed</span>',
                        '<div class="job-actions">' . ($can_delete ? '<a class="job-button" href="javascript:void(0);" onclick="return file_remove(\'' . $file . '\');"><i class="icon-trash iconTrash"></i></a>' : '') . (!$deployed && $can_deploy ? '<a class="job-button" href="javascript:void(0);" onclick="return file_deploy(\'' . $file . '\', \'' . $choose_platform . '\');"><i class="icon-plus-sign iconPlusSign"></i></a>' : '') . ($can_revert ? '<a class="job-button" href="javascript:void(0);" onclick="return file_revert(\'' . $file . '\');"><i class="icon-remove-sign iconRemoveSign"></i></a>' : '') . '</div>',
                    ];
                    $files[filemtime($path . $file) . '_' . $records_total] = $file_row;
                    $records_total++;
                    $records_filtered++;
                }
            }
        }
        krsort($files);
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        Yii::$app->response->data = ['data' => array_values($files), 'recordsTotal' => $records_total, 'recordsFiltered' => $records_filtered];
    }
    public function action_deploy_file()
    {
        $this->deploy_log = [];
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $status = 'error';
        $filename = Yii::$app->request->post('name', '');
        $platform_id = (int) Yii::$app->request->post('platform', 0);
        $locale = (int) Yii::$app->request->post('locale', 0);
        $this->reset_re_cache_flags();
        if ($this->install_file_with_dependencies($filename, ['platform_id' => $platform_id, 'locale' => $locale])) {
            $status = 'ok';
            $this->run_system_re_cache();
        }
        $message = implode('<br>', $this->deploy_log);
        if (!empty($this->ext_class)) {
            if ($menu = \common\helpers\Menu_Helper::get_extension_html_menu($this->ext_class, false, 'extension-menu-item mt-1')) {
                $message .= '<br><br><div class="extensions-menu-title"><b>' . TEXT_MENU_STRUCTURE . ':</b></div>' . $menu;
            }
        }
        unset($this->ext_class);
        Yii::$app->response->data = ['status' => $status, 'text' => $message];
    }
    public function action_revert_file()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR;
        $filename = Yii::$app->request->post('name', '');
        ob_start();
        $zip = new \Zip_Archive();
        if ($zip->open($path . 'uploads' . DIRECTORY_SEPARATOR . $filename) === true) {
            $json = $zip->get_from_name('distribution.json');
            $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
            if (!empty($json)) {
                $distribution = json_decode($json);
                $this->reset_re_cache_flags();
                $status = 'fail';
                switch ($distribution->type) {
                    case 'extension':
                        // Extension
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $old_data = unserialize($record->data);
                            $path_p = $path . 'lib' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'extensions';
                            if (isset($distribution->class)) {
                                $class = (string) $distribution->class;
                                $two_recs = \common\models\Installer::find()->where(['archive_class' => $record->archive_class])->order_by(['archive_version' => SORT_DESC])->limit(2)->all();
                                $prev_ver = count($two_recs) == 2 ? $two_recs[1]->archive_version : null;
                                if (!class_exists($class)) {
                                    if ($ext = \common\helpers\Acl::check_extension($class, 'always')) {
                                        $class = $ext;
                                    }
                                }
                                $this->do_uninstall_class($class, 0, $prev_ver);
                            }
                            $this->revert_file_dst($old_data, $filename, $path_p, true);
                            $record->delete();
                            $this->do_system = true;
                            $this->do_hooks = true;
                            $this->do_menu = true;
                            $status = 'success';
                        }
                        break;
                    case 'design':
                        // Design
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $theme_name = \common\classes\design::page_name($distribution->name);
                            \backend\design\Theme::theme_remove($theme_name, true);
                            $old_data = unserialize($record->data);
                            if (isset($old_data['id'])) {
                                \common\models\Platforms_To_Themes::delete_all(['platform_id' => (int) $old_data['id']]);
                            }
                            if (isset($old_data['platforms_to_themes'])) {
                                foreach ($old_data['platforms_to_themes'] as $platforms_to_themes) {
                                    $p2t = \common\models\Platforms_To_Themes::find()->where(['platform_id' => $platforms_to_themes['platform_id'], 'theme_id' => $platforms_to_themes['theme_id']])->one();
                                    if ($p2t instanceof \common\models\Platforms_To_Themes) {
                                        $p2t->is_default = $platforms_to_themes['is_default'] ?? 0;
                                    } else {
                                        $p2t = new \common\models\Platforms_To_Themes();
                                        $p2t->load_default_values();
                                        $p2t->set_attributes($platforms_to_themes, false);
                                    }
                                    $p2t->save(false);
                                }
                            }
                            $record->delete();
                            $status = 'success';
                        }
                        break;
                    case 'translate':
                        // Translations
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $old_data = unserialize($record->data);
                            if (is_array($old_data)) {
                                foreach ($old_data as $old) {
                                    switch ($old['action']) {
                                        case 'deletelanguage':
                                            if (isset($old['language_id'])) {
                                                \common\helpers\Language::drop_language($old['language_id']);
                                            }
                                            break;
                                        case 'update':
                                            \common\models\Translation::update_all(['translation_value' => $old['translation_value'], 'translated' => $old['translated']], ['hash' => $old['hash'], 'language_id' => $old['language_id']]);
                                            break;
                                        case 'delete':
                                            \common\models\Translation::delete_all(['hash' => $old['hash'], 'language_id' => $old['language_id']]);
                                            break;
                                        default:
                                            break;
                                    }
                                }
                            }
                            $record->delete();
                            $this->do_system = true;
                            $status = 'success';
                        }
                        break;
                    case 'payment':
                        // Payment
                        $module_dir = 'orderPayment';
                    // no break
                    case 'shipping':
                        // Shipping
                        if (empty($module_dir)) {
                            $module_dir = 'orderShipping';
                        }
                    // no break
                    case 'analytic':
                        // Payment
                        if (empty($module_dir)) {
                            $module_dir = 'analytic';
                        }
                    // no break
                    case 'totals':
                        // Order structure
                        if (empty($module_dir)) {
                            $module_dir = 'orderTotal';
                        }
                    // no break
                    case 'label':
                        // Shipping label
                        if (empty($module_dir)) {
                            $module_dir = 'label';
                        }
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $old_data = unserialize($record->data);
                            $path_p = $path . 'lib' . DIRECTORY_SEPARATOR . 'common' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR . $module_dir;
                            if (isset($distribution->class)) {
                                $class = (string) $distribution->class;
                                foreach (\common\models\Platforms::find()->select(['platform_id'])->as_array()->all() as $_platform) {
                                    $this->do_uninstall_class($class, $_platform['platform_id']);
                                    $this->do_recalc_module_sort('delete', $class, $distribution->type, $_platform['platform_id']);
                                }
                            }
                            $this->revert_file_dst($old_data, $filename, $path_p, true);
                            $record->delete();
                            $this->do_system = true;
                            $status = 'success';
                        }
                        break;
                    case 'samples':
                        // Sample data
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $old_data = unserialize($record->data);
                            foreach ($old_data as $action => $old) {
                                switch ($action) {
                                    case 'catalog_categories':
                                        $sdn = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed');
                                        foreach (\common\models\Categories_Description::find()->select('categories_id')->where(['IN', 'categories_seo_page_name', $old])->group_by('categories_id')->as_array()->all() as $category) {
                                            \common\helpers\Categories::remove_category($category['categories_id'], false);
                                            if ($sdn) {
                                                $sdn::delete_category_links($category['categories_id']);
                                            }
                                        }
                                        break;
                                    case 'catalog_products':
                                        $sdn = \common\helpers\Acl::check_extension_allowed('SeoRedirectsNamed', 'allowed');
                                        foreach (\common\models\Products::find()->select('products_id')->where(['IN', 'products_model', $old])->as_array()->all() as $product) {
                                            \common\helpers\Product::remove_product($product['products_id']);
                                            if ($sdn) {
                                                $sdn::delete_product_links($product['products_id']);
                                            }
                                        }
                                        break;
                                    default:
                                        break;
                                }
                            }
                            $record->delete();
                            if (USE_CACHE == 'true') {
                                \common\helpers\System::reset_cache_block('categories');
                                \common\helpers\System::reset_cache_block('also_purchased');
                            }
                            $status = 'success';
                        }
                        break;
                    case 'system':
                    case 'update':
                        // System update
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $old_data = unserialize($record->data);
                            $this->revert_file_dst($old_data, $filename, $path, true);
                            $record->delete();
                            $this->do_migrations = true;
                            $this->do_system = true;
                            $this->do_smarty = true;
                            //$this->doTheme = true;
                            $this->do_hooks = true;
                            $this->do_menu = true;
                            $status = 'success';
                            if ($distribution->type == 'update') {
                                \common\models\Configuration::update_all(['configuration_value' => (string) $distribution->require->version], ['configuration_key' => 'MIGRATIONS_DB_REVISION']);
                            }
                        }
                        break;
                    case 'configuration':
                        $record = \common\models\Installer::find()->where(['filename' => $filename])->one();
                        if ($record instanceof \common\models\Installer) {
                            $old_data = unserialize($record->data);
                            if (is_array($old_data)) {
                                foreach ($old_data as $old) {
                                    switch ($old['action']) {
                                        case 'add':
                                            $conf = \common\models\Configuration::find()->where(['configuration_key' => $old['configuration_key']])->one();
                                            if (!$conf instanceof \common\models\Configuration) {
                                                $conf = new \common\models\Configuration();
                                                $conf->load_default_values();
                                                $conf->configuration_title = $old['configuration_title'];
                                                $conf->configuration_key = $old['configuration_key'];
                                                $conf->configuration_value = $old['configuration_value'];
                                                $conf->configuration_description = $old['configuration_description'];
                                                $conf->configuration_group_id = $old['configuration_group_id'];
                                                $conf->sort_order = $old['sort_order'];
                                                $conf->last_modified = $old['last_modified'];
                                                $conf->date_added = $old['date_added'];
                                                $conf->use_function = $old['use_function'];
                                                $conf->set_function = $old['set_function'];
                                                $conf->save(false);
                                            }
                                            break;
                                        case 'update':
                                            $conf = \common\models\Configuration::find()->where(['configuration_key' => $old['configuration_key']])->one();
                                            if ($conf instanceof \common\models\Configuration) {
                                                $conf->configuration_value = $old['configuration_value'];
                                                $conf->configuration_group_id = $old['configuration_group_id'];
                                                $conf->save(false);
                                            }
                                            break;
                                        case 'delete':
                                            $conf = \common\models\Configuration::find()->where(['configuration_key' => $old['configuration_key']])->one();
                                            if ($conf instanceof \common\models\Configuration) {
                                                $conf->delete();
                                            }
                                            break;
                                        default:
                                            break;
                                    }
                                }
                            }
                            $record->delete();
                            $this->do_system = true;
                            $status = 'success';
                        }
                        break;
                    default:
                        $status = 'fail';
                        break;
                }
                $this->run_system_re_cache();
            }
            $zip->close();
        }
        $output = ob_get_clean();
        if ($status == 'success') {
            Yii::$app->response->data = ['status' => 'ok', 'text' => $output . "<br>File {$filename} reverted."];
        } else {
            Yii::$app->response->data = ['status' => 'error', 'text' => $output . "<br>Can't revert file {$filename}."];
        }
    }
    public function action_remove_file()
    {
        $this->layout = false;
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $filename = Yii::$app->request->post('name', '');
        $filename = \common\helpers\Output::mb_basename($filename);
        if (is_file($path . $filename)) {
            @unlink($path . $filename);
            Yii::$app->response->data = ['status' => 'ok', 'text' => "File {$filename} removed."];
        } else {
            Yii::$app->response->data = ['status' => 'error', 'text' => "Can't remove file {$filename}."];
        }
    }
    public function action_download_file()
    {
        $this->layout = false;
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $filename = Yii::$app->request->get('name', '');
        $filename = \common\helpers\Output::mb_basename($filename);
        $mime_type = \yii\helpers\File_Helper::get_mime_type_by_extension($path . $filename);
        header('Content-Type: ' . $mime_type);
        header('Expires: ' . gmdate('D, d M Y H:i:s') . ' GMT');
        header('Content-Disposition: attachment; filename="' . urlencode($filename) . '"');
        header('Pragma: no-cache');
        readfile($path . $filename);
        die;
    }
    /**
     * Settings
     */
    public function action_upload()
    {
        if (isset($_FILES['file']['tmp_name'])) {
            $xmlfile = file_get_contents($_FILES['file']['tmp_name']);
            $ob = simplexml_load_string($xmlfile);
            if (isset($ob->Menu)) {
                $ob_prepared = \common\helpers\Menu_Helper::prepare_admin_tree($ob->Menu, []);
                tep_db_query('TRUNCATE TABLE admin_boxes;');
                \common\helpers\Menu_Helper::import_admin_tree($ob_prepared);
            }
            if (isset($ob->Groups->item)) {
                foreach ($ob->Groups->item as $item) {
                    $al = \common\models\Access_Levels::find()->select(['access_levels_id'])->where(['access_levels_name' => (string) $item->Name])->one();
                    if (!is_object($al)) {
                        $al = new \common\models\Access_Levels();
                        $al->access_levels_name = (string) $item->Name;
                    }
                    if (is_object($al)) {
                        $selected_ids = [];
                        foreach ($item->Acl->item as $key) {
                            $acl = \common\models\Access_Control_List::find()->where(['access_control_list_key' => (string) $key])->one();
                            if (is_object($acl)) {
                                $selected_ids[] = $acl->access_control_list_id;
                            }
                        }
                        if (count($selected_ids) > 0) {
                            $access_levels_persmissions = implode(',', $selected_ids);
                        } else {
                            $access_levels_persmissions = '';
                        }
                        $al->access_levels_persmissions = $access_levels_persmissions;
                        $al->save();
                    }
                }
            }
            if (isset($ob->Members->item)) {
                foreach ($ob->Members->item as $item) {
                    $admin = false;
                    if (isset($item->id)) {
                        $admin = \common\models\Admin::find()->where(['admin_id' => (int) $item->id])->one();
                    }
                    if (!is_object($admin)) {
                        $admin = new \common\models\Admin();
                    }
                    $admin->admin_username = (string) $item->username;
                    $admin->admin_firstname = (string) $item->firstname;
                    $admin->admin_lastname = (string) $item->lastname;
                    $admin->admin_email_address = (string) $item->email;
                    $admin->admin_phone_number = (string) $item->phone;
                    $admin->languages = (string) $item->languages;
                    $admin->access_levels_id = (int) $item->group;
                    $persmissions = [];
                    if (isset($item->persmissions->include)) {
                        foreach ($item->persmissions->include as $key) {
                            $acl_item = \common\models\Access_Control_List::find()->select(['access_control_list_id'])->where(['access_control_list_key' => (string) $key])->as_array()->one();
                            if (isset($acl_item['access_control_list_id'])) {
                                $persmissions[] = $acl_item['access_control_list_id'];
                            }
                        }
                    }
                    if (isset($item->persmissions->exclude)) {
                        foreach ($item->persmissions->exclude as $key) {
                            $acl_item = \common\models\Access_Control_List::find()->select(['access_control_list_id'])->where(['access_control_list_key' => (string) $key])->as_array()->one();
                            if (isset($acl_item['access_control_list_id'])) {
                                $persmissions[] = $acl_item['access_control_list_id'] * -1;
                            }
                        }
                    }
                    $admin_persmissions = '';
                    if (count($persmissions) > 0) {
                        $admin_persmissions = implode(',', $persmissions);
                    }
                    $admin->admin_persmissions = $admin_persmissions;
                    $admin->save();
                }
            }
            unlink($_FILES['file']['tmp_name']);
        }
    }
    public function action_download()
    {
        $this->layout = false;
        $response = [];
        $xml = new \yii\web\Xml_Response_Formatter();
        $xml->root_tag = 'Install';
        Yii::$app->response->format = 'custom_xml';
        Yii::$app->response->formatters['custom_xml'] = $xml;
        $headers = Yii::$app->response->headers;
        $headers->add('Content-Type', 'text/xml; charset=utf-8');
        $headers->add('Content-Disposition', 'attachment; filename="install.xml"');
        $headers->add('Pragma', 'no-cache');
        $menu = (int) Yii::$app->request->post('menu');
        $groups = (int) Yii::$app->request->post('groups');
        $members = (int) Yii::$app->request->post('members');
        if ($menu == 1) {
            $query_response = \common\models\Admin_Boxes::find()->order_by(['sort_order' => SORT_ASC])->as_array()->all();
            $response['Menu'] = $this->build_xml_tree(0, $query_response, []);
        }
        if ($groups == 1) {
            $Groups = [];
            foreach (\common\models\Access_Levels::find()->all() as $acl) {
                $selected_ids = [];
                if (is_string($acl->access_levels_persmissions)) {
                    $selected_ids = explode(',', $acl->access_levels_persmissions);
                }
                if (!is_array($selected_ids)) {
                    $selected_ids = [];
                }
                $acl_list = \common\models\Access_Control_List::find()->select(['access_control_list_key'])->where(['IN', 'access_control_list_id', $selected_ids])->order_by('sort_order')->as_array()->all();
                $acl_rules = [];
                foreach ($acl_list as $item) {
                    $acl_rules[] = $item['access_control_list_key'];
                }
                $Groups[] = ['Name' => $acl->access_levels_name, 'Acl' => $acl_rules];
            }
            $response['Groups'] = $Groups;
        }
        if ($members == 1) {
            $members_list = \common\models\Admin::find()->as_array()->all();
            $Members = [];
            foreach ($members_list as $item) {
                $persmissions = ['include' => [], 'exclude' => []];
                $admin_persmissions = explode(',', $item['admin_persmissions']);
                foreach ($admin_persmissions as $ap) {
                    if ($ap > 0) {
                        $acl_item = \common\models\Access_Control_List::find()->select(['access_control_list_key'])->where(['access_control_list_id' => $ap])->as_array()->one();
                        if (isset($acl_item['access_control_list_key'])) {
                            $persmissions['include'][] = $acl_item['access_control_list_key'];
                        }
                    } elseif ($ap < 0) {
                        $acl_item = \common\models\Access_Control_List::find()->select(['access_control_list_key'])->where(['access_control_list_id' => $ap * -1])->as_array()->one();
                        if (isset($acl_item['access_control_list_key'])) {
                            $persmissions['exclude'][] = $acl_item['access_control_list_key'];
                        }
                    }
                }
                $Members[] = ['id' => $item['admin_id'], 'username' => $item['admin_username'], 'firstname' => $item['admin_firstname'], 'lastname' => $item['admin_lastname'], 'email' => $item['admin_email_address'], 'phone' => $item['admin_phone_number'], 'languages' => $item['languages'], 'group' => $item['access_levels_id'], 'persmissions' => $persmissions];
            }
            $response['Members'] = $Members;
        }
        return $response;
    }
    public function action_updates()
    {
        \common\helpers\Translation::init('admin/install');
        $this->layout = false;
        $this->check_system_requires();
        $updates = [];
        $version = defined('MIGRATIONS_DB_REVISION') ? MIGRATIONS_DB_REVISION : '';
        $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
        $storage_url = \Yii::$app->params['appStorage.url'];
        $storage_key = $this->get_storage_key();
        if (!isset(\Yii::$app->params['secKey.global']) or \Yii::$app->params['secKey.global'] != $sec_key_global) {
            // wrong security store key
        } elseif (empty($storage_key) || empty($storage_url)) {
            // wrong storage key or url
        } elseif ($request = curl_init()) {
            curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server/system-updates');
            // for testing
            curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
            if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                // Added in cURL 7.41.0
                curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
            }
            curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
            curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
            $post_field_array = ['version' => $version];
            $post_field_array = json_encode($post_field_array);
            curl_setopt($request, CURLOPT_POSTFIELDS, $post_field_array);
            $result = json_decode(curl_exec($request), true);
            $response = curl_getinfo($request);
            curl_close($request);
            if ($response['http_code'] == 200 && isset($result['updates'])) {
                foreach ($result['updates'] as $item) {
                    $updates[] = $item;
                }
            }
        }
        $updates_count = \common\models\Installer::find()->where(['archive_type' => 'update'])->count();
        $installed = defined('INSTALLED_DATE') ? INSTALLED_DATE : '';
        $updated = defined('UPDATED_DATE') ? UPDATED_DATE : '';
        return $this->render('update-list', ['installed' => $installed, 'version' => PROJECT_VERSION_MAJOR . '.' . PROJECT_VERSION_MINOR . '.' . $version . (!empty($updated) ? ' updated at ' . $updated : ''), 'updates' => $updates, 'updatesCount' => $updates_count]);
    }
    public function action_update_log()
    {
        \common\helpers\Translation::init('admin/install');
        $this->layout = false;
        $response_log = [];
        foreach (\common\models\Installer::find()->select(['filename', 'date_added', 'archive_version', 'data'])->where(['archive_type' => 'update'])->order_by('archive_version ASC')->as_array()->all() as $update) {
            $response_log[] = $update['date_added'] . " <font color='green'>" . TEXT_UPDATE_APPLIED . ' ' . $update['filename'] . "</font><br>\n";
            $data = unserialize($update['data']);
            if (is_array($data)) {
                foreach ($data as $item) {
                    $response_log[] = $update['date_added'] . ' ' . $item->action . ' ' . $item->type . ' ' . str_replace('|', DIRECTORY_SEPARATOR, $item->path);
                }
            }
        }
        return $this->render('update-log', ['responseLog' => $response_log]);
    }
    private function send_echo($string)
    {
        echo $string;
        ob_flush();
        flush();
    }
    public function action_save_ignore_list()
    {
        \common\models\Install_Ignore_List::delete_all();
        $dst_file_ignore = Yii::$app->request->post('dst_file_ignore');
        if (is_array($dst_file_ignore)) {
            foreach ($dst_file_ignore as $index => $value) {
                if (!empty($value)) {
                    $file = new \common\models\Install_Ignore_List();
                    $file->id = $index;
                    $file->path = $value;
                    $file->save(false);
                }
            }
        }
        \Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
        return ['status' => 'ok'];
    }
    public function action_update_now()
    {
        $this->check_system_requires();
        @set_time_limit(0);
        @ignore_user_abort(true);
        $force = (int) Yii::$app->request->get('force');
        if ($force) {
            $this->dst_file_ignore = [];
            foreach (\common\models\Install_Ignore_List::find()->as_array()->all() as $file) {
                $this->dst_file_ignore[] = $file['path'];
            }
        } else {
            $this->show_ignore_field = true;
        }
        try {
            \common\models\Install_Ignore_List::delete_all();
        } catch (\Exception $exc) {
            $this->send_echo_for_update('Exception: ' . $exc->get_message(), 'error');
        }
        $this->layout = false;
        header('Content-Type: text/html');
        header('Content-Transfer-Encoding: utf-8');
        header('Pragma: no-cache');
        $conf = \common\models\Configuration::find()->where(['configuration_key' => 'MIGRATIONS_DB_REVISION'])->one();
        if ($conf instanceof \common\models\Configuration) {
            $version = $conf->configuration_value;
        } else {
            $version = '';
        }
        $sec_key_global = md5(\Yii::$app->db->dsn . (defined('INSTALLED_MICROTIME') ? INSTALLED_MICROTIME : ''));
        $storage_url = \Yii::$app->params['appStorage.url'];
        $storage_key = $this->get_storage_key();
        if (!isset(\Yii::$app->params['secKey.global']) or \Yii::$app->params['secKey.global'] != $sec_key_global) {
            // wrong security store key
        } elseif (empty($storage_key) || empty($storage_url)) {
            // wrong storage key or url
        } else {
            $need_re_cache = false;
            while (!empty($version)) {
                if ($request = curl_init()) {
                    $this->send_echo_for_update(TEXT_CHECK_UPDATES . " {$version}");
                    curl_setopt($request, CURLOPT_URL, $storage_url . 'app-api-server/get-update');
                    // for testing
                    curl_setopt($request, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($request, CURLOPT_SSL_VERIFYHOST, false);
                    if (defined('CURLOPT_SSL_VERIFYSTATUS')) {
                        // Added in cURL 7.41.0
                        curl_setopt($request, CURLOPT_SSL_VERIFYSTATUS, false);
                    }
                    curl_setopt($request, CURLOPT_TIMEOUT_MS, 30000);
                    curl_setopt($request, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($request, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json', 'Authorization: Bearer ' . $storage_key . ':' . $sec_key_global]);
                    $post_field_array = ['version' => $version];
                    $post_field_array = json_encode($post_field_array);
                    curl_setopt($request, CURLOPT_POSTFIELDS, $post_field_array);
                    $result = json_decode(curl_exec($request), true);
                    $response = curl_getinfo($request);
                    curl_close($request);
                    //$version = '';
                    if ($response['http_code'] == 200 && isset($result['content'])) {
                        $path = Yii::get_alias('@site_root');
                        $filename = $result['filename'] ?? '';
                        if (!file_exists($path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $filename)) {
                            $content = base64_decode($result['content']);
                            $size = $result['size'] ?? 0;
                            if (strlen($content) == $size) {
                                file_put_contents($path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $filename, $content);
                                $this->send_echo_for_update(TEXT_FOUND_UPDATE . '. ' . TEXT_FILE . " {$filename} " . TEXT_DOWNLOADED);
                            }
                            unset($content);
                        } else {
                            $this->send_echo_for_update(TEXT_FOUND_UPDATE . '. ' . TEXT_FILE . " {$filename} " . TEXT_ALREADY_DOWNLOADED);
                        }
                        unset($result);
                        try {
                            $status = $this->install_file_with_dependencies($filename, ['force' => $force], true);
                        } catch (\Exception $exc) {
                            $status = false;
                            $this->send_echo_for_update('Exception: ' . $exc->get_message(), 'error');
                        }
                        $force = 0;
                        ob_flush();
                        flush();
                        if ($status) {
                            $this->send_echo_for_update("\"{$filename}\" " . TEXT_PACK_INSTALLED, 'success');
                            $zip = new \Zip_Archive();
                            if ($zip->open($path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $filename) === true) {
                                $json = $zip->get_from_name('distribution.json');
                                $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
                                $distribution = json_decode($json);
                                $version = (string) $distribution->version;
                                $zip->close();
                            } else {
                                $version = '';
                            }
                            $updated_date = \common\models\Configuration::find()->where(['configuration_key' => 'UPDATED_DATE'])->one();
                            if ($updated_date instanceof \common\models\Configuration) {
                                $updated_date->last_modified = date('Y-m-d H:i:s');
                            } else {
                                $updated_date = new \common\models\Configuration();
                                $updated_date->load_default_values();
                                $updated_date->configuration_title = 'Date of last update';
                                $updated_date->configuration_key = 'UPDATED_DATE';
                                $updated_date->date_added = date('Y-m-d H:i:s');
                            }
                            $updated_date->configuration_value = date('Y-m-d H:i:s');
                            $updated_date->save(false);
                            $need_re_cache = true;
                            try {
                                $this->run_system_re_cache(true);
                            } catch (\Exception $exc) {
                                $this->send_echo_for_update('Exception: ' . $exc->get_message(), 'error');
                            }
                            ob_flush();
                            flush();
                            //@unlink($path . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $filename);
                        } else {
                            $this->send_echo_for_update("\"{$filename}\" " . TEXT_PACK_ABORTED, 'error');
                            $version = '';
                            echo TEXT_USE . ' <a style="font-size: 30px;" class="btn" href="javascript:void(0)" onclick="return parent.runQuery(1);">' . TEXT_FORCE_UPDATE . '</a>. ' . TEXT_FORCE_UPDATE_INTRO . '.<br>';
                        }
                    } else {
                        if ($response['http_code'] != 400) {
                            $this->send_echo_for_update('Status response: ' . $response['http_code'], 'error');
                        }
                        $this->send_echo_for_update(TEXT_NO_UPDATES);
                        $version = '';
                    }
                }
            }
            if ($need_re_cache) {
                ob_flush();
                flush();
                $this->send_echo_for_update(TEXT_UPDATE_FINISH);
            }
        }
        echo '<br><a class="btn" href="javascript:void(0)" onclick="return parent.checkActualStatus();">' . IMAGE_BACK . '</a>';
    }
    private function send_echo_for_update($message, $type = 'default')
    {
        $class = $style = '';
        switch ($type) {
            case 'error':
                $class = 'ic ic-error';
                //                $style = 'color: #dc3545';
                $style = 'color: red';
                break;
            case 'success':
                $class = 'ic ic-success';
                //                $style = 'color: #198754';
                $style = 'color: green';
                break;
            case 'warning':
                $class = 'ic ic-warning';
                $style = 'color: #ffc107;';
                break;
            case 'info':
                $class = 'ic ic-info';
                $style = 'color:  #0dcaf0;';
                break;
            case 'default':
                $class = 'ic ic-default';
                $style = '';
                break;
        }
        $this->send_echo(sprintf('<div class="%s" style="%s">%s</div>', $class, $style, $message));
    }
    public function action_cleanup_local_storage()
    {
        $path = Yii::get_alias('@site_root') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $backup_path = $path . 'backups' . DIRECTORY_SEPARATOR;
        if ($dir = @dir($path)) {
            while ($file = $dir->read()) {
                $ext = substr($file, strrpos($file, '.') + 1);
                if ($ext != 'zip') {
                    continue;
                }
                $deployed = false;
                $record = \common\models\Installer::find()->where(['filename' => $file])->one();
                if ($record instanceof \common\models\Installer) {
                    $deployed = true;
                    $zip = new \Zip_Archive();
                    if ($zip->open($path . $file) === true) {
                        $json = $zip->get_from_name('distribution.json');
                        $json = preg_replace('#/\*(?:[^*]*(?:\*(?!/))*)*\*/#', '', $json);
                        if (!empty($json)) {
                            $distribution = json_decode($json);
                            list($major, $minor, $patch) = explode('.', (string) $distribution->version);
                            $archive_version = intval($major) + intval($minor) / 100 + intval($patch) / 10000;
                            $check = \common\models\Installer::find()->select(['max(archive_version) as version'])->where(['archive_type' => (string) $distribution->type])->and_where(['archive_class' => (string) $distribution->class])->as_array()->one();
                            if (isset($check['version']) && $check['version'] > $archive_version) {
                                $deployed = false;
                                $record->delete();
                                //delete backup for latest version
                                $check_latest = \common\models\Installer::find()->select(['filename'])->where(['archive_type' => (string) $distribution->type])->and_where(['archive_class' => (string) $distribution->class])->and_where(['archive_version' => $check['version']])->as_array()->one();
                                //filename
                                if (isset($check_latest['filename'])) {
                                    if (is_file($backup_path . $check_latest['filename'])) {
                                        @unlink($backup_path . $check_latest['filename']);
                                    }
                                }
                            }
                        }
                        $zip->close();
                    }
                    unset($zip);
                }
                if ($deployed === false) {
                    @unlink($path . $file);
                    if (is_file($backup_path . $file)) {
                        @unlink($backup_path . $file);
                    }
                }
            }
        }
        return $this->redirect(Yii::$app->url_manager->create_url(['install/', 'set' => 'modules']));
    }
}