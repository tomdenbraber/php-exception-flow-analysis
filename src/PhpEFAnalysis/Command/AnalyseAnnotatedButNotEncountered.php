<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseAnnotatedButNotEncountered extends Command {
	public function configure() {
		$this->setName("analysis:annotated-not-encountered")
			->setDescription("Analyse how often an annotated exception is not encountered")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'A file containing an exceptional flow')
			->addArgument(
				'annotationsFile',
				InputArgument::REQUIRED,
				'A file containing the @throws annotations for each function/method')
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
		if (!is_file($method_order_file) || pathinfo($method_order_file, PATHINFO_EXTENSION) !== "json") {
			die($method_order_file . " is not a valid method order file");
		}

		$method_order = json_decode(file_get_contents($method_order_file), $assoc = true);
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);

		unset($ef["{main}"]);

		$annotations = json_decode(file_get_contents($annotations_file), $assoc = true);

		$annotated_and_not_encountered = [];

		foreach ($ef as $scope_name => $scope_data) {
			if (isset($method_order[$scope_name]) === true && $method_order[$scope_name]["abstract"] === true) {
				continue;
			}

			$encountered = $scope_data["raises"];

			foreach ($scope_data["propagates"] as $scope => $propagated_exceptions) {
				$encountered = array_merge($encountered, $propagated_exceptions);
			}

			foreach ($scope_data["uncaught"] as $guarded_scope => $uncaught_exceptions) {
				$encountered = array_merge($encountered, $uncaught_exceptions);
			}

			$encountered = array_unique($encountered);
			if (($index = array_search("unknown", $encountered)) !== false) {
				unset($encountered[$index]);
			}
			if (($index = array_search("", $encountered)) !== false) {
				unset($encountered[$index]);
			}

			$annotated_and_not_encountered[$scope_name] = [];
			foreach ($annotations[$scope_name] as $annotated_exc => $_) {
				if (in_array($annotated_exc, $encountered, true) === false && in_array(str_replace('\\', "", $annotated_exc), $encountered, true) === false) {
					$annotated_and_not_encountered[$scope_name][] = $annotated_exc;
				}
			}

		}

		$count_annotated_and_not_encountered = 0;
		foreach ($annotated_and_not_encountered as $fn => $annotations) {
			$count_annotated_and_not_encountered += count($annotations);
		}

		if (file_exists($output_path . "/annotated-not-encountered.json") === true) {
			die($output_path . "/annotated-not-encountered.json already exists");
		} else {
			file_put_contents($output_path . "/annotated-not-encountered.json", json_encode([
				"annotated and not encountered count" => $count_annotated_and_not_encountered,
				"annotated and not encountered" => array_filter($annotated_and_not_encountered, function($item) {
					return empty($item) === false;
				}),
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"annotated and not encountered" => $output_path . "/annotated-not-encountered.json",
		]));
	}
}