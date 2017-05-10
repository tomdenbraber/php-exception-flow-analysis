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
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}

		$exception_count = [];


		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		foreach ($ef as $scope => $scope_data) {
			if ($scope === "{main}") { //{main} is not a method or function
				continue;
			}
			$scope_encounters_count = $this->countForScope($scope_data);
			if (isset($exception_count[$scope_encounters_count]) === false) {
				$exception_count["" . $scope_encounters_count] = 1;
			} else {
				$exception_count["" . $scope_encounters_count] += 1;
			}
		}

		ksort($exception_count);

		if (file_exists($output_path . "/encounters-per-method.json") === true) {
			die($output_path . "/encounters-per-method.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-per-method.json", json_encode($exception_count, JSON_FORCE_OBJECT|JSON_PRETTY_PRINT ));
			$output->write(json_encode(["encounters per method" => $output_path . "/encounters-per-method.json"]));
		}
	}


	private function countForScope($scope_data) {
		$encounters = $scope_data["raises"];
		foreach ($scope_data["propagates"] as $method => $propagated_exceptions) {
			$encounters = array_merge($encounters, $propagated_exceptions);
		}

		$count = count($encounters);

		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			foreach ($guarded_scope_data["inclosed"] as $inclosed_scope => $inclosed_scope_data) {
				$count += $this->countForScope($inclosed_scope_data);
			}
		}
		return $count;
	}
}