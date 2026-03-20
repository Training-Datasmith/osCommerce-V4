<?php

declare (strict_types=1);
/**
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 * @author Dmitry Kodinets
 * @email dkodynets@holbi.co.uk
 * @link https://www.holbi.co.uk
 * @copyright Copyright (c) 2000-2022 osCommerce LTD
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace common\extensions\Error_Log_Viewer;

use Yii;
class Error_Log_Viewer extends \common\classes\modules\Module_Extensions
{
    private $zip_path;
    private $zip_file_name;
    private $path;
    public function __construct()
    {
        parent::__construct();
        $this->zip_path = Yii::get_alias('@ext-error-log-viewer', false) . DIRECTORY_SEPARATOR . 'tmp';
        $this->path = DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'logs';
    }
    private static function source_list()
    {
        return ['frontend', 'backend', 'console'];
    }
    public static function get_file($source_file)
    {
        $source_file = str_replace('|', '.', $source_file);
        $tmp = explode('/', $source_file);
        if (count($tmp) > 2) {
            throw new \Exception('Undefined source/file');
        }
        $source = trim($tmp[0]);
        $file_name = trim($tmp[1]);
        $file = new \stdClass();
        $file->error = false;
        if (!in_array($source, self::source_list())) {
            $file->error = true;
            $file->error_message = EXT_ELV_ERR_SOURCE;
            return $file;
        }
        $path = \Yii::get_alias('@' . $source) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'logs';
        if (file_exists($path . DIRECTORY_SEPARATOR . $file_name) && is_file($path . DIRECTORY_SEPARATOR . $file_name)) {
            $file->source_file = $source_file;
            $file->source = $source;
            $file->name = $file_name;
            $file->mask = str_replace('.', '|', $source_file);
            $file->full_path = $path . DIRECTORY_SEPARATOR . $file_name;
            $file->size_text = self::format_size(filesize($file->full_path));
            $file->size = filesize($file->full_path);
            $file->date = filemtime($file->full_path) ? date('Y-m-d H:i:s', filemtime($file->full_path)) : 'Undefined';
        } else {
            $file->error = true;
            $file->error_message = EXT_ELV_ERR_FILE;
        }
        return $file;
    }
    public static function get_files($source)
    {
        $path = \Yii::get_alias('@' . $source) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'logs';
        $files = [];
        if (is_dir($path)) {
            foreach (scandir($path) as $file) {
                if (!is_file($path . DIRECTORY_SEPARATOR . $file)) {
                    continue;
                }
                $files[] = self::get_file($source . '/' . $file);
            }
            return $files;
        }
        return false;
    }
    public static function delete_all()
    {
        foreach (self::source_list() as $source) {
            $path = \Yii::get_alias('@' . $source) . DIRECTORY_SEPARATOR . 'runtime' . DIRECTORY_SEPARATOR . 'logs';
            if (is_dir($path)) {
                foreach (scandir($path) as $file) {
                    if (!is_file($path . DIRECTORY_SEPARATOR . $file)) {
                        continue;
                    }
                    unlink($path . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
    }
    private static function format_size($bytes)
    {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        return $bytes;
    }
    public function Zipping()
    {
        $this->zip_file_name = 'logs_' . Yii::$app->session->get('login_id', 0) . '_' . time() . '.zip';
        if (!is_dir($this->zip_path)) {
            if (!@mkdir($this->zip_path)) {
                return ['status' => 'error', 'description' => EXT_ELV_ERR_CREATE_TMP];
            }
        }
        try {
            $zip = new \Zip_Archive();
            if ($err = $zip->open($this->zip_path . DIRECTORY_SEPARATOR . $this->zip_file_name, \Zip_Archive::CREATE | \Zip_Archive::OVERWRITE) !== true) {
                return ['status' => 'error', 'description' => $err];
            }
            foreach (self::source_list() as $source) {
                if (!file_exists(Yii::get_alias('@' . $source) . $this->path) && !is_dir(Yii::get_alias('@' . $source) . $this->path)) {
                    continue;
                }
                $files = new \Recursive_Iterator_Iterator(new \Recursive_Directory_Iterator(realpath(Yii::get_alias('@' . $source) . $this->path)), \Recursive_Iterator_Iterator::LEAVES_ONLY);
                foreach ($files as $name => $file) {
                    if (!$file->is_dir()) {
                        $file_path = $file->get_real_path();
                        $relative_path = $source . DIRECTORY_SEPARATOR . $file->get_filename();
                        $zip->add_file($file_path, $relative_path);
                    }
                }
            }
            if (!$zip->close()) {
                return ['status' => 'error', 'description' => 'Check permission on dir: ' . $this->zip_path];
            }
            return ['status' => 'ok', 'description' => $this->zip_path . DIRECTORY_SEPARATOR . $this->zip_file_name];
        } catch (\Exception $ex) {
            return ['status' => 'error', 'description' => $ex->get_message()];
        }
    }
    public function delete_old_zip()
    {
        if (file_exists($this->zip_path) && is_dir($this->zip_path)) {
            try {
                foreach (scandir($this->zip_path) as $file) {
                    if (!is_file($this->zip_path . DIRECTORY_SEPARATOR . $file)) {
                        continue;
                    }
                    if (time() - filemtime($this->zip_path . DIRECTORY_SEPARATOR . $file) > 86400) {
                        unlink($this->zip_path . DIRECTORY_SEPARATOR . $file);
                    }
                }
                return true;
            } catch (\Exception $ex) {
                return $ex->get_message();
            }
        }
        return true;
    }
}