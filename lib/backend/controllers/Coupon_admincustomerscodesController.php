<?php

namespace backend\controllers;

use common\models\Coupons;
use common\models\Coupons_Customer_Codes_List;
use Yii;
/**
 * default controller to handle user requests.
 */
class Coupon_admincustomerscodes_Controller extends Sceleton
{
    public $acl = ['BOX_HEADING_MARKETING_TOOLS', 'BOX_HEADING_GV_ADMIN', 'BOX_COUPON_ADMIN'];
    public function before_action($action)
    {
        if (false === \common\helpers\Acl::check_extension_allowed('CouponsAndVauchers', 'allowed')) {
            $this->redirect(['/']);
            return false;
        }
        return parent::before_action($action);
    }
    public function action_coupon_csv_loaded()
    {
        global $language;
        \common\helpers\Translation::init('admin/coupon_admin');
        $this->layout = false;
        $cid = Yii::$app->request->get('cid', '');
        $ajax_list_url = Yii::$app->url_manager->create_url(['/coupon_admincustomerscodes/list', 'cid' => $cid]);
        $ajax_edit_url = Yii::$app->url_manager->create_url(['/coupon_admincustomerscodes/edit', 'cid' => $cid]);
        $ajax_save_url = Yii::$app->url_manager->create_url(['/coupon_admincustomerscodes/save', 'cid' => $cid]);
        $ajax_delete_url = Yii::$app->url_manager->create_url(['/coupon_admincustomerscodes/delete', 'cid' => $cid]);
        $ajax_delete_all_url = Yii::$app->url_manager->create_url(['/coupon_admincustomerscodes/deleteall', 'cid' => $cid]);
        $messages = $_SESSION['messages'];
        unset($_SESSION['messages']);
        return $this->render('../coupon_admin/customers_coupons', ['ajaxListUrl' => $ajax_list_url, 'ajaxEditUrl' => $ajax_edit_url, 'ajaxSaveUrl' => $ajax_save_url, 'ajaxDeleteUrl' => $ajax_delete_url, 'ajaxDeleteAllUrl' => $ajax_delete_all_url, 'cid' => $cid, 'couponUsed' => $this->get_coupon_used_times($cid, '')]);
    }
    public function get_coupon_used_times($cid, $email)
    {
        if (empty($email)) {
            $count_redemptions = tep_db_fetch_array(tep_db_query('select count(*) as cnt from ' . TABLE_COUPON_REDEEM_TRACK . " where coupon_id = '" . $cid . "'"));
            $coupon_used = $count_redemptions['cnt'];
        } else {
            $coupon_used = \common\helpers\Coupon::coupon_used_by($cid, $email);
        }
        return $coupon_used;
    }
    public function action_list()
    {
        global $languages_id;
        \common\helpers\Translation::init('admin/coupon_admin');
        $draw = Yii::$app->request->get('draw');
        $start = Yii::$app->request->get('start');
        $length = Yii::$app->request->get('length');
        $cid = Yii::$app->request->get('cid');
        $records_total_count = Coupons_Customer_Codes_List::find()->where('coupon_id=' . (int) $cid)->count();
        //$recordsTotal = CouponsCustomerCodesList::find()->orderBy('valid_from DESC')->all();
        $records = Coupons_Customer_Codes_List::find()->where('coupon_id=' . (int) $cid);
        if (isset($_GET['search']['value']) && !empty($_GET['search']['value'])) {
            $keywords = $_GET['search']['value'];
            $records->and_where(" only_for_customer LIKE '%" . $keywords . "%' OR coupon_code LIKE '%" . $keywords . "%' ");
        }
        if (isset($_GET['order'][0]['column']) && $_GET['order'][0]['dir']) {
            switch ($_GET['order'][0]['column']) {
                case 0:
                    $order_by = 'only_for_customer ' . $_GET['order'][0]['dir'];
                    break;
                case 1:
                    $order_by = 'coupon_code ' . $_GET['order'][0]['dir'];
                    break;
                case 2:
                    $order_by = 'date_added ' . $_GET['order'][0]['dir'];
                    break;
                default:
                    $order_by = 'only_for_customer DESC';
                    break;
            }
        } else {
            $order_by = 'only_for_customer DESC';
        }
        $records_filtered_count = $records->count();
        $records_total = $records->order_by($order_by)->all();
        //        echo "<pre>";
        //        print_r($records->createCommand()->sql);
        //        echo "</pre>";
        //$couponUsed = (int)$this->getCouponUsedTimes($cid);
        $response_list = [];
        foreach ($records_total as $key => $record) {
            $coupon_used = (int) $this->get_coupon_used_times($cid, $record->only_for_customer);
            $response_list[] = ['only_for_customer' => $coupon_used > 0 ? $record->only_for_customer : '<a href="javascript:void(0);" onClick="return editCustomersCoupon(' . $record->customercode_id . ', ' . $record->coupon_id . ')" title="' . COUPON_CUSTOMERS_COUPONS_EDIT . '">' . $record->only_for_customer . '</i></a>', 'coupon_code' => $record->coupon_code, 'date_added' => !is_null($record->date_added) ? date('d-m-Y', strtotime($record->date_added)) : ' - - - - - - - '];
        }
        $response = ['draw' => $draw, 'recordsTotal' => $records_total_count, 'recordsFiltered' => $records_filtered_count, 'data' => $response_list];
        echo json_encode($response);
    }
    public function action_edit()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        $cid = Yii::$app->request->post('cid');
        $customers_coupon_record_id = (int) Yii::$app->request->post('u_id');
        $ajax_save_url = (int) Yii::$app->url_manager->create_url(['/coupon_admincustomerscodes/save', 'cid' => $cid, 'customercode_id' => $customers_coupon_record_id]);
        $customers_coupont_record = new \stdClass();
        if ($customers_coupon_record_id > 0) {
            $order_name_record = Coupons_Customer_Codes_List::find()->where(['customercode_id' => $customers_coupon_record_id])->one();
            if ($order_name_record !== null) {
                $customers_coupont_record->id = $order_name_record->customercode_id;
                $customers_coupont_record->cid = $order_name_record->coupon_id;
                $customers_coupont_record->only_for_customer = $order_name_record->only_for_customer;
                $customers_coupont_record->coupon_code = $order_name_record->coupon_code;
            } else {
                ?>
<div class="alert alert-error fade in">
    <i data-dismiss="alert" class="icon-remove close"></i>
    Customers coupon record is not valid!
</div>
<?php 
                die;
            }
        } else {
            $customers_coupont_record->id = 0;
            $customers_coupont_record->cid = $order_name_record->coupon_id;
            $customers_coupont_record->only_for_customer = '';
            $customers_coupont_record->coupon_code = '';
        }
        return $this->render_ajax('@backend/themes/basic/coupon_admin/customers_coupons_edit.tpl', ['messages' => [], 'cid' => $cid, 'customersCouponRecordId' => $customers_coupon_record_id, 'customersCoupontRecord' => $customers_coupont_record, 'ajaxSaveUrl' => $ajax_save_url]);
    }
    public function action_save()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        $cid = Yii::$app->request->post('cid');
        $customers_coupon_record_id = (int) Yii::$app->request->post('id');
        $only_for_customer = trim(Yii::$app->request->post('only_for_customer'));
        $coupon_code = Yii::$app->request->post('coupon_code', '');
        if (trim($coupon_code) === '') {
            $coupon_code = \common\helpers\Coupon::create_coupon_code();
        }
        if (!$cid || !$only_for_customer) {
            echo json_encode(['message' => COUPON_REQUIRED_FIELDS_EMPTY]);
            exit;
        }
        $validator = new \yii\validators\Email_Validator();
        if (!$validator->validate($only_for_customer)) {
            echo json_encode(['message' => COUPON_EMAIL_IS_NOT_VALID]);
            exit;
        }
        if (!$customers_coupon_record_id) {
            $coupons_customer_codes_list = Coupons_Customer_Codes_List::find()->where(['only_for_customer' => $only_for_customer, 'coupon_id' => $cid])->one();
            //attempt to save one more record for the existing email
            if ($coupons_customer_codes_list) {
                echo json_encode(['message' => COUPON_EMAIL_DUPLICATE]);
                exit;
            }
            $coupons_customer_codes_list = new Coupons_Customer_Codes_List();
        } else {
            $coupons_customer_codes_list = Coupons_Customer_Codes_List::find()->where(['customercode_id' => $customers_coupon_record_id, 'only_for_customer' => $only_for_customer, 'coupon_id' => $cid, 'coupon_code' => trim($coupon_code)])->one();
            //attempt to save the same record
            if ($coupons_customer_codes_list) {
                echo json_encode(['message' => 'ok']);
                exit;
            }
            $coupons_customer_codes_list = Coupons_Customer_Codes_List::find()->where(['customercode_id' => $customers_coupon_record_id, 'coupon_id' => $cid])->one();
            if ($this->get_coupon_used_times($cid, $coupons_customer_codes_list->only_for_customer)) {
                echo json_encode(['message' => TEXT_COUPON_REDEEMED]);
                exit;
            }
            $check = Coupons::get_coupon_by_code($coupon_code);
            if ($check && $coupons_customer_codes_list->coupon_code != trim($coupon_code)) {
                echo json_encode(['message' => TEXT_COUPON_INCORRECT_DUPLICATE_CODE]);
                exit;
            }
        }
        $coupons_customer_codes_list->date_added = date('Y-m-d H:i:s');
        $coupons_customer_codes_list->coupon_id = $cid;
        $coupons_customer_codes_list->only_for_customer = $only_for_customer;
        $coupons_customer_codes_list->coupon_code = trim($coupon_code);
        //$CouponsCustomerCodesList->validate();
        //var_dump($CouponsCustomerCodesList->errors);
        if ($coupons_customer_codes_list->save()) {
            echo json_encode(['message' => 'ok']);
        } else {
            echo json_encode(['message' => COUPON_CANNOT_PROCESS]);
            exit;
        }
        exit;
    }
    public function action_delete()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        if (Yii::$app->request->post() == false) {
            return false;
        }
        $customers_coupon_record_id = (int) Yii::$app->request->post('id');
        $cid = (int) Yii::$app->request->post('cid');
        if (!$customers_coupon_record_id || !$cid) {
            echo json_encode(['message' => COUPON_CANNOT_PROCESS]);
            exit;
        }
        $coupons_customer_codes_list = Coupons_Customer_Codes_List::find()->where(['customercode_id' => $customers_coupon_record_id, 'coupon_id' => $cid])->one();
        if ($coupons_customer_codes_list && $coupons_customer_codes_list instanceof Coupons_Customer_Codes_List) {
            if ($coupons_customer_codes_list->delete()) {
                echo json_encode(['message' => 'ok']);
            } else {
                echo json_encode(['message' => COUPON_CANNOT_PROCESS]);
            }
        } else {
            echo json_encode(['message' => COUPON_CANNOT_PROCESS]);
        }
        exit;
    }
    public function action_deleteall()
    {
        \common\helpers\Translation::init('admin/coupon_admin');
        if (Yii::$app->request->post() == false) {
            return false;
        }
        $cid = (int) Yii::$app->request->post('cid');
        if (!$cid) {
            echo json_encode(['message' => COUPON_CANNOT_PROCESS_DELETE]);
            exit;
        }
        $coupons_customer_codes_list = Coupons_Customer_Codes_List::find()->where(['coupon_id' => $cid])->all();
        $deletion_error = false;
        foreach ($coupons_customer_codes_list as $item) {
            if (!$item->delete()) {
                $deletion_error = true;
            }
        }
        if ($deletion_error) {
            echo json_encode(['message' => COUPON_CANNOT_PROCESS_DELETE]);
        } else {
            echo json_encode(['message' => 'ok']);
        }
        exit;
    }
}