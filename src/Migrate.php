<?php

namespace Enshtein\WpMigrations;

class Migrate
{
    private static $instance;

    public static function instance($command='migrate')
    {
        if (!isset(self::$instance) && !(self::$instance instanceof Migrate)) {
			self::$instance = new Migrate();
			self::$instance->init($command);
		}
		return self::$instance;
    }

    public function init($command) {
		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command($command, Command::class);
		}
	}

	public function run($args, $assoc_args)
	{
		return 'working...';
	}
}