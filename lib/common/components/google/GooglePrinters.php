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

use Google\Google_Client;
class Google_Printers
{
    private $client;
    private $http_client;
    private $privacy_key = null;
    private $source;
    private static $scopes = ['https://www.googleapis.com/auth/cloudprint'];
    private $error_code;
    private $error_message;
    public function __construct($config_file)
    {
        try {
            $this->privacy_key = json_decode(file_get_contents($config_file), true);
        } catch (\Exception $ex) {
            throw new \Exception('Configuration file ' . basename($config_file) . ' could not be loaded');
        }
        try {
            $this->client = new Google_Client();
            $this->client->set_application_name('Google Printers');
            $this->client->use_application_default_credentials();
            $this->client->set_auth_config($this->privacy_key);
            $this->client->add_scope(self::$scopes);
            $this->http_client = $this->client->authorize();
        } catch (\Exception $ex) {
            $this->error_message = $ex->get_message();
        }
        //echo '<pre>';var_dump($this->httpClient);die;
    }
    public function get_error()
    {
        return ['errorCode' => $this->error_code, 'message' => $this->error_message];
    }
    public function invalid_client()
    {
        $this->error_code = 400;
        $this->error_message = 'Invalid Request';
        return false;
    }
    /**
     * Return printers list
     * @return array with iDs as key
     */
    public function search_printers()
    {
        $params = ['type' => 'DOCS'];
        if (!is_object($this->http_client)) {
            return $this->invalid_client();
        }
        $response = $this->http_client->post('https://www.google.com/cloudprint/search', ['form_params' => $params]);
        $response = json_decode($response->get_body(), true);
        if ($response['errorCode']) {
            $this->error_code = $response['errorCode'];
            $this->error_message = $response['message'];
            return false;
        } else {
            return $this->represent_printers($response['printers']);
        }
    }
    public function represent_printers($printers)
    {
        if (is_array($printers)) {
            $tmp = [];
            foreach ($printers as $printer) {
                if ($printer['type'] == 'DOCS') {
                    continue;
                }
                $tmp[$printer['id']] = $printer['name'] . ' (' . $printer['connectionStatus'] . ')';
            }
            $printers = $tmp;
        }
        return $printers;
    }
    /**
     * Get Printer Params by Cloud Pinter Id
     * @param type $id
     * @return array params| false
     */
    public function get_printer_description($id)
    {
        $printer = $this->get_printer($id);
        if ($printer) {
            return $this->descibe_printer($printer);
        }
        return false;
    }
    protected function descibe_printer($cloud_printer)
    {
        $data = ['orient' => [], 'formats' => [], 'dpi' => []];
        if (is_array($cloud_printer)) {
            if (is_array($cloud_printer['capabilities']['printer'])) {
                $info = $cloud_printer['capabilities']['printer'];
                if (is_array($info['page_orientation']['option'])) {
                    foreach ($info['page_orientation']['option'] as $option) {
                        $data['orient'][] = $option['type'];
                    }
                }
                if (is_array($info['media_size']['option'])) {
                    foreach ($info['media_size']['option'] as $option) {
                        $data['formats'][] = $option['custom_display_name'] . ' (' . ceil($option['width_microns'] / 1000) . 'x' . ceil($option['height_microns'] / 1000) . ' mm)';
                    }
                }
                if (is_array($info['color']['option'])) {
                    foreach ($info['color']['option'] as $option) {
                        $data['dpi'][] = $option['type'];
                    }
                }
            }
        }
        return $data;
    }
    /**
     * GetPrinter by unique ID
     * @param type $id
     * @return printer details| false
     */
    public function get_printer($id)
    {
        $params = ['printerid' => $id];
        $response = $this->http_client->post('https://www.google.com/cloudprint/printer', ['form_params' => $params]);
        $response = json_decode($response->get_body(), true);
        if ($response['errorCode']) {
            $this->error_code = $response['errorCode'];
            $this->error_message = $response['message'];
            return false;
        } else {
            return $response['printers'][0];
        }
    }
    /**
     * accept inviting printer for service account
     * @param type $id - printerId
     * @return type
     */
    public function get_printer_invite($id)
    {
        $params = ['printerid' => $id, 'accept' => true];
        $response = $this->http_client->post('https://www.google.com/cloudprint/processinvite', ['form_params' => $params]);
        return json_decode($response->get_body(), true);
    }
    public function get_jobs($id)
    {
        $params = ['printerid' => $id];
        $response = $this->http_client->post('https://www.google.com/cloudprint/jobs', ['form_params' => $params]);
        $response = json_decode($response->get_body(), true);
        if ($response['errorCode']) {
            $this->error_code = $response['errorCode'];
            $this->error_message = $response['message'];
            return false;
        } else {
            return $response['jobs'];
        }
    }
    private $job;
    public function create_job($id)
    {
        $this->job = new Google_Printer_Job($id);
        return $this->job;
    }
    /**
     * submit job to printer
     * @param type $content
     * @return reponse|boolean
     */
    public function process_job($content)
    {
        if (!$this->job) {
            throw new \Exception('Job is not defined');
        }
        $params = ['printerid' => $this->job->get_printer_id(), 'title' => $this->job->get_title(), 'ticket' => $this->job->get_ticket(), 'content' => $content];
        if ($type = $this->job->get_content_type()) {
            $params['contentType'] = $type;
        }
        $response = $this->http_client->post('https://www.google.com/cloudprint/submit', ['form_params' => $params]);
        if ($response->get_status_code() == 200) {
            $response = json_decode($response->get_body(), true);
            if ($response['job']) {
                $this->job->set_last_job($response['job']);
            }
            return $response;
        } else {
            $this->error_code = $response->get_status_code();
            $this->error_message = $response->get_reason_phrase();
            return false;
        }
    }
    public function get_last_job()
    {
        if (is_object($this->job)) {
            return $this->job->get_last_job();
        }
        return false;
    }
}