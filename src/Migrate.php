<?php

namespace Enshtein\WpMigrations;

use WP_CLI;

class Migrate
{
    private static $instance;

	private $db;

	private $params = [];

    public static function instance($params=[])
    {

		$params = array_merge([
			'command' => 'migrate',
			'table_name' => 'migrations',
			'folder' => 'migrations',
			'path' => '',
			'filename' => 'Y_m_d_His_',
		], $params);

        if (!isset(self::$instance) && !(self::$instance instanceof Migrate)) {
			self::$instance = new Migrate();
			self::$instance->init($params);
		}
		return self::$instance;
    }

    public function init($params) {
		$this->params = $params;
		if (defined('WP_CLI') && WP_CLI) {
			\WP_CLI::add_command($this->params['command'], Command::class);
		}
	}

	public function run($args, $assoc_args)
	{
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		global $wpdb;
		$this->db = $wpdb;

		$this->params['table'] = $this->db->prefix . $this->params['table_name'];

		if (!$this->params['path']) {
			$base_path = __FILE__;
			while ( basename( $base_path ) != 'vendor' ) {
				$base_path = dirname($base_path);
			}
			$this->params['path'] = dirname($base_path);
		}

		$this->install();

		if (count($args)) {
			$command = array_shift($args);
			if (method_exists($this, $command))
				$this->$command($args, $assoc_args);
			else
				\WP_CLI::error("command: $command - not found!", true);	
		} else {
			$this->migrate($args, $assoc_args);
		}
	}

	public function migrate($args, $assoc_args)
	{	
		$this->dbsync();	
		$migrations = $this->db->get_results("SELECT * FROM `{$this->params['table']}` WHERE `batch` <= 0", ARRAY_A);
		$this->up($migrations);
	}

	public function dbsync()
	{
		$folder = rtrim($this->params['path'], '/') . '/' . $this->params['folder'];
		$_files = glob($folder.'/'.'*.php');
		if (is_array($_files) && count($_files)) {
			$migrations = $this->db->get_results("SELECT `migration` FROM `{$this->params['table']}`", ARRAY_A);
			$_db_migrations = [];
			if (is_array($migrations) && count($migrations)) {
				foreach ($migrations as $_item) {
					$_db_migrations[] = $_item['migration'];
				}
			}
			foreach ($_files as $_file) {
				$_migration = basename($_file, '.php');
				if (!in_array($_migration, $_db_migrations)) {
					$this->db->insert($this->params['table'], [
						'migration' => $_migration,
						'batch' => 0,
					]);
				}
			}
		}
	}

	public function create($args, $assoc_args)
	{
		$this->dbsync();
		if (!isset($args[0]) || trim($args[0])=='') {
			\WP_CLI::error('Migration name is empty!');
		}
		$name = mb_strtolower($args[0], 'utf-8');
		$name = trim(str_replace(' ', '_', $name));
		$filename = date($this->params['filename']);
		$filename .= $name;

		$folder = rtrim($this->params['path'], '/') . '/' . $this->params['folder'];

		if ( !file_exists($folder) )
      		wp_mkdir_p($folder);

		$php = file_get_contents(dirname(__FILE__).'/MigrateClass.php');

		if (isset($assoc_args['table']) && trim($assoc_args['table'])) {
			$php = str_replace(
				'// up migration code', 
				'$_sql = "CREATE TABLE `{$wpdb->prefix}' . $assoc_args['table'] . '` (
			`id` int(10) NOT NULL auto_increment,
			PRIMARY KEY (id)
		) {$this->collation()}";
				
		dbDelta($_sql);',
				$php);
			
			$php = str_replace(
				'// down migration code', 
				'$wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}' . $assoc_args['table'] . '`");',
				$php);
		}

		file_put_contents($folder.'/'.$filename.'.php', $php);

		$this->db->insert($this->params['table'], [
			'migration' => $filename,
			'batch' => 0,
		]);
	}

	public function install()
	{	
		if ($this->db->get_var("SHOW TABLES LIKE '{$this->params['table']}'") != $this->params['table']) {
			
			$collation = ! $this->db->has_cap( 'collation' ) ? '' : $this->db->get_charset_collate();

			$_sql = "CREATE TABLE `{$this->params['table']}` (
				`id` int(10)  NOT NULL auto_increment,
				`migration` varchar(255) NOT NULL,
				`batch` int(11) NOT NULL,
				PRIMARY KEY (id)
			) {$collation}";

			dbDelta( $_sql );
		}
	}

	public function reset($args, $assoc_args)
	{
		$migrations = $this->db->get_results("SELECT * FROM `{$this->params['table']}` WHERE `batch` > 0", ARRAY_A);
		$this->down($migrations);
	}

	public function refresh($args, $assoc_args)
	{
		$migrations = $this->db->get_results("SELECT * FROM `{$this->params['table']}` WHERE `batch` > 0", ARRAY_A);
		$this->down($migrations);
		$migrations = $this->db->get_results("SELECT * FROM `{$this->params['table']}` WHERE `batch` <= 0", ARRAY_A);
		$this->up($migrations);
	}

	public function rollback($args, $assoc_args)
	{
		if (isset($assoc_args['step']) && is_integer($assoc_args['step'])) {
			$step = (int) $assoc_args['step'];
		} else {
			$step = 1;
		}

		$migrations = $this->db->get_results("SELECT * FROM `{$this->params['table']}` WHERE `batch` > 0 ORDER BY `id` DESC LIMIT $step", ARRAY_A);

		$this->down($migrations);
	}

	private function down($migrations)
	{
		if (!is_array($migrations) && !count($migrations)) return;
		$folder = rtrim($this->params['path'], '/') . '/' . $this->params['folder'];
		foreach ($migrations as $_migration) {
			$file = $folder.'/'.$_migration['migration'].'.php';
			if (!is_file($file))
				continue;

			$migrate = include($file);
			$migrate->down();

			$this->db->update($this->params['table'], 
				['batch' => 0],
				['id' => $_migration['id']]
			);

			\WP_CLI::line(\WP_CLI::colorize("%GRolled back:%n {$_migration['migration']}"));

			unset($migrate);
		}
	}

	private function up($migrations)
	{
		if (!is_array($migrations) && !count($migrations)) return;

		$batch = (int) $this->db->get_var("SELECT MAX(`batch`) FROM `{$this->params['table']}`");
		$batch += 1;

		$folder = rtrim($this->params['path'], '/') . '/' . $this->params['folder'];
		foreach ($migrations as $_migration) {
			$file = $folder.'/'.$_migration['migration'].'.php';
			if (!is_file($file))
				continue;

			$migrate = include($file);
			$migrate->up();

			$this->db->update($this->params['table'], 
				['batch' => $batch],
				['id' => $_migration['id']]
			);

			\WP_CLI::line(\WP_CLI::colorize("%GMigrated:%n {$_migration['migration']}"));

			unset($migrate);
		}
	}

	public function status($args, $assoc_args)
	{
		$this->dbsync();
		$migrations = $this->db->get_results("SELECT * FROM `{$this->params['table']}` ORDER BY `id` DESC", ARRAY_A);
		if (is_array($migrations) && count($migrations)) {
			$items = [];
			foreach ($migrations as $_migration) {
				$items[] = [
					'Ran?' => $_migration['batch'] ? 'Yes' : 'No',  
					'Migration' => $_migration['migration'],
				];
			}
			\WP_CLI\Utils\format_items('table', $items, array('Ran?', 'Migration'));
		} else {
			\WP_CLI::line('No found migrations!');
		}
	}
}