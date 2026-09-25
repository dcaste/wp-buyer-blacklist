import apiFetch from '@wordpress/api-fetch';
import { createRoot, useEffect, useRef, useState } from '@wordpress/element';
import './style.scss';

type Entry = { id: string; match_type: string; value: string; reason: string; is_active: string; created_at: string };
type AuditEvent = { action: string; actor_id: string | null; actor_name: string | null; created_at: string };
type BlockedAttempt = { id: string; identity_value: string; outcome: string; created_at: string };
type Address = { address_line_1: string; address_line_2: string; city: string; state: string; postcode: string; country: string };
type ImportResult = { token: string; valid: number; created: number | null; duplicates: { row: number; reason: string }[]; rejected: { row: number; reason: string }[] };
type BuyerContext = { buyer: { id: number; name: string; email: string; billing_address: Address; shipping_address: Address } | null; message?: string };
type Summary = { total_entries: number; active_entries: number; inactive_entries: number; blocked_attempts: number };
type ImportHistory = { id: string; file_name: string; entries_found: string; entries_created: string; duplicates: string; rejected: string; outcome: string; created_at: string };

const emptyAddress: Address = { address_line_1: '', address_line_2: '', city: '', state: '', postcode: '', country: '' };
const pageSize = 20;
const matchTypeLabel = ( matchType: string ) => ( { email: 'Email', customer_id: 'Customer ID', billing_address: 'Billing address', shipping_address: 'Shipping address' }[ matchType ] ?? matchType );
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
	const [ message, setMessage ] = useState( '' );
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
	const canManagePrivacyData = root?.dataset.canManagePrivacyData === '1';
	const selectedIds = new URLSearchParams( window.location.search ).getAll( 'entry_ids[]' ).map( Number );
	const dialogId = 'wp-buyer-blacklist-entry-dialog';
	const load = () => Promise.all( [ apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } ), apiFetch< Summary >( { path: '/wp-buyer-blacklist/v1/entry-summary' } ).catch( () => ( { total_entries: 0, active_entries: 0, inactive_entries: 0, blocked_attempts: 0 } ) ) ] ).then( ( [ loaded, totals ] ) => { setEntries( loaded ); setSummary( totals ); setPage( 1 ); } );
	const inspect = async ( entry: Entry ) => {
		setSelectedEntry( entry );
		setReason( entry.reason );
		const [ auditEvents, blockedAttempts, context ] = await Promise.all( [ apiFetch< AuditEvent[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }` } ), apiFetch< BlockedAttempt[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }/blocked-attempts` } ), apiFetch< BuyerContext >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }/buyer-context` } ) ] );
		setEvents( auditEvents );
		setAttempts( blockedAttempts );
		setBuyerContext( context );
	};
	useEffect( () => { load(); }, [] );
	useEffect( () => { if ( tab === 'import-export' ) apiFetch< ImportHistory[] >( { path: '/wp-buyer-blacklist/v1/import-history' } ).then( setImportHistory ); }, [ tab ] );
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		try {
			const path = matchType === 'email' ? '/wp-buyer-blacklist/v1/email-entries' : '/wp-buyer-blacklist/v1/address-entries';
			const data = matchType === 'email' ? { email, reason } : { match_type: matchType, ...address, reason };
			await apiFetch( { path, method: 'POST', data } );
			setEmail( '' ); setAddress( emptyAddress ); setReason( '' ); setMessage( 'Blacklist entry created.' ); load();
		} catch ( error: any ) { setMessage( error.message ); }
	};
	const validateFile = async ( file?: File ) => {
		if ( ! file ) return;
		if ( file.size > 5 * 1024 * 1024 || ! file.name.toLowerCase().endsWith( '.csv' ) ) {
			setMessage( 'Choose a CSV file no larger than 5 MB.' );
			return;
		}
		try { setImportResult( await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/validate', method: 'POST', data: { csv: await file.text(), filename: file.name } } ) ); } catch ( error: any ) { setMessage( error.message ); }
	};
	const validateImport = ( event: React.ChangeEvent< HTMLInputElement > ) => validateFile( event.target.files?.[ 0 ] );
	const confirmImport = async () => {
		if ( ! importResult ) return;
		try { setImportResult( await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/confirm', method: 'POST', data: { token: importResult.token } } ) ); setMessage( 'CSV import completed.' ); load(); } catch ( error: any ) { setMessage( error.message ); }
	};
	const update = async ( data: Record< string, string | boolean > ) => {
		if ( ! selectedEntry ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'PATCH', data } );
			setMessage( 'Blacklist entry updated.' );
			const refreshedEntries = await apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } );
			setEntries( refreshedEntries );
			const refreshedEntry = refreshedEntries.find( ( entry ) => entry.id === selectedEntry.id );
			if ( refreshedEntry ) await inspect( refreshedEntry );
		} catch ( error: any ) { setMessage( error.message ); }
	};
	const remove = async () => {
		if ( ! selectedEntry || ! window.confirm( `Delete ${ displayValue( selectedEntry ) }? This cannot be undone.` ) ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'DELETE', data: { confirm: true } } );
			dialog.current?.close();
			setSelectedEntry( null ); setEvents( [] ); setAttempts( [] ); setMessage( 'Blacklist entry deleted.' ); load();
		} catch ( error: any ) { setMessage( error.message ); }
	};
	const addressField = ( field: keyof Address, label: string, description: string, required = true ) => <p><FieldLabel htmlFor={ `wp-buyer-blacklist-${ field }` }>{ label }</FieldLabel><input aria-describedby={ `wp-buyer-blacklist-${ field }-description` } className="regular-text" id={ `wp-buyer-blacklist-${ field }` } required={ required } value={ address[ field ] } onChange={ ( event ) => setAddress( { ...address, [ field ]: event.target.value } ) } /><small id={ `wp-buyer-blacklist-${ field }-description` }>{ description }</small></p>;
	const visibleEntries = ( selectedIds.length ? entries.filter( ( entry ) => selectedIds.includes( Number( entry.id ) ) ) : entries ).filter( ( entry ) => ( ! typeFilter || entry.match_type === typeFilter ) && ( ! statusFilter || ( 'active' === statusFilter ) === ( '1' === entry.is_active ) ) && `${ displayValue( entry ) } ${ entry.reason }`.toLowerCase().includes( query.toLowerCase() ) ).sort( ( first, second ) => sortDescending ? second.created_at.localeCompare( first.created_at ) : first.created_at.localeCompare( second.created_at ) );
	const pages = Math.max( 1, Math.ceil( visibleEntries.length / pageSize ) );
	const pageEntries = visibleEntries.slice( ( page - 1 ) * pageSize, page * pageSize );
	if ( tab === 'import-export' ) return <main className="wpbb-page wpbb-import-page">
		<div className="wpbb-import-cards">
			{ canManagePrivacyData && <section className="wpbb-card wpbb-export-card"><span className="wpbb-icon-tile is-primary is-upload" aria-hidden="true" /><div><h2>Export Blacklist entries</h2><p>Download all Blacklist entries to a CSV file. You can use this file to keep a backup or import it to another store.</p><a className="button button-primary" href={ root?.dataset.exportUrl }><span className="wpbb-button-icon is-upload is-download" aria-hidden="true" />Export CSV</a><p className="description">The exported file includes all Blacklist entries, including Match type, value, reason, status, and date added.</p></div></section> }
			<section className="wpbb-card wpbb-import-card"><span className="wpbb-icon-tile is-primary is-upload" aria-hidden="true" /><div><h2>Import Blacklist entries</h2><p>Upload a CSV file to add multiple Blacklist entries at once. Existing entries are not removed.</p></div><div className="wpbb-drop-target" onDragOver={ ( event ) => event.preventDefault() } onDrop={ ( event ) => { event.preventDefault(); void validateFile( event.dataTransfer.files[ 0 ] ); } }><span className="wpbb-icon-tile is-primary is-upload" aria-hidden="true" /><p>Drag and drop your CSV file here</p><span>or</span><div className="wpbb-file-picker"><FieldLabel className="button" htmlFor="wp-buyer-blacklist-csv-file"><span className="wpbb-button-icon is-upload" aria-hidden="true" />Choose file</FieldLabel><input className="wpbb-file-input" id="wp-buyer-blacklist-csv-file" type="file" accept=".csv,text/csv" onChange={ validateImport } /></div><small>Accepted file type: CSV (.csv) · Maximum file size: 5 MB</small></div><div className="wpbb-import-example"><span className="wpbb-import-example-icon" aria-hidden="true">i</span><div><strong>Need a template?</strong><p>Download our example CSV file to see the correct format.</p></div><a className="button" href={ exampleCsvUrl }><span className="wpbb-button-icon is-upload is-download" aria-hidden="true" />Download example CSV</a></div></section>
		</div>
		{ importResult && <section className="wpbb-card"><h2>Import review</h2><p>{ importResult.valid } valid, { importResult.duplicates.length } duplicate, { importResult.rejected.length } rejected.</p>{ importResult.token && <button className="button button-primary" onClick={ confirmImport }>Confirm import</button> }<ul>{ [ ...importResult.duplicates, ...importResult.rejected ].map( ( row ) => <li key={ `${ row.row }-${ row.reason }` }>Row { row.row }: { row.reason }</li> ) }</ul>{ importResult.created !== null && <p>Created { importResult.created } entries.</p> }</section> }
		<section className="wpbb-card wpbb-import-history"><h2>Recent imports</h2><p className="description">View the status of your recent import activities.</p>{ importHistory.length ? <div className="wpbb-table-wrap"><table className="widefat striped"><thead><tr><th>Date and time</th><th>File name</th><th>Entries found</th><th>Imported</th><th>Skipped</th><th>Status</th></tr></thead><tbody>{ importHistory.map( ( item ) => <tr key={ item.id }><td>{ item.created_at }</td><td>{ item.file_name }</td><td>{ item.entries_found }</td><td>{ item.entries_created }</td><td>{ item.duplicates }</td><td><span className={ `wpbb-import-status is-${ item.outcome }` }>{ item.outcome === 'completed' ? 'Completed' : item.outcome === 'warning' ? 'Completed with warnings' : 'Failed' }</span></td></tr> ) }</tbody></table></div> : <p>No imports have been recorded.</p> }</section>{ message && <p role="status">{ message }</p> }
	</main>;
	return <main className="wpbb-page">
		<section className="wpbb-summary" aria-label="Blacklist entry summary">{ [ [ 'Total entries', summary.total_entries, 'All Blacklist entries.', 'entries', 'is-primary' ], [ 'Active entries', summary.active_entries, 'Currently blocking matching Buyers.', 'active', 'is-success' ], [ 'Inactive entries', summary.inactive_entries, 'Kept without blocking Buyers.', 'inactive', 'is-neutral' ], [ 'Blocked attempts', summary.blocked_attempts, 'Checkout attempts prevented.', 'blocked-attempts', 'is-danger' ] ].map( ( [ label, total, description, icon, iconClass ] ) => <article className="wpbb-card" key={ String( label ) }><span className={ `wpbb-icon-tile ${ iconClass } is-${ icon }` } aria-hidden="true" /><div><p>{ label }</p><strong>{ total }</strong><small>{ description }</small></div></article> ) }</section>
		<section className="wpbb-card"><form className="wpbb-entry-form" onSubmit={ submit }><h2>Add new Blacklist entry</h2><p className="wpbb-entry-form-intro">Add an email or structured address to block matching Buyers at checkout.</p><p><FieldLabel htmlFor="wp-buyer-blacklist-match-type">Match type</FieldLabel><select aria-describedby="wp-buyer-blacklist-match-type-description" id="wp-buyer-blacklist-match-type" value={ matchType } onChange={ ( event ) => setMatchType( event.target.value ) }><option value="email">Email</option><option value="billing_address">Billing address</option><option value="shipping_address">Shipping address</option></select><small id="wp-buyer-blacklist-match-type-description">Choose the Buyer detail to match during checkout.</small></p>{ matchType === 'email' && <p><FieldLabel htmlFor="wp-buyer-blacklist-email">Email</FieldLabel><input aria-describedby="wp-buyer-blacklist-email-description" className="regular-text" id="wp-buyer-blacklist-email" placeholder="e.g. customer@example.com" type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /><small id="wp-buyer-blacklist-email-description">Enter the full email address to block.</small></p> }{ matchType.includes( 'address' ) && <>{ addressField( 'address_line_1', 'Address line 1', 'Enter the building number and street name.' ) }{ addressField( 'address_line_2', 'Address line 2', 'Add an apartment, suite, or unit when needed.', false ) }{ addressField( 'city', 'City', 'Enter the city or town.' ) }{ addressField( 'state', 'State', 'Enter the state, province, or region.' ) }{ addressField( 'postcode', 'Postcode', 'Enter the postal or ZIP code.' ) }{ addressField( 'country', 'Country', 'Enter the two-letter country code, such as US.' ) }</> }<p className="wpbb-entry-reason"><FieldLabel htmlFor="wp-buyer-blacklist-reason">Reason (optional)</FieldLabel><textarea aria-describedby="wp-buyer-blacklist-reason-description" className="large-text" id="wp-buyer-blacklist-reason" placeholder="Add a reason for this Blacklist entry..." value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /><small id="wp-buyer-blacklist-reason-description">This helps operators understand why the Buyer was blacklisted.</small></p><button className="button button-primary" type="submit">Add entry</button></form></section>{ message && <p role="status">{ message }</p> }
		<section className="wpbb-card wpbb-entries-card"><h2>Blacklist entries</h2><div className="wpbb-filters"><p><FieldLabel htmlFor="wp-buyer-blacklist-search">Search entries</FieldLabel><input id="wp-buyer-blacklist-search" type="search" value={ query } onChange={ ( event ) => { setQuery( event.target.value ); setPage( 1 ); } } /></p><p><FieldLabel htmlFor="wp-buyer-blacklist-type-filter">Match type</FieldLabel><select id="wp-buyer-blacklist-type-filter" value={ typeFilter } onChange={ ( event ) => setTypeFilter( event.target.value ) }><option value="">All Match types</option><option value="email">Email</option><option value="billing_address">Billing address</option><option value="shipping_address">Shipping address</option></select></p><p><FieldLabel htmlFor="wp-buyer-blacklist-status-filter">Status</FieldLabel><select id="wp-buyer-blacklist-status-filter" value={ statusFilter } onChange={ ( event ) => setStatusFilter( event.target.value ) }><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></p><button className="button" type="button" onClick={ () => { setQuery( '' ); setTypeFilter( '' ); setStatusFilter( '' ); } }>Reset</button></div><div className="wpbb-table-wrap"><table className="widefat striped"><thead><tr><th>Match type</th><th>Canonical value</th><th>Reason</th><th>Status</th><th><button className="button-link" onClick={ () => setSortDescending( ! sortDescending ) }>Updated</button></th><th>Actions</th></tr></thead><tbody>{ pageEntries.map( ( entry ) => <tr key={ entry.id }><td>{ matchTypeLabel( entry.match_type ?? 'email' ) }</td><td>{ displayValue( entry ) }</td><td>{ entry.reason }</td><td><span className={ `wp-buyer-blacklist-status-badge ${ entry.is_active === '1' ? 'is-active' : 'is-inactive' }` }>{ entry.is_active === '1' ? 'Active' : 'Inactive' }</span></td><td>{ entry.created_at }</td><td><button className="button" commandfor={ dialogId } command="show-modal" onClick={ () => inspect( entry ) }>Manage</button></td></tr> ) }</tbody></table></div><div className="tablenav"><div className="tablenav-pages" style={ { float: 'left' } }><button className="button" disabled={ page === 1 } onClick={ () => setPage( page - 1 ) }>Previous</button> <span>{ page } of { pages }</span> <button className="button" disabled={ page === pages } onClick={ () => setPage( page + 1 ) }>Next</button></div></div></section>
		<dialog ref={ dialog } id={ dialogId } aria-labelledby="wpbb-entry-dialog-title" closedby="closerequest" onClose={ () => { setSelectedEntry( null ); setBuyerContext( null ); } }>{ selectedEntry && <section className="wpbb-entry-dialog"><header className="wpbb-entry-dialog-header"><div><h2 id="wpbb-entry-dialog-title">Manage Blacklist entry</h2><p>View and manage the details for this Blacklist entry.</p></div><button className="wpbb-entry-dialog-close" aria-label="Close dialog" commandfor={ dialogId } command="close"><span className="wpbb-modal-icon is-close" aria-hidden="true" /></button></header><div className="wpbb-entry-dialog-grid"><div className="wpbb-entry-dialog-main"><section className="wpbb-entry-summary"><span className="wpbb-icon-tile is-primary"><span className="wpbb-modal-icon is-mail" aria-hidden="true" /></span><div><span>{ matchTypeLabel( selectedEntry.match_type ) }</span><h3>{ displayValue( selectedEntry ) }</h3><p>Added on { selectedEntry.created_at } · Entry #{ selectedEntry.id }</p></div><div className="wpbb-entry-summary-status"><span className={ `wp-buyer-blacklist-status-badge ${ selectedEntry.is_active === '1' ? 'is-active' : 'is-inactive' }` }>{ selectedEntry.is_active === '1' ? 'Active' : 'Inactive' }</span><small>{ selectedEntry.is_active === '1' ? 'Currently blocking' : 'Not blocking' }</small></div></section><section className="wpbb-buyer-details"><h3>Buyer details</h3>{ buyerContext?.buyer ? <div className="wpbb-buyer-details-card"><span className="wpbb-buyer-avatar"><span className="wpbb-modal-icon is-user" aria-hidden="true" /></span><div className="wpbb-buyer-identity"><strong>{ buyerContext.buyer.name } (#{ buyerContext.buyer.id })</strong><span>{ buyerContext.buyer.email }</span></div><div className="wpbb-buyer-addresses"><div><span className="wpbb-modal-icon is-pin" aria-hidden="true" /><p><strong>Billing address</strong><br />{ formatAddress( buyerContext.buyer.billing_address ) || 'Not provided' }</p></div><div><span className="wpbb-modal-icon is-pin" aria-hidden="true" /><p><strong>Shipping address</strong><br />{ formatAddress( buyerContext.buyer.shipping_address ) || 'Not provided' }</p></div></div></div> : <p>{ buyerContext?.message ?? 'No uniquely identified registered Buyer.' }</p> }</section><section className="wpbb-entry-reason-editor"><h3>Reason</h3><p>Add a note to help you remember why this Buyer was blacklisted.</p><FieldLabel htmlFor="wp-buyer-blacklist-edit-reason">Reason</FieldLabel><textarea className="large-text" id="wp-buyer-blacklist-edit-reason" required maxLength={ 500 } value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /><small>{ reason.length } / 500</small></section><section className="wpbb-entry-status-editor"><h3>Status</h3><p>Control whether this entry is actively blocking Buyers from placing orders.</p><button className="wpbb-entry-active-toggle" role="switch" aria-label="Active Blacklist entry" aria-checked={ selectedEntry.is_active === '1' } onClick={ () => update( { is_active: selectedEntry.is_active !== '1' } ) }><span aria-hidden="true" />{ selectedEntry.is_active === '1' ? 'Active' : 'Inactive' }</button><small>{ selectedEntry.is_active === '1' ? 'This entry is currently blocking matching Buyers.' : 'This entry is not blocking matching Buyers.' }</small></section><footer className="wpbb-entry-dialog-footer">{ canManagePrivacyData && <><div><button className="button wp-buyer-blacklist-delete-entry" onClick={ remove }><span className="wpbb-button-icon is-trash" aria-hidden="true" />Delete entry</button><p>Permanently remove this entry from the Blacklist.</p></div><a href={ `${ dossierExportUrl }&entry_id=${ selectedEntry.id }`}><span className="wpbb-button-icon is-download" aria-hidden="true" />Export dossier</a></> }<button className="button button-primary" onClick={ () => update( { reason } ) }>Save changes</button></footer></div><aside className="wpbb-entry-dialog-details"><section className="wpbb-entry-dialog-panel wpbb-audit-history"><h3><span className="wpbb-icon-tile is-primary is-audit-history" aria-hidden="true" />Audit history</h3>{ events.length ? <ul>{ events.map( ( event ) => <li key={ `${ event.created_at }-${ event.action }` }><strong>{ event.action }</strong>{ ' ' }<span>by { event.actor_name ?? 'System' }</span><time>{ event.created_at }</time></li> ) }</ul> : <p>No Audit events yet.</p> }</section><section className="wpbb-entry-dialog-panel wpbb-blocked-attempts"><h3><span className="wpbb-icon-tile is-danger is-blocked-attempts" aria-hidden="true" />Blocked attempts <span className="wpbb-attempt-count">{ attempts.length }</span></h3>{ attempts.length ? <ul>{ attempts.map( ( attempt ) => <li key={ attempt.id }>{ attempt.outcome } { attempt.identity_value } at { attempt.created_at }</li> ) }</ul> : <div className="wpbb-no-attempts"><span className="wpbb-icon-tile is-primary"><span className="wpbb-modal-icon is-locked" aria-hidden="true" /></span><strong>No blocked attempts yet</strong><p>This Buyer has not triggered any blocked order attempts.</p></div> }<p className="wpbb-attempts-note"><span className="wpbb-import-example-icon" aria-hidden="true">i</span>When this Buyer attempts to place an order, the attempt appears here.</p></section></aside></div></section> }</dialog>
	</main>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) createRoot( root ).render( <App /> );
