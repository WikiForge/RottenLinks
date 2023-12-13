<?php

namespace WikiForge\RottenLinks;

use Config;
use ConfigFactory;
use HTMLForm;
use HttpStatus;
use ObjectCache;
use SpecialPage;
use Wikimedia\Rdbms\ILoadBalancer;

class SpecialRottenLinks extends SpecialPage {

	/** @var Config */
	private $config;

	/** @var ILoadBalancer */
	private $dbLoadBalancer;

	/**
	 * @param ConfigFactory $configFactory
	 * @param ILoadBalancer $dbLoadBalancer
	 */
	public function __construct(
		ConfigFactory $configFactory,
		ILoadBalancer $dbLoadBalancer
	) {
		parent::__construct( 'RottenLinks' );

		$this->config = $configFactory->makeConfig( 'RottenLinks' );
		$this->dbLoadBalancer = $dbLoadBalancer;
	}

	/**
	 * @param string $par
	 */
	public function execute( $par ) {
		$this->setHeaders();
		$this->outputHeader();
		$this->addHelpLink( 'Extension:RottenLinks' );

		$showBad = $this->getRequest()->getBool( 'showBad' );
		$stats = $this->getRequest()->getBool( 'stats' );

		$pager = new RottenLinksPager( $this->getContext, $this->config, $showBad );

		$formDescriptor = [
			'showBad' => [
				'type' => 'check',
				'name' => 'showBad',
				'label-message' => 'rottenlinks-showbad',
				'default' => $showBad
			],
			'statistics' => [
				'type' => 'check',
				'name' => 'stats',
				'label-message' => 'rottenlinks-stats',
				'default' => $stats
			],
			'limit' => [
				'type' => 'limitselect',
				'name' => 'limit',
				'label-message' => 'table_pager_limit_label',
				'default' => $pager->getLimit(),
				'options' => $pager->getLimitSelectList()
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setMethod( 'get' )->prepareForm()->displayForm( false );

		if ( $stats ) {
			$statForm = HTMLForm::factory( 'ooui', $this->showStatistics( $this->getContext() ), $this->getContext(), 'rottenlinks' );
			$statForm->setMethod( 'get' )->suppressDefaultSubmit()->prepareForm()->displayForm( false );
			return;
		}

		$this->getOutput()->addParserOutputContent( $pager->getFullOutput() );
	}

	/**
	 * Display statistics related to RottenLinks.
	 *
	 * @return array Array with statistics information.
	 */
	private function showStatistics() {
		$dbr = $this->dbLoadBalancer->getMaintenanceConnectionRef( DB_REPLICA );

		$statusNumbers = $dbr->select(
			'rottenlinks',
			'rl_respcode',
			[],
			__METHOD__,
			'DISTINCT'
		);

		$cache = ObjectCache::getLocalClusterInstance();

		$statDescriptor = [
			'runTime' => [
				'type' => 'info',
				'label-message' => 'rottenlinks-runtime',
				'default' => $cache->get( $cache->makeKey( 'RottenLinks', 'runTime' ) ) . ' seconds',
				'section' => 'metadata'
			],
			'runDate' => [
				'type' => 'info',
				'label-message' => 'rottenlinks-rundate',
				'default' => $this->getContext()->getLanguage()->timeanddate( $cache->get( $cache->makeKey( 'RottenLinks', 'lastRun' ) ), true ),
				'section' => 'metadata'
			]
		];

		foreach ( $statusNumbers as $num ) {
			$respCode = $num->rl_respcode;

			$count = (string)$dbr->selectRowCount(
				'rottenlinks',
				'rl_respcode',
				[
					'rl_respcode' => $respCode
				],
				__METHOD__
			);

			$statDescriptor[$respCode] = [
				'type' => 'info',
				'label' => "HTTP: {$respCode} " . ( $respCode != 0 ? HttpStatus::getMessage( $respCode ) : 'No Response' ),
				'default' => $count,
				'section' => 'statistics'
			];
		}

		return $statDescriptor;
	}

	/**
	 * Get the group name for the special page.
	 *
	 * @return string Group name.
	 */
	protected function getGroupName() {
		return 'maintenance';
	}
}
