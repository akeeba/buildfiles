<?php
/**
 * @package   buildfiles
 * @copyright Copyright (c)2010-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

class ClosureTask extends Task
{
	/**
	 * Path to Closure Compiler's JAR file
	 *
	 * @var string
	 */
	protected $compilerPath = '';

	/**
	 * Closure Compiler compilation optimization level
	 *
	 * @var string
	 */
	protected $compilationLevel = 'SIMPLE_OPTIMIZATIONS';

	/**
	 * Should I generate a source map?
	 *
	 * @var bool
	 */
	protected $generateMap = true;

	/**
	 * Source files
	 *
	 * @var  FileSet[]
	 */
	protected $filesets = [];

	/**
	 * Should the build fail if Closure Compiler reports errors in the compilation?
	 *
	 * @var boolean
	 */
	protected $failonerror = false;

	/**
	 * Where should I save minified files?
	 *
	 * @var  string
	 */
	protected $targetPath = '';

	public function __construct()
	{
		$this->compilerPath = __DIR__ . '/library/closure.jar';
	}

	/**
	 * Adds a set of files (nested fileset attribute).
	 */
	public function createFileSet()
	{
		$num = array_push($this->filesets, new FileSet());

		return $this->filesets[$num - 1];
	}

	/**
	 * Should the build fail on error?
	 *
	 * @param boolean $value
	 */
	public function setFailonerror($value)
	{
		$this->failonerror = $value;
	}

	/**
	 * Set the output path
	 *
	 * @param  string $targetPath
	 */
	public function setTargetPath($targetPath)
	{
		$this->targetPath = $targetPath;
	}

	/**
	 * Path to Closure Compiler JAR
	 *
	 * @param  string $compilerPath
	 */
	public function setCompilerPath($compilerPath)
	{
		$this->compilerPath = $compilerPath;
	}

	/**
	 * Compilation optimization level
	 *
	 * @param  string $level
	 */
	public function setCompilationLevel($level)
	{
		$validLevels = [
			'WHITESPACE_ONLY',
			'SIMPLE_OPTIMIZATIONS',
			'ADVANCED_OPTIMIZATIONS',
		];

		$level = strtoupper($level);

		if (!in_array($level, $validLevels))
		{
			throw new BuildException("Invalid compilation level '$level'. Must be one of WHITESPACE_ONLY, SIMPLE_OPTIMIZATIONS, ADVANCED_OPTIMIZATIONS");
		}

		$this->compilationLevel = $level;
	}

	/**
	 * Should I generate a sourcemap?
	 *
	 * @param bool $value
	 *
	 * @return void
	 */
	public function setGenerateSourcemap($value)
	{
		$this->generateMap = in_array($value, [1, 'yes', 'true', true], true);
	}

	/**
	 * Initialization
	 */
	public function init()
	{
		return true;
	}

	/**
	 * The main entry point method.
	 */
	public function main()
	{
		if (count($this->filesets) == 0)
		{
			throw new BuildException("You must specify a 'file' attribute or a nested fileset");
		}

		$commandBase = 'java -jar ' . $this->compilerPath;
		exec($commandBase . ' --helpshort 2>&1', $output);

		if (!preg_match('/"--helpshort" is not a valid option/', implode('', $output)))
		{
			throw new BuildException('Closure Compiler not found!');
		}

		foreach ($this->filesets as $fs)
		{
			try
			{
				$files    = $fs->getDirectoryScanner($this->project)->getIncludedFiles();
				$fullPath = realpath($fs->getDir($this->project));

				if (empty($this->targetPath))
				{
					foreach ($files as $file)
					{
						$inputPath = $fullPath . '/' . $file;
						$target    = $inputPath;

						$this->compileJSFile($target, $inputPath);
					}
				}
				else
				{
					$target = $this->targetPath;

					if (file_exists(dirname($target)) === false)
					{
						mkdir(dirname($target), 0700, true);
					}

					foreach ($files as $file)
					{
						$inputPath = $fullPath . '/' . $file;

						$this->compileJSFile($target . '/' . basename($file), $inputPath);
					}
				}
			}
			catch (BuildException $be)
			{
				// directory doesn't exist or is not readable
				if ($this->failonerror)
				{
					throw $be;
				}
				else
				{
					$this->log($be->getMessage(), $this->quiet ? Project::MSG_VERBOSE : Project::MSG_WARN);
				}
			}
		}
	}

	private function _exec($command)
	{
		$this->log('Minifying files with ' . $command);
		exec($command . ' 2>&1', $output, $return);
		if ($return > 0)
		{
			$out_string = implode("\n", $output);
			$this->log("Error minifiying:\n{$command}\n{$out_string}", Project::MSG_ERR);
			throw new BuildException('error in minification');
		}
	}

	/**
	 * @param $target
	 * @param $inputPath
	 */
	protected function compileJSFile($target, $inputPath)
	{
		$commandBase = 'java -jar ' . $this->compilerPath;

		if ((substr($target, -3) == '.js') && (substr($target, -7) != '.min.js'))
		{
			$target = substr($target, 0, -3) . '.min.js';
		}

		$command = $commandBase;
		$command .= ' --js ' . $inputPath;
		$command .= " --js_output_file " . $target;
		$command .= " --compilation_level " . $this->compilationLevel;

		if ($this->generateMap)
		{
			$mapTarget = substr($target, 0, -3) . '.map';
			$command   .= ' --create_source_map ' . $mapTarget;

			if (DIRECTORY_SEPARATOR != '\\')
			{
				$command .= ' --output_wrapper "%output% //# sourceMappingURL=' . basename($mapTarget) . '"';
			}
		}

		$this->_exec($command);
	}
}
