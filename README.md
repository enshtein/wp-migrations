# wp-migrations

A WordPress library for managing database table schema upgrades and data seeding.

## Installation

- `composer require enshtein/wp-migrations`
- bootstrap the package by adding `\Enshtein\WpMigrations\Migrate::instance();` to an mu-plugin.

## Migrations
By default, the command will look for migration files in **migrations** directory alongside the **vendor** folder.

## Use
Simply run `wp migrate` on the command line using **WP CLI** and any migrations not already run will be executed.

## Create a migration file

`wp migrate create add_price_table`

```
<?php

use Enshtein\WpMigrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        global $wpdb;

        // up migration code
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        global $wpdb;

        // down migration code
    }
};
```

`wp migrate create add_price_table --table=price`

```
<?php

use Enshtein\WpMigrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        global $wpdb;

        $_sql = "CREATE TABLE `{$wpdb->prefix}price` (
           `id` int(10) NOT NULL auto_increment,
           PRIMARY KEY (id)
        ) {$this->collation()}";
				
        dbDelta($_sql);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}price`");
    }
};
```

## Other migration commands

- `wp migrate reset` - command will roll back all of your application's migrations

- `wp migrate refresh` - command will roll back all of your migrations and then execute the migrate command

- `wp migrate rollback` - command rolls back the last "batch" of migrations, which may include multiple migration files

- `wp migrate rollback --step=3` - you may roll back a limited number of migrations by providing the step option to the rollback command

- `wp mimgrate status` - if you would like to see which migrations have run thus far

## Configuration

```
\Enshtein\WpMigrations\Migrate::instance([
    'command' => 'migrate',
    'table_name' => 'migrations',
    'folder' => 'migrations',
    'path' => '',
    'filename' => 'Y_m_d_His_',
]);
```

- **command** - database table name to store migrations
- **table_name** - the main command used to run through WP-CLI
- **folder** - folder name with migration files
- **path** - absolute path to the directory with migration files
- **filename** - migration file name template







