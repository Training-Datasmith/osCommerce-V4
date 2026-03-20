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
namespace common\helpers;

class Export
{
    private static $xls_lib = 'spout';
    private static $to_file;
    private static $to_browser;
    private static $sheet;
    /**
     * create and init default export writer object
     * 2do config default file type per front/back, frontend, preferable by customer/admin
     * $writer= getWriter('filename without extension'); $writer->addRow($header); .... $writer->close();
     * @param string $filename
     * @param bool $toBrowser
     * @param int $customer_id
     * @param string $fileType
     * @return object|null
     */
    public static function get_writer($filename, $to_browser = true, $customer_id = 0, $file_type = '')
    {
        $writer = null;
        if ($file_type == '' && defined('EXPORT_DEFAULT_FILE_TYPE') && in_array(EXPORT_DEFAULT_FILE_TYPE, ['CSV', 'XLSX'])) {
            $file_type = EXPORT_DEFAULT_FILE_TYPE;
        }
        self::$to_file = $filename;
        self::$to_file .= strpos(self::$to_file, '.xlsx') === false ? '.xlsx' : '';
        self::$to_browser = $to_browser;
        switch ($file_type) {
            case 'CSV':
                $filename .= '.csv';
                $writer = new \backend\models\EP\Formatter\CSV('write', [], $filename);
                break;
            case 'XLSX':
            default:
                switch (self::$xls_lib) {
                    case 'spout':
                        $filename .= '.xlsx';
                        $writer = \Box\Spout\Writer\Common\Creator\Writer_Factory::create_from_type(\Box\Spout\Common\Type::XLSX);
                        $default_style = (new \Box\Spout\Writer\Common\Creator\Style\Style_Builder())->set_font_name('Arial')->set_font_size(12)->build();
                        $writer->set_default_row_style($default_style);
                        if ($to_browser) {
                            $writer->open_to_browser($filename);
                        } else {
                            $writer->open_to_file($filename);
                        }
                    // no break
                    case 'phpoffice':
                        {
                            self::$sheet = new \Php_Office\Php_Spreadsheet\Spreadsheet();
                            $writer = self::$sheet->get_active_sheet();
                            break;
                        }
                }
                break;
        }
        return $writer;
    }
    public static function add_row_to_writer($writer, $row_array)
    {
        static $line = 1;
        switch (self::get_writer_type($writer)) {
            case 'csv':
                $writer->add_row($row_array);
                break;
            case 'spout':
                $writer->add_row(\Box\Spout\Writer\Common\Creator\Writer_Entity_Factory::create_row_from_array($row_array));
                break;
            case 'phpoffice':
                $writer->from_array([$row_array], null, 'A' . $line++);
                break;
        }
    }
    public static function finish_writer($writer)
    {
        switch (self::get_writer_type($writer)) {
            case 'csv':
                $writer->close();
                break;
            case 'spout':
                $writer->close();
                break;
            case 'phpoffice':
                $writer = new \Php_Office\Php_Spreadsheet\Writer\Xlsx(self::$sheet);
                if (self::$to_browser) {
                    header('Content-Type: application/vnd.ms-excel');
                    header('Content-Disposition: attachment;filename="' . self::$to_file . '"');
                    header('Cache-Control: max-age=0');
                    $writer->save('php://output');
                } else {
                    $writer->save(self::$to_file);
                }
                break;
        }
    }
    public static function use_php_office()
    {
        self::$xls_lib = 'phpoffice';
    }
    private static function get_writer_type($writer)
    {
        if ($writer instanceof \backend\models\EP\Formatter\CSV) {
            return 'csv';
        } else {
            return self::$xls_lib;
        }
    }
}