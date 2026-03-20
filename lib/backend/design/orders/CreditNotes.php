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
namespace backend\design\orders;

use Yii;
use yii\base\Widget;
class Credit_Notes extends Widget
{
    public $orders_id;
    public $manager;
    public $data;
    public function init()
    {
        parent::init();
    }
    public function run()
    {
        if ($this->orders_id) {
            $splitter = $this->manager->get_order_splitter();
            $rma = $splitter->get_instances_from_splinters($this->orders_id, $splitter::STATUS_RETURNING);
            if ($rma) {
                $rma = array_pop($rma);
                //if ($this->data['isAjax']){
                $rma_total = $splitter->get_due($rma->totals);
                $t_manager = $this->manager->get_transaction_manager();
                $preffered = [];
                $p_transaction = null;
                foreach ($t_manager->get_transactions(true) as $transaction) {
                    $allowed_amount = $transaction->transaction_amount;
                    if ($transaction->transaction_children) {
                        foreach ($transaction->transaction_children as $child) {
                            $allowed_amount -= $child->transaction_amount;
                        }
                    }
                    $transaction->transaction_amount = $allowed_amount;
                    $transaction->currency_id = 0;
                    $curr = \common\models\Currencies::find()->select(['currencies_id'])->where(['code' => $transaction->transaction_currency])->one();
                    if (is_object($curr)) {
                        $transaction->currency_id = $curr->currencies_id;
                    }
                    if (abs($allowed_amount) == abs($rma_total)) {
                        $p_transaction = $transaction;
                    } else {
                        array_push($preffered, $transaction);
                    }
                }
                \yii\helpers\Array_Helper::multisort($preffered, 'transaction_amount');
                if (!is_null($p_transaction)) {
                    array_unshift($preffered, $p_transaction);
                }
                //}
                /*echo "<pre>";
                  print_r($this->manager->get('currency'));
                  echo "</pre>";
                  die();*/
                $url = Yii::$app->url_manager->create_url(['orders/transactions', 'orders_id' => $this->orders_id]);
                return $this->render('credit-notes', ['rma' => $rma, 'preffered' => $preffered, 'manager' => $this->manager, 'url' => $url]);
            }
        }
    }
}