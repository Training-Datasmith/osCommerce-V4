<?php

declare(strict_types=1);

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

namespace common\modules\orderTotal;

use common\classes\modules\ModuleSortOrder;
use common\classes\modules\ModuleStatus;
use common\classes\modules\ModuleTotal;

class ot_bonus_points extends ModuleTotal
{
    public $title;
    public $output;

    protected $defaultTranslationArray = [
        'MODULE_ORDER_TOTAL_BONUS_POINTS_TITLE' => 'Reward points',
        'MODULE_ORDER_TOTAL_BONUS_POINTS_DESCRIPTION' => 'Reward points',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->code = 'ot_bonus_points';
        $this->title = MODULE_ORDER_TOTAL_BONUS_POINTS_TITLE;
        $this->description = MODULE_ORDER_TOTAL_BONUS_POINTS_DESCRIPTION;
        $this->enabled = defined('MODULE_ORDER_TOTAL_BONUS_POINTS_STATUS') && MODULE_ORDER_TOTAL_BONUS_POINTS_STATUS == 'true' && \common\helpers\Extensions::isAllowedAnd('BonusActions', 'isProductPointsEnabled');
        $this->sort_order = defined('MODULE_ORDER_TOTAL_BONUS_POINTS_SORT_ORDER') ? MODULE_ORDER_TOTAL_BONUS_POINTS_SORT_ORDER : 0;
        //$this->credit_class = true;
        $this->output = [];
    }

    public function getIncVATTitle()
    {
        return '';
    }

    public function getIncVAT($visibility_id = 0, $checked = false)
    {
        return '';
    }

    public function getExcVATTitle()
    {
        return '';
    }

    public function getExcVAT($visibility_id = 0, $checked = false)
    {
        return '';
    }

    public function process($replacing_value = -1, $visible = false)
    {
        /** @var \common\extensions\BonusActions\BonusActions $ext */
        if (!($ext = \common\helpers\Extensions::isAllowedAnd('BonusActions', 'isProductPointsEnabled')) || \Yii::$app->user->isGuest) {
            return;
        }

        $order = $this->manager->getOrderInstance();
        $sumPoints = 0;
        foreach ($order->getOrderedProducts() as $product) {
            $sumPoints += $product['qty'] * ($product['bonus_points_cost'] ?? 0);
        }
        if ($sumPoints > 0) {
            $this->output[] = [
                'title' => $this->title,
                'text' => $ext::formatPointAndCurrency($sumPoints),
            ];
        }
    }

    public function pre_confirmation_check()
    {
        return 0;
    }

    public function collect_posts($collect_data)
    {
    }

    public function describe_status_key()
    {
        return new ModuleStatus('MODULE_ORDER_TOTAL_BONUS_POINTS_STATUS', 'true', 'false');
    }

    public function describe_sort_key()
    {
        return new ModuleSortOrder('MODULE_ORDER_TOTAL_BONUS_POINTS_SORT_ORDER');
    }

    public function configure_keys()
    {
        return [
            'MODULE_ORDER_TOTAL_BONUS_POINTS_STATUS' =>
            [
                'title' => 'Display Bonus Points',
                'value' => 'true',
                'description' => 'Do you want this module to display?',
                'sort_order' => '1',
                'set_function' => 'tep_cfg_select_option(array(\'true\', \'false\'), ',
            ],
            'MODULE_ORDER_TOTAL_BONUS_POINTS_SORT_ORDER' =>
            [
                'title' => 'Sort Order',
                'value' => '9',
                'description' => 'Sort order of display.',
                'sort_order' => '2',
            ],
        ];
    }

}
