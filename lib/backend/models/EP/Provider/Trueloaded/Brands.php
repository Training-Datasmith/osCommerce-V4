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
class Brands extends Xml_Base
{
    public function init()
    {
        $this->configure_map = Io_Core::get_export_structure('brands');
        parent::init();
    }
    public function clear_local_data()
    {
        $query = tep_db_query('select * from ' . TABLE_MANUFACTURERS);
        while ($data = tep_db_fetch_array($query)) {
            @unlink(DIR_FS_CATALOG_IMAGES . $data['manufacturers_image']);
        }
        tep_db_query('DELETE FROM ' . TABLE_FILTERS . " WHERE filters_of = 'brand'");
        parent::clear_local_data();
    }
}