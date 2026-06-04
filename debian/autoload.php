<?php
require_once '/usr/share/php/VitexSoftware/DigestModules/autoload.php';
require_once '/usr/share/php/VitexSoftware/DigestRenderer/autoload.php';
require_once '/usr/share/php/mServer/autoload.php';
require_once '/usr/share/php/Ease/autoload.php';
spl_autoload_register(function ($class) {
    $p = 'VitexSoftware\\PohodaDigest\\';
    if (strncmp($p, $class, $l = strlen($p)) !== 0) return;
    $f = '/usr/share/pohoda-digest/' . str_replace('\\', '/', substr($class, $l)) . '.php';
    if (file_exists($f)) require $f;
});
