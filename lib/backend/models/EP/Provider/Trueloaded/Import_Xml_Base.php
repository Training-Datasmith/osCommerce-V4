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
use backend\models\EP\Provider\Datasource_Interface;
use Yii;
abstract class Import_Xml_Base implements Datasource_Interface
{
    protected $feed;
    protected $provider_class;
    protected $row_count = 0;
    protected $config = [];
    /**
     * @var Reader
     */
    protected $reader_obj;
    /**
     * @var Provider
     */
    protected $provider_obj;
    public function __construct($config)
    {
        $this->config = $config;
    }
    public function allow_run_in_popup()
    {
        return true;
    }
    public function get_progress()
    {
        return $this->reader_obj->get_progress();
    }
    public function prepare_process(Messages $message)
    {
        if (empty($this->feed)) {
            throw new \Exception('XML feed is not defined');
        }
        $url = $this->config['base_url'] . '?feed=' . urlencode($this->feed);
        $secure_method = $this->config['secure_method'] ?? null;
        $secure_key = trim($this->config['secure_key'] ?? '');
        $stream_context_params = ['http' => ['timeout' => 1200]];
        switch ($secure_method) {
            case 'get':
                $stream_context_params['http']['method'] = 'GET';
                $url .= '&key=' . $secure_key;
                break;
            case 'post':
                $stream_context_params['http']['method'] = 'POST';
                $stream_context_params['http']['content'] = 'key=' . $secure_key . '&feed=' . urlencode($this->feed);
                break;
            case 'bearer':
                $stream_context_params['http']['header'] = 'Authorization: Bearer ' . $secure_key;
                break;
            default:
                throw new \Exception('Secure method is invalid: ' . $secure_method);
        }
        if (YII_ENV == 'dev') {
            // disable cheking self-signed cert
            $stream_context_params['ssl'] = ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true];
        }
        // download XML feed to working folder
        copy($url, $this->config['workingDirectory'] . urlencode($this->feed) . '.xml', stream_context_create($stream_context_params));
        if (empty($this->provider_class)) {
            throw new \Exception('Provider Class is not defined');
        }
        $this->provider_obj = new $this->provider_class(['job_configure' => isset($this->job_configure['import']) && is_array($this->job_configure['import']) ? $this->job_configure['import'] : []]);
        $reader_config = array_merge(['class' => 'backend\models\EP\Reader\XML', 'filename' => $this->config['workingDirectory'] . urlencode($this->feed) . '.xml'], isset($this->job_configure['import']) && is_array($this->job_configure['import']) ? $this->job_configure['import'] : []);
        foreach ($this->provider_obj->exchange_xml() as $version_info) {
            $reader_config = array_merge($version_info, $reader_config);
        }
        $this->reader_obj = Yii::create_object($reader_config);
        // Clear local data
        if (method_exists($this->provider_obj, 'clearLocalData')) {
            $this->provider_obj->clear_local_data();
        }
    }
    public function process_row(Messages $message)
    {
        if ($data = $this->reader_obj->read()) {
            $this->provider_obj->import_row($data, $message);
            $this->row_count++;
            return true;
        }
        return false;
    }
    public function post_process(Messages $message)
    {
        if ($this->row_count > 0) {
            $message->info('Row(s) Imported: ' . $this->row_count);
        }
    }
}