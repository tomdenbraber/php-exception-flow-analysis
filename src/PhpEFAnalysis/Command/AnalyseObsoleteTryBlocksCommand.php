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
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);

		$try_block_count = [
			"encountered but nothing caught" => 0,
			"nothing encountered" => 0,
			"caught and encountered" => 0
		];

		foreach ($ef as $scope => $scope_data) {
			$scope_accumulated_counts = $this->analyseScope($scope_data);
			$try_block_count["encountered but nothing caught"] += $scope_accumulated_counts["encountered but nothing caught"];
			$try_block_count["nothing encountered"] += $scope_accumulated_counts["nothing encountered"];
			$try_block_count["caught and encountered"] += $scope_accumulated_counts["caught and encountered"];
		}

		print $this->serializeResults($try_block_count);

	}

	private function analyseScope($scope_data) {
		$try_block_count = [
			"encountered but nothing caught" => 0,
			"nothing encountered" => 0,
			"caught and encountered" => 0
		];

		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			print $guarded_scope_name;
			$inclosed_scope_name_arr = array_keys($guarded_scope_data["inclosed"]);
			$inclosed_scope_name = array_pop($inclosed_scope_name_arr); //there can only be one;
			$scope_encounters = $this->scopeEncountersAnException($guarded_scope_data["inclosed"][$inclosed_scope_name]);
			$catch_clauses_catch_something = $this->catchesAnyExceptions($guarded_scope_data);

			if ($scope_encounters === true && $catch_clauses_catch_something === false) {
				$try_block_count["encountered but nothing caught"] += 1;
			} else if ($scope_encounters === false) {
				$try_block_count["nothing encountered"] += 1;
			} else {
				$try_block_count["caught and encountered"] += 1;
			}

			$nested_scope_accumulated_counts = $this->analyseScope($guarded_scope_data["inclosed"][$inclosed_scope_name]);

			$try_block_count["encountered but nothing caught"] += $nested_scope_accumulated_counts["encountered but nothing caught"];
			$try_block_count["nothing encountered"] += $nested_scope_accumulated_counts["nothing encountered"];
			$try_block_count["caught and encountered"] += $nested_scope_accumulated_counts["caught and encountered"];
		}

		return $try_block_count;
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


	private function serializeResults($try_block_counts) {
		$header = "encountered but nothing caught;nothing encountered;caught and encountered\n";
		$body = sprintf("%d;%d;%d\n", $try_block_counts["encountered but nothing caught"], $try_block_counts["nothing encountered"], $try_block_counts["caught and encountered"]);
		return $header . $body;
	}
}