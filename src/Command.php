<?php
namespace Enshtein\WpMigrations;

class Command extends \WP_CLI_Command {

    public function __invoke( $args, $assoc_args ) 
    {
        $migrate = \Enshtein\WpMigrations\Migrate::instance();
        $result = $migrate->run($args, $assoc_args);
    }
}