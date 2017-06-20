<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class AnalyseAllCommand extends Command {
	protected function configure() {
		$this->setName("analysis:all")
			->setDescription("Execute the complete analysis")
			->addArgument("exceptionFlowFile",
				InputArgument::REQUIRED,
				"Path to a file containing an exception flow")
			->addArgument("methodOrderFile",
				InputArgument::REQUIRED,
				"Path to a file containing a method order"
				)
			->addArgument("annotationsFile",
				InputArgument::REQUIRED,
				"Path to a file containing @throws annotations for methods")
			->addArgument(
				"classHierarchyFile",
				InputArgument::REQUIRED,
				"Path to a class hierarchy file")
			->addArgument(
				'pathToCatchClauses',
				InputArgument::REQUIRED,
				'Path to an a file containing exception paths'
			)
			->addArgument(
				'AstSystem',
				InputArgument::REQUIRED,
				'An AstSystem (collection of nodes) of the program to be analysed')
			->addArgument(
				'outputPath',
				InputArgument::REQUIRED,
				'The path to which the analysis results have to be written'
			);;
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$app = $this->getApplication();

		$ast_input = new ArrayInput([
			"AstSystem" => $input->getArgument("AstSystem"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$ef_input = new ArrayInput([
			"exceptionFlowFile" => $input->getArgument("exceptionFlowFile"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$ef_and_method_order = new ArrayInput([
			"exceptionFlowFile" => $input->getArgument("exceptionFlowFile"),
			"methodOrderFile" => $input->getArgument("methodOrderFile"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$ef_and_class_hierarchy = new ArrayInput([
			"exceptionFlowFile" => $input->getArgument("exceptionFlowFile"),
			"classHierarchyFile" => $input->getArgument("classHierarchyFile"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$ef_and_annotations_input = new ArrayInput([
			"exceptionFlowFile" => $input->getArgument("exceptionFlowFile"),
			"annotationsFile" => $input->getArgument("annotationsFile"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$ef_and_annotations_and_method_order_input = new ArrayInput([
			"exceptionFlowFile" => $input->getArgument("exceptionFlowFile"),
			"annotationsFile" => $input->getArgument("annotationsFile"),
			"methodOrderFile" => $input->getArgument("methodOrderFile"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$path_input = new ArrayInput([
			"pathToCatchClauses" => $input->getArgument("pathToCatchClauses"),
			"outputPath" => $input->getArgument("outputPath"),
		]);

		$buffered_output = new BufferedOutput();
		$created_paths = [];

		$preliminary_cmd = $app->find("analysis:preliminary");

		$no_encounters_cmd = $app->find("analysis:encounters-per-method");
		$obsolete_try_cmd = $app->find("analysis:obsolete-try-blocks");
		$obsolete_catch_cmd = $app->find("analysis:obsolete-catch-blocks");
		$catch_by_sub_cmd = $app->find("analysis:catch-by-subsumption");
		$path_analysis_cmd = $app->find("analysis:path-until-caught");

		$raises_annotated_cmd = $app->find("analysis:raises-annotated");
		$encounters_annotated_cmd = $app->find("analysis:encounters-annotated");
		$encounters_contract_cmd = $app->find("analysis:encounters-contract");

		$preliminary_cmd->run($ast_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);

		$no_encounters_cmd->run($ef_and_method_order, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$obsolete_try_cmd->run($ef_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$obsolete_catch_cmd->run($ef_and_class_hierarchy, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$catch_by_sub_cmd->run($ef_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$path_analysis_cmd->run($path_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);

		$raises_annotated_cmd->run($ef_and_annotations_and_method_order_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$encounters_annotated_cmd->run($ef_and_annotations_and_method_order_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$encounters_contract_cmd->run($ef_and_annotations_and_method_order_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);

		$output->write(json_encode($created_paths));
	}
}