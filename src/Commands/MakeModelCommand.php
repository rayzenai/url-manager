<?php

namespace RayzenAI\UrlManager\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

class MakeModelCommand extends GeneratorCommand
{
    protected $signature = 'url-manager:make-model {name : The name of the model}
                            {--migration : Create a new migration file for the model}
                            {--factory : Create a new factory for the model}
                            {--seed : Create a new seeder for the model}
                            {--all : Generate migration, factory, and seeder}';

    protected $description = 'Create a new Eloquent model with HasUrl trait configured';

    protected $type = 'Model';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        parent::handle();

        if ($this->option('all')) {
            $this->input->setOption('migration', true);
            $this->input->setOption('factory', true);
            $this->input->setOption('seed', true);
        }

        if ($this->option('migration')) {
            $this->createMigration();
        }

        if ($this->option('factory')) {
            $this->createFactory();
        }

        if ($this->option('seed')) {
            $this->createSeeder();
        }
    }

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__ . '/stubs/model.stub';
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Models';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        $this->replaceModelVariables($stub);

        return $stub;
    }

    /**
     * Replace the model variables in the stub.
     */
    protected function replaceModelVariables(string &$stub): void
    {
        $modelName = class_basename($this->argument('name'));
        $tableName = Str::snake(Str::pluralStudly($modelName));
        $slugPath = Str::slug(Str::plural($modelName));

        $stub = str_replace('{{ table }}', $tableName, $stub);
        $stub = str_replace('{{ slugPath }}', $slugPath, $stub);
        $stub = str_replace('{{ modelNameSingular }}', Str::lower($modelName), $stub);
    }

    /**
     * Create a migration file for the model.
     */
    protected function createMigration(): void
    {
        $table = Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));

        $this->call('make:migration', [
            'name' => "create_{$table}_table",
            '--create' => $table,
        ]);

        $this->info("💡 Don't forget to add these columns to your migration:");
        $this->line("   - \$table->string('name');");
        $this->line("   - \$table->string('slug')->unique();");
        $this->line("   - \$table->text('description')->nullable();");
        $this->line("   - \$table->unsignedBigInteger('view_count')->default(0);");
        $this->line("   - \$table->boolean('is_active')->default(true);");
    }

    /**
     * Create a factory file for the model.
     */
    protected function createFactory(): void
    {
        $this->call('make:factory', [
            'name' => class_basename($this->argument('name')) . 'Factory',
            '--model' => $this->qualifyClass($this->getNameInput()),
        ]);
    }

    /**
     * Create a seeder file for the model.
     */
    protected function createSeeder(): void
    {
        $this->call('make:seeder', [
            'name' => class_basename($this->argument('name')) . 'Seeder',
        ]);
    }
}
