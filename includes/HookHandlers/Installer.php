<?php

namespace WikiForge\RottenLinks\HookHandlers;

use DatabaseUpdater;
use MediaWiki\Installer\Hook\LoadExtensionSchemaUpdatesHook;

class Installer implements LoadExtensionSchemaUpdatesHook {

	/**
	 * @param DatabaseUpdater $updater
	 */
	public function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'rottenlinks',
			__DIR__ . '/../sql/rottenlinks.sql' );

		$updater->addExtensionField( 'rottenlinks', 'rl_id',
			__DIR__ . '/../sql/patches/patch-add-rl_id.sql' );

		$updater->addExtensionIndex( 'rottenlinks', 'rl_externallink',
			__DIR__ . '/../sql/patches/20210215.sql' );

		$updater->dropExtensionField( 'rottenlinks', 'rl_pageusage',
			__DIR__ . '/../sql/patches/patch-drop-rl_pageusage.sql' );
	}
}
