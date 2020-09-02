<?php

require_once __DIR__ . '/setup.php';

foreach (\glob(__DIR__ . '/Tests/*Test.phpt') as $file) {
	include $file;
}
