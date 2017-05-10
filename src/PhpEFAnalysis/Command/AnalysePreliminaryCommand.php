<?php
namespace PhpEFAnalysis\Command;

use Analysis\CounterIteratorFactory;
use PhpExceptionFlow\AstBridge\SystemTraverser;
use PhpExceptionFlow\AstBridge\System as AstSystem;
use PhpParser\NodeTraverser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AnalysePreliminaryCommand extends Command {
	public function configure() {
		$this->setName("analysis:preliminary")
			 ->setDescription("Do the preliminary analysis on the given project")
			->addArgument(
				'AstSystem',
				InputArgument::REQUIRED,
				'An AstSystem (collection of nodes) of the program to be analysed')
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
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
		$counter_iterator_factory = new CounterIteratorFactory();
		$node_counters = $counter_iterator_factory->create();
		foreach ($node_counters as $node_counter) {
			$ast_traverser->addVisitor($node_counter);
		}

		$system_traverser = new SystemTraverser($ast_traverser);
		$system_traverser->traverse($ast_system);

		$counts = [];
		foreach ($node_counters as $node_counter) {
			$type_splitted = explode("\\", $node_counter->getCountedNodeType());
			$counts[array_pop($type_splitted)] = $node_counter->getCount();
		}

		if (file_exists($output_path . "/preliminary_analysis.json") === true) {
			die($output_path . "/throws_annotations.json already exists");
		} else {
			file_put_contents($output_path . "/preliminary_analysis.json",json_encode($counts, JSON_PRETTY_PRINT));
			$output->write(json_encode(["preliminary analysis" => $output_path . "/preliminary_analysis.json"]));
		}
	}
}