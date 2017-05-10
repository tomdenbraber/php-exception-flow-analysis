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
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);

		$caught_exception_count = [
			"caught by exact type" => 0,
			"caught by subsumption" => 0,
		];
		foreach ($ef as $scope_name => $scope) {
			$current_scope_count = $this->analyseScope($scope);

			$caught_exception_count["caught by exact type"] += $current_scope_count["caught by exact type"];
			$caught_exception_count["caught by subsumption"] += $current_scope_count["caught by subsumption"];
		}

		if (file_exists($output_path . "/catch-by-subsumption.json") === true) {
			die($output_path . "/catch-by-subsumption.json already exists");
		} else {
			file_put_contents($output_path . "/catch-by-subsumption.json", json_encode($caught_exception_count, JSON_PRETTY_PRINT));
			$output->write(json_encode(["catch by subsumption" => $output_path . "/catch-by-subsumption.json"]));
		}
	}

	private function analyseScope($scope_data) {
		$caught_exception_count = [
			"caught by exact type" => 0,
			"caught by subsumption" => 0,
		];

		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			$inclosed_scope_name_arr = array_keys($guarded_scope_data["inclosed"]);
			$inclosed_scope_name = array_pop($inclosed_scope_name_arr); //there can only be one;

			foreach ($guarded_scope_data["catch clauses"] as $type => $caught_types) {
				$catch_clause_type = strtolower($type);
				foreach ($caught_types as $caught_type) {
					if ($caught_type === $catch_clause_type) {
						$caught_exception_count["caught by exact type"] += 1;
					} else {
						$caught_exception_count["caught by subsumption"] += 1;
					}
				}
			}

			$nested_scope_accumulated_counts = $this->analyseScope($guarded_scope_data["inclosed"][$inclosed_scope_name]);

			$caught_exception_count["caught by exact type"] += $nested_scope_accumulated_counts["caught by exact type"];
			$caught_exception_count["caught by subsumption"] += $nested_scope_accumulated_counts["caught by subsumption"];
		}

		return $caught_exception_count;
	}

}
