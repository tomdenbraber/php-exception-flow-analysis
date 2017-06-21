<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseObsoleteTryBlocksCommand extends Command {
	public function configure() {
		$this->setName("analysis:obsolete-try-blocks")
			->setDescription("Get the amount of obsolete try blocks")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'Path to an exception flow file'
			)->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);;
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);

		$try_blocks = [
			"encountered but nothing caught" => [],
			"nothing caught, unknown encountered" => [],
			"nothing encountered" => [],
			"caught and encountered" => []
		];

		foreach ($ef as $scope => $scope_data) {
			$scope_accumulated = $this->analyseScope($scope_data);
			$try_blocks["encountered but nothing caught"] = array_merge($try_blocks["encountered but nothing caught"], $scope_accumulated["encountered but nothing caught"]);
			$try_blocks["nothing caught, unknown encountered"] = array_merge($try_blocks["nothing caught, unknown encountered"], $scope_accumulated["nothing caught, unknown encountered"]);
			$try_blocks["nothing encountered"] = array_merge($try_blocks["nothing encountered"], $scope_accumulated["nothing encountered"]);
			$try_blocks["caught and encountered"] = array_merge($try_blocks["caught and encountered"], $scope_accumulated["caught and encountered"]);
		}


		$try_blocks["encountered but nothing caught count"] = count($try_blocks["encountered but nothing caught"]);
		$try_blocks["nothing caught, unknown encountered count"] = count($try_blocks["nothing caught, unknown encountered"]);
		$try_blocks["nothing encountered count"] = count($try_blocks["nothing encountered"]);
		$try_blocks["caught and encountered count"] = count($try_blocks["caught and encountered"]);

		if (file_exists($output_path . "/obsolete-try-blocks.json") === true) {
			die($output_path . "/obsolete-try-blocks.json already exists");
		} else {
			file_put_contents($output_path . "/obsolete-try-blocks.json", json_encode($try_blocks, JSON_PRETTY_PRINT));
			$output->write(json_encode(["obsolete try blocks" => $output_path . "/obsolete-try-blocks.json"]));
		}
	}

	private function analyseScope($scope_data) {
		$try_blocks = [
			"encountered but nothing caught" => [],
			"nothing caught, unknown encountered" => [],
			"nothing encountered" => [],
			"caught and encountered" => []
		];

		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			$inclosed_scope_name_arr = array_keys($guarded_scope_data["inclosed"]);
			$inclosed_scope_name = array_pop($inclosed_scope_name_arr); //there can only be one;
			$scope_encounters_any = $this->scopeEncountersAnException($guarded_scope_data["inclosed"][$inclosed_scope_name]);
			$catch_clauses_catch_something = $this->catchesAnyExceptions($guarded_scope_data);

			if ($scope_encounters_any === true && $catch_clauses_catch_something === false) {
				$encounters = $this->getEncountersForScope($guarded_scope_data["inclosed"][$inclosed_scope_name]);
				$encounters_unknown = false;
				foreach ($encounters as $encountered_exception) {
					if ($encountered_exception === "" || $encountered_exception === "unknown") {
						$encounters_unknown = true;
						break;
					}
				}
				if ($encounters_unknown === true) {
					$try_blocks["nothing caught, unknown encountered"][] = $guarded_scope_name;
				} else {
					$try_blocks["encountered but nothing caught"][] = $guarded_scope_name;
				}
			} else if ($scope_encounters_any === false) {
				$try_blocks["nothing encountered"][] = $guarded_scope_name;
			} else {
				$try_blocks["caught and encountered"][] = $guarded_scope_name;
			}

			$nested_scope_accumulated = $this->analyseScope($guarded_scope_data["inclosed"][$inclosed_scope_name]);

			$try_blocks["encountered but nothing caught"] = array_merge($try_blocks["encountered but nothing caught"], $nested_scope_accumulated["encountered but nothing caught"]);
			$try_blocks["nothing caught, unknown encountered"] = array_merge($try_blocks["nothing caught, unknown encountered"], $nested_scope_accumulated["nothing caught, unknown encountered"]);
			$try_blocks["nothing encountered"] = array_merge($try_blocks["nothing encountered"], $nested_scope_accumulated["nothing encountered"]);
			$try_blocks["caught and encountered"] = array_merge($try_blocks["caught and encountered"], $nested_scope_accumulated["caught and encountered"]);
		}

		return $try_blocks;
	}

	private function catchesAnyExceptions($guarded_scope_data) {
		foreach ($guarded_scope_data["catch clauses"] as $catch_clause_type => $caught_exceptions) {
			if (empty($caught_exceptions) === false) {
				return true;
			}
		}
		return false;
	}

	private function scopeEncountersAnException($scope) {
		return empty($scope["raises"]) === false || empty($scope["propagates"]) === false || empty($scope["uncaught"]) === false;
	}

	private function getEncountersForScope($scope) {
		$encounters = $scope["raises"];
		foreach ($scope["propagates"] as $propagated_exceptions) {
			$encounters = array_merge($propagated_exceptions, $encounters);
		}
		foreach ($scope["uncaught"] as $uncaught_exceptions) {
			$encounters = array_merge($uncaught_exceptions, $encounters);
		}
		return $encounters;
	}
}