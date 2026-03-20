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
namespace backend\widgets;

use yii\base\Widget;
use yii\helpers\Array_Helper;
use yii\helpers\Html;
class Admin_Notifier_Widget extends Widget
{
    public $notifier;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        $notifications = $this->notifier->get_unread_notifications();
        $response = '';
        if ($notifications) {
            $notifications_by_type = Array_Helper::index($notifications, null, 'type');
            foreach ($notifications_by_type as $type => $notifications) {
                $items = [];
                foreach ($notifications as $notification) {
                    $items[] = $notification->date_added . '  ' . TEXT_MESSAGE . ' ' . $notification->message;
                }
                $response .= Html::ul($items, ['class' => "admin-notifications-list alert-{$type}"]);
            }
            return $response;
        }
        return;
    }
}