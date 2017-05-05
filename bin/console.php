<?php
include __DIR__ . "/../vendor/autoload.php";

use \Symfony\Component\Console\Application;
use \PhpEFAnalysis\Command\AnalyseEncountersPerMethodCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteTryBlocksCommand;

$application = new Application();
$application->add(new AnalyseEncountersPerMethodCommand());
$application->add(new AnalyseObsoleteTryBlocksCommand());
$application->run();
