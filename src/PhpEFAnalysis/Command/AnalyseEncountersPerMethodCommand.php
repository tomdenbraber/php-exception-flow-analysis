<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseEncountersPerMethodCommand extends Command {
	public function configure() {
		$this->setName("analysis:encounters-per-method")
			->setDescription("Get the amount of encountered exceptions per method")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'Path to an exception flow file'
			)->addArgument(
				'methodOrderFile',
				InputArgument::REQUIRED,
				'A file containing a partial order of methods'
			)->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$method_order_file = $input->getArgument('methodOrderFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		if (!is_file($method_order_file) || pathinfo($method_order_file, PATHINFO_EXTENSION) !== "json") {
			die($method_order_file . " is not a valid method order file");
		}

		$exception_count = [
			"unique root counts" => [],
			"unique type counts" => [],
			"unique root methods" => [],
			"unique type methods" => [],
			"exception type counts" => [], //how often an exception occurs, is actually kinda independent of methods.
		];

		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$method_data = json_decode(file_get_contents($method_order_file), $assoc = true);
		foreach ($ef as $scope => $scope_data) {
			if ($scope === "{main}" || (isset($method_data[$scope]) === true && $method_data[$scope]["abstract"] === true)) { //{main} is not a method or function, and abstract functions do not encounter anything by definition
				continue;
			}
			$scope_encounters = $this->gatherForScope($scope_data);
			$scope_encounters_count = count($scope_encounters);
			if (isset($exception_count["unique root counts"][$scope_encounters_count]) === false) {
				$exception_count["unique root counts"]["" . $scope_encounters_count] = 1;
				$exception_count["unique root methods"]["" . $scope_encounters_count] = [$scope];

			} else {
				$exception_count["unique root counts"]["" . $scope_encounters_count] += 1;
				$exception_count["unique root methods"]["" . $scope_encounters_count][] = $scope;
			}

			foreach($scope_encounters as $exception) {
				if (isset($exception_count["exception type counts"][$exception]) === false) {
					$exception_count["exception type counts"][$exception] = 0;
				}
				$exception_count["exception type counts"][$exception] += 1;
			}


			$scope_encounters_unique_type = array_unique($scope_encounters);
			$scope_encounters_unique_type_count = count($scope_encounters_unique_type);
			if (isset($exception_count["unique type counts"][$scope_encounters_unique_type_count]) === false) {
				$exception_count["unique type counts"]["" . $scope_encounters_unique_type_count] = 1;
				$exception_count["unique type methods"]["" . $scope_encounters_unique_type_count] = [$scope];

			} else {
				$exception_count["unique type counts"]["" . $scope_encounters_unique_type_count] += 1;
				$exception_count["unique type methods"]["" . $scope_encounters_unique_type_count][] = $scope;
			}
		}

		ksort($exception_count["unique root counts"]);
		ksort($exception_count["unique type counts"]);
		ksort($exception_count["unique root methods"]);
		ksort($exception_count["unique type methods"]);
		arsort($exception_count["exception type counts"]);

		if (file_exists($output_path . "/encounters-per-method.json") === true) {
			die($output_path . "/encounters-per-method.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-per-method.json", json_encode($exception_count, JSON_PRETTY_PRINT ));
			$output->write(json_encode(["encounters per method" => $output_path . "/encounters-per-method.json"]));
		}
	}

	private function gatherForScope($scope_data, $encounters = []) {
		$encounters = array_merge($scope_data["raises"], $encounters);
		foreach ($scope_data["propagates"] as $method => $propagated_exceptions) {
			$encounters = array_merge($encounters, $propagated_exceptions);
		}
		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			foreach ($guarded_scope_data["inclosed"] as $inclosed_scope => $inclosed_scope_data) {
				$encounters = $this->gatherForScope($inclosed_scope_data, $encounters);
			}
		}
		return $encounters;
	}
}