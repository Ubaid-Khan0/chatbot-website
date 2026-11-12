<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$required = ['public/index.php','src/bootstrap.php','views/login.php','views/setup.php','public/assets/admin-api.js','public/assets/php-site.js'];
$failed = false;
foreach ($required as $file) if (!is_file($root . '/' . $file)) { fwrite(STDERR, "Missing: {$file}\n"); $failed = true; }
foreach (glob($root . '/{src,public,views,scripts}/*.php', GLOB_BRACE) ?: [] as $file) { $output=[];$code=0;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($file),$output,$code);if($code!==0){fwrite(STDERR,implode("\n",$output)."\n");$failed=true;} }
exit($failed ? 1 : 0);
