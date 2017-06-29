<?php
namespace PhpEFAnalysis\Command;

use PhpExceptionFlow\AstBridge\System as AstSystem;
use PhpExceptionFlow\AstBridge\SystemTraverser;
use PhpParser\NodeTraverser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use ThrowsCollector\AnnotationCollectingVisitor;
use ThrowsCollector\JsonPrinter;

class BuildAnnotationsSetCommand extends Command {
	public function configure() {
		$this->setName("build:throws-annotations-set")
			->setDescription("Build the @throws annotations set")
			->addArgument(
				'AstSystem',
				InputArgument::REQUIRED,
				'An AstSystem (collection of nodes) of the program')
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the annotation set has to be written'
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$output_path = $input->getArgument('outputPath');
		$ast_filepath = $input->getArgument('AstSystem');
		$ast_system = new AstSystem();
		if (is_dir($ast_filepath)) {
			foreach (glob($ast_filepath . "/*.cache") as $cache_file) {
				list($partial_ast, $errors, $cached_mtime) = unserialize(file_get_contents($cache_file));
				$ast_system->addAst($cache_file, $partial_ast);
			}
		} else {
			die($ast_filepath . " is not a valid directory");
		}

		$ast_traverser = new NodeTraverser();
		$annotation_collector = new AnnotationCollectingVisitor();
		$ast_traverser->addVisitor($annotation_collector);
		$system_traverser = new SystemTraverser($ast_traverser);

		$system_traverser->traverse($ast_system);
		$annotation_printer = new JsonPrinter();

		$annotations = $annotation_printer->printAnnotations($annotation_collector->getAnnotations());

		if (file_exists($output_path . "/throws_annotations.json") === true) {
			die($output_path . "/throws_annotations.json already exists");
		} else {
			file_put_contents($output_path . "/throws_annotations.json", json_encode($annotations["Resolved Annotations"], JSON_PRETTY_PRINT));
		}

		if (file_exists($output_path . "/throws_annotations_unresolved.json") === true) {
			die($output_path . "/throws_annotations_unresolved.json already exists");
		} else {
			file_put_contents($output_path . "/throws_annotations_unresolved.json", json_encode($annotations["Unresolved Annotations"], JSON_PRETTY_PRINT));
		}
		$output->write(json_encode([
			"throws annotation set (unresolved)" => $output_path . "/throws_annotations_unresolved.json",
			"throws annotation set" => $output_path . "/throws_annotations.json"
		]));
	}
}