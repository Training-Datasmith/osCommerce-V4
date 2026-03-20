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

class Admin
{
    public static function get_admins_with_walkin_orders()
    {
        $order_admin_ids = \common\models\Orders::find()->distinct()->select(['admin_id'])->as_array()->all();
        $order_admin_ids = \yii\helpers\Array_Helper::map($order_admin_ids, 'admin_id', 'admin_id');
        unset($order_admin_ids[0]);
        $admins = [];
        if (count($order_admin_ids) > 0) {
            foreach (\common\models\Admin::find()->where(['IN', 'admin_id', $order_admin_ids])->all() as $admin) {
                $admins[] = $admin;
            }
        }
        return $admins;
    }
    /**
     * returns list of all [active] admins <login_failture<5>
     * @param bool $allDetails include all admin details
     * @return array [admin_id => '', 'listTitle' => concat(admin_lastname, admin_firstname, admin_email_address) [, *] ]
     */
    public static function get_list($all_details = false)
    {
        $q = \common\models\Admin::find()->and_where(' login_failture < 5 ')->add_select(['listTitle' => new \yii\db\Expression('concat(admin_lastname, " ", admin_firstname, " ", admin_email_address)')])->add_select('admin_id')->order_by('admin_lastname, admin_firstname, admin_email_address');
        if ($all_details) {
            $q->add_select('*');
        }
        return $q->as_array()->all();
    }
    public static function app_shop_connected_message()
    {
        if (defined('PROJECT_RELEASE_TYPE') && PROJECT_RELEASE_TYPE == 'VERSIONING') {
            return;
        }
        global $login_id;
        $admin = \common\models\Admin::find_one($login_id);
        $storage_key = $admin->storage_key ?? '';
        if (empty($storage_key)) {
            $message = defined('MESSAGE_KEY_EMPTY') ? constant('MESSAGE_KEY_EMPTY') : 'Your shop is not connected with <a href="%1$s">App Shop</a>. Why and how do I need to connect to App Shop? Please see the article <a target="_blank" href="%2$s">Connecting to App shop</a> for more details.';
            $app_url = \Yii::$app->url_manager->create_url('install');
            $wiki_url = 'https://www.oscommerce.com/wiki/index.php?title=Connecting_to_App_Shop';
            \Yii::$container->get('message_stack')->add(sprintf($message, $app_url, $wiki_url), 'alert', 'info');
        } else {
            $storage_url = \Yii::$app->params['appStorage.url'];
            $contents = @file_get_contents($storage_url . 'patch/last-known.json');
            $current = defined('MIGRATIONS_DB_REVISION') ? MIGRATIONS_DB_REVISION : '';
            if (!empty($contents) && !empty($current)) {
                $json = json_decode($contents);
                if (isset($json->version)) {
                    $version = (string) $json->version;
                    if ($current != $version) {
                        $message = defined('MESSAGE_SYSTEM_UPDATES') ? constant('MESSAGE_SYSTEM_UPDATES') : 'The updates are available in <a href="%1$s">App Shop</a>. Please check it for more details.';
                        $app_url = \Yii::$app->url_manager->create_url(['install', 'set' => 'updates']);
                        \Yii::$container->get('message_stack')->add(sprintf($message, $app_url), 'alert', 'info');
                    }
                }
            }
        }
    }
    /**
     * @return array|false
     */
    public static function limited_platform_list()
    {
        $limited_platforms = false;
        $admin_id = (int) $_SESSION['login_id'];
        if (false === \common\helpers\Acl::rule(['SUPERUSER'])) {
            $limited_platforms = [];
            $platforms = \common\models\Admin_Platforms::find()->where(['admin_id' => $admin_id])->as_array()->all();
            foreach ($platforms as $platform) {
                $limited_platforms[(int) $platform['platform_id']] = (int) $platform['platform_id'];
            }
        }
        return $limited_platforms;
    }
    public static function is_backend_strict_access_allowed($client_ip = null)
    {
        if (!\common\helpers\System::is_backend() || !defined('STRICT_ACCESS_STATUS') || STRICT_ACCESS_STATUS != 'True') {
            return true;
        }
        $allowed = false;
        if (is_null($client_ip)) {
            $client_ip = \common\helpers\System::get_ip_address();
        }
        $ip_white_list = preg_split('/[,;\s]/', defined('STRICT_ACCESS_ALLOWED_IP') ? STRICT_ACCESS_ALLOWED_IP : '', -1, PREG_SPLIT_NO_EMPTY);
        $ip_white_list = array_map('trim', $ip_white_list);
        foreach ($ip_white_list as $white_ip) {
            if (strpos($white_ip, '/') !== false) {
                if (\yii\helpers\Ip_Helper::in_range($client_ip, $white_ip)) {
                    $allowed = true;
                }
            } else if ($client_ip == $white_ip) {
                $allowed = true;
            }
        }
        return $allowed;
    }
    public static function check_backend_strict_access_allowed($client_ip = null)
    {
        if (is_null($client_ip)) {
            $client_ip = \common\helpers\System::get_ip_address();
        }
        if (!self::is_backend_strict_access_allowed($client_ip)) {
            header('HTTP/1.0 403 Forbidden');
            echo (defined('TEXT_PAGE_ACCESS_FORBIDDEN') ? TEXT_PAGE_ACCESS_FORBIDDEN : 'Access Denied') . ' ' . $client_ip;
            die;
        }
    }
}