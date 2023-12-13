<?php

namespace WikiForge\RottenLinks\Maintenance;

use Maintenance;
use MediaWiki\MediaWikiServices;
use ObjectCache;
use WikiForge\RottenLinks\RottenLinks;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}

require_once "$IP/maintenance/Maintenance.php";

class UpdateExternalLinks extends Maintenance {
	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Updates rottenlinks database table based on externallinks table.' );
	}

	public function execute() {
		$time = time();

		$config = MediaWikiServices::getInstance()->getConfigFactory()->makeConfig( 'RottenLinks' );
		$dbw = $this->getDB( DB_PRIMARY );

		$this->output( "Dropping all existing recorded entries\n" );

		$dbw->delete( 'rottenlinks',
			'*',
			__METHOD__
		);

		$res = $dbw->select(
			'externallinks',
			[
				'el_from',
				'el_to_domain_index',
				'el_to_path'
			],
			[],
			__METHOD__
		);

		$rottenlinksarray = [];

		foreach ( $res as $row ) {
			$rottenlinksarray[$row->el_to_domain_index . $row->el_to_path][] = (int)$row->el_from;
		}

		$exclude = (array)$config->get( 'RottenLinksExcludeProtocols' );

		foreach ( $rottenlinksarray as $url => $pages ) {
			$url = $this->decodeDomainName( $url );

			if ( substr( $url, 0, 2 ) === '//' ) {
				$url = 'https:' . $url;
			}

			$urlexp = explode( ':', $url );

			if ( isset( $urlexp[0] ) && in_array( strtolower( $urlexp[0] ), $exclude ) ) {
				continue;
			}

			$mainSite = explode( '/', $urlexp[1] );

			if ( isset( $mainSite[2] ) && in_array( $mainSite[2], $exclude ) ) {
				continue;
			}

			$resp = RottenLinks::getResponse( $url );
			$pagecount = count( $pages );

			$dbw->insert( 'rottenlinks',
				[
					'rl_externallink' => $url,
					'rl_respcode' => $resp
				],
				__METHOD__
			);

			$this->output( "Added externallink ($url) used on $pagecount with code $resp\n" );
		}

		$time = time() - $time;

		$cache = ObjectCache::getLocalClusterInstance();
		$cache->set( $cache->makeKey( 'RottenLinks', 'lastRun' ), $dbw->timestamp() );
		$cache->set( $cache->makeKey( 'RottenLinks', 'runTime' ), $time );

		$this->output( "Script took {$time} seconds.\n" );
	}

	/**
	 * Apparently, MediaWiki URL-encodes the whole URL, including the domain name,
	 * before storing it in the DB. This breaks non-ASCII domains.
	 * URL-decoding the domain part turns these URLs back into valid syntax.
	 *
	 * @param string $url The URL to decode.
	 *
	 * @return string The URL with the decoded domain name.
	 */
	private function decodeDomainName( string $url ): string {
		$urlexp = explode( '://', $url, 2 );
		if ( count( $urlexp ) === 2 ) {
			$locexp = explode( '/', $urlexp[1], 2 );
			$domain = urldecode( $locexp[0] );
			$url = $urlexp[0] . '://' . $domain;
			if ( count( $locexp ) === 2 ) {
				$url = $url . '/' . $locexp[1];
			}
		}

		return $url;
	}
}

$maintClass = UpdateExternalLinks::class;
require_once RUN_MAINTENANCE_IF_MAIN;
