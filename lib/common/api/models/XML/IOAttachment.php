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
namespace common\api\models\XML;

use yii\helpers\File_Helper;
class Io_Attachment extends Complex
{
    public $location = '';
    public $attach_file;
    public $url;
    public $checksum_sha1;
    public $checksum_md5;
    public $limit_mode = [];
    public $options = [];
    public function get_attachment_file_name()
    {
        if (!empty($this->value)) {
            $physical_file = Io_Core::get()->get_local_location($this->location . '/' . $this->value);
            if (is_file($physical_file)) {
                return $physical_file;
            }
        }
        return false;
    }
    public function get_attachment_mode_variants()
    {
        return [
            /*'value',*/
            'attach_file',
            'file_info',
            'url',
            'inline',
            'checksum_md5',
            'checksum_sha1',
        ];
    }
    public function serialize_to(\Simple_Xml_Element $parent)
    {
        if (!empty($this->value)) {
            //$parent->url = \Yii::getAlias($this->uri.$this->value);
            //$parent->file = \Yii::getAlias($this->path.$this->value);
            if (!(isset($this->options['noValue']) && $this->options['noValue'] === true)) {
                $parent->value = $this->value;
            }
            $physical_file = Io_Core::get()->get_local_location($this->location . '/' . $this->value);
            if (is_file($physical_file) && is_array($this->limit_mode)) {
                if (isset($this->options['mergeLimitOptions']) && is_array($this->options['mergeLimitOptions'])) {
                    $work_modes = array_unique(array_merge($this->options['mergeLimitOptions'], count($this->limit_mode) > 0 ? $this->limit_mode : Io_Core::get()->get_attachment_modes()));
                } else {
                    $work_modes = count($this->limit_mode) > 0 ? $this->limit_mode : Io_Core::get()->get_attachment_modes();
                }
                if (in_array('attach_file', $work_modes)) {
                    $parent->attach_file = empty($this->attach_file) ? $physical_file : $this->attach_file;
                }
                if (in_array('url', $work_modes)) {
                    $parent->url = Io_Core::get()->get_public_location($this->location . '/' . $this->value);
                }
                if (in_array('checksum_md5', $work_modes)) {
                    $parent->checksum_md5 = md5_file($physical_file);
                }
                if (in_array('checksum_sha1', $work_modes)) {
                    $parent->checksum_sha1 = sha1_file($physical_file);
                }
                if (in_array('inline', $work_modes)) {
                    $parent->inline = base64_encode(file_get_contents($physical_file));
                }
                if (in_array('file_info', $work_modes)) {
                    $parent->filesize = filesize($physical_file);
                    $parent->last_modified = date('c', filemtime($physical_file));
                    if (function_exists('mime_content_type')) {
                        $parent->content_type = mime_content_type($physical_file);
                        if (strpos($parent->content_type, 'image/') === 0) {
                            $image_info = @getimagesize($physical_file);
                            $parent->image_width = $image_info[0];
                            $parent->image_height = $image_info[1];
                        }
                    }
                }
            }
        }
    }
    public static function restore_from(\Simple_Xml_Element $node, $obj)
    {
        if (trim($node->value) !== '' || trim($node->attach_file) !== '' || trim($node->url) !== '' || trim($node->inline) !== '') {
            if (!is_object($obj) || !$obj instanceof Complex) {
                $obj = Io_Core::create_object('IOAttachment');
            }
            $obj->value = strval($node->value);
            if (trim($node->attach_file) !== '') {
                $obj->attach_file = trim($node->attach_file);
                //if ( empty($obj->value) ) $obj->value = basename($obj->attach_file);
            } elseif (trim($node->inline) !== '') {
                $source_file = tempnam(Io_Core::get()->get_local_location('@attachment_root/'), basename(strval($node->value)));
                $node->attach_file = $source_file;
                @file_put_contents($source_file, base64_decode($node->inline));
            }
            if (trim($node->url) !== '') {
                $obj->url = trim($node->url);
                //if ( empty($obj->value) ) $obj->value = basename($obj->url);
            }
            if (trim($node->checksum_md5) != '') {
                $obj->checksum_md5 = trim($node->checksum_md5);
            }
            if (trim($node->checksum_sha1) != '') {
                $obj->checksum_sha1 = trim($node->checksum_sha1);
            }
            return $obj;
        }
        return '';
    }
    public function to_import_model()
    {
        $source_file = '';
        /*if ( !empty($this->attach_file) ) {
              $sourceFile = IOCore::get()->getLocalLocation('@attachment_root/'.$this->attach_file);
          }else*/
        if (!empty($this->url)) {
            $source_file = $this->url;
        }
        if (!empty($source_file)) {
            if (empty($this->value)) {
                $this->value = basename($source_file);
            }
            if (isset($this->options) && !empty($this->options['importVia']) && $this->options['importVia'] === 'File') {
                $_file_params = ['sourceFile' => $source_file];
                if ($this->checksum_sha1) {
                    $_file_params['checksum_sha1'] = $this->checksum_sha1;
                }
                return new \common\models\File\Upload($_file_params);
            } else {
                $physical_file = Io_Core::get()->get_local_location($this->location . '/' . $this->value);
                if (!is_dir(dirname($physical_file))) {
                    try {
                        File_Helper::create_directory(dirname($physical_file), 0777);
                        @copy($source_file, $physical_file);
                        @chmod($physical_file, 0666);
                    } catch (\Exception $ex) {
                    }
                } else {
                    @copy($source_file, $physical_file);
                    @chmod($physical_file, 0666);
                }
            }
        }
        return parent::to_import_model();
    }
}