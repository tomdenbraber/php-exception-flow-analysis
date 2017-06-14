<?php
namespace PhpEFAnalysis\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

class BuildAndAnalyseCommand extends Command {

	public function configure() {
		$this->setName("build-and-analyse")
			->setDescription("Build and analyse the exception flow")
			->addArgument("pathToProject",
				InputArgument::REQUIRED,
				"The path to the system to be analysed")
			->addArgument("pathToOutputFolder",
				InputArgument::REQUIRED,
				"The pat to the output folder");
	}

	public function execute(InputInterface $input, OutputInterface $output) {
		$app = $this->getApplication();
		$build_ef_cmd = $app->find("build:exception-flow");
		$build_annotations_cmd = $app->find("build:throws-annotations-set");
		$analyse_all = $app->find("analysis:all");

		$build_ef_input = new ArrayInput([
			"command" => "build:exception-flow",
			"pathToProject" => $input->getArgument("pathToProject"),
			"pathToOutputFolder" =>  $input->getArgument("pathToOutputFolder"),
		]);

		$buffered_output = new BufferedOutput();
		$build_ef_cmd->run($build_ef_input, $buffered_output);

		$paths = json_decode($buffered_output->fetch(), $assoc = true);
		$path_splitted = explode("/", $paths["exception flow"]);
		array_pop($path_splitted);
		$project_results_path = implode("/", $path_splitted);


		$build_annotations_input = new ArrayInput([
			"command" => "build:throws-annotations-set",
			"AstSystem" => $paths["ast system cache"],
			"outputPath" => $project_results_path,
		]);

		$build_annotations_cmd->run($build_annotations_input, $buffered_output);

		$paths = array_merge($paths, json_decode($buffered_output->fetch(), $assoc = true));

		$analyse_all_input = new ArrayInput([
			"command" => "analysis:all",
			"exceptionFlowFile" => $paths["exception flow"],
			"methodOrderFile" => $paths["method order"],
			"classHierarchyFile" => $paths["class hierarchy"],
			"annotationsFile" => $paths["throws annotation set"],
			"AstSystem" => $paths["ast system cache"],
			"pathToCatchClauses" => $paths["path to catch clauses"],
			"outputPath" => $project_results_path,
		]);
		$analyse_all->run($analyse_all_input, $buffered_output);

		$paths = array_merge($paths, json_decode($buffered_output->fetch(), $assoc = true));
		$output->writeln("Generated artifacts:");
		foreach ($paths as $artifact => $path) {
			$output->writeln([$artifact, $path]);
		}

	}

}