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
class Account extends Widget
{
    public $manager;
    public $contact_form;
    public $shipping_form;
    public $billing_form;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        return $this->render('account', ['manager' => $this->manager, 'contact' => $this->contact_form, 'shipping' => $this->shipping_form, 'billing' => $this->billing_form, 'showGroup' => \common\helpers\Extensions::is_customer_groups_allowed(), 'platforms' => \yii\helpers\Array_Helper::map(\common\classes\platform::get_list(false), 'id', 'text'), 'groups' => \yii\helpers\Array_Helper::map(\common\models\Groups::find()->all(), 'groups_id', 'groups_name'), 'url' => \yii\helpers\Url::to(array_merge(['editor/create-account'], Yii::$app->request->get_query_params()))]);
    }
}