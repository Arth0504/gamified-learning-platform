<?php
require 'config/db.php';
$r = $pdo->query('DESCRIBE students');
foreach ($r->fetchAll() as $c) {
    echo $c['Field'] . PHP_EOL;
}
