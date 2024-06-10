/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { getAdminLink } from '@woocommerce/settings';

/**
 * Internal dependencies
 */
import { Navigation } from '../components/navigation/navigation';
import { IntroOptInEvent } from '../index';

export const BuilderIntro = ( {
	sendEvent,
	navigationProgress = 80,
}: {
	sendEvent: ( event: IntroOptInEvent ) => void;
	navigationProgress: number;
} ) => {
	const [ file, setFile ] = useState( null );
	const [ message, setMessage ] = useState( '' );

	const handleFileChange = ( event: any ) => {
		setFile( event.target.files[ 0 ] );
	};

	const handleUpload = () => {
		if ( ! file ) {
			setMessage( 'Please select a file first.' );
			return;
		}

		const formData = new FormData();
		formData.append( 'file', file );

		fetch( '/wp-json/blueprint/process', {
			method: 'POST',
			body: formData,
		} )
			.then( ( response ) => response.json() )
			.then( ( data ) => {
				if ( data.status === 'success' ) {
					setMessage(
						'File uploaded successfully. Redirecting to Woo Home.'
					);
					window.setTimeout( () => {
						window.location.href = getAdminLink(
							'admin.php?page=wc-admin'
						);
					}, 1000 );
				} else {
					setMessage( `Error: ${ data.message }` );
				}
			} )
			.catch( ( error ) => {
				setMessage( `Error: ${ error.message }` );
			} );
	};
	return (
		<>
			<Navigation
				percentage={ navigationProgress }
				skipText={ __( 'Skip setup', 'woocommerce' ) }
				onSkip={ () =>
					sendEvent( {
						type: 'INTRO_SKIPPED',
						payload: { optInDataSharing: false },
					} )
				}
			/>
			<div className="woocommerce-profiler-builder-intro">
				<h1>
					{ __(
						'Upload your Blueprint to provision your site',
						'woocommerce'
					) }{ ' ' }
				</h1>

				<input
					className="woocommerce-profiler-builder-intro-file-input"
					type="file"
					onChange={ handleFileChange }
				/>
				<Button variant="primary" onClick={ handleUpload }>
					{ __( 'Upload Blueprint', 'woocommerce' ) }
				</Button>
				<div>{ message }</div>
			</div>
		</>
	);
};
