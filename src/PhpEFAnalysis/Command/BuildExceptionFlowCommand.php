<?php
namespace PhpEFAnalysis\Command;

use PhpExceptionFlow\Runner as EFRunner;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BuildExceptionFlowCommand extends Command {

	protected function configure() {
		$this->setName('build:exception-flow')
			->setDescription('Build the exception for a given project')
			->addArgument(
				'pathToProject',
				InputArgument::REQUIRED,
				'path to the project that you want to analyse')
			->addArgument(
				'pathToOutputFolder',
				InputArgument::REQUIRED,
				'path to the folder for the results');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$path_to_project = $input->getArgument('pathToProject');
		$path_to_output_folder = $input->getArgument('pathToOutputFolder');


		$ef_runner = new EFRunner($path_to_project, $path_to_output_folder);
		$ef_runner->run();

		$output->write(json_encode($ef_runner->output_files));
	}
}