<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseDifferenceBetweenAbstractAndImplementationCommand extends Command {
	public function configure() {
		$this->setName("analysis:annotations-difference-abstract-implementation")
			->setDescription("Analyse the difference between annotations ")
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
		$annotations_file = $input->getArgument('annotationsFile');
		$method_order_file = $input->getArgument('methodOrderFile');
		$output_path = $input->getArgument('outputPath');

		if (!is_file($annotations_file) || pathinfo($annotations_file, PATHINFO_EXTENSION) !== "json") {
			die($annotations_file . " is not a valid annotations file");
		}
		if (!is_file($method_order_file) || pathinfo($method_order_file, PATHINFO_EXTENSION) !== "json") {
			die($method_order_file . " is not a valid method order file");
		}

		$method_order = json_decode(file_get_contents($method_order_file), $assoc = true);
		$annotations = json_decode(file_get_contents($annotations_file), $assoc = true);

		$annotated = [
			"at abstract, not at implementation" => [],
			"not at abstract, at implementation" => [],
			"at abstract and at implementation" => [],
			"has no super" => [],
			"has no implementation" => [],
		];

		$covered_children = [];

		foreach ($method_order as $method => $method_data) {
			if ($method_data['abstract'] === true) {
				$abstract_annotations = $annotations[$method];

				if (empty($method_data['descendants']) === true) {
					$annotated["has no implementation"][] = $method;
				} else {
					$annotated["at abstract, not at implementation"][$method] = [];
					$annotated["not at abstract, at implementation"][$method] = [];
					$annotated["at abstract and at implementation"][$method] = [];

					foreach ($method_data['descendants'] as $descendant) {
						$descendant_annotations = $annotations[$descendant];

						$annotated["at abstract, not at implementation"][$method][$descendant] = array_diff($abstract_annotations, $descendant_annotations);
						$annotated["not at abstract, at implementation"][$method][$descendant] = array_diff($descendant_annotations, $abstract_annotations);
						$annotated["at abstract and at implementation"][$method][$descendant] = array_intersect($abstract_annotations, $descendant_annotations);

						$covered_children[$descendant] = $descendant;
					}
				}
			}
		}

		// now mark all methods that do not have a super method
		foreach ($method_order as $method => $method_data) {
			if ($method_data["abstract"] !== true && isset($covered_children[$method]) === false) {
				$annotated["has no super"][] = $method;
			}
		}

		$annotated["has no super count"] = count($annotated["has no super"]);
		$annotated["has no implementation count"] = count($annotated["has no implementation"]);
		$annotated["at abstract, not at implementation count"] = $this->countRecursive($annotated["at abstract, not at implementation"]);
		$annotated["not at abstract, at implementation count"] = $this->countRecursive($annotated["not at abstract, at implementation"]);
		$annotated["at abstract and at implementation count"] = $this->countRecursive($annotated["at abstract and at implementation"]);
		
		if (file_exists($output_path . "/annotations-difference-abstract-implementation.json") === true) {
			die($output_path . "/annotations-difference-abstract-implementation.json already exists");
		} else {
			file_put_contents($output_path . "/annotations-difference-abstract-implementation.json", json_encode([
				$annotated
			], JSON_PRETTY_PRINT));
		}

		$output->write(json_encode([
			"annotations difference abstract implementation" => $output_path . "/annotations-difference-abstract-implementation.json",
		]));
	}

	private function countRecursive($method_data) {
		$sum = 0;
		foreach ($method_data as $abstract => $descendants) {
			foreach ($descendants as $descendant => $annotations) {
				$sum += count($annotations);
			}
		}
		return $sum;
	}
}