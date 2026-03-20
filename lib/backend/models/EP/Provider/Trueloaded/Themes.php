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
class Themes extends Xml_Base
{
    public function init()
    {
        $this->configure_map = Io_Core::get_export_structure('themes');
        parent::init();
    }
    public function prepare_export($use_columns, $filter)
    {
        //hideProperties
        if (is_array($filter)) {
            $this->with_images = isset($filter['with_images']) && $filter['with_images'];
            if (!$this->with_images) {
                $key_data = key($this->configure_map['Data']);
                $this->configure_map['Data'][$key_data]['properties']['themeBackup'] = false;
                parent::init();
            }
        }
        parent::prepare_export($use_columns, $filter);
    }
}