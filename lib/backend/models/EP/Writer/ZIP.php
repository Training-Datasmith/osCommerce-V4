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
namespace backend\models\EP\Writer;

use backend\models\EP\Exception;
use yii\base\Base_Object;
class ZIP extends Base_Object implements Writer_Interface
{
    public $filename;
    public $feed;
    public $feed_writer;
    protected $tmpfilename = false;
    /**
     * @var \ZipArchive
     */
    protected $zip;
    protected $_first_write = true;
    protected $columns = [];
    public function __set($name, $value)
    {
        try {
            parent::__set($name, $value);
        } catch (\Exception $ex) {
        }
    }
    public function set_columns(array $columns)
    {
        $this->columns = $columns;
    }
    public function write(array $write_data)
    {
        if ($this->_first_write) {
            $this->_first_write = false;
            $this->zip = new \Zip_Archive();
            if ($this->filename == 'php://output') {
                $this->tmpfilename = tempnam(sys_get_temp_dir(), 'ep_zip_write');
                $archive_status = $this->zip->open($this->tmpfilename, \Zip_Archive::OVERWRITE);
            } else {
                $archive_status = $this->zip->open($this->filename, \Zip_Archive::CREATE | \Zip_Archive::OVERWRITE);
            }
            if ($archive_status !== true) {
                throw new Exception('Create file error [' . $archive_status . ']');
            }
            if (is_array($this->feed) && !empty($this->feed['feed_filename']) && $this->feed['format']) {
                $this->feed['temporary_filename'] = tempnam(sys_get_temp_dir(), 'ep_zip_sub_feed');
                $this->feed_writer = \Yii::create_object(['class' => 'backend\models\EP\Writer\\' . $this->feed['format'], 'filename' => $this->feed['temporary_filename']]);
                $this->feed_writer->set_columns($this->columns);
            }
        }
        if (substr(strval(key($write_data)), 0, 1) == ':') {
            if (isset($write_data[':feed_data'])) {
                $this->feed_writer->write($write_data);
            }
            if (isset($write_data[':attachments'])) {
                foreach ($write_data[':attachments'] as $write_file) {
                    $this->check_archive_directory($write_file['localname']);
                    $this->zip->add_file($write_file['filename'], $write_file['localname']);
                }
            }
            return;
        } else if (!isset($write_data[0]) && $this->feed_writer) {
            $this->feed_writer->write($write_data);
        } else {
            foreach ($write_data as $write_file) {
                if (isset($write_file['localname'])) {
                    $this->check_archive_directory($write_file['localname']);
                    $this->zip->add_file($write_file['filename'], $write_file['localname']);
                } elseif (isset($write_file['string'])) {
                    $this->check_archive_directory($write_file['filename']);
                    $this->zip->add_from_string($write_file['filename'], $write_file['string']);
                }
            }
        }
    }
    protected function check_archive_directory($archive_filename)
    {
        $archive_dir = dirname($archive_filename) . '/';
        if (dirname($archive_filename) != './') {
            if (false === $this->zip->locate_name($archive_dir)) {
                $this->zip->add_empty_dir($archive_dir);
            }
        }
    }
    public function close()
    {
        if ($this->zip) {
            if ($this->feed_writer && is_array($this->feed) && !empty($this->feed['feed_filename'])) {
                $this->feed_writer->close();
                $this->zip->add_file($this->feed['temporary_filename'], $this->feed['feed_filename']);
                $this->zip->close();
                if (is_array($this->feed) && !empty($this->feed['temporary_filename']) && is_file($this->feed['temporary_filename'])) {
                    @unlink($this->feed['temporary_filename']);
                }
            } else {
                $this->zip->close();
            }
            //$this->file_handle = null;
        }
        if ($this->filename == 'php://output' && is_file($this->tmpfilename)) {
            readfile($this->tmpfilename);
            unlink($this->tmpfilename);
        }
    }
}