/**
 * External dependencies
 */
import {
	createSlotFill,
	ToggleControl,
	RadioControl,
	Button,
} from '@wordpress/components';

import apiFetch from '@wordpress/api-fetch';

import { useState, createElement, useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SETTINGS_SLOT_FILL_CONSTANT } from '../../settings/settings-slots';
import './style.scss';

const { Fill } = createSlotFill( SETTINGS_SLOT_FILL_CONSTANT );

const Blueprint = () => {
	const [ exportEnabled, setExportEnabled ] = useState( true );
	const exportBlueprint = async () => {
		setExportEnabled( false );
		const response = await apiFetch( {
			path: `/blueprint/export`,
			method: 'GET',
		} );

		// Create a link element and trigger the download
		const url = window.URL.createObjectURL(
			new Blob( [ JSON.stringify( response.schema, null, 2 ) ] )
		);
		const link = document.createElement( 'a' );
		link.href = url;
		link.setAttribute( 'download', 'woo-blueprint.json' );
		document.body.appendChild( link );
		link.click();
		document.body.removeChild( link );
		setExportEnabled( true );
	};

	useEffect( () => {
		const saveButton = document.getElementsByClassName(
			'woocommerce-save-button'
		)[ 0 ];
		if ( saveButton ) {
			saveButton.style.display = 'none';
		}
	} );
	return (
		<div className="blueprint-settings-slotfill">
			<h2>{ __( 'Blueprint', 'woocommerce' ) }</h2>
			<p className="blueprint-settings-slotfill-description">
				{ __( 'Import/Export your Blueprint schema.', 'woocommerce' ) }
			</p>
			<Button
				isPrimary
				onClick={ () => {
					exportBlueprint();
				} }
				disabled={ ! exportEnabled }
				isBusy={ ! exportEnabled }
			>
				{ __( 'Export', 'woocommerce' ) }
			</Button>
			<p>
				Export can take a few seconds depending on your network speed.
			</p>
		</div>
	);
};

const BlueprintSlotfill = () => {
	return (
		<Fill>
			<Blueprint />
		</Fill>
	);
};

export const registerBlueprintSlotfill = () => {
	registerPlugin( 'woocommerce-admin-blueprint-settings-slotfill', {
		scope: 'woocommerce-blueprint-settings',
		render: BlueprintSlotfill,
	} );
};
