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
namespace common\helpers;

use common\classes\modules\Module_Ver;
class Modules_Migrations
{
    public static function up($code, $since_ver = null, $type = 'extension', $to_ver = null)
    {
        if (empty($since_ver)) {
            $since_ver = \common\helpers\Modules::get_module_ver_db_installed($code, $type);
            if (empty($since_ver)) {
                $since_ver = new Module_Ver();
            }
        }
        if (empty($to_ver)) {
            $to_ver = \common\helpers\Modules::get_module_ver_file($code, $type);
            \common\helpers\Assert::is_not_empty($to_ver, "Cannot get current version for {$type}: {$code}");
        }
        self::up_down(true, $code, $type, $since_ver, $to_ver);
    }
    public static function down($code, $downto_ver, $type = 'extension', $since_ver = null)
    {
        if (empty($since_ver)) {
            $since_ver = \common\helpers\Modules::get_module_ver_file($code, $type);
            \common\helpers\Assert::is_not_empty($since_ver, "Cannot get current version for {$type}: {$code}");
        }
        self::up_down(false, $code, $type, $downto_ver, $since_ver);
    }
    private static function up_down($up, $code, $type, $since_ver, $to_ver)
    {
        $since_ver = Module_Ver::parse($since_ver);
        $to_ver = Module_Ver::parse($to_ver);
        $module = \common\classes\modules\Module::get_module($code, $type);
        $migrations = $module::get_migrations_since($code, $since_ver, $up, $to_ver);
        self::do($code, $type, $since_ver, $to_ver, $migrations, $up);
    }
    private static function do($code, $type, Module_Ver $since_ver, Module_Ver $to_ver, $migrations, $up)
    {
        if (!is_array($migrations)) {
            return;
        }
        $func = $up ? 'safeUp' : 'safeDown';
        foreach ($migrations as $class) {
            if (!class_exists($class)) {
                \Yii::warning("Can't apply migration: {$class} does not exist");
                continue;
            }
            $m = \common\models\Modules_Migrations::find()->where(['classname' => $class])->one();
            if ($up && !empty($m)) {
                \Yii::warning("Can't apply migration: {$class} was already applied");
                continue;
            }
            if (!$up && empty($m)) {
                \Yii::warning("Can't revert migration: {$class} was not applied");
                continue;
            }
            $migrate = new $class();
            $migrate->compact = true;
            $migrate->{$func}();
            if ($up) {
                $m = new \common\models\Modules_Migrations();
                $m->code = $code;
                $m->type = $type;
                $m->ver_from = $since_ver->to_number();
                $m->ver_to = $to_ver->to_number();
                $m->classname = $class;
                $m->save(false);
            } else {
                $m->delete();
            }
        }
    }
    public static function clear($code, $type = 'extension')
    {
        \common\models\Modules_Migrations::delete_all(['code' => $code, 'type' => $type]);
    }
}