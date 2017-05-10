<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseEncountersContractCommand extends Command {
	public function configure() {
		$this->setName("analysis:encounters-contract")
			->setDescription("Analyse how often an encountered exception is not documented in a supertype")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'A file containing an exceptional flow')
			->addArgument(
				'annotationsFile',
				InputArgument::REQUIRED,
				'A file containing the @throws annotations for each function/method'
			)
			->addArgument(
				'methodOrderFile',
				InputArgument::REQUIRED,
				'A file containing a partial order of methods'
			)
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$annotations_file = $input->getArgument('annotationsFile');
		$method_order_file = $input->getArgument('methodOrderFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		if (!is_file($annotations_file) || pathinfo($annotations_file, PATHINFO_EXTENSION) !== "json") {
			die($annotations_file . " is not a valid annotations file");
		}

		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$annotations_file = json_decode(file_get_contents($annotations_file), $assoc = true);
		$method_order = json_decode(file_get_contents($method_order_file), $assoc = true);


		$annotations = $annotations_file["Resolved Annotations"];
		foreach ($annotations as $method => $types) {
			foreach ($types as $type_1 => $type_2) {
				$annotations[$method][strtolower($type_1)] = strtolower($type_2);
				unset($annotations[$method][$type_1]);
			}
		}

		$misses = [];
		$correct = [];

		foreach ($ef as $scope_name => $scope_data) {
			$method_order_scope_name = strtolower($scope_name);
			if (isset($method_order[$method_order_scope_name]) === false) { //for functions, {main}
				continue;
			}

			$encounters = $scope_data["raises"];

			foreach ($scope_data["propagates"] as $scope => $propagated_exceptions) {
				$encounters = array_merge($encounters, $propagated_exceptions);
			}

			foreach ($scope_data["uncaught"] as $guarded_scope => $uncaught_exceptions) {
				$encounters = array_merge($encounters, $uncaught_exceptions);
			}

			$ancestors = $method_order[$method_order_scope_name]["ancestors"];
			foreach ($ancestors as $ancestor) {
				if (isset($misses[$ancestor]) === false) {
					$misses[$ancestor] = [];
					$correct[$ancestor] = [];
				}

				foreach ($encounters as $encounter) {
					if (isset($annotations[$ancestor][$encounter]) === false && isset($annotations[$ancestor]["\\" . $encounter]) === false) {
						if (!isset($misses[$ancestor][$encounter]) === false) {
							$misses[$ancestor][$encounter] = [];
						}
						$misses[$ancestor][$encounter][] = $scope_name;
					} else {
						if (!isset($correct[$ancestor][$encounter]) === false) {
							$correct[$ancestor][$encounter] = [];
						}
						$correct[$ancestor][$encounter][] = $scope_name;
					}
				}
			}
		}

		if (file_exists($output_path . "/encounters-contract-specific.json") === true) {
			die($output_path . "/encounters-contract-specific.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-contract-specific.json", json_encode($misses, JSON_PRETTY_PRINT));
		}


		if (file_exists($output_path . "/encounters-contract-numbers.json") === true) {
			die($output_path . "/encounters-contract-numbers.json already exists");
		} else {
			$unique_miss_count = 0;
			$unique_correct_count = 0;
			foreach ($misses as $function => $missed_for_fn) {
				$unique_miss_count += count(array_keys($missed_for_fn));
			}
			foreach ($correct as $function => $correct_for_fn) {
				$unique_correct_count += count(array_keys($correct_for_fn));
			}
			file_put_contents($output_path . "/encounters-contract-numbers.json", json_encode([
				"correctly annotated" => $unique_correct_count,
				"not annotated" => $unique_miss_count,
			], JSON_PRETTY_PRINT));
			$output->write(json_encode([]));
		}

		$output->write(json_encode([
			"encounters contract specific" => $output_path . "/encounters-contract-specific.json",
			"encounters contract numbers" => $output_path . "/encounters-contract-numbers.json"]
		));



	}
}