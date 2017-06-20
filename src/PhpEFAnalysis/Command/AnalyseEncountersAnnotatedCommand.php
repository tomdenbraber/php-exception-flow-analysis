<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseEncountersAnnotatedCommand extends Command {
	public function configure() {
		$this->setName("analysis:encounters-annotated")
			->setDescription("Analyse how often an actual encountered exception is not documented ")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'A file containing an exceptional flow')
			->addArgument(
				'annotationsFile',
				InputArgument::REQUIRED,
				'A file containing the @throws annotations for each function/method')
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$annotations_file = $input->getArgument('annotationsFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		if (!is_file($annotations_file) || pathinfo($annotations_file, PATHINFO_EXTENSION) !== "json") {
			die($annotations_file . " is not a valid annotations file");
		}

		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$annotations_file = json_decode(file_get_contents($annotations_file), $assoc = true);
		$annotations = $annotations_file["Resolved Annotations"];
		/*foreach ($annotations as $method => $types) {
			foreach ($types as $type_1 => $type_2) {
				$annotations[$method][strtolower($type_1)] = strtolower($type_2);
				unset($annotations[$method][$type_1]);
			}
		}*/

		$misses = [];
		$correct = [];

		foreach ($ef as $scope_name => $scope_data) {
			$encounters = $scope_data["raises"];

			foreach ($scope_data["propagates"] as $scope => $propagated_exceptions) {
				$encounters = array_merge($encounters, $propagated_exceptions);
			}

			foreach ($scope_data["uncaught"] as $guarded_scope => $uncaught_exceptions) {
				$encounters = array_merge($encounters, $uncaught_exceptions);
			}

			$encounters = array_unique($encounters);
			if (($index = array_search("unknown", $encounters)) !== false) {
				unset($encounters[$index]);
			}

			$misses[$scope_name] = [];
			$correct[$scope_name] = [];
			foreach ($encounters as $encounter) {
				if (isset($annotations[$scope_name][$encounter]) === false && isset($annotations[$scope_name]['\\' . $encounter]) === false) {
					//not documented, so add to misses
					$misses[$scope_name][] = $encounter;
				} else {
					$correct[$scope_name][] = $encounter;
				}
			}
		}

		$count_missed = 0;
		foreach ($misses as $fn => $exceptions) {
			$count_missed += count($exceptions);
		}
		$count_correct = 0;
		foreach ($correct as $fn => $exceptions) {
			$count_correct += count($exceptions);
		}

		if (file_exists($output_path . "/encounters-annotated-specific.json") === true) {
			die($output_path . "/encounters-annotated-specific.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-annotated-specific.json", json_encode(array_filter($misses, function($item) {
				return empty($item) === false;
			}), JSON_PRETTY_PRINT));
		}


		if (file_exists($output_path . "/encounters-annotated-numbers.json") === true) {
			die($output_path . "/encounters-annotated-numbers.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-annotated-numbers.json", json_encode([
				"correctly annotated" => $count_correct,
				"not annotated" => $count_missed,
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"encounters annotated specific" => $output_path . "/encounters-annotated-specific.json",
			"encounters annotated numbers" => $output_path . "/encounters-annotated-numbers.json",
		]));
	}
}