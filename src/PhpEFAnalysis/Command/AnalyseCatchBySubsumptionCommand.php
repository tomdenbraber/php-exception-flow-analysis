<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseCatchBySubsumptionCommand extends Command {

	public function configure() {
		$this->setName("analysis:catch-by-subsumption")
			->setDescription("Analyse the amount of exceptions that are caught by subsumption")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'Path to an exception flow file')
			->addArgument(
				"classHierarchyFile",
				InputArgument::REQUIRED,
				"Path to a class hierarchy file")
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$class_hierarchy_file = $input->getArgument('classHierarchyFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($class_hierarchy_file) || pathinfo($class_hierarchy_file, PATHINFO_EXTENSION) !== "json") {
			die($class_hierarchy_file . " is not a valid class hierarchy file");
		}
		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$class_hiearchy = json_decode(file_get_contents($class_hierarchy_file), $assoc = true);

		$caught_exception_type_distance_to_caught = [
			"catch clause type to throwable" => [],
			"catch clause type to exception" => [],
			"catch clause to caught type" => [],
			"caught type to throwable" => [],
			"caught type to exception" => [],
			"caught type occurring in catch to clause distance" => [],
			"caught type occurring in dist to exception" => [],
		];
		foreach ($ef as $scope_name => $scope) {
			$current_scope_count = $this->analyseScope($scope, $class_hiearchy);

			$caught_exception_type_distance_to_caught = self::mergeArrays($caught_exception_type_distance_to_caught, $current_scope_count);
		}

		ksort($caught_exception_type_distance_to_caught["catch clause type to throwable"]);
		ksort($caught_exception_type_distance_to_caught["catch clause type to exception"]);
		ksort($caught_exception_type_distance_to_caught["caught type to throwable"]);
		ksort($caught_exception_type_distance_to_caught["caught type to exception"]);
		ksort($caught_exception_type_distance_to_caught["catch clause to caught type"]);
		ksort($caught_exception_type_distance_to_caught["caught type occurring in catch to clause distance"]);
		ksort($caught_exception_type_distance_to_caught["caught type occurring in dist to exception"]);

		if (file_exists($output_path . "/catch-by-subsumption.json") === true) {
			die($output_path . "/catch-by-subsumption.json already exists");
		} else {
			file_put_contents($output_path . "/catch-by-subsumption.json", json_encode($caught_exception_type_distance_to_caught, JSON_PRETTY_PRINT|JSON_FORCE_OBJECT));
			$output->write(json_encode(["catch by subsumption" => $output_path . "/catch-by-subsumption.json"]));
		}
	}

	private function analyseScope($scope_data, $class_hiearchy) {
		$catch_clause_distances = [
			"catch clause type to throwable" => [],
			"catch clause type to exception" => [],
			"catch clause to caught type" => [],
			"caught type to throwable" => [],
			"caught type to exception" => [],
			"caught type occurring in catch to clause distance" => [],
			"caught type occurring in dist to exception" => [],
		];

		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			$inclosed_scope_name_arr = array_keys($guarded_scope_data["inclosed"]);
			$inclosed_scope_name = array_pop($inclosed_scope_name_arr); //there can only be one;

			foreach ($guarded_scope_data["catch clauses"] as $type => $caught_types) {
				$catch_clause_type = strtolower($type);

				$catch_clause_to_throwable = $this->calculateDistanceBetween("throwable", $catch_clause_type, $class_hiearchy);
				if (isset($catch_clause_distances["catch clause type to throwable"][$catch_clause_to_throwable]) === false) {
					$catch_clause_distances["catch clause type to throwable"][$catch_clause_to_throwable] = 0;
				}
				$catch_clause_distances["catch clause type to throwable"][$catch_clause_to_throwable] += 1;


				$catch_clause_to_exception = $this->calculateDistanceBetween("exception", $catch_clause_type, $class_hiearchy);
				if (isset($catch_clause_distances["catch clause type to exception"][$catch_clause_to_exception]) === false) {
					$catch_clause_distances["catch clause type to exception"][$catch_clause_to_exception] = 0;
				}
				$catch_clause_distances["catch clause type to exception"][$catch_clause_to_exception] += 1;

				foreach ($caught_types as $caught_type) {
					$distance_to_catch_type = $this->calculateDistanceBetween($catch_clause_type, $caught_type, $class_hiearchy);
					$distance_to_throwable = $this->calculateDistanceBetween("throwable", $caught_type, $class_hiearchy);
					$distance_to_exception = $this->calculateDistanceBetween("exception", $caught_type, $class_hiearchy);

					if (isset($catch_clause_distances["catch clause to caught type"][$distance_to_catch_type]) === false) {
						$catch_clause_distances["catch clause to caught type"][$distance_to_catch_type] = 0;
					}
					$catch_clause_distances["catch clause to caught type"][$distance_to_catch_type] += 1;

					if (isset($catch_clause_distances["caught type to throwable"][$distance_to_throwable]) === false) {
						$catch_clause_distances["caught type to throwable"][$distance_to_throwable] = 0;
					}
					$catch_clause_distances["caught type to throwable"][$distance_to_throwable] += 1;

					if (isset($catch_clause_distances["caught type to exception"][$distance_to_exception]) === false) {
						$catch_clause_distances["caught type to exception"][$distance_to_exception] = 0;
					}
					$catch_clause_distances["caught type to exception"][$distance_to_exception] += 1;

					if (isset($catch_clause_distances["caught type occurring in catch to clause distance"][$distance_to_catch_type]) === false) {
						$catch_clause_distances["caught type occurring in catch to clause distance"][$distance_to_catch_type] = [];
					}
					if (isset($catch_clause_distances["caught type occurring in catch to clause distance"][$distance_to_catch_type][$caught_type]) === false) {
						$catch_clause_distances["caught type occurring in catch to clause distance"][$distance_to_catch_type][$caught_type] = 0;
					}
					$catch_clause_distances["caught type occurring in catch to clause distance"][$distance_to_catch_type][$caught_type] += 1;


					if (isset($catch_clause_distances["caught type occurring in dist to exception"][$distance_to_exception]) === false) {
						$catch_clause_distances["caught type occurring in dist to exception"][$distance_to_exception] = [];
					}
					if (isset($catch_clause_distances["caught type occurring in dist to exception"][$distance_to_exception][$caught_type]) === false) {
						$catch_clause_distances["caught type occurring in dist to exception"][$distance_to_exception][$caught_type] = 0;
					}
					$catch_clause_distances["caught type occurring in dist to exception"][$distance_to_exception][$caught_type] += 1;
				}
			}

			$nested_scope_accumulated_counts = $this->analyseScope($guarded_scope_data["inclosed"][$inclosed_scope_name], $class_hiearchy);
			$catch_clause_distances = self::mergeArrays($catch_clause_distances, $nested_scope_accumulated_counts);
		}

		return $catch_clause_distances;
	}

	private function calculateDistanceBetween($class_1, $class_2, $class_hierarchy) {
		return count(array_intersect($class_hierarchy["class resolved by"][$class_1], $class_hierarchy["class resolves"][$class_2])) - 1;
	}

	/**
	 * Merges array_2 into array_1
	 * @param array $array_1
	 * @param array $array_2
	 */
	private static function mergeArrays(array $array_1, array $array_2) {
		foreach ($array_2 as $key => $value) {
			if (isset($array_1[$key]) === false) {
				$array_1[$key] = $value;
			} else {
				if (is_array($value) === true && is_array($array_1[$key]) === true) {
					$array_1[$key] = self::mergeArrays($array_1[$key], $value);
				} else if (is_int($value) === true && is_int($array_1[$key]) === true) {
					$array_1[$key] += $value;
				} else {
					throw new \LogicException("unmergeable arrays!");
				}
			}
		}
		return $array_1;
	}
}
