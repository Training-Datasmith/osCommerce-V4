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

class Io_Gallery_Attachment extends Io_Attachment
{
    public $record;
    public $archive_file_name;
    public static function can_use_archive_name($name, $physical_file)
    {
        static $pool = [];
        $check_file_sha1 = '';
        if (isset($pool[$name])) {
            if (empty($pool[$name]['sha1'])) {
                $pool[$name]['sha1'] = sha1_file($pool[$name]['file']);
            }
            $check_file_sha1 = sha1_file($physical_file);
            if ($check_file_sha1 !== $pool[$name]['sha1']) {
                return false;
            } else {
                return true;
            }
        }
        $pool[$name] = ['file' => $physical_file, 'sha1' => $check_file_sha1];
        return true;
    }
    public function get_attachment_file_name()
    {
        $attachment_file_name = parent::get_attachment_file_name();
        if (!empty($this->value) && $attachment_file_name) {
            $this->archive_file_name = $this->value;
            if (is_object($this->record) && !empty($this->record->orig_file_name)) {
                $this->archive_file_name = $this->record->orig_file_name;
                if (!static::can_use_archive_name($this->archive_file_name, $attachment_file_name)) {
                    $this->archive_file_name = implode('_', $this->record->get_primary_key(true)) . '_' . $this->record->orig_file_name;
                }
            }
        }
        return $attachment_file_name;
    }
}