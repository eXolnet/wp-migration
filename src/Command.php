<?php

namespace Exolnet\WPMigration;

use WP_CLI;

class Command
{
    /**
     * The migrator instance.
     * @var \Exolnet\WPMigration\Migrator
     */
    protected $migrator;

    public function __construct()
    {
        $this->checkMigrationsTable();
        $this->migrator = new Migrator();
    }

    /**
     *
     */
    private function checkMigrationsTable()
    {
        if (!get_option('exolnet_migrations_table_created', false)) {
            global $wpdb;

            $ptbd_table_name = $wpdb->prefix . 'exolnet_migration';

            if ($wpdb->get_var("SHOW TABLES LIKE '" . $ptbd_table_name . "'") != $ptbd_table_name) {
                $sql = 'CREATE TABLE ' . $ptbd_table_name . '(
            `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`))';

                if (!function_exists('dbDelta')) {
                    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                }

                dbDelta($sql);
                update_option('exolnet_migrations_table_created', true);
            } else {
                $wpdb->query('TRUNCATE ' . $ptbd_table_name . '');
                update_option('exolnet_migrations_table_created', true);
            }
        }
    }

    /**
     * Run or rollback the migration files
     *
     * ## OPTIONS
     *
     * [--rollback]
     * : A flag to run the rollback of the latest migration or a specific batch using the step
     *
     * [--nodev]
     * : Run only migration that are not listed as development. To specify if the migration only in
     *   development you need to set the public variable `public $environment = 'development';`
     *   in your migration class
     *
     * [--step]
     * : Force the migrations to be run so they can be rolled back individually.
     *
     * ## EXAMPLES
     *
     *     wp exolnet migrate
     *
     * @param array $args
     * @param array $assocArgs
     * @throws \WP_CLI\ExitException
     */
    public function migrate($args, array $assocArgs)
    {
        if (array_key_exists('nodev', $assocArgs) && $assocArgs['nodev']) {
            $this->migrator->setNoDev(true);
        }

        if (array_key_exists('rollback', $assocArgs) && $assocArgs['rollback']) {
            $this->migrator->rollback($this->getMigrationPaths(), [
                'step' => $assocArgs['step'] ?? 0,
            ]);
            return;
        }

        $this->migrator->run($this->getMigrationPaths(), [
            'step' => $assocArgs['step'] ?? false,
        ]);
    }

    /**
     * @return array
     * @throws \WP_CLI\ExitException
     */
    private function getMigrationPaths()
    {
        $migrationsFolder = $this->getMigrationFolderPath();

        if (!is_dir($migrationsFolder)) {
            WP_CLI::error('No migrations folder found!');
        }

        return array_diff(scandir($migrationsFolder, 0), [".", ".."]);
    }

    /**
     * Make a migration skeleton file in the themes folder
     *
     * ## OPTIONS
     *
     * <migrationName>
     * : Name of the migration in Camel Case
     *
     *
     * ## EXAMPLES
     *
     *     wp migration make exampleMigration
     *
     * @param array $args
     * @throws \WP_CLI\ExitException
     */
    public function make($args)
    {
        list($migrationName) = $args;

        $migrationName = ucfirst($migrationName);

        $prettyMigrationName = $this->decamelize($migrationName);

        $prettyMigrationName = date("Y_m_d_his") . '_' . $prettyMigrationName . '.php';

        if (!is_dir($this->getMigrationFolderPath())) {
            if (!mkdir($this->getMigrationFolderPath(), 0755)) {
                WP_CLI::error('Unable to create migrations folder in :' . $this->getMigrationFolderPath());
                return;
            }
        }

        $newMigrationFilePath = $this->getMigrationFolderPath() . $prettyMigrationName;

        if (!$migrationBoilerPlate = file_get_contents(__DIR__ . '/migrationBoilerplate.php')) {
            WP_CLI::error('Unable to create migration file');
        }

        $migrationBoilerPlate = str_replace('MigrationBoilerplate', $migrationName, $migrationBoilerPlate);

        if (!file_put_contents($newMigrationFilePath, $migrationBoilerPlate)) {
            WP_CLI::error('Unable to create migration file');
        }

        WP_CLI::success('Migration ' . $prettyMigrationName . ' was created!');
    }

    /**
     * @return string
     */
    private function getMigrationFolderPath(): string
    {
        $migrationsFolder = get_template_directory() . '/migrations/';
        return $migrationsFolder;
    }

    /**
     * @param $string
     * @return string
     */
    private function decamelize($string)
    {
        return strtolower(preg_replace(['/([a-z\d])([A-Z])/', '/([^_])([A-Z][a-z])/'], '$1_$2', $string));
    }
}
