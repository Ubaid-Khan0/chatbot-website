<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$source = $root . '/dist';
$destination = $root . '/public/assets';
$files = ['styles.css', 'script.js', 'admin.css', 'nexum-chatbot-favicon.png', 'nexum-robot-mascot.png'];
if (!is_dir($destination) && !mkdir($destination, 0775, true) && !is_dir($destination)) throw new RuntimeException('Unable to create public asset directory.');
foreach ($files as $file) if (!copy($source . '/' . $file, $destination . '/' . $file)) throw new RuntimeException("Unable to copy {$file}.");
$script = (string) file_get_contents($destination . '/script.js');
$script = str_replace('contact.html?plan=', '/contact?plan=', $script);
file_put_contents($destination . '/script.js', $script);
echo "Public assets refreshed.\n";
