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
namespace backend\components;

use Yii;
class Admin_Events
{
    /**
     * @var true
     */
    public $enabled;
    public function __construct()
    {
        $this->enabled = true;
    }
    public function register_notification_event(): void
    {
        if ($this->enabled) {
            $_notifier = new \backend\models\Admin_Notifier();
            if ($_count = $_notifier->get_unread_count()) {
                if (!Yii::$app->request->is_ajax) {
                    Yii::$app->controller->view->notification_count = $_count;
                } else {
                    Yii::$app->on('notifier-list', function () use ($_notifier): void {
                        Yii::$app->response->clear_output_buffers();
                        echo \backend\widgets\Admin_Notifier_Widget::widget(['notifier' => $_notifier]);
                        exit;
                    });
                    if (Yii::$app->request->get('action') == 'show-notifier-list') {
                        Yii::$app->trigger('notifier-list');
                    }
                }
            }
        }
    }
}