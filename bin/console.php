<?php
include __DIR__ . "/../vendor/autoload.php";

use \Symfony\Component\Console\Application;
use \PhpEFAnalysis\Command\AnalyseAllCommand;
use \PhpEFAnalysis\Command\AnalyseEncountersPerMethodCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteTryBlocksCommand;
use \PhpEFAnalysis\Command\AnalyseObsoleteCatchBlocksCommand;
use \PhpEFAnalysis\Command\AnalyseCatchBySubsumptionCommand;
use \PhpEFAnalysis\Command\AnalyseRaisesAnnotatedCommand;
use \PhpEFAnalysis\Command\AnalysePropagatesUncaughtAnnotatedCommand;
use \PhpEFAnalysis\Command\AnalyseAnnotatedButNotEncountered;
use \PhpEFAnalysis\Command\AnalyseEncountersContractCommand;
use \PhpEFAnalysis\Command\AnalysePathUntilCaughtCommand;
use \PhpEFAnalysis\Command\BuildAnnotationsSetCommand;
use \PhpEFAnalysis\Command\BuildExceptionFlowCommand;
use \PhpEFAnalysis\Command\AnalysePreliminaryCommand;
use \PhpEFAnalysis\Command\BuildAndAnalyseCommand;

$application = new Application();
$application->add(new AnalyseAllCommand());
$application->add(new AnalyseEncountersPerMethodCommand());
$application->add(new AnalyseObsoleteTryBlocksCommand());
$application->add(new AnalyseObsoleteCatchBlocksCommand());
$application->add(new AnalyseCatchBySubsumptionCommand());
$application->add(new AnalysePathUntilCaughtCommand());
$application->add(new AnalyseRaisesAnnotatedCommand());
$application->add(new AnalysePropagatesUncaughtAnnotatedCommand());
$application->add(new AnalyseAnnotatedButNotEncountered());
$application->add(new AnalyseEncountersContractCommand());

$application->add(new AnalysePreliminaryCommand());
$application->add(new BuildAnnotationsSetCommand());

$application->add(new BuildExceptionFlowCommand());
$application->add(new BuildAndAnalyseCommand());

$application->run();
