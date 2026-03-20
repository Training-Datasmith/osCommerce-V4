<?php

declare (strict_types=1);
/*
 * This file is part of osCommerce ecommerce platform.
 * osCommerce the ecommerce
 *
 * @link https://www.oscommerce.com
 * @copyright Copyright (c) 2005 Holbi Group Ltd
 *
 * Released under the GNU General Public License
 * For the full copyright and license information, please view the LICENSE.TXT file that was distributed with this source code.
 */
namespace backend\models\EP\Reader;

class Xls_Read_Filter implements \Php_Office\Php_Spreadsheet\Reader\I_Read_Filter
{
    private $start_row = 0;
    private $end_row = 0;
    private $columns = [];
    /**  Get the list of rows and columns to read  */
    public function __construct($start_row, $end_row, $columns)
    {
        $this->start_row = $start_row;
        $this->end_row = $end_row;
        $this->columns = $columns;
    }
    public function read_cell($column, $row, $worksheet_name = '')
    {
        //  Only read the rows and columns that were configured
        if ($row >= $this->start_row && $row <= $this->end_row) {
            if (in_array($column, $this->columns)) {
                return true;
            }
        }
        return false;
    }
}