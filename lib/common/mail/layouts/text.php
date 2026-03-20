<?php

/* @var $this \yii\web\View view component instance */
/* @var $message \yii\mail\MessageInterface the message being composed */
/* @var $content string main view render result */
$this->begin_page();
$this->begin_body();
echo $content;
$this->end_body();
$this->end_page();