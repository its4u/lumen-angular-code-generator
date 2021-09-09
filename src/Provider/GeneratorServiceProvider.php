<?php

namespace its4u\lumenAngularCodeGenerator\Provider;

use Illuminate\Support\ServiceProvider;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenModelCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenModelsCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenControllerCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenControllersCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenBulkControllerCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenRoutesCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateAngularModelCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateAngularModelsCommand;
use its4u\lumenAngularCodeGenerator\Command\GenerateLumenSwaggerInfoCommand;

/**
 * Class GeneratorServiceProvider
 * @package its4u\lumenAngularCodeGenerator\Provider
 */
class GeneratorServiceProvider extends ServiceProvider
{
    /**
     * {@inheritDoc}
     */
    public function register()
    {
        $this->commands([
            GenerateLumenModelCommand::class,
            GenerateLumenModelsCommand::class,
            GenerateLumenControllerCommand::class,
            GenerateLumenControllersCommand::class,
            GenerateLumenBulkControllerCommand::class,
            GenerateLumenRoutesCommand::class,
            GenerateAngularModelCommand::class,
            GenerateAngularModelsCommand::class,
            GenerateLumenSwaggerInfoCommand::class,
            
        ]);

    }
}