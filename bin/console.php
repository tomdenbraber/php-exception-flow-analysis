<?php
include __DIR__ . "/../vendor/autoload.php";

use \Symfony\Component\Console\Application;
use \PhpEFAnalysis\Command\AnalyseEncountersPerMethodCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteTryBlocksCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteCatchBlocksCommand;
use \PhpEFAnalysis\Command\AnalyseCatchBySubsumptionCommand;
use \PhpEFAnalysis\Command\AnalyseRaisesAnnotatedCommand;
use \PhpEFAnalysis\Command\AnalyseEncountersAnnotatedCommand;
use \PhpEFAnalysis\Command\AnalyseEncountersContractCommand;
use \PhpEFAnalysis\Command\BuildAnnotationsSetCommand;
use \PhpEFAnalysis\Command\AnalysePreliminaryCommand;

$application = new Application();
$application->add(new AnalyseEncountersPerMethodCommand());
$application->add(new AnalyseObsoleteTryBlocksCommand());
$application->add(new AnalyseObsoleteCatchBlocksCommand());
$application->add(new AnalyseCatchBySubsumptionCommand());
$application->add(new AnalyseRaisesAnnotatedCommand());
$application->add(new AnalyseEncountersAnnotatedCommand());
$application->add(new AnalyseEncountersContractCommand());

$application->add(new BuildAnnotationsSetCommand());
$application->add(new AnalysePreliminaryCommand());
$application->run();
