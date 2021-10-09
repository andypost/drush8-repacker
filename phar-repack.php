#!/usr/bin/env php
<?php
// Changes files in phar-archive.
// https://www.php.net/manual/phar.using.object.php

$input = 'replace';
$version = '8.4.8';

$options = [
  'backup' => ['b' => TRUE],
  'download' => ['d' => TRUE],
  'file::' => ['f::' => 'drush.phar'],
  'hash::' => ['h::' => $hash = 'sha512'],
  'input::' => ['i::' => NULL],
  'url::' => ['u::' => "https://github.com/drush-ops/drush/releases/download/$version/drush.phar"],
  'verbose' => ['v' => FALSE],
  'write' => ['w' => FALSE],
  'exclude' => ['x' => TRUE],
];

array_walk($options, function (&$value, $long) {
  $short = key($value);
  $default = current($value);
  $input = getopt($short, [$long]);
  $key = rtrim($long, ':');
  $value = $input[$key] ?? $input[rtrim($short, ':')] ?? NULL;
  if (str_ends_with($short, ':')) $value = $value ?? $default;
  // Process flags.
  else $value = isset($value) ? !$default : $default;
});

$phar = $options['file::'];
$src = $options['url::'];

// Disable backup.
$opt_backup = $options['backup'];
// Disable download.
$opt_download = $options['download'];
// Enable compression.
$opt_compress = $options['compress'] ?? FALSE;
// Disable minify.
$opt_minify = $options['exclude'];
// Disable patching.
$opt_patch = $options['input::'] ?? TRUE;
// Show unchanged.
//$opt_unchanged = !($opts['u'] ?? TRUE);
// Enable verbose.
$opt_verbose = $options['verbose'];
// Flag to apply changes.
$opt_write = $options['write'];

my_msg("Analizing $phar");
$flip = [TRUE => 'yes', FALSE => 'no'];
my_msg("- download: " . $flip[$opt_download]);
my_msg("- backup: " . $flip[$opt_backup]);
my_msg("- patch: " . $flip[$opt_patch ? TRUE : FALSE]);
my_msg("- minify: " . $flip[$opt_minify]);
//my_msg("- unchanged: " . $flip[$opt_unchanged]);
my_msg("- verbose: " . $flip[$opt_verbose]);
//my_msg("- compress (broken): " . $flip[$opt_compress]);
my_msg("- write: " . $flip[$opt_write]);


if ($opt_write && !\Phar::canWrite()) {
  my_fail("Can't write phar archives, use to run as 'php -dphar.readonly=0 {$argv[0]}'");
}

if ($opt_download && !file_exists($phar)) {
  echo "File $phar is not found, downloading... $src ...";
  $src = file_get_contents($src);
  if (!$src) {
    my_fail("failed to get $src");
  }
  $src = file_put_contents($phar, $src);
  if (!$src) {
    my_fail("failed to save $phar");
  }
  my_msg("ok");
}

$backup = "$phar.bak";
if ($opt_backup) {
  echo "Backup of $phar to $backup ...";
  if (file_exists($backup)) {
    my_msg("skipped as exists");
  }
  else {
    if (copy($phar, $backup)) {
      my_msg("saved");
    }
    else {
      my_fail("failed");
    }
  }
}

$cleanup = [
  'README.md',
  'drush.api.php',
  'drush_logo-black.png',
  'docs',
  'examples',
  'misc/windrush_build',
  'src',
  'vendor/doctrine',
  'vendor/jakub-onderka/php-console-highlighter/examples',
  'vendor/nikic/php-parser/test_old',
  'vendor/phpdocumentor',
  'vendor/phpspec',
  'vendor/phpunit',
  'vendor/sebastian',
  'vendor/drush_logo-black.png',
];

$files = [];
$offset = strlen($phar) + 1;
$handle = new \Phar($phar);
/** @var \SplFileInfo $item */
foreach (new \RecursiveIteratorIterator($handle) as $item) {
  if ($item->isFile()) {
    $file = $item->getPathname();
    $files[] = substr($file, strpos($file, "$phar/") + $offset);
  }
}

$replace = [];
if ($opt_patch) {
  echo "Processing files to add in $input... ";
  if (!is_dir($input)) {
    mkdir($input, 0755, TRUE);
  }
  $iterator = new \RecursiveIteratorIterator(
    new \RecursiveDirectoryIterator($input)
  );
  /** @var \SplFileInfo $info */
  foreach ($iterator as $info) {
    if ($info->isFile()) {
      $file = substr($info->getPathname(), strlen($input) + 1);
      $replace[] = $file;
    }
  }
  my_msg(count($replace) . ' found');
}

my_msg("Processing $phar...");
$counters = [
  'all' => 0,
  'add' => 0,
  'del' => 0,
];
foreach ($files as $file) {
  $counters['all']++;
  if ($replace && in_array($file, $replace, TRUE)) {
    my_replace($file);
    $counters['add']++;
    continue;
  }
  foreach ($cleanup as $prefix) {
    if (str_starts_with($file, $prefix)) {
      my_delete($file);
      $counters['del']++;
      continue 2;
    }
  }
}
array_walk($counters, function ($value, $key) {
  my_msg("$key - $value");
});

if ($opt_compress) {
  echo "Compressing with GZ...";
  if ($opt_write) {
    if ($handle->convertToExecutable(NULL, \Phar::GZ, '.phar.gz')) {
      my_msg("Compressed with GZ (broken right now)");
    }
    else {
      my_fail("Failed to compress with GZ");
    }
  }
  else {
    my_msg("skipped");
  }
}


function my_msg($string) {
  echo $string . PHP_EOL;
}

function my_info($string) {
  global $opt_verbose;
  if ($opt_verbose) {
    my_msg($string);
  }
}

function my_fail($string) {
  my_msg($string);
  die(1);
}

function my_replace($file) {
  global $phar, $input, $hash, $opt_write;
  $target = "phar://$phar/$file";
  $old = file_get_contents($target);
  $old_hash = hash($hash, $old);
  $new = file_get_contents("$input/$file");
  $new_hash = hash($hash, $new);
  if ($old_hash !== $new_hash) {
    if ($opt_write) {
      if (file_put_contents($target, $new)) {
        my_info("+ $file");
      }
      else {
        my_info("+! $file");
      }
    }
    else {
      my_info("+* $file");
    }
    my_info("   $hash checksums");
    my_info("   - $old_hash");
    my_info("   + $new_hash");
  }
  else {
    my_info("+= $file");
  }
}

function my_delete($file) {
  global $phar, $opt_minify, $opt_write;
  if ($opt_minify) {
    if ($opt_write) {
      $target = "phar://$phar/$file";
      if (unlink($target)) {
        my_info("- $file");
      }
      else {
        my_info("-! $file");
      }
    }
    else {
      my_info("-* $file");
    }
  }
}
