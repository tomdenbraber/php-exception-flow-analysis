<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseRaisesAnnotatedCommand extends Command {
	public function configure() {
		$this->setName("analysis:raises-annotated")
			->setDescription("Analyse how often an actual raised exception is not documented ")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'A file containing an exceptional flow')
			->addArgument(
				'annotationsFile',
				InputArgument::REQUIRED,
				'A file containing the @throws annotations for each function/method'
			)
			->addOption('outputSpecificInstances',
				null,
				InputOption::VALUE_NONE,
				'Set this option if you want to see the specific instances of violations'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$annotations_file = $input->getArgument('annotationsFile');
		$output_specific_instances = $input->getOption("outputSpecificInstances");

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		if (!is_file($annotations_file) || pathinfo($annotations_file, PATHINFO_EXTENSION) !== "json") {
			die($annotations_file . " is not a valid annotations file");
		}

		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$annotations_file = json_decode(file_get_contents($annotations_file), $assoc = true);
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
			$counting_raises = array_unique($scope_data["raises"]);
			$misses[$scope_name] = [];
			$correct[$scope_name] = [];
			foreach ($counting_raises as $counting_raise) {
				if (isset($annotations[$scope_name][$counting_raise]) === false && isset($annotations[$scope_name]["\\" . $counting_raise]) === false) {
					//not documented, so add to misses
					$misses[$scope_name][] = $counting_raise;
				} else {
					$correct[$scope_name][] = $counting_raise;
				}
			}
		}

		if ($output_specific_instances === true) {
			echo json_encode(array_filter($misses, function($item) {
				return empty($item) === false;
			}), JSON_PRETTY_PRINT);
		} else {
			$count_missed = 0;
			foreach ($misses as $fn => $exceptions) {
				$count_missed += count($exceptions);
			}
			$count_correct = 0;
			foreach ($correct as $fn => $exceptions) {
				$count_correct += count($exceptions);
			}

			print sprintf("misses;correct\n%d;%d", $count_missed, $count_correct);
		}
	}
}