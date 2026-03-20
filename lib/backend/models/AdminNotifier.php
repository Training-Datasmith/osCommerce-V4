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
namespace backend\models;

use common\models\Admin_Messages;
/*
 * Admin Notifier
 */
class Admin_Notifier
{
    /* save  retrieved class message or simple message */
    public function add_notification($class, $message, $type = 'info')
    {
        $admin_message = new Admin_Messages();
        if (is_object($class) && $class instanceof \backend\models\Notification_Interface) {
            $admin_message->set_attributes(['class' => $class::class_name(), 'message' => $class->prepare_admin_message($message)], false);
        } else {
            $admin_message->message = $message;
        }
        $admin_message->status = 'unread';
        $admin_message->type = $type;
        $admin_message->save();
    }
    /* return array retrieved messages */
    public function get_unread_notifications()
    {
        $_list = Admin_Messages::get_unread()->order_by('date_added desc')->all();
        if ($_list) {
            foreach ($_list as $key => $notification) {
                $_list[$key]->status = 'read';
                $_list[$key]->save();
                if (!empty($notification->class) && class_exists($notification->class)) {
                    $object = new $notification->class();
                    $_list[$key]->message = $object->get_admin_message($notification->message);
                }
            }
        }
        return $_list;
    }
    public function get_unread_count()
    {
        return Admin_Messages::get_unread()->count();
    }
    public function get_last_notification()
    {
        //to do
    }
    public function get_all_notifications()
    {
        //to do
    }
}