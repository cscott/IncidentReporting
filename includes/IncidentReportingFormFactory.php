<?php

class IncidentReportingFormFactory {
	public function getFormDescriptor(
		Database $dbw,
		int $id = 0,
		bool $edit = false,
		IContextSource $context
	) {
		global $wgIncidentReportingServices, $wgIncidentReportingTaskUrl;

		OutputPage::setupOOUI(
			strtolower( $context->getSkin()->getSkinName() ),
			$context->getLanguage()->getDir()
		);

		if ( !$id ) {
			$action = 'create';
		} elseif ( $edit ) {
			$action = 'edit';
		} else {
			$action = 'view';
		}

		if ( $action == 'create' ) {
			$data = NULL;
		} else {
			$data = $dbw->selectRow(
				'incidents',
				'*',
				[
					'i_id' => $id
				]
			);
		}

		$irServices = [];
		$irServicesUrl = [];
		foreach ( $wgIncidentReportingServices as $service => $url ) {
			$niceName = str_replace( ' ', '-', strtolower( $service ) );
			$irServices[$service] = $niceName;

			if ( $url ) {
				$irServicesUrl[$niceName] = $url;
			}
		}

		$revServices = array_flip( $irServices );

		if ( !is_null( $data ) ) {
			$respArray = explode( "\n", $data->i_responders );
			$responders = [];

			if ( count( $respArray ) != 0 ) {
				foreach ( $respArray as $resp ) {
					$responders[] = Linker::userLink( (int)User::newFromName( $resp )->getId(), $resp );
				}
			}
		}

		$reviewers = [
			'reviewed' => [],
			'unreviewed' => [],
			'all' => []
		];

		if ( $id ) {
			$dbReviewers = $dbw->select(
				'incidents_reviewer',
				'*',
				[
					'r_incident' => $id
				]
			);

			foreach ( $dbReviewers as $db ) {
				if ( $db->r_timestamp ) {
					$reviewers['reviewed'][] = Linker::userLink( User::newFromName( $db->r_user )->getId(), $db->r_user );
				} else {
					$reviewers['unreviewed'][] = Linker::userLink( User::newFromName( $db->r_user )->getId(), $db->r_user );
				}

				$reviewers['all'][] = $db->r_user;
			}

			$reviewers['reviewed'] = ( count( $reviewers['reviewed'] ) != 0 ) ? implode( ', ', $reviewers['reviewed'] ) : 'None';
			$reviewers['unreviewed'] = ( count( $reviewers['unreviewed'] ) != 0 ) ? implode( ', ', $reviewers['unreviewed'] ) : 'None';

		}

		$buildDescriptor = [
			'service' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-label-service',
				'options' => $irServices,
				'default' => ( !is_null( $data ) ) ? $data->i_service : '',
				'section' => 'main'
			],
			'cause' => [
				'type' => 'select',
				'label-message' => 'incidentreporting-label-cause',
				'options' => [
					wfMessage( 'incidentreporting-label-human' )->text() => 'human',
					wfMessage( 'incidentreporting-label-technical' )->text() => 'technical',
					wfMessage( 'incidentreporting-label-upstream' )->text() => 'upstream'
				],
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? $data->i_cause : 'human'
			],
			'control-aggravation' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-aggravation',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? (bool)$data->i_aggravation : false
			],
			'aggravation' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? $data->i_aggravation : '',
				'hide-if' => [ '!==', 'wpcontrol-aggravation', '1' ]
			],
			'control-known' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-known',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? (bool)$data->i_known : false
			],
			'known' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? $data->i_known : '',
				'hide-if' => [ '!==', 'wpcontrol-known', '1' ]
			],
			'control-preventable' => [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-preventable',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? (bool)$data->i_preventable : false
			],
			'preventable' => [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-explain',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? $data->i_preventable : '',
				'hide-if' => [ '!==', 'wpcontrol-preventable', '1' ]
			],
			'responders' => [
				'type' => 'usersmultiselect',
				'label-message' => 'incidentreporting-label-responders',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? $data->i_responders : '',
				'exitsts' => true
			],
			'review' => [
				'type' => 'usersmultiselect',
				'label-message' => 'incidentreporting-label-reviewers',
				'section' => 'main',
				'default' => ( isset( $reviewers['all'] ) ) ? implode( "\n", $reviewers['all'] ) : '',
				'exists' => true
			],
		];

		$viewDescriptor = [
			'service' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-service',
				'default' => ( isset( $irServicesUrl[$data->i_service] ) ) ? Linker::makeExternalLink( $irServicesUrl[$data->i_service], $revServices[$data->i_service] ) : $revServices[$data->i_service],
				'raw' => true,
				'section' => 'main'
			],
			'outage-visible' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-outage-visible',
				'section' => 'main',
				'default' => wfMessage( 'incidentreporting-label-outage-formatted', ( $data->i_outage_visible / 60 ) )->text()
			],
			'outage-total' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-outage-total',
				'section' => 'main',
				'default' => wfMessage( 'incidentreporting-label-outage-formatted', ( $data->i_outage_total / 60 ) )->text()
			],
			'cause' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-cause',
				'section' => 'main',
				'default' => wfMessage( "incidentreporting-label-{$data->i_cause}" )->text(),
			],
			'aggravation' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-aggravation',
				'section' => 'main',
				'raw' => true,
				'default' => $data->i_aggravation ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'known' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-known',
				'section' => 'main',
				'raw' => true,
				'default' => $data->i_known ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'preventable' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-preventable',
				'section' => 'main',
				'raw' => true,
				'default' => $data->i_preventable ?? wfMessage( 'incidentreporting-label-na' )->text()
			],
			'responders' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-responders',
				'section' => 'main',
				'raw' => true,
				'default' => implode( "\n", $responders )
			],
			'review' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-reviewers',
				'section' => 'main',
				'default' => wfMessage( 'incidentreporting-label-reviewers-info', $reviewers['reviewed'], $reviewers['unreviewed'] )->text(),
				'raw' => true
			],
			'published' => [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-published',
				'section' => 'main',
				'default' => ( !is_null( $data->i_published ) ) ? wfTimestamp( TS_RFC2822, (int)$data->i_published ) : wfMessage( 'incidentreporting-label-notpublished' )->text()
			]
		];

		if ( is_null( $data ) || is_null( $data->i_published ) ) {
			$buildDescriptor['publish'] = [
				'type' => 'check',
				'label-message' => 'incidentreporting-label-publish',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? (bool)$data->i_published : false,
				'disabled' => !$edit
			];
		}

		// build a log like above
		$buildLog = [];
		$logData = $dbw->select(
			'incidents_log',
			'*',
			[
				'log_incident' => $id
			]
		);

		if ( $action == 'view' ) {
			if ( $logData ) {
				foreach ( $logData as $ldata ) {
					$buildLog[$ldata->log_id] = [
						'type' => 'info',
						'label' => wfMessage( 'incidentreporting-log-header', $ldata->log_actor, wfTimestamp( TS_RFC2822, (int)$ldata->log_timestamp ), $ldata->log_state ),
						'section' => 'logs',
						'subsection' => (string)$ldata->log_id,
						'raw' => true,
						'default' => $context->getOutput()->parse( $ldata->log_action )
					];
				}
			} else {
				$buildLog[] = [
					'type' => 'info',
					'label-message' => 'incidentreporting-log-no-data'
				];
			}
		} else {
			$logId = 0;

			if ( $action == 'edit' && $logData ) {
				foreach ( $logData as $ldata ) {
					$logId = (int)$ldata->log_id;

					$buildLog["{$logId}-timestamp"] = [
						'type' => 'datetime',
						'label' => wfMessage( 'incidentreporting-log-timestamp', $logId )->parse(),
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => wfTimestamp( TS_ISO_8601, (int)$ldata->log_timestamp )
					];

					$buildLog["{$logId}-actor"] = [
						'type' => 'select',
						'label' => wfMessage( 'incidentreporting-log-actor', $logId )->parse(),
						'options' => [
							wfMessage( 'incidentreporting-log-actor-information' )->text() => 'information',
							wfMessage( 'incidentreporting-log-actor-user' )->text() => 'user'
						],
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => ( $ldata->log_actor == 'information' ) ? 'information' : 'user'
					];

					$buildLog["{$logId}-user"] = [
						'type' => 'user',
						'label' => wfMessage( 'incidentreporting-log-user', $logId )->parse(),
						'exists' => true,
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => $ldata->log_actor,
						'hide-if' => [ '!==', "wp{$logId}-actor", 'user' ]
					];
					$buildLog["{$logId}-action"] = [
						'type' => 'text',
						'label' => wfMessage( 'incidentreporting-log-action', $logId )->parse(),
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => $ldata->log_action,
					];

					$buildLog["{$logId}-state"] = [
						'type' => 'select',
						'label' => wfMessage( 'incidentreporting-log-state', $logId )->parse(),
						'options' => [
							wfMessage( 'incidentreporting-log-up' )->text() => 'up',
							wfMessage( 'incidentreporting-log-partial' )->text() => 'partial',
							wfMessage( 'incidentreporting-log-down' )->text() => 'down'
						],
						'section' => 'logs',
						'subsection' => (string)$logId,
						'default' => $ldata->log_state
					];
				}
			}

			for ( $newId = $logId + 1; $newId <= $logId + 10; $newId++ ) {
				$buildLog["{$newId}-timestamp"] = [
					'type' => 'datetime',
					'label' => wfMessage( 'incidentreporting-log-timestamp', $newId )->parse(),
					'section' => 'logs',
					'subsection' => (string)$newId
				];

				$buildLog["{$newId}-actor"] = [
					'type' => 'select',
					'label' => wfMessage( 'incidentreporting-log-actor', $newId )->parse(),
					'options' => [
						wfMessage( 'incidentreporting-log-actor-information' )->text() => 'information',
						wfMessage( 'incidentreporting-log-actor-user' )->text() => 'user'
					],
					'section' => 'logs',
					'subsection' => (string)$newId
				];

				$buildLog["{$newId}-user"] = [
					'type' => 'user',
					'label' => wfMessage( 'incidentreporting-log-user', $newId )->parse(),
					'exists' => true,
					'section' => 'logs',
					'subsection' => (string)$newId,
					'hide-if' => [ '!==', "{$newId}-actor", 'user' ]
				];

				$buildLog["{$newId}-action"] = [
					'type' => 'text',
					'label' => wfMessage( 'incidentreporting-log-action', $newId )->parse(),
					'section' => 'logs'
				];

				$buildLog["{$newId}-state"] = [
					'type' => 'select',
					'label' => wfMessage( 'incidentreporting-log-state', $newId )->text(),
					'options' => [
						wfMessage( 'incidentreporting-log-up' )->text() => 'up',
						wfMessage( 'incidentreporting-log-partial' )->text() => 'partial',
						wfMessage( 'incidentreporting-log-down' )->text() => 'down'
					],
					'section' => 'logs',
					'subsection' => (string)$newId
				];
			}

			$buildLog['logs-number'] = [
				'type' => 'hidden',
				'default' => (string)$newId,
				'section' => 'logs'
			];
		}

		// actionables
		if ( $action == 'view' ) {
			$aArray = json_decode( $data->i_tasks, true );

			foreach ( $aArray as $task ) {
				$tasks[] = '<a href="' . $wgIncidentReportingTaskUrl . $task . '">' . $task . '</a>';
			}

			$viewDescriptor['actionables'] = [
				'type' => 'info',
				'label-message' => 'incidentreporting-label-actionables',
				'section' => 'main',
				'raw' => true,
				'default' => ( count( $tasks ) != 0 ) ? implode( "\n", $tasks ) : wfMessage( 'incidentreporting-label-no-actionables' )->text()
			];
		} else {
			$buildDescriptor['actionables'] = [
				'type' => 'textarea',
				'label-message' => 'incidentreporting-label-actionables',
				'section' => 'main',
				'default' => ( !is_null( $data ) ) ? implode( "\n", json_decode( $data->i_tasks, trye ) ) : ''
			];
		}

		$buildDescriptor[$action] = [
			'type' => 'submit',
			'default' => wfMessage( "incidentreporting-{$action}" )->text(),
			'section' => 'main'
		];

		if ( $context->getUser()->isAllowed( 'editincident' ) ) {
			$viewDescriptor['view'] = [
				'type' => 'submit',
				'default' => wfMessage( 'incidentreporting-view')->text(),
				'section' => 'main'
			];
		}

		$formDescriptor = [];

		if ( $action == 'view' ) {
			$formDescriptor = array_merge( $viewDescriptor, $buildLog );
		} else {
			$formDescriptor = array_merge( $buildDescriptor, $buildLog );
		}

		return $formDescriptor;
	}


	public function getForm(
		int $id = 0,
		bool $edit = false,
		Database $dbw,
		IContextSource $context,
		$formClass = IncidentReportingOOUIForm::class
	) {
		$formDescriptor = $this->getFormDescriptor( $dbw, $id, $edit, $context );

		$htmlForm = new $formClass( $formDescriptor, $context, 'incidentreporting' );

		$htmlForm->setId( 'mw-baseform-ir' );
		$htmlForm->suppressDefaultSubmit();
		$htmlForm->setSubmitCallback(
			function ( array $formData, HTMLForm $form ) use ( $id, $dbw, $context ) {
				return $this->submitForm( $formData, $form, $id, $dbw, $context );
			}
		);

		$irUser = $context->getUser()->getName();

		$isReviewer = $dbw->selectRow(
			'incidents_reviewer',
			'*',
			[
				'r_user' => $irUser,
				'r_incident' => $id
			]
		);

		if ( $isReviewer && !$isReviewer->r_timestamp ) {
			$dbw->update(
				'incidents_reviewer',
				[
					'r_timestamp' => $dbw->timestamp()
				],
				[
					'r_user' => $irUser,
					'r_incident' => $id
				]
			);
		}

		return $htmlForm;
	}

	protected function submitForm(
		array $formData,
		HTMLForm $form,
		int $id = 0,
		Database $dbw,
		IContextSource $context
	) {
		$out = $context->getOutput();

		if ( $formData['view'] ) {
			header( 'Location: ' . SpecialPage::getTitleFor( 'IncidentReports' )->getFullUrl() . '/' . $id . '/edit' );

			return true;
		}

		// Handle main data for the incident
		$dbIncident = [
			'i_service' => $formData['service'],
			'i_cause' => $formData['cause'],
			'i_aggravation' => ( $formData['control-aggravation'] ) ? $formData['aggravation'] : NULL,
			'i_known' => ( $formData['control-known'] ) ? $formData['known'] : NULL,
			'i_preventable' => ( $formData['control-preventable'] ) ? $formData['preventable'] : NULL,
			'i_responders' => $formData['responders'],
			'i_tasks' => ( $formData['actionables'] ) ? json_encode( explode( "\n", $formData['actionables'] ) ) : NULL
		];

		if ( isset( $formData['publish'] ) && $formData['publish'] ) {
			$dbIncident['i_published'] = $dbw->timestamp();
		} else {
			$dbIncident['i_published'] = NULL;
		}

		if ( $id != 0 ) {
			$dbw->update(
				'incidents',
				$dbIncident,
				[
					'i_id' => $id
				]
			);
		} else {
			$dbw->insert(
				'incidents',
				$dbIncident
			);

			// Not a nice way but it's a way
			$id = $dbw->selectRow(
				'incidents',
				'i_id',
				$dbIncident
			)->i_id;
		}

		// Handle reviewers
		if ( $formData['review'] ) {
			$reviewers = explode( "\n", $formData['review'] );

			$dbReviewers = $dbw->select(
				'incidents_reviewer',
				'r_user',
				[
					'r_incident' => $id
				]
			);

			foreach ( $dbReviewers as $db ) {
				$reviewers = array_diff( $reviewers, (array)$db->r_user );
			}

			foreach ( $reviewers as $reviewer ) {
				$dbw->insert(
					'incidents_reviewer',
					[
						'r_incident' => $id,
						'r_user' => $reviewer,
						'r_timestamp' => NULL
					]
				);
			}
		}

		// Handle events
		$eventNumber = (int)$formData['logs-number'];

		for ( $eId = 1; $eId < $eventNumber; $eId++ ) {
			if ( $formData["{$eId}-timestamp"] == NULL ) {
				continue;
			}

			$dbEvent = [
				'log_incident' => $id,
				'log_id' => $eId,
				'log_actor' => ( $formData["{$eId}-user"] ) ?? $formData["{$eId}-actor"],
				'log_action' => $formData["{$eId}-action"],
				'log_timestamp' => wfTimestamp( TS_UNIX, $formData["{$eId}-timestamp"] ),
				'log_state' => $formData["{$eId}-state"]
			];

			$exists = $dbw->selectRow(
				'incidents_log',
				'*',
				[
					'log_id' => $eId,
					'log_incident' => $id
				]
			);

			if ( $exists ) {
				$dbw->update(
					'incidents_log',
					$dbEvent,
					[
						'log_id' => $eId,
						'log_incident' => $id
					]
				);
			} else {
				$dbw->insert(
					'incidents_log',
					$dbEvent
				);
			}
		}

		// Outage data
		$logData = $dbw->select(
			'incidents_log',
			'*',
			[
				'log_incident' => $id
			]
		);

		$outageTotal = 0;
		$outageVisible = 0;
		$curState = NULL;
		$curTime = NULL;

		foreach ( $logData as $odata ) {
			$workTime = ( ( !is_null( $curTime ) ) ? $odata->log_timestamp - $curTime : 0 ) / 60;

			if ( $odata->log_state == 'down'  || ( $odata->log_state != 'down' && $curState == 'down' ) ) {
				$outageVisible = $outageVisible + $workTime;
			}

			$outageTotal = $outageTotal + $workTime;
			$curState = $odata->log_state;
			$curTime = $odata->log_timestamp;
		}

		$dbw->update(
			'incidents',
			[
				'i_outage_total' => $outageTotal,
				'i_outage_visible' => $outageVisible
			],
			[
				'i_id' => $id
			]
		);

		// Will log eventually after cutover
//		$irLogEntry = new ManualLogEntry( 'incidentreporting', '' );
//		$irLogEntry->setPerformer( $form->getContext()->getUser() );
//		$irLogEntry->setTarget( $form->getTitle() );
//		$irLogID = $farmerLogEntry->insert();
//		$irLogEntry->publish( $farmerLogID );

		$out->addHTML( '<div class="successbox">' . wfMessage( 'incidentreporting-success' )->escaped() . '</div>' );

		return true;
	}
}