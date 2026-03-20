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

use Yii;
/**
 * default controller to handle user requests.
 */
class Cache_control_Controller extends Sceleton
{
    public $acl = ['TEXT_SETTINGS', 'BOX_HEADING_CACHE_CONTROL'];
    public function action_index()
    {
        $this->selected_menu = ['settings', 'cache_control'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('cache_control/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $messages = [];
        if (isset($_SESSION['messages'])) {
            $messages = $_SESSION['messages'];
            unset($_SESSION['messages']);
            if (!is_array($messages)) {
                $messages = [];
            }
        }
        return $this->render('index', ['messages' => $messages]);
    }
    public function action_flush()
    {
        set_time_limit(0);
        \common\helpers\Translation::init('admin/cache_control');
        $runtime_path = Yii::get_alias('@runtime');
        $all_runtime_directories = [];
        $all_runtime_directories[] = $runtime_path;
        $runtime_dir_name = str_replace(Yii::get_alias('@backend'), '', Yii::get_alias('@runtime'));
        $other_apps_aliases = ['@frontend', '@console', '@pos', '@superadmin', '@rest'];
        foreach ($other_apps_aliases as $_apps_alias) {
            $_app_runtime_dir = Yii::get_alias($_apps_alias . $runtime_dir_name, false);
            if (!$_app_runtime_dir || !is_dir($_app_runtime_dir)) {
                continue;
            }
            $all_runtime_directories[] = $_app_runtime_dir;
        }
        header('Content-Type: text/html');
        header('Content-Transfer-Encoding: utf-8');
        header('Pragma: no-cache');
        ob_start();
        $message_type = 'warning';
        //success warning
        /**
         * System
         */
        if (Yii::$app->request->post('system') == 1) {
            Yii::$app->get_cache()->flush();
            $message = TEXT_SYSTEM_WARNING;
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        /**
         * Smarty
         */
        if (Yii::$app->request->post('smarty') == 1) {
            foreach ($all_runtime_directories as $runtime_directory) {
                $smarty_path = $runtime_directory . DIRECTORY_SEPARATOR . 'Smarty' . DIRECTORY_SEPARATOR . 'compile' . DIRECTORY_SEPARATOR . '*.*';
                array_map('unlink', glob($smarty_path));
            }
            //remove css cache
            $themes_path = DIR_FS_CATALOG . 'themes' . DIRECTORY_SEPARATOR;
            $dir = scandir($themes_path);
            foreach ($dir as $theme) {
                if (file_exists($themes_path . $theme . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR)) {
                    \yii\helpers\File_Helper::remove_directory($themes_path . $theme . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR);
                }
            }
            $message = TEXT_SMARTY_WARNING;
            ?>
        <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
            <?php 
            echo $message;
            ?>
        </div>  
        
<?php 
        }
        /**
         * Debug
         */
        if (Yii::$app->request->post('debug') == 1) {
            foreach ($all_runtime_directories as $runtime_directory) {
                $debug_path = $runtime_directory . DIRECTORY_SEPARATOR . 'debug' . DIRECTORY_SEPARATOR . '*.*';
                array_map('unlink', glob($debug_path));
            }
            $message = TEXT_DEBUG_WARNING;
            ?>
        <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
            <?php 
            echo $message;
            ?>
        </div>  
        
<?php 
        }
        /**
         * Logs
         */
        if (Yii::$app->request->post('logs') == 1) {
            foreach ($all_runtime_directories as $runtime_directory) {
                $logs_path = $runtime_directory . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . '*.*';
                array_map('unlink', glob($logs_path));
            }
            $message = TEXT_LOGS_WARNING;
            ?>
        <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
            <?php 
            echo $message;
            ?>
        </div>          
        
<?php 
        }
        /**
         * Image cache
         */
        if (Yii::$app->request->post('image_cache') == 1) {
            \common\classes\Images::cache_flush(true);
            \common\classes\Images::clean_image_reference();
            $message = TEXT_IMAGE_CACHE_CLEANED;
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        /**
         * Theme cache
         */
        if (Yii::$app->request->post('theme') == 1) {
            if (\backend\design\Style::flush_cache_all()) {
                $message = 'Theme cache flushed';
            } else {
                $message = 'Theme cache flush is already in process';
            }
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>
            <?php 
        }
        if (Yii::$app->request->post('opcache_reset') == 1) {
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            $message = 'Opcode cache flushed';
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        if (Yii::$app->request->post('hooks') == 1) {
            \common\helpers\Hooks::rebuild_hooks();
            $message = 'Hooks cache flushed';
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        if (Yii::$app->request->post('app_shop_cache') == 1) {
            \common\models\Install_List_Cache::delete_all();
            $message = TEXT_INSTALL_CACHE . ' flushed';
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        if (Yii::$app->request->post('prod_stock_cache') == 1) {
            $products_query = \common\models\Products::find()->as_array();
            foreach ($products_query->each() as $product) {
                echo ' ';
                \common\helpers\Product::do_cache($product['products_id']);
                ob_flush();
                flush();
            }
            unset($products_query);
            $message = 'Product Stock Cache flushed';
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        /**
         * Categories cache
         */
        if (Yii::$app->request->post('categories_cache') == 1) {
            \common\components\Categories_Cache::get_cpc()::invalidate_all();
            echo '<div class="pop-mess-cont pop-mess-cont-' . $message_type . '">' . PRODUCTS_IN_CATEGORIES_FLUSHED . '</div>';
        }
        if (Yii::$app->request->post('do_migrations') == 1) {
            defined('STDIN') or define('STDIN', fopen('php://input', 'r'));
            defined('STDOUT') or define('STDOUT', fopen('php://output', 'w'));
            $old_app = \Yii::$app;
            new \yii\console\Application(['id' => 'Command runner', 'basePath' => '@site_root', 'components' => ['db' => $old_app->db, 'cache' => ['class' => 'yii\caching\FileCache', 'cachePath' => '@frontend/runtime/cache'], 'log' => ['targets' => [['class' => 'yii\log\FileTarget', 'levels' => ['error', 'warning']]]], 'errorHandler' => ['class' => '\common\classes\TlErrorHandlerConsole']]]);
            ob_start();
            try {
                \Yii::$app->run_action('migrate/up', ['migrationPath' => '@console/migrations/', 'interactive' => false]);
            } catch (\Throwable $e) {
                \Yii::warning('Error running migrate/up action: ' . $e->get_message());
            }
            $buffer = ob_get_flush();
            \Yii::$app = $old_app;
            $message = strpos($buffer, 'Migration failed') ? 'Migration failed' : 'Migrations applied';
            \Yii::warning("{$message}\n{$buffer}");
            ?>
            <div class="pop-mess-cont pop-mess-cont-<?php 
            echo $message_type;
            ?>">
                <?php 
            echo $message;
            ?>
            </div>

            <?php 
        }
        ob_end_flush();
    }
}