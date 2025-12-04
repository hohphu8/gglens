<?php

//Đăng ký để theo dõi mình tại: https://www.youtube.com/@kodoku169

require_once 'GoogleLens.php';

$filepath = '1.png';

$gglen = new GoogleLens;

var_dump($gglen->detect($filepath, true));