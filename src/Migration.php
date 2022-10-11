<?php

namespace Enshtein\WpMigrations;

abstract class Migration {
    abstract protected function up();
    abstract protected function down();

    protected function collation() {
		global $wpdb;
		if (!$wpdb->has_cap('collation')) {
			return '';
		}
		return $wpdb->get_charset_collate();
	}
}