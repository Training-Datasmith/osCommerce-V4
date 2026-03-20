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
namespace common\helpers;

class Mail
{
    public static $design_template = '';
    public static $template_params = [];
    public static function get_parsed_email_template($template_key, $email_params = '', $language_id = -1, $platform_id = -1, $aff_id = -1, $design_template = '')
    {
        global $languages_id;
        if ($platform_id == -1) {
            $platform_id = \common\classes\platform::current_id();
        } else {
            $platform_id = \common\classes\platform::valid_id($platform_id);
        }
        if (is_array($email_params) && !isset($email_params['NEW_PASSWORD_SENTENCE'])) {
            $email_params['NEW_PASSWORD_SENTENCE'] = \common\helpers\Php8::get_const('TEXT_NEW_PASSWORD_SENTENCE', 'Your new password is:');
        }
        $data_query = tep_db_query('select ett.email_templates_subject, ett.email_templates_body, et.email_templates_id from ' . TABLE_EMAIL_TEMPLATES . ' et, ' . TABLE_EMAIL_TEMPLATES_TEXTS . " ett where et.email_templates_id = ett.email_templates_id and et.email_templates_key = '" . tep_db_input($template_key) . "' and ett.language_id = '" . (int) ($language_id > 0 ? $language_id : $languages_id) . "' and ett.affiliate_id = '" . (int) ($aff_id >= 0 ? $aff_id : 0) . "' and et.email_template_type = '" . (EMAIL_USE_HTML != 'true' ? 'plaintext' : 'html') . "' and ett.platform_id = '" . $platform_id . "'");
        $data = tep_db_fetch_array($data_query);
        if (empty($data['email_templates_subject']) || empty($data['email_templates_body'])) {
            $data_query = tep_db_query('select ett.email_templates_subject, ett.email_templates_body from ' . TABLE_EMAIL_TEMPLATES . ' et, ' . TABLE_EMAIL_TEMPLATES_TEXTS . " ett where et.email_templates_id = ett.email_templates_id and et.email_templates_key = '" . tep_db_input($template_key) . "' and ett.language_id = '" . (int) ($language_id > 0 ? $language_id : $languages_id) . "' and ett.affiliate_id = '" . (int) ($aff_id >= 0 ? $aff_id : 0) . "' and et.email_template_type = '" . (EMAIL_USE_HTML != 'true' ? 'plaintext' : 'html') . "' and ett.platform_id = '" . \common\classes\platform::real_default_id() . "'");
            $default_data = tep_db_fetch_array($data_query);
            if (empty($data['email_templates_subject'])) {
                $data['email_templates_subject'] = $default_data['email_templates_subject'];
            }
            if (empty($data['email_templates_body'])) {
                $data['email_templates_body'] = $default_data['email_templates_body'];
            }
        }
        if ($design_template) {
            self::$design_template = $design_template;
        } else {
            $email_template = \common\models\Email_Templates_To_Design_Template::find_one(['email_templates_id' => $data['email_templates_id'], 'platform_id' => $platform_id]);
            if ($email_template instanceof \common\models\Email_Templates_To_Design_Template) {
                self::$design_template = $email_template->email_design_template;
            }
        }
        $params = ['STORE_NAME' => '', 'STORE_URL' => '', 'HTTP_HOST' => '', 'STORE_OWNER_EMAIL_ADDRESS' => '', 'CUSTOMER_EMAIL' => '', 'CUSTOMER_FIRSTNAME' => '', 'CUSTOMER_LASTNAME' => '', 'CUSTOMER_NAME' => '', 'NEW_PASSWORD' => '', 'USER_GREETING' => '', 'ORDER_NUMBER' => '', 'ORDER_DATE_LONG' => '', 'ORDER_DATE_SHORT' => '', 'BILLING_ADDRESS' => '', 'DELIVERY_ADDRESS' => '', 'PAYMENT_METHOD' => '', 'ORDER_COMMENTS' => '', 'NEW_ORDER_STATUS' => '', 'ORDER_TOTALS' => '', 'PRODUCTS_ORDERED' => '', 'ORDER_INVOICE_URL' => '', 'COUPON_AMOUNT' => '', 'COUPON_NAME' => '', 'COUPON_DESCRIPTION' => '', 'COUPON_CODE' => '', 'TRACKING_NUMBER' => '', 'TRACKING_NUMBER_URL' => '', 'STORE_TESTIMONIALS_URL' => '', 'PRODUCTS_ORDERED_REVIEW' => '', 'STORE_OWNER' => ''];
        foreach (['email_templates_subject', 'email_templates_body'] as $key_index) {
            if (preg_match_all('/##([A-Z_0-9]+)##/', $data[$key_index], $other_keys)) {
                foreach ($other_keys[1] as $other_key) {
                    if (!isset($params[$other_key])) {
                        $params[$other_key] = '';
                    }
                }
            }
        }
        if (\frontend\design\Info::is_totally_admin()) {
            $params['STORE_URL'] = rtrim(tep_catalog_href_link('/', '', 'SSL', false), '/') . '/';
        } else {
            $params['STORE_URL'] = rtrim(tep_href_link('/', '', 'SSL', false), '/') . '/';
        }
        if (is_array($email_params)) {
            foreach ($email_params as $key => $value) {
                $params[$key] = $value;
            }
        }
        //// i'd rather fill in store-related params (STORE_NAME, host etc) here (1 time) and not before compose of each mail
        $params = self::fill_store_related_keys($params, $platform_id, $language_id > 0 ? $language_id : $languages_id);
        self::$template_params = $params;
        $data['email_templates_body'] = preg_replace_callback('/{{([^}]+)}}/', self::class . '::removeEmptyKeysBox', $data['email_templates_body']);
        if (is_array($params) && count($params) > 0) {
            $escape_params = ['CUSTOMER_FIRSTNAME', 'CUSTOMER_LASTNAME', 'CUSTOMER_NAME', 'CUSTOMER_EMAIL'];
            $patterns = [];
            $replace = [];
            foreach ($params as $k => $v) {
                $v = addcslashes($v, '\$');
                $patterns[] = '(##' . preg_quote($k) . '##)';
                if (in_array($k, $escape_params)) {
                    $replace[] = Html::encode($v);
                } else {
                    $replace[] = $v;
                }
            }
            $data['email_templates_subject'] = preg_replace($patterns, $replace, $data['email_templates_subject']);
            $data['email_templates_body'] = preg_replace($patterns, $replace, $data['email_templates_body']);
        }
        return [$data['email_templates_subject'], $data['email_templates_body']];
    }
    public static function remove_empty_keys_box($matches)
    {
        $str = preg_replace_callback('/##([A-Za-z0-9_]+)##/', self::class . '::removeEmptyKeys', $matches[0]);
        if (strlen($str) == strlen($matches[0])) {
            return $matches[1];
        } else {
            return '';
        }
    }
    public static function fill_store_related_keys($keys, $platform_id, $languages_id)
    {
        static $cache = [];
        $platform_config = \Yii::$app->get('platform')->config($platform_id);
        foreach ($keys as $key => $val) {
            // skip not empty  and not store related
            if (!empty($val) || !in_array($key, ['STORE_TESTIMONIALS_URL', 'STORE_NAME', 'STORE_OWNER_EMAIL_ADDRESS', 'STORE_OWNER', 'HTTP_HOST'])) {
                continue;
            }
            if (isset($cache[$platform_id . '_' . $languages_id][$key])) {
                $keys[$key] = $cache[$platform_id . '_' . $languages_id][$key];
                continue;
            }
            $v = '';
            try {
                switch ($key) {
                    case 'STORE_TESTIMONIALS_URL':
                        if (defined('FILENAME_TESTIMONIALS')) {
                            $link = FILENAME_TESTIMONIALS;
                        } else {
                            $link = 'testimonials';
                        }
                        if (function_exists('tep_catalog_href_link')) {
                            $v = \common\helpers\Output::get_clickable_link(tep_catalog_href_link($link, '', 'SSL'));
                        } else {
                            $v = \common\helpers\Output::get_clickable_link(tep_href_link($link, '', 'SSL'));
                        }
                        break;
                    case 'STORE_NAME':
                        $v = $platform_config->const_value('STORE_NAME');
                        break;
                    case 'STORE_OWNER_EMAIL_ADDRESS':
                        $v = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                        break;
                    case 'STORE_OWNER':
                        $v = $platform_config->const_value('STORE_OWNER');
                        break;
                    case 'HTTP_HOST':
                        if (function_exists('tep_catalog_href_link')) {
                            $v = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('', '', 'SSL'));
                        } else {
                            $v = \common\helpers\Output::get_clickable_link(tep_href_link('', '', 'SSL'));
                        }
                        break;
                }
            } catch (\Exception $e) {
                \Yii::warning(' #### ' . print_r($e->get_message(), 1), 'TLDEBUG');
            }
            if (!empty($v)) {
                $keys[$key] = $cache[$platform_id . '_' . $languages_id][$key] = $v;
            }
        }
        return $keys;
    }
    public static function remove_empty_keys($matches)
    {
        if (strlen(self::$template_params[$matches[1]]) > 1) {
            return $matches[0];
        } else {
            return '';
        }
    }
    public static function email_templates_list()
    {
        $orders_status_template = [];
        $orders_status_templates_query = tep_db_query('select * from ' . TABLE_EMAIL_TEMPLATES . ' where 1 group by email_templates_key');
        while ($email_templates = tep_db_fetch_array($orders_status_templates_query)) {
            $name_key = 'TEXT_EMAIL_' . str_replace(' ', '_', strtoupper($email_templates['email_templates_key']));
            $email_templates_key = defined($name_key) ? constant($name_key) : $email_templates['email_templates_key'];
            $orders_status_template[$email_templates['email_templates_key']] = $email_templates_key;
        }
        return $orders_status_template;
    }
    public static function get_email_templates_body($email_templates_id, $language_id, $platform_id = -1)
    {
        if ($platform_id == -1) {
            $platform_id = \common\classes\platform::current_id();
        }
        $data_query = tep_db_query('select email_templates_body from ' . TABLE_EMAIL_TEMPLATES_TEXTS . " where email_templates_id = '" . (int) $email_templates_id . "' and language_id = '" . (int) $language_id . "' and platform_id = '" . $platform_id . "'");
        $data = tep_db_fetch_array($data_query);
        return $data['email_templates_body'] ?? null;
    }
    public static function get_email_templates_subject($email_templates_id, $language_id, $platform_id = -1)
    {
        if ($platform_id == -1) {
            $platform_id = \common\classes\platform::current_id();
        }
        $data_query = tep_db_query('select email_templates_subject from ' . TABLE_EMAIL_TEMPLATES_TEXTS . " where email_templates_id = '" . (int) $email_templates_id . "' and language_id = '" . (int) $language_id . "' and platform_id = '" . $platform_id . "'");
        $data = tep_db_fetch_array($data_query);
        return $data['email_templates_subject'] ?? null;
    }
    public static function get_email_design_templates($email_templates_id, $platform_id = -1)
    {
        if ($platform_id == -1) {
            $platform_id = \common\classes\platform::current_id();
        }
        $email_design_template = \common\models\Email_Templates_To_Design_Template::find_one(['email_templates_id' => $email_templates_id, 'platform_id' => $platform_id])->email_design_template ?? null;
        $theme_id = \common\models\Platforms_To_Themes::find_one($platform_id)->theme_id;
        $theme_name = \common\models\Themes::find_one(['id' => $theme_id])->theme_name ?? null;
        $design_templates = \common\models\Themes_Settings::find()->select(['setting_value'])->where(['theme_name' => $theme_name, 'setting_group' => 'added_page', 'setting_name' => 'email'])->as_array()->all();
        $list = [];
        $list[] = ['name' => '', 'title' => 'Main', 'active' => $email_design_template ? true : false, 'theme_name' => $theme_name];
        foreach ($design_templates as $design_template) {
            $active = false;
            $template_name = \common\classes\design::page_name($design_template['setting_value']);
            if ($template_name == $email_design_template) {
                $active = true;
            }
            $list[] = ['name' => $template_name, 'title' => $design_template['setting_value'], 'active' => $active, 'theme_name' => $theme_name];
        }
        return $list;
    }
    /**
     * @param \common\classes\extended\OrderAbstract $order
     * @return array
     * @throws \yii\base\InvalidConfigException
     */
    public static function email_params_from_order($order)
    {
        /**
         * @var platform_config $platform_config
         */
        $_keep_platform_id = \Yii::$app->get('platform')->config()->get_id();
        $_keep_language = \Yii::$app->settings->get('languages_id');
        $_keep_language_locale = \Yii::$app->settings->get('locale');
        $platform_config = \Yii::$app->get('platform')->config($order->info['platform_id']);
        $email_params = [];
        $email_params['STORE_NAME'] = $platform_config->const_value('STORE_NAME');
        if (\frontend\design\Info::is_totally_admin()) {
            $email_params['STORE_URL'] = rtrim(tep_catalog_href_link('/', '', 'SSL', false), '/') . '/';
        } else {
            $email_params['STORE_URL'] = rtrim(tep_href_link('/', '', 'SSL', false), '/') . '/';
        }
        $email_params['ORDER_NUMBER'] = method_exists($order, 'getOrderNumber') ? $order->get_order_number() : $order->order_id;
        $TEXT_VIEW = \common\helpers\Translation::get_translation_value('IMAGE_VIEW', 'admin/main', $order->info['language_id']);
        if (function_exists('tep_catalog_href_link')) {
            $email_params['ORDER_INVOICE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('account/history-info', 'order_id=' . $order->order_id, 'SSL'), $TEXT_VIEW);
        } else {
            $email_params['ORDER_INVOICE_URL'] = \common\helpers\Output::get_clickable_link(tep_href_link('account/history-info', 'order_id=' . $order->order_id, 'SSL'), $TEXT_VIEW);
        }
        $email_language = new \common\classes\language(\common\classes\language::get_code($order->info['language_id']));
        \Yii::$app->settings->set('locale', $email_language->language['locale']);
        $email_language->set_locale();
        $formats = $email_language->get_language_formats($order->info['language_id']);
        $keep_currency = \Yii::$app->settings->get('currency');
        \Yii::$app->settings->set('currency', $order->info['currency']);
        $DATE_FORMAT_LONG = isset($formats['DATE_FORMAT_LONG']) ? $formats['DATE_FORMAT_LONG'] : (defined('DATE_FORMAT_LONG') ? DATE_FORMAT_LONG : '');
        $DATE_FORMAT_SHORT = isset($formats['DATE_FORMAT_SHORT']) ? $formats['DATE_FORMAT_SHORT'] : (defined('DATE_FORMAT_SHORT') ? DATE_FORMAT_SHORT : '');
        if (!defined('DATE_FORMAT_SHORT')) {
            define('DATE_FORMAT_SHORT', isset($formats['DATE_FORMAT_SHORT']) ? $formats['DATE_FORMAT_SHORT'] : '%d %b %Y');
        }
        $email_params['ORDER_DATE_LONG'] = \common\helpers\Date::date_long($order->info['date_purchased'], $DATE_FORMAT_LONG);
        $email_params['ORDER_DATE_SHORT'] = \common\helpers\Date::date_short($order->info['date_purchased'], $DATE_FORMAT_SHORT);
        $email_params['DELIVERY_ADDRESS'] = '';
        $email_params['BILLING_ADDRESS'] = \common\helpers\Address::address_format($order->billing['format_id'], $order->billing, 0, '', '<br>');
        $email_params['DELIVERY_ADDRESS'] = '';
        if ($order->content_type != 'virtual') {
            $email_params['DELIVERY_ADDRESS'] = \common\helpers\Address::address_format($order->delivery['format_id'], $order->delivery, 0, '', '<br>');
        }
        $email_params['PAYMENT_METHOD'] = $order->info['payment_method'];
        $email_params['SHIPPING_METHOD'] = $order->info['shipping_method'];
        $email_params['ORDER_COMMENTS'] = '';
        if (isset($order->info['order_status'])) {
            $email_params['NEW_ORDER_STATUS'] = \common\helpers\Order::get_order_status_name($order->info['order_status'], $order->info['language_id']);
        } elseif (isset($order->info['orders_status'])) {
            $email_params['NEW_ORDER_STATUS'] = \common\helpers\Order::get_order_status_name($order->info['orders_status'], $order->info['language_id']);
        }
        $email_params['CUSTOMER_FIRSTNAME'] = $order->customer['firstname'];
        $email_params['CUSTOMER_LASTNAME'] = $order->customer['lastname'];
        $email_params['CUSTOMER_NAME'] = $order->customer['name'];
        $email_params['CUSTOMER_EMAIL'] = $order->customer['email_address'];
        $email_params['PRODUCTS_ORDERED'] = $order->get_products_html_for_email();
        $email_params['ORDER_TOTALS'] = $order->get_order_totals_html_for_email();
        \Yii::$app->get('platform')->config($_keep_platform_id);
        \Yii::$app->settings->set('locale', $_keep_language_locale);
        $email_language->set_locale();
        if ($keep_currency) {
            \Yii::$app->settings->set('currency', $keep_currency);
        }
        return $email_params;
    }
    /**
    * parses email template and sends email.
    * @param string $to_name
    * @param string $to_email_address
    * @param string $email_subject
    * @param string $email_text
    * @param string $from_email_name
    * @param string $from_email_address
    * @param array $email_params
    * @param array|string $headers
    * @param array $attachment [
                                 'name' => $filename,
                                 'file' => file_get_contents($filename)
                               ]
    * @return boolean
    */
    public static function send($to_name, $to_email_address, $email_subject, $email_text, $from_email_name, $from_email_address, $email_params = [], $headers = '', $attachments = false, $settings = [])
    {
        if (SEND_EMAILS != 'true') {
            return false;
        }
        if (empty($to_email_address)) {
            return false;
        }
        $to_email_array = [];
        foreach (is_array($to_email_address) ? $to_email_address : explode(',', trim($to_email_address)) as $email) {
            $email = trim($email);
            if ($email != '') {
                if (!preg_match('/\<.+\@.+\>/', $email)) {
                    $email = "<{$email}>";
                }
                $to_email_array[] = $email;
            }
        }
        unset($email);
        $to_email_address = implode(',', $to_email_array);
        unset($to_email_array);
        try {
            $message = \common\modules\email\Transport::get_transport();
            if (!is_object($message)) {
                throw new \Exception('Could not create a message object: ' . var_export($message, true));
            }
            $text = strip_tags(preg_replace('/<br( \/)?>/ims', "\n", $email_text));
            if (EMAIL_USE_HTML == 'true') {
                if (!@$settings['add_br']) {
                    $email_text = str_replace(["\r\n", "\n", "\r"], '<br>', $email_text);
                    if (strip_tags($email_text, '<a>') != $email_text) {
                        //from template
                        $email_text = preg_replace('#(<br */?>\s*){3,}#i', '<br><br>', $email_text);
                    }
                }
                $theme_name = \backend\design\Theme::get_theme_name($settings['platform_id'] ?? \common\classes\platform::current_id());
                $contents = self::get_email_content(self::$design_template ?? '', $theme_name);
                $contents = str_replace(["\r\n", "\n", "\r"], '', $contents);
                if (is_array($email_params) && empty($email_params['STORE_URL'])) {
                    if (\frontend\design\Info::is_totally_admin()) {
                        $email_params['STORE_URL'] = rtrim(tep_catalog_href_link('/', '', 'SSL', false), '/') . '/';
                    } else {
                        $email_params['STORE_URL'] = rtrim(tep_href_link('/', '', 'SSL', false), '/') . '/';
                    }
                }
                $email_subject = str_replace('$', '/$/', $email_subject);
                $email_text = str_replace('$', '/$/', $email_text);
                $search = ["'##EMAIL_TITLE##'i", "'##EMAIL_TEXT##'i"];
                $replace = [$email_subject, $email_text];
                if (is_array($email_params) && count($email_params) > 0) {
                    foreach ($email_params as $key => $value) {
                        $search[] = "'##" . $key . "##'i";
                        $replace[] = $value;
                    }
                }
                $email_text = str_replace('/$/', '$', preg_replace($search, $replace, $contents));
                if (function_exists('tep_catalog_href_link')) {
                    $_tmp_site_url = parse_url(tep_catalog_href_link('link'));
                } else {
                    $_tmp_site_url = parse_url(tep_href_link('link'));
                }
                $HOST = $_tmp_site_url['scheme'] . '://' . $_tmp_site_url['host'];
                $PATH = rtrim(substr($_tmp_site_url['path'], 0, strpos($_tmp_site_url['path'], 'link')), '/');
                $email_text = preg_replace('/(<img[^>]+src=)"\/([^"]+)"/i', '$1"' . $HOST . '/$2"', $email_text);
                $email_text = preg_replace('/(<img[^>]+src=)"(?![a-z]{3,5}:)([^"]+)"/i', '$1"' . $HOST . $PATH . '/$2"', $email_text);
                //VL generally [a-z]{3,5} could be replaced either with https? (images by http(s) protocol in emails) or [a-z][a-z0-9\-+.]+ (by any protocol)
                $has_tag_p = preg_match('/<p>/', $email_text) ? true : false;
                if ($has_tag_p) {
                    $email_text = str_replace(["\r\n", "\n", "\r"], '', $email_text);
                    $email_text = preg_replace("/(<\\/p>)(<br[\\s\\/]*>)(<p>)?/mi", '$1$3', $email_text);
                }
                $message->add_html($email_text, $text);
            } else {
                $message->add_text($text);
            }
            if (is_array($attachments)) {
                foreach ($attachments as $attachment) {
                    if (!empty($attachment['file']) && !empty($attachment['name'])) {
                        $message->add_attachment($attachment['file'], $attachment['name']);
                    }
                }
            }
            $message->build_message();
            // {{ admin bcc
            if (defined('ALL_EMAIL_BCC_COPY') && trim(ALL_EMAIL_BCC_COPY) != '') {
                $message->add_bcc(trim(ALL_EMAIL_BCC_COPY));
            }
            // }} admin bcc
            $err_msg = '';
            $res = (int) $message->send($to_name, $to_email_address, $from_email_name, $from_email_address, $email_subject, $headers) > 0;
            if (!$res) {
                $err_msg = 'Unknown error';
                \Yii::warning(sprintf('Email was not sent. Unknown error while sending email to %s <%s> from %s <%s>', $to_name, $to_email_address, $from_email_name, $from_email_address));
            }
        } catch (\Throwable $e) {
            $err_msg = 'Error: ' . $e->get_message();
            \Yii::warning(sprintf("Email was not sent. Error while sending email to %s <%s> from %s <%s>: %s\n %s", $to_name, $to_email_address, $from_email_name, $from_email_address, $e->get_message(), $e->get_trace_as_string()));
            $res = false;
        }
        foreach (\common\helpers\Hooks::get_list('email/after-sending') as $filename) {
            include $filename;
        }
        if (!($ext = \common\helpers\Extensions::is_allowed('ReportEmailsHistory')) || method_exists($ext, 'saveEmail')) {
            return $res;
        }
        //add to EmailsHistory
        /** @var \common\extensions\ReportEmailsHistory\models\EmailsHistory $model */
        if ($model = \common\helpers\Acl::check_extension_table_exist('ReportEmailsHistory', 'EmailsHistory')) {
            if (is_array($email_params)) {
                foreach (['NEW_PASSWORD', 'PASSWORD_INVALID', 'SECURITY_KEY'] as $field) {
                    if (isset($email_params[$field]) and trim($email_params[$field]) != '') {
                        $email_text = str_replace($email_params[$field], '[HIDDEN]', $email_text);
                    }
                }
                unset($field);
            }
            try {
                $emails_history = new $model();
                $emails_history->load_default_values();
                $emails_history->to_name = $to_name;
                $emails_history->to_email_address = $to_email_address;
                $emails_history->from_email_name = $from_email_name;
                $emails_history->from_email_address = $from_email_address;
                $emails_history->email_subject = $email_subject;
                /*preg_match('/(security)|(password)/i', $email_subject, $matches);
                  if (count($matches) > 0) {
                      $email_text = 'Hidden';
                  }
                  if (preg_match('/password\s*(is|:)/i', $email_text)) {
                      $email_text = 'Hidden';
                  }*/
                $emails_history->email_text = $email_text;
                $emails_history->headers = is_array($headers) ? serialize($headers) : $headers;
                $emails_history->date_sent = date('Y-m-d H:i:s');
                if (\Yii::$app->db->get_table_schema($emails_history->table_name(), true)->get_column('sending_result') !== null) {
                    $emails_history->sending_result = (int) !$res;
                    // 0 - success
                    $emails_history->sending_error_msg = $err_msg;
                }
                $emails_history->save(false);
            } catch (\Exception $exc) {
                \Yii::warning($exc->get_message() . ' ' . $exc->get_trace_as_string(), 'ErrorMailSendEmailHistorySave');
            }
        }
        return $res;
    }
    public static function send_plain($to_name, $to_email_address, $email_subject, $email_text, $from_email_name, $from_email_address, $email_params = [], $headers = '')
    {
        if (SEND_EMAILS != 'true') {
            return false;
        }
        try {
            $message = \common\modules\email\Transport::get_transport();
        } catch (\Exception $e) {
            echo $e->get_message();
        }
        $message->add_text($email_text);
        $message->build_message();
        // {{ admin bcc
        if (defined('ALL_EMAIL_BCC_COPY') && trim(ALL_EMAIL_BCC_COPY) != '') {
            $message->add_bcc(trim(ALL_EMAIL_BCC_COPY));
        }
        // }} admin bcc
        return (int) $message->send($to_name, $to_email_address, $from_email_name, $from_email_address, $email_subject, $headers) > 0;
    }
    public static function get_type_list($with_all = false)
    {
        global $languages_id;
        $orders_status_type = [];
        if ($with_all) {
            $orders_status_type[0] = TEXT_ALL;
        }
        $orders_status_types_query = tep_db_query("select type_id, type_name from email_template_types where language_id = '" . (int) $languages_id . "'");
        while ($orders_status_types = tep_db_fetch_array($orders_status_types_query)) {
            $orders_status_type[$orders_status_types['type_id']] = $orders_status_types['type_name'];
        }
        return $orders_status_type;
    }
    public static function get_sms_type_list($with_all = false)
    {
        $orders_status_type = [];
        if ($with_all) {
            $orders_status_type[0] = TEXT_ALL;
        }
        return $orders_status_type;
    }
    public static function get_sms_templates_body($sms_templates_id, $language_id, $platform_id = -1)
    {
        if ($platform_id == -1) {
            $platform_id = \common\classes\platform::current_id();
        }
        $sms_templates_texts_record = \common\models\Sms_Templates_Texts::find()->where(['sms_templates_id' => (int) $sms_templates_id])->and_where(['language_id' => (int) $language_id])->and_where(['platform_id' => (int) $platform_id])->as_array(true)->one();
        return isset($sms_templates_texts_record['sms_templates_body']) ? $sms_templates_texts_record['sms_templates_body'] : '';
    }
    public static function get_sms_template_parsed($template_key, $email_params = '', $language_id = -1, $platform_id = -1, $affiliate_id = -1)
    {
        global $languages_id;
        $template_key = trim($template_key);
        $language_id = (int) $language_id;
        $platform_id = (int) $platform_id;
        $affiliate_id = (int) $affiliate_id;
        if ($platform_id == -1) {
            $platform_id = \common\classes\platform::current_id();
        } else {
            $platform_id = \common\classes\platform::valid_id($platform_id);
        }
        $sms_template_array = \common\models\Sms_Templates_Texts::find()->alias('stt')->left_join(\common\models\Sms_Templates::table_name() . ' st', 'stt.sms_templates_id = st.sms_templates_id')->where(['st.sms_templates_key' => $template_key])->and_where(['stt.platform_id' => $platform_id])->and_where(['stt.language_id' => (int) ($language_id > 0 ? $language_id : $languages_id)])->and_where(['stt.affiliate_id' => (int) ($affiliate_id > 0 ? $affiliate_id : 0)])->as_array(true)->one();
        $params = ['STORE_NAME' => '', 'HTTP_HOST' => '', 'STORE_OWNER_EMAIL_ADDRESS' => '', 'CUSTOMER_EMAIL' => '', 'CUSTOMER_FIRSTNAME' => '', 'NEW_PASSWORD' => '', 'USER_GREETING' => '', 'ORDER_NUMBER' => '', 'ORDER_DATE_LONG' => '', 'ORDER_DATE_SHORT' => '', 'BILLING_ADDRESS' => '', 'DELIVERY_ADDRESS' => '', 'PAYMENT_METHOD' => '', 'ORDER_COMMENTS' => '', 'NEW_ORDER_STATUS' => '', 'ORDER_TOTALS' => '', 'PRODUCTS_ORDERED' => '', 'ORDER_INVOICE_URL' => '', 'COUPON_AMOUNT' => '', 'COUPON_NAME' => '', 'COUPON_DESCRIPTION' => '', 'COUPON_CODE' => '', 'TRACKING_NUMBER' => '', 'TRACKING_NUMBER_URL' => ''];
        if (is_array($email_params)) {
            foreach ($email_params as $key => $value) {
                $params[$key] = $value;
            }
        }
        self::$template_params = $params;
        $sms_template_array['sms_templates_body'] = preg_replace_callback('/{{([^}]+)}}/', self::class . '::removeEmptyKeysBox', $sms_template_array['sms_templates_body'] ?? null);
        if (is_array($params) && count($params) > 0) {
            $patterns = [];
            $replace = [];
            foreach ($params as $k => $v) {
                $patterns[] = '(##' . preg_quote($k) . '##)';
                $replace[] = str_replace('$', '/$/', $v);
            }
            $sms_template_array['sms_templates_body'] = str_replace('/$/', '$', preg_replace($patterns, $replace, $sms_template_array['sms_templates_body']));
        }
        return $sms_template_array['sms_templates_body'];
    }
    /**
     * Copy email template texts from source platform (default if not set) to other
     *
     * @param $target_platform_id
     * @param int $source_platform_id
     * @throws \yii\db\Exception
     */
    public static function copy_platform_emails($target_platform_id, $source_platform_id = 0)
    {
        if (empty($source_platform_id)) {
            $source_platform_id = \common\classes\platform::default_id();
        }
        if ($target_platform_id == 0 || (int) $source_platform_id == (int) $target_platform_id) {
            return;
        }
        \common\models\Email_Templates_Texts::delete_all(['platform_id' => $target_platform_id]);
        foreach (\common\models\Email_Templates_Texts::find()->where(['platform_id' => (int) $source_platform_id])->as_array()->batch(50) as $email_texts) {
            foreach (array_keys($email_texts) as $_idx) {
                $email_texts[$_idx]['platform_id'] = $target_platform_id;
            }
            \common\models\Email_Templates_Texts::get_db()->create_command()->batch_insert(\common\models\Email_Templates_Texts::table_name(), array_keys($email_texts[0]), $email_texts)->execute();
        }
    }
    public static function get_email_content($page_name, $theme_name)
    {
        defined('THEME_NAME') or define('THEME_NAME', $theme_name);
        if (!defined('BASE_URL')) {
            if (function_exists('tep_catalog_href_link')) {
                $url = tep_catalog_href_link('');
            } else {
                $url = tep_href_link('');
            }
            define('BASE_URL', $url);
        }
        if (empty($page_name)) {
            $page_name = \common\classes\design::page_name('email');
        }
        try {
            $res = \frontend\design\Block::widget(['name' => $page_name, 'params' => ['type' => 'email', 'params' => ['absoluteUrl' => true, 'theme_name' => $theme_name, 'page_block' => 'email']]]);
        } catch (\Throwable $e) {
            \Yii::warning(__FUNCTION__ . ' : ' . $e->get_message() . "\n" . $e->get_trace_as_string());
        }
        return empty($res) ? '##EMAIL_TEXT##' : $res;
    }
}