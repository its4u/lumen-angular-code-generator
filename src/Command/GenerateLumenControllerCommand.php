<?php

namespace its4u\lumenAngularCodeGenerator\Command;

use Illuminate\Console\Command;
use its4u\lumenAngularCodeGenerator\Config;
use its4u\lumenAngularCodeGenerator\Generator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

/**
 * Class GenerateLumenControllerCommand
 * @package its4u\lumenAngularCodeGenerator\Command
 */
class GenerateLumenControllerCommand extends Command
{
    /**
     * @var string
     */
    protected $name = 'bilibo:lumen:ctrl';

/**
     * @var string
     */
    protected $description = 'Generate CRUD controller for a table name.';

    /**
     * @var Generator
     */
    protected $generator;

    /** 
     * @param Generator $generator
     */
    public function __construct(Generator $generator)
    {
        parent::__construct();

        $this->generator = $generator;
    }

    /**
     * Handler for lumen command
     */
    public function handle()
    {
        return $this->fire();
    }

    /**
     * Executes the command
     */
    public function fire()
    {
        $config = $this->createConfig();

        $ctrl = $this->generator->generateController($config);

        $this->output->writeln(sprintf('Controller %s generated', $ctrl->getName()->getName()));
    }

    /**
     * @return Config
     */
    protected function createConfig()
    {
        $config = [];

        foreach ($this->getArguments() as $argument) {
            if (!empty($this->argument($argument[0]))) {
                $config[$argument[0]] = $this->argument($argument[0]);
            }
        }
        foreach ($this->getOptions() as $option) {
            if (!empty($this->option($option[0]))) {
                $config[$option[0]] = $this->option($option[0]);
            }
        }

        return new Config($config);
    }

    /**
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['class-name', InputArgument::REQUIRED, 'Name of the table'],
        ];
    }

    /**
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['table-name', 'tn', InputOption::VALUE_OPTIONAL, 'Name of the table to use', null],
            ['lumen-ctrl-output-path', 'op', InputOption::VALUE_OPTIONAL, 'Directory to store generated controller', null],
            ['lumen-ctrl-namespace', 'ns', InputOption::VALUE_OPTIONAL, 'Namespace of the controller', null],
            ['base-class-lumen-ctrl-name', 'bc', InputOption::VALUE_OPTIONAL, 'Class that controller must extend', null],
            ['config', 'c', InputOption::VALUE_OPTIONAL, 'Path to config file to use', null],
            ['no-timestamps', 'ts', InputOption::VALUE_NONE, 'Set timestamps property to false', null],
            ['date-format', 'df', InputOption::VALUE_OPTIONAL, 'dateFormat property', null],
            ['connection', 'cn', InputOption::VALUE_OPTIONAL, 'Connection property', null],
        ];
    }
}
