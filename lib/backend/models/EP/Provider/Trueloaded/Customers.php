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
class Customers extends Xml_Base
{
    public function init()
    {
        $this->configure_map = Io_Core::get_export_structure('customers');
        parent::init();
    }
    public function prepare_export($use_columns, $filter)
    {
        if (is_array($filter)) {
            if (isset($filter['platform_id']) && !empty($filter['platform_id'])) {
                $this->active_query->and_where(['=', 'platform_id', (int) $filter['platform_id']]);
            }
        }
        parent::prepare_export($use_columns, $filter);
    }
    public function clear_local_data()
    {
        \common\helpers\Customer::trunk_customers();
        tep_db_query('TRUNCATE TABLE ' . TABLE_PRODUCTS_NOTIFY);
        tep_db_query('TRUNCATE TABLE ' . TABLE_VIRTUAL_GIFT_CARD_BASKET);
        tep_db_query('TRUNCATE TABLE wedding_registry');
        tep_db_query('TRUNCATE TABLE wedding_registry_inviting');
        tep_db_query('TRUNCATE TABLE wedding_registry_products');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_link_customers');
        tep_db_query('TRUNCATE TABLE ep_holbi_soap_kv_storage');
        tep_db_query('TRUNCATE TABLE gdpr_check');
        tep_db_query('TRUNCATE TABLE guest_check');
        tep_db_query('TRUNCATE TABLE personal_catalog');
    }
}