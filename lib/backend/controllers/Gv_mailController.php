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
namespace backend\controllers;

use common\services\Customers_Service;
use Yii;
/**
 * GV mail controller to handle user requests.
 */
class Gv_mail_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_HEADING_GV_ADMIN', 'BOX_GV_ADMIN_MAIL'];
    /** @var CustomersService */
    private $customers_service;
    public function __construct($id, $module, Customers_Service $customers_service, array $config = [])
    {
        parent::__construct($id, $module, $config);
        $this->customers_service = $customers_service;
    }
    public function before_action($action)
    {
        if (false === \common\helpers\Acl::check_extension_allowed('CouponsAndVauchers', 'allowed')) {
            $this->redirect(['/']);
            return false;
        }
        return parent::before_action($action);
    }
    public function action_index()
    {
        $languages_id = \Yii::$app->settings->get('languages_id');
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->selected_menu = ['marketing', 'gv_admin', 'gv_mail'];
        $this->navigation[] = ['link' => Yii::$app->url_manager->create_url('gv_mail/index'), 'title' => HEADING_TITLE];
        $this->view->heading_title = HEADING_TITLE;
        $cid = Yii::$app->request->get('cid', 0);
        $only = Yii::$app->request->get('only', 0);
        $customers = [];
        $customer = urldecode(isset($_GET['customer']) ? $_GET['customer'] : Yii::$app->request->get('mail_sent_to', 0));
        $customers_email_address = '';
        if ($only) {
            $mail_query = tep_db_query('select customers_email_address, customers_firstname, customers_lastname from ' . TABLE_CUSTOMERS . " where customers_id = '" . (int) $only . "'");
            while ($customers_values = tep_db_fetch_array($mail_query)) {
                $customers_email_address = $customers_values['customers_email_address'];
                $customers[] = ['id' => $customers_values['customers_email_address'], 'text' => $customers_values['customers_lastname'] . ', ' . $customers_values['customers_firstname'] . ' (' . $customers_values['customers_email_address'] . ')'];
            }
        } else {
            $customers[] = ['id' => '', 'text' => TEXT_SELECT_CUSTOMER];
            $customers[] = ['id' => '***', 'text' => TEXT_ALL_CUSTOMERS];
            /** @var \common\extensions\Subscribers\Subscribers $subscr  */
            if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
                $customers[] = ['id' => '**D', 'text' => TEXT_NEWSLETTER_CUSTOMERS];
            }
            $mail_query = tep_db_query('select customers_email_address, customers_firstname, customers_lastname from ' . TABLE_CUSTOMERS . ' order by customers_lastname');
            while ($customers_values = tep_db_fetch_array($mail_query)) {
                $customers[] = ['id' => $customers_values['customers_email_address'], 'text' => $customers_values['customers_lastname'] . ', ' . $customers_values['customers_firstname'] . ' (' . $customers_values['customers_email_address'] . ')'];
            }
        }
        $c_info = new \stdClass();
        $amount = 0;
        $currencies = Yii::$container->get('currencies');
        if ($cid) {
            $cc_query = tep_db_query('select coupon_id, coupon_code, coupon_amount, coupon_currency, coupon_type,  coupon_expire_date, (now() < coupon_expire_date or year(coupon_expire_date) in (0, 1970) ) as not_expired from ' . TABLE_COUPONS . " where coupon_id = '" . (int) $cid . "'");
            $cc_list = tep_db_fetch_array($cc_query);
            $c_info = new \Object_Info($cc_list);
            //echo '<pre>';print_r($cInfo);
            if ($c_info->coupon_type == 'P') {
                $amount = number_format($c_info->coupon_amount, 2) . '%';
            } else {
                $amount = $currencies->format($c_info->coupon_amount, false, $c_info->coupon_currency);
            }
        }
        $list = [];
        $type = Yii::$app->request->get('type', 'G');
        if (!($cid && $c_info->not_expired)) {
            $query = tep_db_query('select c.coupon_code, c.coupon_id, c.coupon_currency, c.coupon_amount, c.coupon_type, cd.coupon_name from ' . TABLE_COUPONS . ' c left join ' . TABLE_COUPONS_DESCRIPTION . " cd on cd.coupon_id=c.coupon_id and cd.language_id = '" . (int) $languages_id . "' where (now() < c.coupon_expire_date or year(c.coupon_expire_date) in (0, 1970) ) = 1 and c.coupon_active='Y' and c.coupon_for_recovery_email = 0 " . ($type == 'C' ? " and c.coupon_type != 'G'" : " and c.coupon_type = 'G'") . ' order by c.date_created');
            if (tep_db_num_rows($query)) {
                while ($row = tep_db_fetch_array($query)) {
                    if ($row['coupon_type'] == 'P') {
                        $amount = number_format($row['coupon_amount'], 2) . '%';
                    } else {
                        $amount = $currencies->format($row['coupon_amount'], false, $row['coupon_currency']);
                    }
                    $list[$row['coupon_id']] = $row['coupon_code'] . ' (' . $amount . ') ' . (!empty($row['coupon_name']) ? $row['coupon_name'] : '');
                }
            }
        }
        $messages = [];
        if (Yii::$app->session->has_flash('success')) {
            $messages['success'] = Yii::$app->session->get_flash('success');
        } elseif (Yii::$app->session->has_flash('error')) {
            $messages['danger'] = Yii::$app->session->get_flash('error');
        }
        Yii::$app->session->remove_all_flashes();
        if (Yii::$app->request->get('mail_sent_to')) {
            $messages['success'] = sprintf(NOTICE_EMAIL_SENT_TO, urldecode(Yii::$app->request->get('mail_sent_to')));
        }
        $params = ['cid' => $cid, 'customers' => $customers, 'amount' => $amount, 'cInfo' => $c_info, 'list' => $list, 'messages' => $messages, 'customer' => $customer, 'type' => $type, 'customers_email_address' => $customers_email_address];
        if (Yii::$app->request->is_ajax) {
            if (Yii::$app->request->get('mail_sent_to')) {
                return $this->render_ajax('index', $params);
            } else {
                return $this->render_ajax('index', $params);
            }
        } else {
            return $this->render('index', $params);
        }
    }
    public function action_preview()
    {
        \common\helpers\Translation::init('admin/gv_mail');
        /*
               $this->selectedMenu = array('marketing', 'gv_admin', 'gv_mail');
               $this->navigation[] = array('link' => Yii::$app->urlManager->createUrl('gv_mail/index'), 'title' => HEADING_TITLE);
        
               $this->view->headingTitle = HEADING_TITLE;
        
               if (($_POST['customers_email_address'] || $_POST['email_to'])) {
        
                   if (($_GET['action'] == 'preview') && (!$_POST['amount'] && !isset($_POST['coupon_id']))) {
                       Yii::$app->controller->view->errorMessage = ERROR_NO_AMOUNT_SELECTED;
                       Yii::$app->controller->view->errorMessageType = 'error';
                   }
        
                   switch ($_POST['customers_email_address']) {
                       case '***':
                           $mail_sent_to = TEXT_ALL_CUSTOMERS;
                           break;
                       case '**D':
                           $mail_sent_to = TEXT_NEWSLETTER_CUSTOMERS;
                           break;
                       default:
                           $mail_sent_to = $_POST['customers_email_address'];
                           if ($_POST['email_to']) {
                               $mail_sent_to = $_POST['email_to'];
                           }
                           break;
                   }
                   ob_start();
                   echo tep_draw_form('mail', 'gv_mail' . '/sendemailtouser');
        
                   if (strpos(Yii::$app->request->referrer, 'customers')) {
                       echo tep_draw_hidden_field('referrer', Yii::$app->request->getReferrer());
                       ?>
                       <script>
                           $('form[name=mail]').submit(function () {
                               $.post($(this).attr('action'),
                                       $('form[name=mail]').serialize(),
                                       function (data) {
                                           $('.pop-up-content').html(data);
                                       });
                               return false;
                           })
                       </script>
                       <?php
                   }
                   ?>
                   <table border="0" width="100%" cellpadding="0" cellspacing="2">
                       <tr>
                           <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                       </tr>
                       <tr>
                           <td class="smallText"><b><?php echo TEXT_CUSTOMER; ?></b><br><?php echo $mail_sent_to; ?></td>
                       </tr>
                       <tr>
                           <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                       </tr>
                       <tr>
                           <td class="smallText"><b><?php echo TEXT_FROM; ?></b><br><?php echo htmlspecialchars(tep_db_prepare_input($_POST['from'])); ?></td>
                       </tr>
                       <tr>
                           <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                       </tr>
                       <tr>
                           <td class="smallText"><b><?php echo TEXT_SUBJECT; ?></b><br><?php echo htmlspecialchars(tep_db_prepare_input($_POST['subject'])); ?></td>
                       </tr>
                       <tr>
                           <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                       </tr>
                       <tr>
                           <td class="smallText"><b><?php echo TEXT_AMOUNT; ?></b><br><?php echo nl2br(htmlspecialchars(tep_db_prepare_input($_POST['amount']))); ?></td>
                       </tr>
                       <tr>
                           <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                       </tr>
                       <tr>
                           <td class="smallText"><b>  <?php if (EMAIL_USE_HTML == 'true') {
                       echo (tep_db_prepare_input($_POST['message']));
                   } else {
                       echo htmlspecialchars(tep_db_prepare_input($_POST['message']));
                   } ?></td>
                       </tr>
                       <tr>
                           <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
                       </tr>
                       <tr>
                           <td>
                               <?php
        
                               reset($_POST);
                               while (list($key, $value) = each($_POST)) {
                                   if (!is_array($_POST[$key])) {
                                       echo tep_draw_hidden_field($key, htmlspecialchars(stripslashes($value)));
                                   }
                               }
                               ?>
                               <table border="0" width="100%" cellpadding="0" cellspacing="2">
                                   <tr>
        
                                   <tr>
                                       <td align="right"><?php echo '<a href="' . tep_href_link('gv_mail') . '" class="btn btn-cancel">' . IMAGE_CANCEL . '</a> <input type="submit" class="btn btn-primary" value="' . IMAGE_SEND_EMAIL . '">'; ?></td>
                                   </tr>
                                   <td class="smallText">
                   <?php if (EMAIL_USE_HTML == 'false') {
                       echo tep_image_submit--does-not-exist('button_back.gif', IMAGE_BACK, 'name="back"');
                   }
                   ?><?php if (EMAIL_USE_HTML == 'false') {
                       echo(TEXT_EMAIL_BUTTON_HTML);
                   } else {
                       echo(TEXT_EMAIL_BUTTON_TEXT);
                   }
                   ?>
                                   </td>
                       </tr>
                   </table></td>
                   </tr>
                   </table>
                   </form>
                   <?php
                   $buf = ob_get_contents();
                   ob_clean();
        
                   if (Yii::$app->request->isAjax) {
                       return $this->renderAjax('index', ['content' => $buf]);
                   } else {
                       return $this->render('index', ['content' => $buf]);
                   }
               } else {
                   Yii::$app->session->setFlash('error', 'Please define customer or group');
                   return $this->redirect('index');
               }
        *
        */
    }
    /**
     * @param string|null $term
     * @return \yii\web\Response
     */
    public function action_customer_search(string $term = null)
    {
        if ($term === '') {
            $term = null;
        }
        $predict = [['id' => '***', 'value' => TEXT_ALL_CUSTOMERS]];
        /** @var \common\extensions\Subscribers\Subscribers $subscr  */
        if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
            $predict[] = ['id' => '**D', 'value' => TEXT_NEWSLETTER_CUSTOMERS];
        }
        $customers = $this->customers_service->find_all_by_term_limit($term, true, 20, 0, true, ['customers_email_address as id', 'TRIM(CONCAT(customers_lastname, " ", customers_firstname, "(", customers_email_address, ")" )) as value']);
        $customers = array_merge($predict, $customers);
        return $this->as_json($customers);
    }
    public function action_sendemailtouser()
    {
        if (\Yii::$app->request->post('customers_email_address') && !\Yii::$app->request->post('back_x')) {
            $coupon_id = Yii::$app->request->post('coupon_id', 0);
            \common\helpers\Translation::init('admin/gv_mail');
            $error = false;
            if (\Yii::$app->request->post('amount') !== null && !is_numeric(\Yii::$app->request->post('amount'))) {
                $error = true;
                Yii::$app->session->set_flash('error', ERROR_COUPON_AMOUNT);
            }
            if ((\Yii::$app->request->post('customers_email_address') == '***' || \Yii::$app->request->post('customers_email_address') == '**D') && \common\helpers\Validations::validate_email(\Yii::$app->request->post('customers_email_address_field'))) {
                $_POST['customers_email_address'] = \Yii::$app->request->post('customers_email_address_field');
            }
            switch (\Yii::$app->request->post('customers_email_address')) {
                case '***':
                    $mail_query = tep_db_query('select customers_firstname, customers_lastname, customers_email_address, customers_id, platform_id, language_id from ' . TABLE_CUSTOMERS);
                    $mail_sent_to = TEXT_ALL_CUSTOMERS;
                    break;
                case '**D':
                    //select nothing if no subscribers extension
                    /** @var \common\extensions\Subscribers\Subscribers $subscr  */
                    if ($subscr = \common\helpers\Acl::check_extension_allowed('Subscribers', 'allowed')) {
                        $mail_query = $subscr::get_db_query(['where' => 'all_lists = 1']);
                    } else {
                        //!! where 0
                        $mail_query = tep_db_query('select customers_firstname, customers_lastname, customers_email_address, customers_id, platform_id, language_id from ' . TABLE_CUSTOMERS . " where 0 and customers_newsletter = '1'");
                    }
                    $mail_sent_to = TEXT_NEWSLETTER_CUSTOMERS;
                    break;
                default:
                    $customers_email_address = tep_db_prepare_input($_POST['customers_email_address']);
                    if ($customers_email_address) {
                        $mail_query = tep_db_query('select customers_firstname, customers_lastname, customers_email_address, customers_id, platform_id, language_id from ' . TABLE_CUSTOMERS . " where customers_email_address = '" . tep_db_input($customers_email_address) . "'");
                        $mail_sent_to = $_POST['customers_email_address'];
                    }
                    break;
            }
            if (!$error) {
                $subject = tep_db_prepare_input($_POST['subject']);
                $platforms = [];
                if (tep_db_num_rows($mail_query)) {
                    while ($mail = tep_db_fetch_array($mail_query)) {
                        $platform_config = \Yii::$app->get('platform')->config($mail['platform_id']);
                        $STORE_NAME = $platform_config->const_value('STORE_NAME');
                        $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                        $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                        if (!isset($platforms[$mail['platform_id']])) {
                            $platform_query = tep_db_fetch_array(tep_db_query('select default_currency from ' . TABLE_PLATFORMS . " where platform_id = '" . (int) $mail['platform_id'] . "'"));
                            $platforms[$mail['platform_id']] = $platform_query['default_currency'];
                            if (!tep_not_null($platforms[$mail['platform_id']])) {
                                $platforms[$mail['platform_id']] = DEFAULT_CURRENCY;
                            }
                        }
                        $data = \common\helpers\Coupon::generate_customer_gvcc($coupon_id, $mail['customers_email_address'], \Yii::$app->request->post('amount'), $platforms[$mail['platform_id']], $mail['customers_id']);
                        $email_params = [];
                        $email_params['STORE_NAME'] = $STORE_NAME;
                        $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('', '', 'NONSSL'));
                        $email_params['CUSTOMER_FIRSTNAME'] = $mail['customers_firstname'];
                        $email_params['CUSTOMER_LASTNAME'] = $mail['customers_lastname'];
                        $email_params['COUPON_CODE'] = $data['id1'];
                        $email_params['COUPON_NAME'] = $subject;
                        $email_params['COUPON_DESCRIPTION'] = $_POST['message'];
                        $email_params['COUPON_AMOUNT'] = $data['amount'];
                        $template = $data['type'] == 'C' ? 'Send coupon' : 'Send voucher';
                        [$email_subject, $email_text] = \common\helpers\Mail::get_parsed_email_template($template, $email_params, $mail['language_id'], $mail['platform_id']);
                        \common\helpers\Mail::send($mail['customers_firstname'] . ' ' . $mail['customers_lastname'], $mail['customers_email_address'], $email_subject, $email_text, $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                    }
                } elseif (isset($_POST['customers_email_address']) && !empty($_POST['customers_email_address'])) {
                    $data = \common\helpers\Coupon::generate_customer_gvcc($coupon_id, $_POST['customers_email_address'], $_POST['amount'], DEFAULT_CURRENCY);
                    $platform_id = \common\classes\platform::default_id();
                    $platform_config = \Yii::$app->get('platform')->config($platform_id);
                    $STORE_NAME = $platform_config->const_value('STORE_NAME');
                    $STORE_OWNER_EMAIL_ADDRESS = $platform_config->const_value('STORE_OWNER_EMAIL_ADDRESS');
                    $STORE_OWNER = $platform_config->const_value('STORE_OWNER');
                    $email_params = [];
                    $email_params['STORE_NAME'] = $STORE_NAME;
                    $email_params['STORE_URL'] = \common\helpers\Output::get_clickable_link(tep_catalog_href_link('', '', 'NONSSL'));
                    $email_params['COUPON_CODE'] = $data['id1'];
                    $email_params['COUPON_NAME'] = $subject;
                    $email_params['COUPON_DESCRIPTION'] = $_POST['message'];
                    $email_params['COUPON_AMOUNT'] = $data['amount'];
                    $template = $data['type'] == 'C' ? 'Send coupon' : 'Send voucher';
                    list($email_subject, $email_text) = \common\helpers\Mail::get_parsed_email_template($template, $email_params, -1, $platform_id);
                    \common\helpers\Mail::send('', $_POST['customers_email_address'], $email_subject, $email_text, '', $STORE_OWNER, $STORE_OWNER_EMAIL_ADDRESS);
                }
            }
            if (Yii::$app->request->is_ajax) {
                if (Yii::$app->session->has_flash('success')) {
                    $content = Yii::$app->session->get_flash('success');
                } elseif (Yii::$app->session->has_flash('error')) {
                    $content = Yii::$app->controller->view->error_message = Yii::$app->session->get_flash('error');
                }
                Yii::$app->session->remove_all_flashes();
                if ($mail_sent_to) {
                    $content = sprintf(NOTICE_EMAIL_SENT_TO, urldecode($mail_sent_to));
                }
                echo json_encode(['message' => $content, 'messageType' => 'success']);
                exit;
            } else if (!$error) {
                return $this->redirect(Yii::$app->url_manager->create_url(['gv_mail', 'mail_sent_to' => urlencode($mail_sent_to), 'cid' => $coupon_id]));
            } else {
                return $this->redirect(Yii::$app->url_manager->create_url(['gv_mail', 'customer' => $_POST['customers_email_address']]));
            }
        }
    }
}