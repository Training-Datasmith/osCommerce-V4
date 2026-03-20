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

use common\components\google\Google_Printers;
use Yii;
class Printers
{
    public static function get_config_path()
    {
        return Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR;
    }
    /**
     * setup alone job to first found printer
     * @param type $platform_id
     * @param type $title
     * @param type $job
     * @param type $mime
     * @return boolean
     */
    public static function set_file_to_queue($platform_id, $title, $job, $mime = 'text/html')
    {
        if ($platform_id) {
            $service = \common\models\Cloud_Services::find()->alias('s')->where(['s.platform_id' => $platform_id])->join_with(['printers p'])->one();
            if ($service) {
                $config_dir = Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR;
                $file = $config_dir . $service->key;
                $google_printers = new Google_Printers($file);
                if ($google_printers) {
                    $processed = self::push_job_to_printer($google_printers, $service->printers[0]->cloud_printer_id, $title, $job, $mime);
                    //echo'<pre>';print_r($processed);
                    return $processed['success'];
                }
            }
        }
        return false;
    }
    /**
     * setup job to Cloud printer via Google Printers environment
     * @param \common\components\google\GooglePrinters $googlePrinters
     * @param type $cloud_printer_id
     * @param type $title
     * @param type $content
     * @param type $mime
     * @return type
     */
    public static function push_job_to_printer(Google_Printers $google_printers, $cloud_printer_id, $title, $content, $mime = 'text/html')
    {
        $g_job = $google_printers->create_job($cloud_printer_id);
        $g_job->set_title($title);
        $g_job->set_content_type($mime);
        $response = $google_printers->process_job($content);
        return $response;
    }
    /**
     * set Jobs to Printers
     * @param type $documentName
     * @param type $platform_id
     * @param array $job - array([$title => 'Title', 'content' => 'Print Content', 'mime' => 'text/html'], []), content may be public link to file with mime => 'url'
     */
    public static function set_jobs_to_optimal_queue($document_name, $platform_id, array $jobs)
    {
        $response = [];
        if ($platform_id) {
            $service = \common\models\Cloud_Services::find()->alias('s')->where(['s.platform_id' => $platform_id])->inner_join_with(['printers p' => function (\yii\db\Active_Query $query) use ($document_name) {
                $query->inner_join_with(['documents d' => function ($query) use ($document_name) {
                    $query->on_condition(['d.document_name' => $document_name]);
                }]);
            }])->one();
            //may be some services - need additional correlation about printing ways between services
            if ($service) {
                $config_dir = Yii::get_alias('@common') . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'google' . DIRECTORY_SEPARATOR;
                $file = $config_dir . $service->key;
                $google_printers = new Google_Printers($file);
                if ($google_printers) {
                    if (is_array($service->printers)) {
                        //may be some printers for one document - need corellation about printers adjustment
                        if (!is_array($jobs[0])) {
                            $jobs = [$jobs];
                        }
                        if (count($jobs) <= count($service->printers)) {
                            //one job to one printer
                            foreach ($jobs as $index => $job) {
                                $job['mime'] = $job['mime'] ? $job['mime'] : 'text/html';
                                $job_response = self::push_job_to_printer($google_printers, $service->printers[$index]->cloud_printer_id, $job['title'], $job['content'], $job['mime']);
                                $response[$index] = $job_response;
                            }
                        } elseif (count($jobs) > count($service->printers)) {
                            //spread jobs between printers
                            foreach ($jobs as $index => $job) {
                                $printer_id = self::get_next_printer($service->printers)->cloud_printer_id;
                                $job['mime'] = $job['mime'] ? $job['mime'] : 'text/html';
                                $job_response = self::push_job_to_printer($google_printers, $printer_id, $job['title'], $job['content'], $job['mime']);
                                $response[$index] = $job_response;
                            }
                        }
                    }
                }
            }
        }
        return $response;
    }
    public static function get_next_printer(array $printers_pool)
    {
        static $index = 0;
        $printer = $printers_pool[$index];
        $index++;
        if (!isset($printers_pool[$index])) {
            $index = 0;
        }
        return $printer;
    }
}