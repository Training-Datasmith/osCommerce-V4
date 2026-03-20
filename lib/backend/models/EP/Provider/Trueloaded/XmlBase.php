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

use backend\models\EP\Messages;
use backend\models\EP\Provider\Export_Interface;
use backend\models\EP\Provider\Import_Interface;
use backend\models\EP\Provider\Provider_Abstract;
use common\api\models\XML\Io_Attachment;
use common\api\models\XML\Io_Core;
use common\api\models\XML\Io_Data;
use common\api\models\XML\Project;
use common\api\models\XML\Related_Serialize;
use common\api\models\XML\Xm_Lto_Data_Parser;
use yii\db\Active_Query;
use yii\db\Batch_Query_Result;
class Xml_Base extends Provider_Abstract implements Import_Interface, Export_Interface
{
    /**
     * @var BatchQueryResult
     */
    protected $batch_query;
    protected $process_queue = [];
    /**
     * @var RelatedSerialize
     */
    protected $serializer;
    /**
     * @var  XMLtoDataParser
     */
    protected $xml_parser;
    protected $configure_map = [];
    private $first_write = true;
    /**
     * @var ActiveQuery
     */
    protected $active_query;
    protected $with_images = false;
    public $job_configure;
    public function init()
    {
        $this->first_write = true;
        $this->xml_parser = new Xm_Lto_Data_Parser();
        $this->xml_parser->set_configure_map($this->configure_map);
        $this->serializer = new Related_Serialize();
        $this->serializer->set_configure_map($this->configure_map);
        Project::check_local_projects();
        Io_Core::get();
        if (is_array($this->job_configure) && isset($this->job_configure['import'])) {
            if (!empty($this->job_configure['import']['projectCode'])) {
                Io_Core::get()->set_project_by_code($this->job_configure['import']['projectCode']);
            }
        }
        if ($this->directory_obj) {
            $this->set_images_directory($this->directory_obj->files_root());
        }
        \common\api\models\XML\Project::check_local_projects();
        $Data = $this->configure_map['Data'];
        $collection = key($Data);
        $this->active_query = $collection::find()->where([]);
        if (!empty($Data[$collection]['where'])) {
            $this->active_query->and_where($Data[$collection]['where']);
        }
        if (!empty($Data[$collection]['orderBy'])) {
            $this->active_query->order_by($Data[$collection]['orderBy']);
        }
        parent::init();
    }
    public function set_images_directory($images_folder)
    {
        $this->import_folder = $images_folder;
        Io_Core::get()->append_location('@attachment_root', $this->import_folder);
    }
    public function clear_local_data()
    {
        if (is_array($this->configure_map['covered_tables'] ?? null)) {
            foreach ($this->configure_map['covered_tables'] as $table) {
                tep_db_query('TRUNCATE TABLE ' . $table);
            }
        }
    }
    public function exchange_xml()
    {
        $root_config = current($this->configure_map['Data']);
        list($rows_tag, $row_tag) = explode('>', $root_config['xmlCollection'], 2);
        $header = $this->configure_map['Header'];
        if (!is_array($header)) {
            $header = ['type' => $header];
        }
        return [['Header' => $header, 'rowsTag' => $rows_tag, 'rowTag' => $row_tag, 'importData' => 'SimpleXml']];
    }
    public function prepare_export($use_columns, $filter)
    {
        Io_Core::get()->set_project_id(1);
        if (is_array($filter)) {
            if (isset($filter['projectId']) && $filter['projectId'] > 0) {
                Io_Core::get()->set_project_id((int) $filter['projectId']);
            }
            $this->with_images = isset($filter['with_images']) && $filter['with_images'];
            if ($this->with_images) {
                Io_Core::get()->set_attachment_mode(['attach_file']);
            }
        }
        //echo $this->activeQuery->createCommand()->rawSql; die;
        $this->batch_query = $this->active_query->each();
        $this->batch_query->rewind();
    }
    public function export_row()
    {
        $data = $this->batch_query->current();
        if (is_object($data)) {
            $this->batch_query->next();
            $collection_config = current($this->configure_map['Data']);
            list($_dummy, $element_tag) = explode('>', $collection_config['xmlCollection'], 2);
            $iodata = $this->serializer->export_model($data, $collection_config);
            $write_data = [':xmlConfig' => [], ':feed_data' => []];
            if ($this->first_write) {
                $write_data[':xmlConfig'] = current($this->exchange_xml());
                if (empty($write_data[':xmlConfig']['Header']['projectCode'])) {
                    $write_data[':xmlConfig']['Header']['projectCode'] = Io_Core::get()->get_project_code();
                }
            }
            foreach ($iodata->get_attachment_list() as $io_attachment) {
                /**
                 * @var IOAttachment $IOAttachment
                 */
                if ($file = $io_attachment->get_attachment_file_name()) {
                    if (!isset($write_data[':attachments'])) {
                        $write_data[':attachments'] = [];
                    }
                    $in_archive_name = 'images/' . ($io_attachment->archive_file_name ? $io_attachment->archive_file_name : $io_attachment->value);
                    // {{ themes archive hack
                    if (strpos($io_attachment->value, '/') === 0) {
                        $in_archive_name = 'images/' . substr($io_attachment->value, strrpos($io_attachment->value, '/'));
                    }
                    // }} themes archive hack
                    $write_data[':attachments'][] = ['filename' => $file, 'localname' => $in_archive_name];
                    $io_attachment->attach_file = $in_archive_name;
                }
            }
            $write_data[':feed_data'][0] = Io_Data::serialize_to_simple_xml($iodata, $element_tag);
            return $write_data;
        }
        return false;
    }
    public function import_row($data, Messages $message)
    {
        if (!$data instanceof \Simple_Xml_Element) {
            return;
        }
        $io_data = $this->xml_parser->make_io_data($data);
        $process_model = key($this->configure_map['Data']);
        $this->serializer->import_model($process_model, $io_data, current($this->configure_map['Data']));
    }
    public function post_process(Messages $message)
    {
    }
}