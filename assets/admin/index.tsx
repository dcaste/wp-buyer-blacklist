import { speak } from '@wordpress/a11y';
import apiFetch from '@wordpress/api-fetch';
import { createInterpolateElement, createRoot, useEffect, useRef, useState } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import './style.scss';

type Entry = { id: string; match_type: string; value: string; reason: string; is_active: string; created_at: string };
type AuditEvent = { action: string; actor_id: string | null; actor_name: string | null; created_at: string };
type BlockedAttempt = { id: string; identity_value: string; outcome: string; created_at: string };
type Address = { address_line_1: string; address_line_2: string; city: string; state: string; postcode: string; country: string };
type ImportResult = { token: string; valid: number; created: number | null; duplicates: { row: number; reason: string }[]; rejected: { row: number; reason: string }[] };
type BuyerContext = { buyer: { id: number; name: string; email: string; billing_address: Address; shipping_address: Address } | null; message?: string };
type Notice = { text: string; error: boolean } | null;
type Summary = { total_entries: number; active_entries: number; inactive_entries: number; blocked_attempts: number };
type ImportHistory = { id: string; file_name: string; entries_found: string; entries_created: string; duplicates: string; rejected: string; outcome: string; created_at: string };

const emptyAddress: Address = { address_line_1: '', address_line_2: '', city: '', state: '', postcode: '', country: '' };
const pageSize = 20;
const matchTypeLabel = ( matchType: string ) => ( { email: __( 'Email', 'wp-buyer-blacklist' ), customer_id: __( 'Customer ID', 'wp-buyer-blacklist' ), billing_address: __( 'Billing address', 'wp-buyer-blacklist' ), shipping_address: __( 'Shipping address', 'wp-buyer-blacklist' ) }[ matchType ] ?? matchType );
const formatAddress = ( address: Address ) => [ address.address_line_1, address.address_line_2, address.city, address.state, address.postcode, address.country ].filter( Boolean ).join( ', ' );
const displayValue = ( entry: Entry ) => {
	if ( ! ( entry.match_type ?? 'email' ).includes( 'address' ) ) return entry.value;
	try {
		return formatAddress( JSON.parse( entry.value ) as Address );
	} catch {
		return entry.value;
	}
};

function FieldLabel( { children, className, htmlFor }: { children: React.ReactNode; className?: string; htmlFor: string } ) {
	return <label className={ className } htmlFor={ htmlFor }>{ children }</label>;
}

export function App() {
	const [ entries, setEntries ] = useState< Entry[] >( [] );
	const [ summary, setSummary ] = useState< Summary >( { total_entries: 0, active_entries: 0, inactive_entries: 0, blocked_attempts: 0 } );
	const [ events, setEvents ] = useState< AuditEvent[] >( [] );
	const [ attempts, setAttempts ] = useState< BlockedAttempt[] >( [] );
	const [ buyerContext, setBuyerContext ] = useState< BuyerContext | null >( null );
	const [ selectedEntry, setSelectedEntry ] = useState< Entry | null >( null );
	const [ matchType, setMatchType ] = useState( 'email' );
	const [ email, setEmail ] = useState( '' );
	const [ address, setAddress ] = useState< Address >( emptyAddress );
	const [ reason, setReason ] = useState( '' );
	const [ message, setMessage ] = useState< Notice >( null );
	const [ dialogMessage, setDialogMessage ] = useState< Notice >( null );
	const [ detailsStatus, setDetailsStatus ] = useState< 'loading' | 'failed' | 'ready' >( 'ready' );
	const [ detailsVersion, setDetailsVersion ] = useState( 0 );
	const [ importResult, setImportResult ] = useState< ImportResult | null >( null );
	const [ importHistory, setImportHistory ] = useState< ImportHistory[] >( [] );
	const [ page, setPage ] = useState( 1 );
	const [ query, setQuery ] = useState( '' );
	const [ typeFilter, setTypeFilter ] = useState( '' );
	const [ statusFilter, setStatusFilter ] = useState( '' );
	const [ sortDescending, setSortDescending ] = useState( true );
	const dialog = useRef< HTMLDialogElement >( null );
	const root = document.getElementById( 'wp-buyer-blacklist-admin' );
	const tab = root?.dataset.tab ?? 'manage';
	const dossierExportUrl = root?.dataset.dossierExportUrl ?? '';
	const exampleCsvUrl = root?.dataset.exampleCsvUrl ?? '';
	const usersUrl = root?.dataset.usersUrl ?? '';
	const canManagePrivacyData = root?.dataset.canManagePrivacyData === '1';
	const selectedIds = new URLSearchParams( window.location.search ).getAll( 'entry_ids[]' ).map( Number );
	const dialogId = 'wp-buyer-blacklist-entry-dialog';
	// Shows a message and announces it. The page behind the modal dialog is inert, so messages from dialog actions show inside it.
	const notify = ( text: string, { error = false, inDialog = false } = {} ) => {
		( inDialog ? setDialogMessage : setMessage )( { text, error } );
		speak( text, error ? 'assertive' : 'polite' );
	};
	const load = () => Promise.all( [ apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } ), apiFetch< Summary >( { path: '/wp-buyer-blacklist/v1/entry-summary' } ).catch( () => ( { total_entries: 0, active_entries: 0, inactive_entries: 0, blocked_attempts: 0 } ) ) ] ).then( ( [ loaded, totals ] ) => { setEntries( loaded ); setSummary( totals ); setPage( 1 ); } ).catch( ( error: any ) => notify( error.message, { error: true } ) );
	const inspect = ( entry: Entry ) => {
		setSelectedEntry( entry );
		setReason( entry.reason );
		setEvents( [] ); setAttempts( [] ); setBuyerContext( null ); setDialogMessage( null );
		setDetailsStatus( 'loading' );
		setDetailsVersion( ( version ) => version + 1 );
	};
	const selectedId = selectedEntry?.id;
	useEffect( () => {
		if ( ! selectedId ) return;
		// Ignores responses for an entry that is no longer selected.
		let ignore = false;
		Promise.all( [ apiFetch< AuditEvent[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedId }` } ), apiFetch< BlockedAttempt[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedId }/blocked-attempts` } ), apiFetch< BuyerContext >( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedId }/buyer-context` } ) ] ).then( ( [ auditEvents, blockedAttempts, context ] ) => {
			if ( ignore ) return;
			setEvents( auditEvents ); setAttempts( blockedAttempts ); setBuyerContext( context ); setDetailsStatus( 'ready' );
		} ).catch( ( error: any ) => {
			if ( ignore ) return;
			setDetailsStatus( 'failed' );
			notify( error.message, { error: true, inDialog: true } );
		} );
		return () => { ignore = true; };
	}, [ selectedId, detailsVersion ] );
	useEffect( () => { load(); }, [] );
	useEffect( () => { if ( tab === 'import-export' ) apiFetch< ImportHistory[] >( { path: '/wp-buyer-blacklist/v1/import-history' } ).then( setImportHistory ).catch( ( error: any ) => notify( error.message, { error: true } ) ); }, [ tab ] );
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		try {
			const path = matchType === 'email' ? '/wp-buyer-blacklist/v1/email-entries' : '/wp-buyer-blacklist/v1/address-entries';
			const data = matchType === 'email' ? { email, reason } : { match_type: matchType, ...address, reason };
			await apiFetch( { path, method: 'POST', data } );
			setEmail( '' ); setAddress( emptyAddress ); setReason( '' ); notify( __( 'Blacklist entry created.', 'wp-buyer-blacklist' ) ); load();
		} catch ( error: any ) { notify( error.message, { error: true } ); }
	};
	const validateFile = async ( file?: File ) => {
		if ( ! file ) return;
		if ( file.size > 5 * 1024 * 1024 || ! file.name.toLowerCase().endsWith( '.csv' ) ) {
			notify( __( 'Choose a CSV file no larger than 5 MB.', 'wp-buyer-blacklist' ), { error: true } );
			return;
		}
		try { setImportResult( await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/validate', method: 'POST', data: { csv: await file.text(), filename: file.name } } ) ); } catch ( error: any ) { notify( error.message, { error: true } ); }
	};
	const validateImport = ( event: React.ChangeEvent< HTMLInputElement > ) => validateFile( event.target.files?.[ 0 ] );
	const confirmImport = async () => {
		if ( ! importResult ) return;
		try { setImportResult( await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/confirm', method: 'POST', data: { token: importResult.token } } ) ); notify( __( 'CSV import completed.', 'wp-buyer-blacklist' ) ); load(); } catch ( error: any ) { notify( error.message, { error: true } ); }
	};
	const update = async ( data: Record< string, string | boolean > ) => {
		if ( ! selectedEntry ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'PATCH', data } );
			notify( __( 'Blacklist entry updated.', 'wp-buyer-blacklist' ), { inDialog: true } );
			const refreshedEntries = await apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } );
			setEntries( refreshedEntries );
			const refreshedEntry = refreshedEntries.find( ( entry ) => entry.id === selectedEntry.id );
			if ( refreshedEntry ) {
				setSelectedEntry( refreshedEntry );
				setReason( refreshedEntry.reason );
				setDetailsVersion( ( version ) => version + 1 );
			}
		} catch ( error: any ) { notify( error.message, { error: true, inDialog: true } ); }
	};
	const remove = async () => {
		if ( ! selectedEntry || ! window.confirm( sprintf( __( 'Delete %s? This cannot be undone.', 'wp-buyer-blacklist' ), displayValue( selectedEntry ) ) ) ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'DELETE', data: { confirm: true } } );
			dialog.current?.close();
			setSelectedEntry( null ); setEvents( [] ); setAttempts( [] ); setBuyerContext( null ); setDialogMessage( null ); notify( __( 'Blacklist entry deleted.', 'wp-buyer-blacklist' ) ); load();
		} catch ( error: any ) { notify( error.message, { error: true, inDialog: true } ); }
	};
	const addressField = ( field: keyof Address, label: string, description: string, required = true ) => <p><FieldLabel htmlFor={ `wp-buyer-blacklist-${ field }` }>{ label }</FieldLabel><input aria-describedby={ `wp-buyer-blacklist-${ field }-description` } className="regular-text" id={ `wp-buyer-blacklist-${ field }` } required={ required } value={ address[ field ] } onChange={ ( event ) => setAddress( { ...address, [ field ]: event.target.value } ) } /><small id={ `wp-buyer-blacklist-${ field }-description` }>{ description }</small></p>;
	const visibleEntries = ( selectedIds.length ? entries.filter( ( entry ) => selectedIds.includes( Number( entry.id ) ) ) : entries ).filter( ( entry ) => ( ! typeFilter || entry.match_type === typeFilter ) && ( ! statusFilter || ( 'active' === statusFilter ) === ( '1' === entry.is_active ) ) && `${ displayValue( entry ) } ${ entry.reason }`.toLowerCase().includes( query.toLowerCase() ) ).sort( ( first, second ) => sortDescending ? second.created_at.localeCompare( first.created_at ) : first.created_at.localeCompare( second.created_at ) );
	const detailsStatusText = 'loading' === detailsStatus ? __( 'Loading…', 'wp-buyer-blacklist' ) : __( 'This information could not be loaded.', 'wp-buyer-blacklist' );
	const pages = Math.max( 1, Math.ceil( visibleEntries.length / pageSize ) );
	const pageEntries = visibleEntries.slice( ( page - 1 ) * pageSize, page * pageSize );
	if ( tab === 'import-export' ) return <main className="wpbb-page wpbb-import-page">
		<div className="wpbb-import-cards">
			{ canManagePrivacyData && <section className="wpbb-card wpbb-export-card"><span className="wpbb-icon-tile is-primary is-upload" aria-hidden="true" /><div><h2>{ __( 'Export Blacklist entries', 'wp-buyer-blacklist' ) }</h2><p>{ __( 'Download all Blacklist entries to a CSV file. You can use this file to keep a backup or import it to another store.', 'wp-buyer-blacklist' ) }</p><a className="button button-primary" href={ root?.dataset.exportUrl }><span className="wpbb-button-icon is-upload is-download" aria-hidden="true" />{ __( 'Export CSV', 'wp-buyer-blacklist' ) }</a><p className="description">{ __( 'The exported file includes all Blacklist entries, including Match type, value, reason, status, and date added.', 'wp-buyer-blacklist' ) }</p></div></section> }
			<section className="wpbb-card wpbb-import-card"><span className="wpbb-icon-tile is-primary is-upload" aria-hidden="true" /><div><h2>{ __( 'Import Blacklist entries', 'wp-buyer-blacklist' ) }</h2><p>{ __( 'Upload a CSV file to add multiple Blacklist entries at once. Existing entries are not removed.', 'wp-buyer-blacklist' ) }</p></div><div className="wpbb-drop-target" onDragOver={ ( event ) => event.preventDefault() } onDrop={ ( event ) => { event.preventDefault(); void validateFile( event.dataTransfer.files[ 0 ] ); } }><span className="wpbb-icon-tile is-primary is-upload" aria-hidden="true" /><p>{ __( 'Drag and drop your CSV file here', 'wp-buyer-blacklist' ) }</p><span>{ __( 'or', 'wp-buyer-blacklist' ) }</span><div className="wpbb-file-picker"><FieldLabel className="button" htmlFor="wp-buyer-blacklist-csv-file"><span className="wpbb-button-icon is-upload" aria-hidden="true" />{ __( 'Choose file', 'wp-buyer-blacklist' ) }</FieldLabel><input className="wpbb-file-input" id="wp-buyer-blacklist-csv-file" type="file" accept=".csv,text/csv" onChange={ validateImport } /></div><small>{ __( 'Accepted file type: CSV (.csv) · Maximum file size: 5 MB', 'wp-buyer-blacklist' ) }</small></div><div className="wpbb-import-example"><span className="wpbb-import-example-icon" aria-hidden="true">i</span><div><strong>{ __( 'Need a template?', 'wp-buyer-blacklist' ) }</strong><p>{ __( 'Download our example CSV file to see the correct format.', 'wp-buyer-blacklist' ) }</p></div><a className="button" href={ exampleCsvUrl }><span className="wpbb-button-icon is-upload is-download" aria-hidden="true" />{ __( 'Download example CSV', 'wp-buyer-blacklist' ) }</a></div></section>
		</div>
		{ importResult && <section className="wpbb-card"><h2>{ __( 'Import review', 'wp-buyer-blacklist' ) }</h2><p>{ sprintf( __( '%1$d valid, %2$d duplicate, %3$d rejected.', 'wp-buyer-blacklist' ), importResult.valid, importResult.duplicates.length, importResult.rejected.length ) }</p>{ importResult.token && <button className="button button-primary" onClick={ confirmImport }>{ __( 'Confirm import', 'wp-buyer-blacklist' ) }</button> }<ul>{ [ ...importResult.duplicates, ...importResult.rejected ].map( ( row ) => <li key={ `${ row.row }-${ row.reason }` }>{ sprintf( __( 'Row %1$d: %2$s', 'wp-buyer-blacklist' ), row.row, row.reason ) }</li> ) }</ul>{ importResult.created !== null && <p>{ sprintf( _n( 'Created %d entry.', 'Created %d entries.', importResult.created, 'wp-buyer-blacklist' ), importResult.created ) }</p> }</section> }
		<section className="wpbb-card wpbb-import-history"><h2>{ __( 'Recent imports', 'wp-buyer-blacklist' ) }</h2><p className="description">{ __( 'View the status of your recent import activities.', 'wp-buyer-blacklist' ) }</p>{ importHistory.length ? <div className="wpbb-table-wrap"><table className="widefat striped"><thead><tr><th>{ __( 'Date and time', 'wp-buyer-blacklist' ) }</th><th>{ __( 'File name', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Entries found', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Imported', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Skipped', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Status', 'wp-buyer-blacklist' ) }</th></tr></thead><tbody>{ importHistory.map( ( item ) => <tr key={ item.id }><td>{ item.created_at }</td><td>{ item.file_name }</td><td>{ item.entries_found }</td><td>{ item.entries_created }</td><td>{ item.duplicates }</td><td><span className={ `wpbb-import-status is-${ item.outcome }` }>{ item.outcome === 'completed' ? __( 'Completed', 'wp-buyer-blacklist' ) : item.outcome === 'warning' ? __( 'Completed with warnings', 'wp-buyer-blacklist' ) : __( 'Failed', 'wp-buyer-blacklist' ) }</span></td></tr> ) }</tbody></table></div> : <p>{ __( 'No imports have been recorded.', 'wp-buyer-blacklist' ) }</p> }</section>{ message && <p className="wpbb-notice">{ message.text }</p> }
	</main>;
	return <main className="wpbb-page">
		<section className="wpbb-summary" aria-label={ __( 'Blacklist entry summary', 'wp-buyer-blacklist' ) }>{ [ [ __( 'Total entries', 'wp-buyer-blacklist' ), summary.total_entries, __( 'All Blacklist entries.', 'wp-buyer-blacklist' ), 'entries', 'is-primary' ], [ __( 'Active entries', 'wp-buyer-blacklist' ), summary.active_entries, __( 'Currently blocking matching Buyers.', 'wp-buyer-blacklist' ), 'active', 'is-success' ], [ __( 'Inactive entries', 'wp-buyer-blacklist' ), summary.inactive_entries, __( 'Kept without blocking Buyers.', 'wp-buyer-blacklist' ), 'inactive', 'is-neutral' ], [ __( 'Blocked attempts', 'wp-buyer-blacklist' ), summary.blocked_attempts, __( 'Checkout attempts prevented.', 'wp-buyer-blacklist' ), 'blocked-attempts', 'is-danger' ] ].map( ( [ label, total, description, icon, iconClass ] ) => <article className="wpbb-card" key={ String( label ) }><span className={ `wpbb-icon-tile ${ iconClass } is-${ icon }` } aria-hidden="true" /><div><p>{ label }</p><strong>{ total }</strong><small>{ description }</small></div></article> ) }</section>
		<section className="wpbb-card"><form className="wpbb-entry-form" onSubmit={ submit }><h2>{ __( 'Add new Blacklist entry', 'wp-buyer-blacklist' ) }</h2><p className="wpbb-entry-form-intro">{ __( 'Add an email or structured address to block matching Buyers at checkout.', 'wp-buyer-blacklist' ) }</p><p><FieldLabel htmlFor="wp-buyer-blacklist-match-type">{ __( 'Match type', 'wp-buyer-blacklist' ) }</FieldLabel><select aria-describedby="wp-buyer-blacklist-match-type-description" id="wp-buyer-blacklist-match-type" value={ matchType } onChange={ ( event ) => setMatchType( event.target.value ) }><option value="email">{ __( 'Email', 'wp-buyer-blacklist' ) }</option><option value="billing_address">{ __( 'Billing address', 'wp-buyer-blacklist' ) }</option><option value="shipping_address">{ __( 'Shipping address', 'wp-buyer-blacklist' ) }</option></select><small id="wp-buyer-blacklist-match-type-description">{ createInterpolateElement( __( 'Choose the Buyer detail to match during checkout. To block by Customer ID, enroll the Customer from the <a>Users screen</a>.', 'wp-buyer-blacklist' ), { a: <a href={ usersUrl } /> } ) }</small></p>{ matchType === 'email' && <p><FieldLabel htmlFor="wp-buyer-blacklist-email">{ __( 'Email', 'wp-buyer-blacklist' ) }</FieldLabel><input aria-describedby="wp-buyer-blacklist-email-description" className="regular-text" id="wp-buyer-blacklist-email" placeholder={ __( 'e.g. customer@example.com', 'wp-buyer-blacklist' ) } type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /><small id="wp-buyer-blacklist-email-description">{ __( 'Enter the full email address to block.', 'wp-buyer-blacklist' ) }</small></p> }{ matchType.includes( 'address' ) && <>{ addressField( 'address_line_1', __( 'Address line 1', 'wp-buyer-blacklist' ), __( 'Enter the building number and street name.', 'wp-buyer-blacklist' ) ) }{ addressField( 'address_line_2', __( 'Address line 2', 'wp-buyer-blacklist' ), __( 'Add an apartment, suite, or unit when needed.', 'wp-buyer-blacklist' ), false ) }{ addressField( 'city', __( 'City', 'wp-buyer-blacklist' ), __( 'Enter the city or town.', 'wp-buyer-blacklist' ) ) }{ addressField( 'state', __( 'State', 'wp-buyer-blacklist' ), __( 'Enter the state, province, or region.', 'wp-buyer-blacklist' ) ) }{ addressField( 'postcode', __( 'Postcode', 'wp-buyer-blacklist' ), __( 'Enter the postal or ZIP code.', 'wp-buyer-blacklist' ) ) }{ addressField( 'country', __( 'Country', 'wp-buyer-blacklist' ), __( 'Enter the two-letter country code, such as US.', 'wp-buyer-blacklist' ) ) }</> }<p className="wpbb-entry-reason"><FieldLabel htmlFor="wp-buyer-blacklist-reason">{ __( 'Reason', 'wp-buyer-blacklist' ) }</FieldLabel><textarea aria-describedby="wp-buyer-blacklist-reason-description" className="large-text" id="wp-buyer-blacklist-reason" required placeholder={ __( 'Add a reason for this Blacklist entry...', 'wp-buyer-blacklist' ) } value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /><small id="wp-buyer-blacklist-reason-description">{ __( 'This helps operators understand why the Buyer was blacklisted.', 'wp-buyer-blacklist' ) }</small></p><button className="button button-primary" type="submit">{ __( 'Add entry', 'wp-buyer-blacklist' ) }</button></form></section>{ message && <p className="wpbb-notice">{ message.text }</p> }
		<section className="wpbb-card wpbb-entries-card"><h2>{ __( 'Blacklist entries', 'wp-buyer-blacklist' ) }</h2><div className="wpbb-filters"><p><FieldLabel htmlFor="wp-buyer-blacklist-search">{ __( 'Search entries', 'wp-buyer-blacklist' ) }</FieldLabel><input id="wp-buyer-blacklist-search" type="search" value={ query } onChange={ ( event ) => { setQuery( event.target.value ); setPage( 1 ); } } /></p><p><FieldLabel htmlFor="wp-buyer-blacklist-type-filter">{ __( 'Match type', 'wp-buyer-blacklist' ) }</FieldLabel><select id="wp-buyer-blacklist-type-filter" value={ typeFilter } onChange={ ( event ) => setTypeFilter( event.target.value ) }><option value="">{ __( 'All Match types', 'wp-buyer-blacklist' ) }</option><option value="email">{ __( 'Email', 'wp-buyer-blacklist' ) }</option><option value="customer_id">{ __( 'Customer ID', 'wp-buyer-blacklist' ) }</option><option value="billing_address">{ __( 'Billing address', 'wp-buyer-blacklist' ) }</option><option value="shipping_address">{ __( 'Shipping address', 'wp-buyer-blacklist' ) }</option></select></p><p><FieldLabel htmlFor="wp-buyer-blacklist-status-filter">{ __( 'Status', 'wp-buyer-blacklist' ) }</FieldLabel><select id="wp-buyer-blacklist-status-filter" value={ statusFilter } onChange={ ( event ) => setStatusFilter( event.target.value ) }><option value="">{ __( 'All statuses', 'wp-buyer-blacklist' ) }</option><option value="active">{ __( 'Active', 'wp-buyer-blacklist' ) }</option><option value="inactive">{ __( 'Inactive', 'wp-buyer-blacklist' ) }</option></select></p><button className="button" type="button" onClick={ () => { setQuery( '' ); setTypeFilter( '' ); setStatusFilter( '' ); } }>{ __( 'Reset', 'wp-buyer-blacklist' ) }</button></div><div className="wpbb-table-wrap"><table className="widefat striped"><thead><tr><th>{ __( 'Match type', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Canonical value', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Reason', 'wp-buyer-blacklist' ) }</th><th>{ __( 'Status', 'wp-buyer-blacklist' ) }</th><th><button className="button-link" onClick={ () => setSortDescending( ! sortDescending ) }>{ __( 'Updated', 'wp-buyer-blacklist' ) }</button></th><th>{ __( 'Actions', 'wp-buyer-blacklist' ) }</th></tr></thead><tbody>{ pageEntries.map( ( entry ) => <tr key={ entry.id }><td>{ matchTypeLabel( entry.match_type ?? 'email' ) }</td><td>{ displayValue( entry ) }</td><td>{ entry.reason }</td><td><span className={ `wp-buyer-blacklist-status-badge ${ entry.is_active === '1' ? 'is-active' : 'is-inactive' }` }>{ entry.is_active === '1' ? __( 'Active', 'wp-buyer-blacklist' ) : __( 'Inactive', 'wp-buyer-blacklist' ) }</span></td><td>{ entry.created_at }</td><td><button className="button" commandfor={ dialogId } command="show-modal" onClick={ () => inspect( entry ) }>{ __( 'Manage', 'wp-buyer-blacklist' ) }</button></td></tr> ) }</tbody></table></div><div className="tablenav"><div className="tablenav-pages" style={ { float: 'left' } }><button className="button" disabled={ page === 1 } onClick={ () => setPage( page - 1 ) }>{ __( 'Previous', 'wp-buyer-blacklist' ) }</button> <span>{ sprintf( __( '%1$d of %2$d', 'wp-buyer-blacklist' ), page, pages ) }</span> <button className="button" disabled={ page === pages } onClick={ () => setPage( page + 1 ) }>{ __( 'Next', 'wp-buyer-blacklist' ) }</button></div></div></section>
		<dialog ref={ dialog } id={ dialogId } aria-labelledby="wpbb-entry-dialog-title" closedby="closerequest" onClose={ () => { setSelectedEntry( null ); setBuyerContext( null ); setEvents( [] ); setAttempts( [] ); setDialogMessage( null ); } }>{ selectedEntry && <section className="wpbb-entry-dialog"><header className="wpbb-entry-dialog-header"><div><h2 id="wpbb-entry-dialog-title">{ __( 'Manage Blacklist entry', 'wp-buyer-blacklist' ) }</h2><p>{ __( 'View and manage the details for this Blacklist entry.', 'wp-buyer-blacklist' ) }</p></div><button className="wpbb-entry-dialog-close" aria-label={ __( 'Close dialog', 'wp-buyer-blacklist' ) } commandfor={ dialogId } command="close"><span className="wpbb-modal-icon is-close" aria-hidden="true" /></button></header>{ dialogMessage && <p className="wpbb-notice wpbb-entry-dialog-notice">{ dialogMessage.text }</p> }<div className="wpbb-entry-dialog-grid"><div className="wpbb-entry-dialog-main"><section className="wpbb-buyer-details"><h3><span className="wpbb-buyer-avatar"><span className="wpbb-modal-icon is-user" aria-hidden="true" /></span>{ __( 'Buyer details', 'wp-buyer-blacklist' ) }</h3><div className="wpbb-buyer-details-card"><div className="wpbb-buyer-entry"><span>{ matchTypeLabel( selectedEntry.match_type ?? 'email' ) }</span><strong>{ displayValue( selectedEntry ) }</strong></div>{ detailsStatus !== 'ready' ? <p>{ detailsStatusText }</p> : buyerContext?.buyer ? <><div className="wpbb-buyer-identity"><strong>{ buyerContext.buyer.name } (#{ buyerContext.buyer.id })</strong><span>{ buyerContext.buyer.email }</span></div><div className="wpbb-buyer-addresses"><div><span className="wpbb-modal-icon is-pin" aria-hidden="true" /><p><strong>{ __( 'Billing address', 'wp-buyer-blacklist' ) }</strong><br />{ formatAddress( buyerContext.buyer.billing_address ) || __( 'Not provided', 'wp-buyer-blacklist' ) }</p></div><div><span className="wpbb-modal-icon is-pin" aria-hidden="true" /><p><strong>{ __( 'Shipping address', 'wp-buyer-blacklist' ) }</strong><br />{ formatAddress( buyerContext.buyer.shipping_address ) || __( 'Not provided', 'wp-buyer-blacklist' ) }</p></div></div></> : <p>{ buyerContext?.message ?? __( 'No uniquely identified registered Buyer.', 'wp-buyer-blacklist' ) }</p> }</div></section><section className="wpbb-entry-reason-editor"><h3>{ __( 'Reason', 'wp-buyer-blacklist' ) }</h3><p>{ __( 'Add a note to help you remember why this Buyer was blacklisted.', 'wp-buyer-blacklist' ) }</p><FieldLabel htmlFor="wp-buyer-blacklist-edit-reason">{ __( 'Reason', 'wp-buyer-blacklist' ) }</FieldLabel><textarea className="large-text" id="wp-buyer-blacklist-edit-reason" required maxLength={ 500 } value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /><small>{ reason.length } / 500</small></section><section className="wpbb-entry-status-editor"><h3>{ __( 'Status', 'wp-buyer-blacklist' ) }</h3><p>{ __( 'Control whether this entry is actively blocking Buyers from placing orders.', 'wp-buyer-blacklist' ) }</p><button className="wpbb-entry-active-toggle" role="switch" aria-label={ __( 'Active Blacklist entry', 'wp-buyer-blacklist' ) } aria-checked={ selectedEntry.is_active === '1' } onClick={ () => update( { is_active: selectedEntry.is_active !== '1' } ) }><span aria-hidden="true" />{ selectedEntry.is_active === '1' ? __( 'Active', 'wp-buyer-blacklist' ) : __( 'Inactive', 'wp-buyer-blacklist' ) }</button><small>{ selectedEntry.is_active === '1' ? __( 'This entry is currently blocking matching Buyers.', 'wp-buyer-blacklist' ) : __( 'This entry is not blocking matching Buyers.', 'wp-buyer-blacklist' ) }</small></section><footer className="wpbb-entry-dialog-footer">{ canManagePrivacyData && <><div><button className="button wp-buyer-blacklist-delete-entry" onClick={ remove }><span className="wpbb-button-icon is-trash" aria-hidden="true" />{ __( 'Delete entry', 'wp-buyer-blacklist' ) }</button><p>{ __( 'Permanently remove this entry from the Blacklist.', 'wp-buyer-blacklist' ) }</p></div><a className="button" href={ `${ dossierExportUrl }&entry_id=${ selectedEntry.id }`}><span className="wpbb-button-icon is-download" aria-hidden="true" />{ __( 'Export dossier', 'wp-buyer-blacklist' ) }</a></> }<button className="button button-primary" onClick={ () => update( { reason } ) }>{ __( 'Save changes', 'wp-buyer-blacklist' ) }</button></footer></div><aside className="wpbb-entry-dialog-details"><section className="wpbb-entry-dialog-panel wpbb-audit-history"><h3><span className="wpbb-icon-tile is-primary is-audit-history" aria-hidden="true" />{ __( 'Audit history', 'wp-buyer-blacklist' ) }</h3>{ detailsStatus !== 'ready' ? <p>{ detailsStatusText }</p> : events.length ? <ul>{ events.map( ( event ) => <li key={ `${ event.created_at }-${ event.action }` }><strong>{ event.action }</strong>{ ' ' }<span>{ sprintf( __( 'by %s', 'wp-buyer-blacklist' ), event.actor_name ?? __( 'System', 'wp-buyer-blacklist' ) ) }</span><time>{ event.created_at }</time></li> ) }</ul> : <p>{ __( 'No Audit events yet.', 'wp-buyer-blacklist' ) }</p> }</section><section className="wpbb-entry-dialog-panel wpbb-blocked-attempts"><h3><span className="wpbb-icon-tile is-danger is-blocked-attempts" aria-hidden="true" />{ __( 'Blocked attempts', 'wp-buyer-blacklist' ) } <span className="wpbb-attempt-count">{ attempts.length }</span></h3>{ detailsStatus !== 'ready' ? <p>{ detailsStatusText }</p> : attempts.length ? <ol>{ attempts.map( ( attempt ) => <li key={ attempt.id }><time>{ attempt.created_at }</time></li> ) }</ol> : <div className="wpbb-no-attempts"><span className="wpbb-modal-icon is-locked" aria-hidden="true" /><strong>{ __( 'No blocked attempts yet', 'wp-buyer-blacklist' ) }</strong><p>{ __( 'This Buyer has not triggered any blocked order attempts.', 'wp-buyer-blacklist' ) }</p></div> }<p className="wpbb-attempts-note"><span className="wpbb-import-example-icon" aria-hidden="true">i</span>{ __( 'When this Buyer attempts to place an order, the attempt appears here.', 'wp-buyer-blacklist' ) }</p></section></aside></div></section> }</dialog>
	</main>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) createRoot( root ).render( <App /> );
