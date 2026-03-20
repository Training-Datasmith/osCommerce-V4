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
namespace backend\models\EP\Provider\Trueloaded;

use common\api\models\XML\Io_Core;
use yii\db\Expression;
class Quotes extends Xml_Base
{
    public function init()
    {
        $this->configure_map = Io_Core::get_export_structure('quotes');
        parent::init();
    }
    public function prepare_export($use_columns, $filter)
    {
        if (is_array($filter)) {
            if (isset($filter['platform_id']) && !empty($filter['platform_id'])) {
                $this->active_query->and_where(['=', 'platform_id', (int) $filter['platform_id']]);
            }
            $order_filter = isset($filter['order']) && is_array($filter['order']) ? $filter['order'] : [];
            if (isset($order_filter['date_type_range']) && $order_filter['date_type_range'] == 'exact') {
                if (!empty($order_filter['date_from'])) {
                    $this->active_query->and_where(['>=', 'date_purchased', substr($order_filter['date_from'], 0, 10) . ' 00:00:00']);
                }
                if (!empty($order_filter['date_to'])) {
                    $this->active_query->and_where(['<=', 'date_purchased', substr($order_filter['date_to'], 0, 10) . ' 23:59:59']);
                }
            } elseif (isset($order_filter['date_type_range']) && $order_filter['date_type_range'] == 'year/month') {
                $year = $order_filter['year'];
                $this->active_query->and_where(['=', new Expression('YEAR(date_purchased)'), $year]);
                $month = $order_filter['month'];
                if (!empty($month)) {
                    $this->active_query->and_where(['=', new Expression('DATE_FORMAT(date_purchased,\'%Y%m\')'), $year . sprintf('%02s', (int) $month)]);
                }
            } elseif (isset($order_filter['date_type_range']) && $order_filter['date_type_range'] == 'presel') {
                switch ($order_filter['interval']) {
                    case 'week':
                        $this->active_query->and_where(['=', 'date_purchased', date('Y-m-d', strtotime('monday this week'))]);
                        break;
                    case 'month':
                        $this->active_query->and_where(['=', 'date_purchased', date('Y-m-d', strtotime('first day of this month'))]);
                        break;
                    case 'year':
                        $this->active_query->and_where(['>=', 'date_purchased', date('Y') . '-01-01']);
                        break;
                    case '1':
                        $this->active_query->and_where(['>=', 'date_purchased', date('Y-m-d')]);
                        break;
                    case '3':
                    case '7':
                    case '14':
                    case '30':
                        $this->active_query->and_where(['>=', 'date_purchased', date('Y-m-d', strtotime('-' . $order_filter['interval'] . ' days'))]);
                        break;
                }
            }
        }
        parent::prepare_export($use_columns, $filter);
    }
}