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
namespace backend\design;

use yii\helpers\File_Helper;
class Backups
{
    public static function create($theme_name, $backup_id)
    {
        $path = DIR_FS_CATALOG . 'lib' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $theme_name;
        File_Helper::create_directory($path, 0777);
        $zip_name = $path . DIRECTORY_SEPARATOR . $backup_id . '.zip';
        $zip = new \Zip_Archive();
        if ($zip->open($zip_name, \Zip_Archive::CREATE) === true) {
            foreach ([$theme_name, $theme_name . '-mobile'] as $theme) {
                $json = \backend\design\Theme::get_theme_json($theme);
                $zip->add_from_string($theme . '.json', $json);
            }
            $zip->close();
        }
    }
    public static function backup_restore($backup_id, $theme_name)
    {
        $zip_name = DIR_FS_CATALOG . 'lib' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $theme_name . DIRECTORY_SEPARATOR . $backup_id . '.zip';
        foreach ([$theme_name, $theme_name . '-mobile'] as $theme) {
            $zip_text = file_get_contents('zip://' . $zip_name . '#' . $theme . '.json');
            $theme_array = json_decode($zip_text, true);
            if (is_array($theme_array)) {
                Theme::import_theme($theme_array, $theme);
                Style::create_cache($theme);
            }
        }
    }
    public static function delete($backup_id)
    {
        if (!$backup_id) {
            return false;
        }
        $design_backup = \common\models\Design_Backups::find_one(['backup_id' => $backup_id]);
        if (!$design_backup) {
            return false;
        }
        $design_backup->delete();
        $zip_name = DIR_FS_CATALOG . 'lib' . DIRECTORY_SEPARATOR . 'backend' . DIRECTORY_SEPARATOR . 'design' . DIRECTORY_SEPARATOR . 'backups' . DIRECTORY_SEPARATOR . $design_backup->theme_name . DIRECTORY_SEPARATOR . $backup_id . '.zip';
        if (is_file($zip_name)) {
            unlink($zip_name);
        }
    }
}