<?php
include __DIR__ . "/../vendor/autoload.php";

use \Symfony\Component\Console\Application;
use \PhpEFAnalysis\Command\AnalyseEncountersPerMethodCommand;


$application = new Application();
$application->add(new AnalyseEncountersPerMethodCommand());
$application->run();
