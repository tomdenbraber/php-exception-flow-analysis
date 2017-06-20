<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseEncountersAnnotatedCommand extends Command {
	public function configure() {
		$this->setName("analysis:encounters-annotated")
			->setDescription("Analyse how often an actual encountered exception is not documented ")
			->addArgument(
				'exceptionFlowFile',
				InputArgument::REQUIRED,
				'A file containing an exceptional flow')
			->addArgument(
				'annotationsFile',
				InputArgument::REQUIRED,
				'A file containing the @throws annotations for each function/method')
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$exception_flow_file = $input->getArgument('exceptionFlowFile');
		$annotations_file = $input->getArgument('annotationsFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($exception_flow_file) || pathinfo($exception_flow_file, PATHINFO_EXTENSION) !== "json") {
			die($exception_flow_file . " is not a valid flow file");
		}
		if (!is_file($annotations_file) || pathinfo($annotations_file, PATHINFO_EXTENSION) !== "json") {
			die($annotations_file . " is not a valid annotations file");
		}

		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);

		unset($ef["{main}"]);

		$annotations_file = json_decode(file_get_contents($annotations_file), $assoc = true);
		$annotations = $annotations_file["Resolved Annotations"];

		$encountered_and_not_annotated = [];
		$encountered_and_annotated = [];
		$annotated_and_not_encountered = [];

		foreach ($ef as $scope_name => $scope_data) {
			$encounters = $scope_data["raises"];

			foreach ($scope_data["propagates"] as $scope => $propagated_exceptions) {
				$encounters = array_merge($encounters, $propagated_exceptions);
			}

			foreach ($scope_data["uncaught"] as $guarded_scope => $uncaught_exceptions) {
				$encounters = array_merge($encounters, $uncaught_exceptions);
			}

			$encounters = array_unique($encounters);
			if (($index = array_search("unknown", $encounters)) !== false) {
				unset($encounters[$index]);
			}
			if (($index = array_search("", $encounters)) !== false) {
				unset($encounters[$index]);
			}

			$encountered_and_not_annotated[$scope_name] = [];
			$encountered_and_annotated[$scope_name] = [];
			$annotated_and_not_encountered[$scope_name] = [];
			foreach ($encounters as $encounter) {
				if (isset($annotations[$scope_name][$encounter]) === false && isset($annotations[$scope_name]['\\' . $encounter]) === false) {
					//not documented, so add to encountered_and_not_annotated
					$encountered_and_not_annotated[$scope_name][] = $encounter;
				} else {
					$encountered_and_annotated[$scope_name][] = $encounter;
				}
			}


			foreach ($annotations[$scope_name] as $annotated_exc => $_) {
				if (in_array($annotated_exc, $encounters, true) === false && in_array(str_replace('\\', "", $annotated_exc), $encounters, true) === false) {
					$annotated_and_not_encountered[$scope_name][] = $annotated_exc;
				}
			}

		}

		$count_encountered_and_not_annotated = 0;
		foreach ($encountered_and_not_annotated as $fn => $exceptions) {
			$count_encountered_and_not_annotated += count($exceptions);
		}
		$count_encountered_and_annotated = 0;
		foreach ($encountered_and_annotated as $fn => $exceptions) {
			$count_encountered_and_annotated += count($exceptions);
		}
		$count_annotated_and_not_encountered = 0;
		foreach ($annotated_and_not_encountered as $fn => $annotations) {
			$count_annotated_and_not_encountered += count($annotations);
		}

		if (file_exists($output_path . "/encounters-annotated-specific.json") === true) {
			die($output_path . "/encounters-annotated-specific.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-annotated-specific.json", json_encode([
				"encountered and not annotated" => array_filter($encountered_and_not_annotated, function($item) {
					return empty($item) === false;
				}),
				"encountered and annotated" => array_filter($encountered_and_annotated, function($item) {
					return empty($item) === false;
				}),
				"annotated and not encountered" => array_filter($annotated_and_not_encountered, function($item) {
					return empty($item) === false;
				}),
			], JSON_PRETTY_PRINT));
		}


		if (file_exists($output_path . "/encounters-annotated-numbers.json") === true) {
			die($output_path . "/encounters-annotated-numbers.json already exists");
		} else {
			file_put_contents($output_path . "/encounters-annotated-numbers.json", json_encode([
				"correctly annotated" => $count_encountered_and_annotated,
				"not annotated" => $count_encountered_and_not_annotated,
				"annotated and not encountered" => $count_annotated_and_not_encountered,
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"encounters annotated specific" => $output_path . "/encounters-annotated-specific.json",
			"encounters annotated numbers" => $output_path . "/encounters-annotated-numbers.json",
		]));
	}
}