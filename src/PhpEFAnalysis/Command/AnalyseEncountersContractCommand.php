<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
		$annotations = json_decode(file_get_contents($annotations_file), $assoc = true);
		$method_order = json_decode(file_get_contents($method_order_file), $assoc = true);

		unset($ef["{main}"]);

		$encountered_but_not_annotated = [];
		$encountered_and_annotated = [];
		$annotated_and_not_encountered = []; //counts all exceptions that are annotated on an abstract method, but never encountered.

		$encounters_per_scope = [];

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
			$encounters = array_unique($encounters);
			if (($index = array_search("unknown", $encounters, true)) !== false) {
				unset($encounters[$index]);
			}
			if (($index = array_search("", $encounters, true)) !== false) {
				unset($encounters[$index]);
			}

			$encounters_per_scope[$scope_name] = $encounters;

			$ancestors = $method_order[$method_order_scope_name]["ancestors"];
			foreach ($ancestors as $ancestor => $method_data) {
				if (
					isset($method_order[$ancestor]) === false || //can happen because of prefixes.
					$method_order[$ancestor]["abstract"] !== true
				) {
					continue;
				}

				if (isset($encountered_but_not_annotated[$ancestor]) === false) {
					$encountered_but_not_annotated[$ancestor] = [];
					$encountered_and_annotated[$ancestor] = [];
				}

				foreach ($encounters as $encounter) {
					if (isset($annotations[$ancestor][$encounter]) === false && isset($annotations[$ancestor]["\\" . $encounter]) === false) {
						if (isset($encountered_but_not_annotated[$ancestor][$encounter]) === false) {
							$encountered_but_not_annotated[$ancestor][$encounter] = [];
						}
						$encountered_but_not_annotated[$ancestor][$encounter][] = $scope_name;
					} else {
						if (isset($encountered_and_annotated[$ancestor][$encounter]) === false) {
							$encountered_and_annotated[$ancestor][$encounter] = [];
						}
						$encountered_and_annotated[$ancestor][$encounter][] = $scope_name;
					}
				}
			}
		}

		foreach ($method_order as $method => $method_data) {
			if ($method_data["abstract"] !== true) {
				continue;
			}

			$contractual_annotations = $annotations[$method];
			foreach ($method_data["descendants"] as $descendant) {
				if (
					isset($method_order[$descendant]) === false ||         //this child has been left out because of a prefix
					$method_order[$descendant]["abstract"] === true //abstract child cannot violate contract
				) {
					continue;
				}

				foreach ($contractual_annotations as $annotation) {
					if (in_array($annotation, $encounters_per_scope[$descendant], true) === true || in_array(str_replace('\\', "", $annotation), $encounters_per_scope[$descendant], true) === true) {
						unset($contractual_annotations[$annotation]);
					}
				}
			}

			//now, contractual annotations contains the exceptions which were annotated but not thrown in any of the implementing methods
			$annotated_and_not_encountered[$method] = $contractual_annotations;
		}

		if (file_exists($output_path . "/encounters-contract-specific.json") === true) {
			die($output_path . "/encounters-contract-specific.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-contract-specific.json", json_encode([
				"encountered but not annotated" => array_filter($encountered_but_not_annotated, function($item) {
					return empty($item) === false;
				}),
				"encountered and annotated" => array_filter($encountered_and_annotated, function($item) {
					return empty($item) === false;
				}),
				"annotated and not encountered" => array_filter($annotated_and_not_encountered, function($item) {
					return empty($item) === false;
				}),
			], JSON_PRETTY_PRINT));
		}


		if (file_exists($output_path . "/encounters-contract-numbers.json") === true) {
			die($output_path . "/encounters-contract-numbers.json already exists");
		} else {
			$methods_miss_count = 0;
			$exceptions_miss_count = 0;
			$violating_methods_count = 0;
			$unique_violating_methods_count = 0;
			$methods_correct_count = 0;
			$exceptions_correct_count = 0;
			$complying_methods_count = 0;
			$unique_complying_methods_count = 0;
			$redundant_annotated_method_count = 0;
			$redundant_annotation_count = 0;



			foreach ($encountered_but_not_annotated as $function => $missed_for_fn) {
				if (empty($missed_for_fn) === true) {
					continue;
				}

				$methods_miss_count += 1;
				$exceptions_miss_count += count(array_keys($missed_for_fn));
				$violating_merged = [];
				foreach ($missed_for_fn as $exception => $violating_fns) {
					$violating_methods_count += count($violating_fns);
					$violating_merged = array_merge($violating_merged, $violating_fns);
				}
				$unique_violating_methods_count += count(array_unique($violating_merged));
			}
			foreach ($encountered_and_annotated as $function => $correct_for_fn) {
				if (empty($correct_for_fn) === true) {
					continue;
				}

				$methods_correct_count += 1;
				$exceptions_correct_count += count(array_keys($correct_for_fn));
				$complying_merged = [];
				foreach ($correct_for_fn as $exception => $complying_fns) {
					$complying_methods_count += count($complying_fns);
					$complying_merged = array_merge($complying_merged, $complying_fns);
				}
				$unique_complying_methods_count += count(array_unique($complying_merged));
			}
			foreach ($annotated_and_not_encountered as $method => $annotations) {
				if (empty($annotations) === true) {
					continue;
				}
				$redundant_annotated_method_count += 1;
				$redundant_annotation_count += count($annotations);
			}


			file_put_contents($output_path . "/encounters-contract-numbers.json", json_encode([
				"encountered but not annotated" => [
					"methods" => $methods_miss_count,
					"exceptions" => $exceptions_miss_count,
					"unique violating functions" => $unique_violating_methods_count,
					"total violations" => $violating_methods_count,
				],
				"encountered and annotated" => [
					"methods" => $methods_correct_count,
					"exceptions" => $exceptions_correct_count,
					"unique complying functions" => $unique_complying_methods_count,
					"total compliances" => $complying_methods_count,
				],
				"annotated and not encountered" => [
					"methods" => $redundant_annotated_method_count,
					"annotations" => $redundant_annotation_count,
				]
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"encounters contract specific" => $output_path . "/encounters-contract-specific.json",
			"encounters contract numbers" => $output_path . "/encounters-contract-numbers.json"]
		));
	}
}