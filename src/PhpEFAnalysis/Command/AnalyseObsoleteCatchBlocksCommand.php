<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseObsoleteCatchBlocksCommand extends Command {


	public function configure() {
		$this->setName("analysis:obsolete-catch-blocks")
			->setDescription("Get the amount of catch blocks that is obsolete")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'Path to an exception flow file'
			)
			->addArgument(
				'classHierarchyFile',
				InputArgument::REQUIRED,
				'Path to a class hierarchy file'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$class_hierarchy_file = $input->getArgument('classHierarchyFile');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$ch = json_decode(file_get_contents($class_hierarchy_file), $assoc = true);

		if (!is_file($class_hierarchy_file) || pathinfo($class_hierarchy_file, PATHINFO_EXTENSION) !== "json") {
			die($class_hierarchy_file . " is not a valid flow file");
		}

		$catch_block_count = [
			"type not encountered" => 0,
			"type caught by earlier catch" => 0,
			"(sub)type caught" => 0,
		];

		foreach ($ef as $scope_name => $scope) {
			$current_scope_count = $this->analyseScope($scope, $ch);

			$catch_block_count["type not encountered"] += $current_scope_count["type not encountered"];
			$catch_block_count["type caught by earlier catch"] += $current_scope_count["type caught by earlier catch"];
			$catch_block_count["(sub)type caught"] += $current_scope_count["(sub)type caught"];
		}

		print $this->serializeResults($catch_block_count);
	}

	private function analyseScope($scope_data, $class_hierarchy) {
		$catch_block_count = [
			"type not encountered" => 0,
			"type caught by earlier catch" => 0,
			"(sub)type caught" => 0,
		];


		foreach ($scope_data["guarded scopes"] as $guarded_scope_name => $guarded_scope_data) {
			print $guarded_scope_name;
			$inclosed_scope_name_arr = array_keys($guarded_scope_data["inclosed"]);
			$inclosed_scope_name = array_pop($inclosed_scope_name_arr); //there can only be one;

			foreach ($guarded_scope_data["catch clauses"] as $type => $caught_types) {
				$catch_clause_type = strtolower($type);
				if (empty($caught_types) === false) {
					$catch_block_count['(sub)type caught'] += 1;
				} else {
					$catchable_types = $class_hierarchy["class resolved by"][$catch_clause_type];
					$inclosed_encounters = $this->getEncountersForScope($guarded_scope_data["inclosed"][$inclosed_scope_name]);

					$exception_already_caught = false;

					foreach ($inclosed_encounters as $inclosed_encounter) {
						if (isset($catchable_types[$inclosed_encounter]) === true) {
							//the inclosed encounter *could* have been caught by this catch clause, but the catch clause itself is empty,
							//meaning that inclosed_encounter has already been caught.
							$exception_already_caught = true;
						}
					}

					if ($exception_already_caught === true) {
						$catch_block_count["type caught by earlier catch"] += 1;
					} else {
						$catch_block_count["type not encountered"] += 1;
					}
				}
			}

			$nested_scope_accumulated_counts = $this->analyseScope($guarded_scope_data["inclosed"][$inclosed_scope_name], $class_hierarchy);

			$catch_block_count["type not encountered"] += $nested_scope_accumulated_counts["type not encountered"];
			$catch_block_count["type caught by earlier catch"] += $nested_scope_accumulated_counts["type caught by earlier catch"];
			$catch_block_count["(sub)type caught"] += $nested_scope_accumulated_counts["(sub)type caught"];
		}

		return $catch_block_count;
	}

	private function getEncountersForScope($scope) {
		return array_merge($scope["raises"], $scope["propagates"], $scope["uncaught"]);
	}


	private function serializeResults($catch_block_count) {
		$header = "type not encountered;type caught by earlier catch;(sub)type caught\n";
		$body = sprintf("%d;%d;%d\n", $catch_block_count["type not encountered"], $catch_block_count["type caught by earlier catch"], $catch_block_count["(sub)type caught"]);
		return $header . $body;
	}

}