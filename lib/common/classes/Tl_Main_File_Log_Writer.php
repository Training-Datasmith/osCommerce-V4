<?php

declare (strict_types=1);
namespace common\classes;

class Tl_Main_File_Log_Writer extends \yii\log\File_Target
{
    protected function get_context_message()
    {
        $sys_info = \common\helpers\System::get_sys_info();
        $s = "\n";
        foreach ($sys_info as $key => $info) {
            if (!empty($info) && $info != 'unknown') {
                $s .= "{$key}: {$info}\n";
            }
        }
        $msg = parent::get_context_message();
        $msg = preg_replace_callback("/'email_address' => '(.*)'/", function ($matches) {
            $email = $matches[1];
            $start = substr($email, 0, 2);
            $end = substr($email, -2);
            $masked_email = $start . str_repeat('*', 5) . $end;
            return "'email_address' => '" . $masked_email . "'";
        }, $msg);
        $msg = preg_replace("/'password' => '(.*)'/", sprintf("'password' => '%s'", str_repeat('*', 5)), $msg);
        // remove any other emails
        $msg = preg_replace_callback("/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})/", function ($matches) {
            $email = $matches[0];
            $start = substr($email, 0, 2);
            $end = substr($email, -2);
            $masked_email = $start . str_repeat('*', 5) . $end;
            return $masked_email;
        }, $msg);
        return $s . $msg;
    }
}