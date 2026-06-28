<?php
// Ubah working directory ke root folder agar require_once relatif berjalan dengan benar
chdir(dirname(__DIR__));

// Tambahkan root folder ke include path PHP
set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__DIR__));

// Panggil router utama index.php
require_once dirname(__DIR__) . '/index.php';
