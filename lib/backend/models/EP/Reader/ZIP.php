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
namespace backend\models\EP\Reader;

use yii\base\Base_Object;
class ZIP extends Base_Object implements Reader_Interface
{
    protected $num_files = 0;
    protected $file_cursor = 0;
    public $filename;
    protected $file_handle;
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\Exception $ex) {
        }
    }
    public function read_columns()
    {
        return [];
    }
    public function read()
    {
        if (!$this->file_handle) {
            $this->file_handle = new \Zip_Archive();
            $this->file_handle->open($this->filename);
            $this->num_files = $this->file_handle->num_files;
            $this->file_cursor = 0;
        }
        if ($this->file_handle && $this->file_cursor < $this->file_handle->num_files) {
            $filename = $this->file_handle->get_name_index($this->file_cursor);
            $stream = $this->file_handle->get_stream($filename);
            $this->file_cursor++;
            if (preg_match('#[/|\\\\]$#', $filename)) {
                // skip directory
                return $this->read();
            }
            return ['filename' => $filename, 'stream' => $stream];
        }
        return false;
    }
    public function current_position()
    {
        return $this->file_cursor;
    }
    public function set_data_position($position)
    {
        $this->file_cursor = $position;
    }
    public function get_progress()
    {
        $file_position = $this->current_position();
        $percent_done = min(100, $file_position / filesize($this->num_files) * 100);
        return number_format($percent_done, 1, '.', '');
    }
}