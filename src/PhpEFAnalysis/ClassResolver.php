<?php
namespace PhpEFAnalysis;

class ClassResolver {
	private $class_hierarchy;
	public function __construct($class_hierarchy) {
		$this->class_hierarchy = $class_hierarchy;
	}

	public function resolvesTo(string $class, string $supertype) {
		return isset($this->class_hierarchy["class resolves"][$class][$supertype]);
	}
}