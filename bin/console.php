<?php
include __DIR__ . "/../vendor/autoload.php";

use \Symfony\Component\Console\Application;
use \PhpEFAnalysis\Command\AnalyseEncountersPerMethodCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteTryBlocksCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteCatchBlocksCommand;
use \PhpEFAnalysis\Command\AnalyseCatchBySubsumptionCommand;
use \PhpEFAnalysis\Command\BuildAnnotationsSetCommand;

$application = new Application();
$application->add(new AnalyseEncountersPerMethodCommand());
$application->add(new AnalyseObsoleteTryBlocksCommand());
$application->add(new AnalyseObsoleteCatchBlocksCommand());
$application->add(new AnalyseCatchBySubsumptionCommand());
$application->add(new BuildAnnotationsSetCommand());
$application->run();
