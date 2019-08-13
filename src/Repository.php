<?php

namespace Exolnet\WPMigration;

class Repository
{
    /**
     * Get the ran migrations.
     *
     * @return array
     */
    public function getRan()
    {
        global $wpdb;

        $sql = "SELECT migration as name FROM {$wpdb->prefix}exolnet_migration as em";

        $migrations = $wpdb->get_results($sql, 'ARRAY_A');

        $migrationCleaned = [];
        if (!empty($migrations) && is_array($migrations)) {
            foreach ($migrations as $migration) {
                $migrationCleaned[] = $migration['name'];
            }
        }

        return $migrationCleaned;
    }

    /**
     * Get list of migrations.
     *
     * @param int $steps
     * @return array
     */
    public function getMigrations($steps)
    {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}exolnet_migration as em
                WHERE batch >= 1 ORDER BY batch DESC, migration DESC LIMIT {$steps}";

        return $wpdb->get_results($sql, 'ARRAY_A');
    }

    /**
     * Get the last migration batch.
     *
     * @return array
     */
    public function getLast()
    {
        global $wpdb;

        $sql = "SELECT * FROM {$wpdb->prefix}exolnet_migration as em
                WHERE batch = {$this->getLastBatchNumber()} ORDER BY migration DESC";

        return $wpdb->get_results($sql, 'ARRAY_A');
    }

    /**
     * Get the next migration batch number.
     *
     * @return int
     */
    public function getNextBatchNumber()
    {
        return $this->getLastBatchNumber() + 1;
    }

    /**
     * Get the last migration batch number.
     *
     * @return int
     */
    public function getLastBatchNumber()
    {
        global $wpdb;

        $max = $wpdb->get_results(
            "SELECT MAX(`batch`) as max FROM {$wpdb->prefix}exolnet_migration as em",
            'ARRAY_A'
        );

        return absint($max[0]['max']) ?? 0;
    }

    /**
     * Log that a migration was run.
     *
     * @param string $file
     * @param int $batch
     * @return void
     */
    public function log($file, $batch)
    {
        global $wpdb;

        $record = ['migration' => $file, 'batch' => $batch];

        $wpdb->insert(
            "{$wpdb->prefix}exolnet_migration",
            $record
        );
    }

    /**
     * Remove a migration from the log.
     *
     * @param string $migration
     * @return void
     */
    public function delete($migration)
    {
        global $wpdb;
        $record = ['migration' => $migration];

        $wpdb->delete(
            "{$wpdb->prefix}exolnet_migration",
            $record
        );
    }
}
