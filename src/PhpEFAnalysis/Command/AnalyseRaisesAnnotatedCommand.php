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
		$output_path = $input->getArgument("outputPath");

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		if (!is_file($annotations_file) || pathinfo($annotations_file, PATHINFO_EXTENSION) !== "json") {
			die($annotations_file . " is not a valid annotations file");
		}
		if (!is_file($method_order_file) || pathinfo($method_order_file, PATHINFO_EXTENSION) !== "json") {
			die($method_order_file . " is not a valid method order file");
		}

		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$annotations_file = json_decode(file_get_contents($annotations_file), $assoc = true);
		$method_order = json_decode(file_get_contents($method_order_file), $assoc = true);
		$annotations = $annotations_file["Resolved Annotations"];

		unset($ef["{main}"]);

		$misses = [];
		$correct = [];

		foreach ($ef as $scope_name => $scope_data) {
			if (isset($method_order[$scope_name]) === true && $method_order[$scope_name]["abstract"] === true) {
				continue;
			}

			$counting_raises = array_unique($scope_data["raises"]);
			if (($index = array_search("unknown", $counting_raises, true)) !== false) {
				unset($counting_raises[$index]);
			}
			if (($index = array_search("", $counting_raises, true)) !== false) {
				unset($counting_raises[$index]);
			}

			$misses[$scope_name] = [];
			$correct[$scope_name] = [];
			foreach ($counting_raises as $counting_raise) {
				if (isset($annotations[$scope_name][$counting_raise]) === false && isset($annotations[$scope_name]['\\' . $counting_raise]) === false) {
					//not documented, so add to misses
					$misses[$scope_name][] = $counting_raise;
					print $scope_name . " did not annotate " . '\\' . $counting_raise . "\n";
				} else {
					$correct[$scope_name][] = $counting_raise;
				}
			}
		}


		if (file_exists($output_path . "/raises-annotated-specific.json") === true) {
			die($output_path . "/raises-annotated-specific.json already exists");
		} else {
			file_put_contents($output_path . "/raises-annotated-specific.json", json_encode([
				"raised and not annotated" => array_filter($misses, function($item) {
					return empty($item) === false;
				}),
				"raised and annotated" => array_filter($correct, function($item) {
					return empty($item) === false;
				}),
			], JSON_PRETTY_PRINT));
		}

		if (file_exists($output_path . "/raises-annotated-numbers.json") === true) {
			die($output_path . "/raises-annotated-numbers.json already exists");
		} else {
			$count_missed = 0;
			foreach ($misses as $fn => $exceptions) {
				$count_missed += count($exceptions);
			}
			$count_correct = 0;
			foreach ($correct as $fn => $exceptions) {
				$count_correct += count($exceptions);
			}

			file_put_contents($output_path . "/raises-annotated-numbers.json", json_encode([
				"correctly annotated" => $count_correct,
				"not annotated" => $count_missed,
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"raises annotated specific" => $output_path . "/raises-annotated-specific.json",
			"raises annotated numbers" => $output_path . "/raises-annotated-numbers.json",
		]));
	}
}