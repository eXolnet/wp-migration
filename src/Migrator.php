<?php

namespace Exolnet\WPMigration;

class Migrator
{
    /**
     * The migration repository implementation.
     * @var \Exolnet\WPMigration\Repository
     */
    protected $repository;
    /**
     * @var string
     */
    private $migrationsFolder;

    /**
     * @var bool
     */
    private $noDev = false;

    /**
     * Create a new migrator instance.
     *
     */
    public function __construct()
    {
        $this->repository = new Repository();

        $this->migrationsFolder = get_template_directory() . '/migrations/';
    }

    /**
     * Run the pending migrations at a given path.
     *
     * @param array|string $paths
     * @param array $options
     * @return array
     */
    public function run($paths = [], array $options = []): array
    {
        // Once we grab all of the migration files for the path, we will compare them
        // against the migrations that have already been run for this package then
        // run each of the outstanding migrations against a database connection.
        $files = $this->getMigrationFiles($paths);

        $this->requireFiles($migrations = $this->pendingMigrations(
            $files,
            $this->repository->getRan()
        ));

        if ($this->noDev) {
            $migrations = $this->filterNoDev($migrations);
        }

        // Once we have all these migrations that are outstanding we are ready to run
        // we will go ahead and run them "up". This will execute each migration as
        // an operation against a database. Then we'll return this list of them.
        $this->runPending($migrations, $options);

        return $migrations;
    }

    /**
     * Rollback the last migration operation.
     *
     * @param array|string $paths
     * @param array $options
     * @return array
     */
    public function rollback($paths = [], array $options = [])
    {
        // We want to pull in the last batch of migrations that ran on the previous
        // migration operation. We'll then reverse those migrations and run each
        // of them "down" to reverse the last migration "operation" which ran.
        $migrations = $this->getMigrationsForRollback($options);

        if (count($migrations) === 0) {
            \WP_CLI::log('Nothing to rollback.');

            return [];
        }

        return $this->rollbackMigrations($migrations, $paths, $options);
    }

    /**
     * Get the migrations for a rollback operation.
     *
     * @param array $options
     * @return array
     */
    protected function getMigrationsForRollback(array $options)
    {
        if (($steps = $options['step'] ?? 0) > 0) {
            return $this->repository->getMigrations($steps);
        } else {
            return $this->repository->getLast();
        }
    }

    /**
     * Rollback the given migrations.
     *
     * @param array $migrations
     * @param array|string $paths
     * @param array $options
     * @return array
     */
    protected function rollbackMigrations(array $migrations, $paths, array $options)
    {
        $rolledBack = [];

        $this->requireFiles($files = $this->getMigrationFiles($paths));

        // Next we will run through all of the migrations and call the "down" method
        // which will reverse each migration in order. This getLast method on the
        // repository already returns these migration's names in reverse order.
        foreach ($migrations as $migration) {
            $fileName = $migration['migration'] . '.php';
            if (!in_array($fileName, $files)) {
                \WP_CLI::error("Migration not found: {$migration['migration']}");
                continue;
            }

            $rolledBack[] = $fileName;

            $this->runDown($fileName, $migration['migration']);
        }

        return $rolledBack;
    }

    /**
     * Run "down" a migration instance.
     *
     * @param string $file
     * @param string $migration
     * @return void
     */
    protected function runDown($file, $migration)
    {
        // First we will get the file name of the migration so we can resolve out an
        // instance of the migration. Once we get an instance we can either run a
        // pretend execution of the migration or we can run the real migration.
        $instance = $this->resolve(
            $name = $this->getMigrationName($file)
        );

        \WP_CLI::log("Rolling back: {$name}");

        $this->runMigration($instance, 'down');

        // Once we have successfully run the migration "down" we will remove it from
        // the migration repository so it will be considered to have not been run
        // by the application then will be able to fire by any later operation.
        $this->repository->delete($migration);

        \WP_CLI::log("Rolled back:  {$name}");
    }

    /**
     * Get the migration files that have not yet run.
     *
     * @param array $files
     * @param array $ran
     * @return array
     */
    protected function pendingMigrations(array $files, array $ran): array
    {
        return array_filter($files, function ($fileName) use ($ran) {
            return !in_array(str_replace('.php', '', $fileName), $ran);
        });
    }

    /**
     * Run an array of migrations.
     *
     * @param array $migrations
     * @param array $options
     * @return void
     */
    public function runPending(array $migrations, array $options = [])
    {
        // First we will just make sure that there are any migrations to run. If there
        // aren't, we will just make a note of it to the developer so they're aware
        // that all of the migrations have been run against this database system.
        if (count($migrations) == 0) {
            \WP_CLI::log('Nothing to migrate.');

            return;
        }

        // Next, we will get the next batch number for the migrations so we can insert
        // correct batch number in the database migrations repository when we store
        // each migration's execution. We will also extract a few of the options.
        $batch = $this->repository->getNextBatchNumber();

        $step = $options['step'] ?? false;

        // Once we have the array of migrations, we will spin through them and run the
        // migrations "up" so the changes are made to the databases. We'll then log
        // that the migration was run so we don't repeat it next time we execute.
        foreach ($migrations as $file) {
            $this->runUp($file, $batch);

            if ($step) {
                $batch++;
            }
        }
    }

    /**
     * Run "up" a migration instance.
     *
     * @param string $file
     * @param int $batch
     * @param bool $pretend
     * @return void
     */
    protected function runUp($file, $batch)
    {
        // First we will resolve a "real" instance of the migration class from this
        // migration file name. Once we have the instances we can run the actual
        // command such as "up" or "down", or we can just simulate the action.
        $migration = $this->resolve(
            $name = $this->getMigrationName($file)
        );

        \WP_CLI::log("Migrating: {$name}");

        $this->runMigration($migration, 'up');

        // Once we have run a migrations class, we will log that it was run in this
        // repository so that we don't try to run it next time we do a migration
        // in the application. A migration repository keeps the migrate order.
        $this->repository->log($name, $batch);

        \WP_CLI::log("Migrated:  {$name}");
    }

    /**
     * Get all of the migration files in a given path.
     *
     * @param string|array $paths
     * @return array
     */
    public function getMigrationFiles($paths)
    {
        return array_filter($paths, function ($fileName) {
            return strpos($fileName, 'php') !== false;
        });
    }

    /**
     * Require in all the migration files in a given path.
     *
     * @param array $files
     * @return void
     */
    public function requireFiles(array $files)
    {
        foreach ($files as $file) {
            require_once($this->migrationsFolder . $file);
        }
    }

    /**
     * Resolve a migration instance from a file.
     *
     * @param string $file
     * @return object
     */
    public function resolve($file)
    {
        $class = $this->studly(implode('_', array_slice(explode('_', $file), 4)));

        return new $class;
    }

    /**
     * Convert a value to studly caps case.
     * @param string $value
     * @return mixed
     */
    private function studly(string $value)
    {
        $value = ucwords(str_replace(['-', '_'], ' ', $value));
        return str_replace(' ', '', $value);
    }

    /**
     * Get the name of the migration.
     *
     * @param string $path
     * @return string
     */
    public function getMigrationName($path)
    {
        return str_replace('.php', '', basename($path));
    }

    /**
     * Run a migration.
     *
     * @param object $migration
     * @param string $method
     * @return void
     */
    private function runMigration($migration, string $method)
    {
        if (method_exists($migration, $method)) {
            $migration->{$method}();
        }
    }

    /**
     * @param bool $value
     */
    public function setNoDev(bool $value)
    {
        $this->noDev = $value;
    }

    /**
     * @param array $migrations
     * @return array
     */
    private function filterNoDev(array $migrations)
    {
        return array_filter($migrations, function ($file) {
            $migration = $this->resolve(
                $name = $this->getMigrationName($file)
            );
            if (!$this->noDev || ($this->noDev && $migration->environment === 'production')) {
                return true;
            }

            return false;
        });
    }
}
