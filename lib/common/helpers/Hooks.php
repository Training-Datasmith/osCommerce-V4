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

use Yii;
class Hooks
{
    public static function get_list($page_name, $page_area = '')
    {
        self::check_hook_exists($page_name, $page_area, " in common/extensions/methodology.txt\nDear developer - don't forget add your hook into this file");
        self::rebuild_hooks_if_needed();
        $response = [];
        $query_raw = \Yii::$app->get_cache()->get_or_set($page_name . '|' . $page_area, function () use ($page_name, $page_area) {
            return \common\models\Hooks::find()->where(['page_name' => $page_name, 'page_area' => $page_area])->order_by('sort_order, hook_id')->as_array()->all();
        }, 0, new \yii\caching\Tag_Dependency(['tags' => ['hooks_all']]));
        foreach ($query_raw as $row) {
            if (\common\helpers\Extensions::is_allowed($row['extension_name'])) {
                // mostly for disabled
                if (file_exists($row['extension_file'])) {
                    if (defined('DBG_HOOKS')) {
                        \common\helpers\Dbg::if_defined($row['extension_name'])::logf('Hook will be called (ext=%s, page=%s, area=%s)', $row['extension_name'], $page_name, $page_area);
                    }
                    $response[] = $row['extension_file'];
                } else {
                    \common\helpers\Php::throw_or_log("File for hook {$page_name} area {$page_area} is not found: " . $row['extension_file']);
                }
            }
        }
        return $response;
    }
    private static $has_records = null;
    private static function rebuild_hooks_if_needed()
    {
        if (is_null(self::$has_records)) {
            self::$has_records = \common\models\Hooks::find()->select(new \yii\db\Expression('1'))->limit(1)->exists();
        }
        if (!self::$has_records) {
            self::$has_records = true;
            self::build_hooks();
        }
    }
    private static function get_def_hook_path($ext_code)
    {
        return \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR . $ext_code . DIRECTORY_SEPARATOR . 'hooks' . DIRECTORY_SEPARATOR;
    }
    public static function unregister_hooks($ext_code)
    {
        \common\models\Hooks::delete_all(['extension_name' => $ext_code]);
    }
    public static function register_hooks($Items, $ext_code)
    {
        $def_path = self::get_def_hook_path($ext_code);
        if (is_array($Items)) {
            foreach ($Items as $item) {
                $record = \common\models\Hooks::find_one(['page_name' => $item['page_name'], 'page_area' => $item['page_area'] ?? '', 'sort_order' => $item['sort_order'] ?? 100, 'extension_name' => $ext_code]);
                if (empty($record)) {
                    $record = new \common\models\Hooks();
                    // there is no sense to do find before
                    $record->load_default_values();
                }
                $record->page_name = $item['page_name'];
                $record->page_area = $item['page_area'] ?? '';
                self::check_hook_exists($record->page_name, $record->page_area, " for module {$ext_code}");
                $record->sort_order = $item['sort_order'] ?? 100;
                $record->extension_name = $ext_code;
                if (empty($item['extension_file'])) {
                    $base_name = $item['page_name'] . '.' . (empty($item['page_area']) ? 'php' : $item['page_area'] . '.tpl');
                    $record->extension_file = $def_path . str_replace('/', '.', $base_name);
                } else {
                    $record->extension_file = $item['extension_file'];
                }
                if (!file_exists($record->extension_file)) {
                    \Yii::warning("Registering hook for {$ext_code}: file '{$record->extension_file}' not exists");
                }
                try {
                    $record->save(false);
                } catch (\Exception $e) {
                    \Yii::warning("Registering hook for {$ext_code} db error: " . $e->get_message());
                }
            }
        }
    }
    public static function rebuild_hooks()
    {
        self::reset_hooks();
        self::build_hooks();
    }
    private static function build_hooks()
    {
        if (\Yii::$app->mutex->acquire('build-hooks')) {
            try {
                $path = \Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'extensions' . DIRECTORY_SEPARATOR;
                if ($dir = @dir($path)) {
                    while ($file = $dir->read()) {
                        /** @var \common\classes\modules\ModuleExtensions $ext */
                        if (($ext = \common\helpers\Acl::check_extension($file, 'getAdminHooks')) && \common\helpers\Extensions::is_installed($ext::get_module_code())) {
                            self::register_hooks($ext::get_admin_hooks(), $file);
                        }
                    }
                    $dir->close();
                }
            } finally {
                Yii::$app->mutex->release('build-hooks');
                self::clear_cache();
            }
        }
    }
    public static function reset_hooks()
    {
        \Yii::$app->get_db()->create_command('TRUNCATE ' . \common\models\Hooks::table_name())->execute();
    }
    private static function clear_cache()
    {
        \yii\caching\Tag_Dependency::invalidate(\Yii::$app->cache, 'hooks_all');
    }
    private static $depricated_hooks = [['page_name' => 'Order', 'page_area' => 'save_order/before'], ['page_name' => 'Order', 'page_area' => 'save_order/after'], ['page_name' => 'Order', 'page_area' => 'notify_customer'], ['page_name' => 'account/order_history_info', 'page_area' => 'order-product'], ['page_name' => 'checkout/index', 'page_area' => ''], ['page_name' => 'checkout/process', 'page_area' => ''], ['page_name' => 'checkout/after-process', 'page_area' => ''], ['page_name' => 'checkout/success', 'page_area' => ''], ['page_name' => 'sceleton/register-href-lang', 'page_area' => ''], ['page_name' => 'sceleton/set-meta', 'page_area' => ''], ['page_name' => 'catalog/search-suggest', 'page_area' => '']];
    public static function is_hook_exists($page_name, $page_area = '')
    {
        if (!empty(array_filter(self::$depricated_hooks, function ($val) use ($page_name, $page_area) {
            return $val['page_name'] === $page_name && $val['page_area'] === $page_area;
        }))) {
            return true;
        }
        $hooks = self::get_available_hooks();
        return is_array($hooks) && !empty(array_filter($hooks, function ($val) use ($page_name, $page_area) {
            return $val['page_name'] === $page_name && $val['page_area'] === $page_area;
        }));
    }
    public static function check_hook_exists($page_name, $page_area = '', $suffix_message = '')
    {
        if (self::can_check_hook() && !self::is_extensions_hook($page_name, $page_area)) {
            \common\helpers\Assert::assert(self::is_hook_exists($page_name, $page_area), "There is no hook '{$page_name}', '{$page_area}'" . $suffix_message);
        }
    }
    private static function is_extensions_hook($page_name, $page_area)
    {
        return strncmp('ext/', $page_name, strlen('ext/')) === 0;
    }
    public static function can_check_hook()
    {
        return defined('YII_DEBUG') && YII_DEBUG && \common\helpers\System::is_development() && file_exists(\Yii::get_alias('@common/extensions/methodology.txt')) && is_array(self::get_available_hooks());
    }
    private static function get_hook_names()
    {
        if (($file = @file_get_contents(\Yii::get_alias('@common/extensions/methodology.txt'))) === false) {
            throw new \Exception('Error while reading methodology.txt: ' . (error_get_last()['message'] ?? 'Unknown error'));
        }
        if (!preg_match('/<HOOKS>\s*(.*)<\/HOOKS>/si', $file, $find)) {
            throw new \Exception('Tag HOOKS not found');
        }
        $res = preg_match_all("#^'([-\\w/]*)'\\s*,\\s*'([-\\w/]*)'#mx", $find[1], $out);
        if (!$res) {
            throw new \Exception('Wrong structure inside tag HOOKS: ' . $res === false ? \common\helpers\Php8::preg_last_error_msg() : 'Hooks not found');
        }
        $res = [];
        foreach ($out[1] as $key => $hook_name) {
            $res[] = ['page_name' => $hook_name, 'page_area' => $out[2][$key]];
        }
        return $res;
    }
    private static function get_hook_names_safe()
    {
        try {
            return self::get_hook_names();
        } catch (\Throwable $e) {
            \common\helpers\Php::handle_error_prod($e, 'Error while get hooks names');
            return null;
        }
    }
    private static $available_hooks = null;
    private static function get_available_hooks()
    {
        if (is_null(self::$available_hooks)) {
            self::$available_hooks = self::get_hook_names_safe();
        }
        return self::$available_hooks;
    }
}