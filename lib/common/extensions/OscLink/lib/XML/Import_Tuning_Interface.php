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
namespace Osc_Link\XML;

interface Import_Tuning_Interface
{
    public function before_import_save($update_object, $data);
    public function after_import($update_object, $data, $is_new_record);
    public function after_import_entity($update_object, $data, $res);
    public function after_clean($model, $id, $res);
    public function after_clean_entity($model, $id, $res);
}