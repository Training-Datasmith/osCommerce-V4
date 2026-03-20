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
namespace backend\design\editor;

use Yii;
use yii\base\Widget;
class Delete_Order extends Widget
{
    public $manager;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if (!\common\helpers\Acl::rule(['ACL_ORDER', 'IMAGE_DELETE'])) {
            return '';
        }
        if ($this->manager->is_instance() && $this->manager->get_cart()->order_id) {
            return $this->render('delete-order', ['url' => Yii::$app->url_manager->create_absolute_url(array_merge(['editor/checkout', 'action' => 'show_delete'], Yii::$app->request->get_query_params()))]);
        }
    }
}