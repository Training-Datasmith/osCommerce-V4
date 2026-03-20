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
namespace common\components\google;

class Google_Printer_Job
{
    private $printer_id;
    private $title;
    public function __construct($printer_id)
    {
        $this->printer_id = $printer_id;
    }
    public function get_printer_id()
    {
        return $this->printer_id;
    }
    public function set_title($title)
    {
        $this->title = $title;
    }
    public function get_title()
    {
        return $this->title ? $this->title : 'Printing process ' . date('Y-m-d H:i:s');
    }
    private $copies;
    public function set_copies($copies)
    {
        $this->copies = (int) $copies;
    }
    public function get_copies()
    {
        return $this->copies ? $this->copies : 1;
    }
    private $content_type;
    public function set_content_type($type)
    {
        $this->content_type = $type;
    }
    public function get_content_type()
    {
        return $this->content_type ? $this->content_type : false;
    }
    private $version = '1.0';
    public function get_ticket()
    {
        return ['version' => $this->version, 'print' => ['copies' => ['copies' => $this->get_copies()]]];
    }
    private $last_job;
    public function set_last_job($last_job)
    {
        if ($last_job['id']) {
            $this->last_job = $last_job;
        }
    }
    public function get_last_job()
    {
        return $this->last_job;
    }
}