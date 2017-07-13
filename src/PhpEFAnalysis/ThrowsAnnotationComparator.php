<?php
namespace PhpEFAnalysis;

class ThrowsAnnotationComparator {

	private $class_hierarchy;

	const NO = 0;
	const YES = 1;
	const PROBABLY = 2;
	const STILL_UNKNOWN = 4;


	public function __construct(array $class_hierarchy) {
		$this->class_hierarchy = $class_hierarchy;
	}

	/**
	 * @param string $method
	 * @param string $exception
	 * @param string[] $annotations
	 * @return int
	 */
	public function isAnnotated(string $method, string $exception, array $annotations) {
		foreach ($annotations as $annotation) {
			$comparison = $this->compareExceptionToAnnotation($method, $exception, $annotation);
			if ($comparison !== self::STILL_UNKNOWN) {
				return $comparison;
			}
		}
		return self::NO;
	}

	public function isEncountered($method, array $exceptions, string $annotation) {
		foreach ($exceptions as $exception) {
			$comparison = $this->compareExceptionToAnnotation($method, $exception, $annotation);
			if ($comparison !== self::STILL_UNKNOWN) {
				return $comparison;
			}
		}
		$method_splitted = explode("\\", $method);
		array_pop($method_splitted);
		$method_splitted[] = $annotation;
		$context = implode("\\", $method_splitted);

		return self::NO;
	}

	private function compareExceptionToAnnotation($method, $exception, $annotation) {
		$method_splitted = explode("\\", $method);
		array_pop($method_splitted);
		$context = implode("\\", $method_splitted);

		if ($annotation === $exception) {
			return self::YES;
		} else if (substr($annotation, 1) === $exception) {
			return self::YES;
		} else {
			//check whether there exists an exception in the namespace of the given method
			if (strpos($annotation, "\\") === 0) {
				$inferred_throws_annotation = $context . $annotation;
			} else {
				$inferred_throws_annotation = $context . "\\" . $annotation;
			}

			if (isset($this->class_hierarchy['class resolved by'][$inferred_throws_annotation]) === true) {
				if ($exception === $inferred_throws_annotation) {
					return self::PROBABLY;
				}
			}
		}
		return self::STILL_UNKNOWN;
	}
}