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
			)
			->addArgument(
				'onlyAnalyseWithPrefix',
				InputArgument::OPTIONAL,
				"If this parameter is set, only scopes with a certain prefix are analysed"
			);
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$app = $this->getApplication();

		$prefix = $input->getArgument("onlyAnalyseWithPrefix");
		if ($prefix !== null && empty($prefix) === false) {
			$prefix = strtolower($prefix);
			$exception_flow_dump = $this->prepareFile($input->getArgument("exceptionFlowFile"), $prefix);
			$method_order_dump = $this->prepareFile($input->getArgument("methodOrderFile"), $prefix);
			$annotations_dump = $this->prepareFile($input->getArgument("annotationsFile"), $prefix);
			$catch_paths_dump = $this->prepareFile($input->getArgument("pathToCatchClauses"), $prefix);
		}

		$prelim_input = new ArrayInput([
			"AstSystem" => $input->getArgument("AstSystem"),
			"annotationsFile" => $input->getArgument("annotationsFile"),
			"outputPath" => $input->getArgument("outputPath"),
			"onlyAnalyseWithPrefix" => $input->getArgument("onlyAnalyseWithPrefix"),
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

		$preliminary_cmd->run($prelim_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);

		$no_encounters_cmd->run($ef_and_method_order, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$obsolete_try_cmd->run($ef_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$obsolete_catch_cmd->run($ef_and_class_hierarchy, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$catch_by_sub_cmd->run($ef_and_class_hierarchy, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$path_analysis_cmd->run($path_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);

		$raises_annotated_cmd->run($ef_and_annotations_and_method_order_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$encounters_annotated_cmd->run($ef_and_annotations_and_method_order_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);
		$encounters_contract_cmd->run($ef_and_annotations_and_method_order_input, $buffered_output);
		$created_paths = array_merge(json_decode($buffered_output->fetch(), $assoc = true), $created_paths);

		if ($prefix !== null && empty($prefix) === false) {
			 $this->restoreFile($input->getArgument("exceptionFlowFile"), $exception_flow_dump);
			 $this->restoreFile($input->getArgument("methodOrderFile"), $method_order_dump);
			 $this->restoreFile($input->getArgument("pathToCatchClauses"), $catch_paths_dump);
			 $this->restoreFile($input->getArgument("annotationsFile"), $annotations_dump);
		}

		$output->write(json_encode($created_paths));
	}

	/**
	 * @param string $file_indexed_by_scopes
	 * @param string $prefix
	 * @return string the path to the dumped scopes
	 */
	private function prepareFile(string $file_indexed_by_scopes, string $prefix) : string {
		$scope_file = json_decode(file_get_contents($file_indexed_by_scopes), $assoc = true);
		$ignored_scopes = [];
		foreach ($scope_file as $scope => $scope_data) {
			if (strpos($scope, $prefix) !== 0) {
				$ignored_scopes[$scope] = $scope_data;
				unset($scope_file[$scope]);
			}
		}

		file_put_contents(dirname($file_indexed_by_scopes) . "/dumped_" . basename($file_indexed_by_scopes), json_encode($ignored_scopes, JSON_PRETTY_PRINT));
		file_put_contents($file_indexed_by_scopes, json_encode($scope_file, JSON_PRETTY_PRINT));
		return dirname($file_indexed_by_scopes) . "/dumped_" . basename($file_indexed_by_scopes);
	}



	private function restoreFile($file_indexed_by_scopes, $temp_dump_file) {
		$scope_file = json_decode(file_get_contents($file_indexed_by_scopes), $assoc = true);
		$ignored_scopes = json_decode(file_get_contents($temp_dump_file), $assoc = true);
		foreach ($ignored_scopes as $scope => $scope_data) {
			$scope_file[$scope] = $scope_data;
		}

		file_put_contents($file_indexed_by_scopes, json_encode($scope_file, JSON_PRETTY_PRINT));
	}
}