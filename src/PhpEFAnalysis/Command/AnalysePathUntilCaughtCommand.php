<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalysePathUntilCaughtCommand extends Command {

	public function configure() {
		$this->setName("analysis:path-until-caught")
			->setDescription("Get the typical path length until an exception is caught")
			->addArgument(
				'pathToCatchClauses',
				InputArgument::REQUIRED,
				'Path to an a file containing exception paths'
			)
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$path_to_catch_clauses_file = $input->getArgument('pathToCatchClauses');
		$output_path = $input->getArgument("outputPath");

		if (!is_file($path_to_catch_clauses_file) || pathinfo($path_to_catch_clauses_file, PATHINFO_EXTENSION) !== "json") {
			die($path_to_catch_clauses_file . " is not a valid path file");
		}

		$complete_path_analysis = [
			"lengths" => [],
		];

		$paths_until_caught = json_decode(file_get_contents($path_to_catch_clauses_file), $assoc = true);

		foreach ($paths_until_caught as $scope_name => $exception_paths) {
			foreach ($exception_paths as $exception_type => $path) {
				$path = array_slice($path, 0, count($path) - 1); //the last entry is a catches node, which is always of the same scope as the last scope in the entry
				$length = count($path);
				if (isset($complete_path_analysis["lengths"][$length]) === false) {
					$complete_path_analysis["lengths"][$length] = 0;
				}
				$complete_path_analysis["lengths"][$length] += 1;
			}
		}

		if (file_exists($output_path . "/path-analysis.json") === true) {
			die($output_path . "/path-analysis.json already exists");
		} else {
			file_put_contents($output_path . "/path-analysis.json", json_encode($complete_path_analysis, JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"path analysis" => $output_path . "/path-analysis.json",
		]));
	}
}