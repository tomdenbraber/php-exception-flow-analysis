<?php
namespace PhpEFAnalysis\Command;

use PhpEFAnalysis\ClassResolver;
use PhpEFAnalysis\ThrowsAnnotationComparator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalysePropagatesUncaughtAnnotatedCommand extends Command {
	public function configure() {
		$this->setName("analysis:propagates-uncaught-annotated")
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
				'methodOrderFile',
				InputArgument::REQUIRED,
				'A file containing a partial order of methods'
			)
			->addArgument(
				"classHierarchyFile",
				InputArgument::REQUIRED,
				"Path to a class hierarchy file")
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
		$class_hierarchy_file = $input->getArgument("classHierarchyFile");
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
		if (!is_file($class_hierarchy_file) || pathinfo($class_hierarchy_file, PATHINFO_EXTENSION) !== "json") {
			die($class_hierarchy_file . " is not a valid method order file");
		}


		$method_order = json_decode(file_get_contents($method_order_file), $assoc = true);
		$ef = json_decode(file_get_contents($exception_flow_file), $assoc = true);
		$class_hierarchy = json_decode(file_get_contents($class_hierarchy_file), $assoc = true);;

		unset($ef["{main}"]);

		$annotations = json_decode(file_get_contents($annotations_file), $assoc = true);

		$encountered_and_not_annotated = [];
		$encountered_and_annotated = [];
		$encountered_and_probably_annotated = [];
		$encountered_and_not_annotated_logic = [];
		$encountered_and_annotated_logic = [];
		$encountered_and_probably_annotated_logic = [];



		$comparator = new ThrowsAnnotationComparator($class_hierarchy);
		$resolver = new ClassResolver($class_hierarchy);

		foreach ($ef as $scope_name => $scope_data) {
			if (isset($method_order[$scope_name]) === true && $method_order[$scope_name]["abstract"] === true) {
				continue;
			}

			$propagated_or_uncaught = [];

			foreach ($scope_data["propagates"] as $scope => $propagated_exceptions) {
				$propagated_or_uncaught = array_merge($propagated_or_uncaught, $propagated_exceptions);
			}

			foreach ($scope_data["uncaught"] as $guarded_scope => $uncaught_exceptions) {
				$propagated_or_uncaught = array_merge($propagated_or_uncaught, $uncaught_exceptions);
			}

			$propagated_or_uncaught = array_unique($propagated_or_uncaught);
			if (($index = array_search("unknown", $propagated_or_uncaught)) !== false) {
				unset($propagated_or_uncaught[$index]);
			}
			if (($index = array_search("", $propagated_or_uncaught)) !== false) {
				unset($propagated_or_uncaught[$index]);
			}

			$encountered_and_not_annotated[$scope_name] = [];
			$encountered_and_annotated[$scope_name] = [];
			$encountered_and_probably_annotated[$scope_name] = [];
			$encountered_and_not_annotated_logic[$scope_name] = [];
			$encountered_and_annotated_logic[$scope_name] = [];
			$encountered_and_probably_annotated_logic[$scope_name] = [];

			foreach ($propagated_or_uncaught as $exception) {
				$comparison = $comparator->isAnnotated($scope_name, $exception, $annotations[$scope_name]);
				$resolves_to_logicexception = $resolver->resolvesTo($exception, "logicexception");

				switch ($comparison) {
					case ThrowsAnnotationComparator::NO:
						$encountered_and_not_annotated[$scope_name][] = $exception;
						if ($resolves_to_logicexception) {
							$encountered_and_not_annotated_logic[$scope_name][] = $exception;
						}
						break;
					case ThrowsAnnotationComparator::YES:
						$encountered_and_annotated[$scope_name][] = $exception;
						if ($resolves_to_logicexception) {
							$encountered_and_annotated_logic[$scope_name][] = $exception;
						}
						break;
					case ThrowsAnnotationComparator::PROBABLY:
						$encountered_and_probably_annotated[$scope_name][] = $exception;
						if ($resolves_to_logicexception) {
							$encountered_and_probably_annotated_logic[$scope_name][] = $exception;
						}
						break;
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
		$count_encountered_and_probably_annotated = 0;
		foreach ($encountered_and_probably_annotated as $fn => $exceptions) {
			$count_encountered_and_probably_annotated += count($exceptions);
		}

		$count_encountered_and_not_annotated_logic = 0;
		foreach ($encountered_and_not_annotated_logic as $fn => $exceptions) {
			$count_encountered_and_not_annotated_logic += count($exceptions);
		}
		$count_encountered_and_annotated_logic = 0;
		foreach ($encountered_and_annotated_logic as $fn => $exceptions) {
			$count_encountered_and_annotated_logic += count($exceptions);
		}
		$count_encountered_and_probably_annotated_logic = 0;
		foreach ($encountered_and_probably_annotated_logic as $fn => $exceptions) {
			$count_encountered_and_probably_annotated_logic += count($exceptions);
		}

		if (file_exists($output_path . "/propagated-or-uncaught-annotated-specific.json") === true) {
			die($output_path . "/propagated-or-uncaught-annotated-specific.json already exists");
		} else {
			file_put_contents($output_path . "/propagated-or-uncaught-annotated-specific.json", json_encode([
				"encountered and not annotated" => array_filter($encountered_and_not_annotated, function($item) {
					return empty($item) === false;
				}),
				"encountered and annotated" => array_filter($encountered_and_annotated, function($item) {
					return empty($item) === false;
				}),
				"encountered and probably annotated" => array_filter($encountered_and_probably_annotated, function($item) {
					return empty($item) === false;
				}),
				"encountered and not annotated (resolves to logic)" => array_filter($encountered_and_not_annotated_logic, function($item) {
					return empty($item) === false;
				}),
				"encountered and annotated (resolves to logic)" => array_filter($encountered_and_annotated_logic, function($item) {
					return empty($item) === false;
				}),
				"encountered and probably annotated (resolves to logic)" => array_filter($encountered_and_probably_annotated_logic, function($item) {
					return empty($item) === false;
				}),
			], JSON_PRETTY_PRINT));
		}


		if (file_exists($output_path . "/propagated-or-uncaught-annotated-numbers.json") === true) {
			die($output_path . "/propagated-or-uncaught-annotated-numbers.json already exists");
		} else {
			file_put_contents($output_path . "/propagated-or-uncaught-annotated-numbers.json", json_encode([
				"correctly annotated" => $count_encountered_and_annotated,
				"not annotated" => $count_encountered_and_not_annotated,
				"probably annotated" => $count_encountered_and_probably_annotated,
				"correctly annotated (resolves to logic)" => $count_encountered_and_annotated_logic,
				"not annotated (resolves to logic)" => $count_encountered_and_not_annotated_logic,
				"probably annotated (resolves to logic)" => $count_encountered_and_probably_annotated_logic,
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"propagated or uncaught annotated specific" => $output_path . "/propagated-or-uncaught-annotated-specific.json",
			"propagated or uncaught annotated numbers" => $output_path . "/propagated-or-uncaught-annotated-numbers.json",
		]));
	}
}