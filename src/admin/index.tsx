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

const emptyAddress: Address = { address_line_1: '', address_line_2: '', city: '', state: '', postcode: '', country: '' };
const pageSize = 20;
const statusBadgeStyle = {
	inactive: { backgroundColor: '#fcf0f1', border: '1px solid #d63638', borderRadius: '2em', color: '#b32d2e', fontSize: '12px', fontWeight: 500, padding: '.15em .5em', whiteSpace: 'nowrap' },
};
const destructiveButtonStyle = { borderColor: '#b32d2e', color: '#b32d2e' };
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

function FieldLabel( { children, htmlFor }: { children: React.ReactNode; htmlFor: string } ) {
	return <label htmlFor={ htmlFor }>{ children }</label>;
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
	const submit = async ( event: React.FormEvent ) => {
		event.preventDefault();
		try {
			const path = matchType === 'email' ? '/wp-buyer-blacklist/v1/email-entries' : '/wp-buyer-blacklist/v1/address-entries';
			const data = matchType === 'email' ? { email, reason } : { match_type: matchType, ...address, reason };
			await apiFetch( { path, method: 'POST', data } );
			setEmail( '' ); setAddress( emptyAddress ); setReason( '' ); setMessage( 'Blacklist entry created.' ); load();
		} catch ( error: any ) { setMessage( error.message ); }
	};
	const validateImport = async ( event: React.ChangeEvent< HTMLInputElement > ) => {
		const file = event.target.files?.[ 0 ];
		if ( ! file ) return;
		try { setImportResult( await apiFetch< ImportResult >( { path: '/wp-buyer-blacklist/v1/csv-import/validate', method: 'POST', data: { csv: await file.text() } } ) ); } catch ( error: any ) { setMessage( error.message ); }
	};
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
	const addressField = ( field: keyof Address, label: string, required = true ) => <p><FieldLabel htmlFor={ `wp-buyer-blacklist-${ field }` }>{ label }</FieldLabel><input className="regular-text" id={ `wp-buyer-blacklist-${ field }` } required={ required } value={ address[ field ] } onChange={ ( event ) => setAddress( { ...address, [ field ]: event.target.value } ) } /></p>;
	const visibleEntries = ( selectedIds.length ? entries.filter( ( entry ) => selectedIds.includes( Number( entry.id ) ) ) : entries ).filter( ( entry ) => ( ! typeFilter || entry.match_type === typeFilter ) && ( ! statusFilter || ( 'active' === statusFilter ) === ( '1' === entry.is_active ) ) && `${ displayValue( entry ) } ${ entry.reason }`.toLowerCase().includes( query.toLowerCase() ) ).sort( ( first, second ) => sortDescending ? second.created_at.localeCompare( first.created_at ) : first.created_at.localeCompare( second.created_at ) );
	const pages = Math.max( 1, Math.ceil( visibleEntries.length / pageSize ) );
	const pageEntries = visibleEntries.slice( ( page - 1 ) * pageSize, page * pageSize );
	if ( tab === 'import-export' ) return <section><h2>Import and export</h2><p><a className="button" href={ root?.dataset.exportUrl }>Export Blacklist CSV</a> <a className="button" href={ exampleCsvUrl }>Download example CSV</a></p><p><FieldLabel htmlFor="wp-buyer-blacklist-csv-file">CSV file</FieldLabel><input className="regular-text" id="wp-buyer-blacklist-csv-file" type="file" accept=".csv,text/csv" onChange={ validateImport } /></p>{ importResult && <><p>{ importResult.valid } valid, { importResult.duplicates.length } duplicate, { importResult.rejected.length } rejected.</p>{ importResult.token && <button className="button button-primary" onClick={ confirmImport }>Confirm import</button> }<ul>{ [ ...importResult.duplicates, ...importResult.rejected ].map( ( row ) => <li key={ `${ row.row }-${ row.reason }` }>Row { row.row }: { row.reason }</li> ) }</ul>{ importResult.created !== null && <p>Created { importResult.created } entries.</p> }</> }{ message && <p role="status">{ message }</p> }</section>;
	return <main className="wpbb-page">
		<section className="wpbb-summary" aria-label="Blacklist entry summary">{ [ [ 'Total entries', summary.total_entries, 'All Blacklist entries.' ], [ 'Active entries', summary.active_entries, 'Currently blocking matching Buyers.' ], [ 'Inactive entries', summary.inactive_entries, 'Kept without blocking Buyers.' ], [ 'Blocked attempts', summary.blocked_attempts, 'Checkout attempts prevented.' ] ].map( ( [ label, total, description ] ) => <article className="wpbb-card" key={ String( label ) }><p>{ label }</p><strong>{ total }</strong><small>{ description }</small></article> ) }</section>
		<section className="wpbb-card"><form onSubmit={ submit }><h2>Add new Blacklist entry</h2><p><FieldLabel htmlFor="wp-buyer-blacklist-match-type">Match type</FieldLabel><select id="wp-buyer-blacklist-match-type" value={ matchType } onChange={ ( event ) => setMatchType( event.target.value ) }><option value="email">Email</option><option value="billing_address">Billing address</option><option value="shipping_address">Shipping address</option></select></p>{ matchType === 'email' && <p><FieldLabel htmlFor="wp-buyer-blacklist-email">Email</FieldLabel><input className="regular-text" id="wp-buyer-blacklist-email" type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /></p> }{ matchType.includes( 'address' ) && <>{ addressField( 'address_line_1', 'Address line 1' ) }{ addressField( 'address_line_2', 'Address line 2', false ) }{ addressField( 'city', 'City' ) }{ addressField( 'state', 'State' ) }{ addressField( 'postcode', 'Postcode' ) }{ addressField( 'country', 'Country' ) }</> }<p><FieldLabel htmlFor="wp-buyer-blacklist-reason">Reason (optional)</FieldLabel><textarea className="large-text" id="wp-buyer-blacklist-reason" value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></p><button className="button button-primary" type="submit">Add entry</button> <a className="button-link" href="?page=wp-buyer-blacklist&tab=import-export">Import CSV</a></form></section>{ message && <p role="status">{ message }</p> }
		<section className="wpbb-card"><h2>Blacklist entries</h2><div className="wpbb-filters"><p><FieldLabel htmlFor="wp-buyer-blacklist-search">Search entries</FieldLabel><input id="wp-buyer-blacklist-search" type="search" value={ query } onChange={ ( event ) => { setQuery( event.target.value ); setPage( 1 ); } } /></p><p><FieldLabel htmlFor="wp-buyer-blacklist-type-filter">Match type</FieldLabel><select id="wp-buyer-blacklist-type-filter" value={ typeFilter } onChange={ ( event ) => setTypeFilter( event.target.value ) }><option value="">All Match types</option><option value="email">Email</option><option value="billing_address">Billing address</option><option value="shipping_address">Shipping address</option></select></p><p><FieldLabel htmlFor="wp-buyer-blacklist-status-filter">Status</FieldLabel><select id="wp-buyer-blacklist-status-filter" value={ statusFilter } onChange={ ( event ) => setStatusFilter( event.target.value ) }><option value="">All statuses</option><option value="active">Active</option><option value="inactive">Inactive</option></select></p><button className="button" type="button" onClick={ () => { setQuery( '' ); setTypeFilter( '' ); setStatusFilter( '' ); } }>Reset</button></div><div className="wpbb-table-wrap"><table className="widefat striped"><thead><tr><th>Match type</th><th>Canonical value</th><th>Reason</th><th>Status</th><th><button className="button-link" onClick={ () => setSortDescending( ! sortDescending ) }>Updated</button></th><th>Actions</th></tr></thead><tbody>{ pageEntries.map( ( entry ) => <tr key={ entry.id }><td>{ matchTypeLabel( entry.match_type ?? 'email' ) }</td><td>{ displayValue( entry ) }</td><td>{ entry.reason }</td><td><span className={ `wp-buyer-blacklist-status-badge ${ entry.is_active === '1' ? 'is-active' : 'is-inactive' }` }>{ entry.is_active === '1' ? 'Active' : 'Inactive' }</span></td><td>{ entry.created_at }</td><td><button className="button" commandfor={ dialogId } command="show-modal" onClick={ () => inspect( entry ) }>Manage</button></td></tr> ) }</tbody></table></div><div className="tablenav"><div className="tablenav-pages" style={ { float: 'left' } }><button className="button" disabled={ page === 1 } onClick={ () => setPage( page - 1 ) }>Previous</button> <span>{ page } of { pages }</span> <button className="button" disabled={ page === pages } onClick={ () => setPage( page + 1 ) }>Next</button></div></div></section>
		<dialog ref={ dialog } id={ dialogId } closedby="closerequest" onClose={ () => { setSelectedEntry( null ); setBuyerContext( null ); } }>{ selectedEntry && <section><h2>Manage Blacklist entry</h2><p><b>Match type</b><br />{ matchTypeLabel( selectedEntry.match_type ) }<br /><b>Canonical value</b><br />{ displayValue( selectedEntry ) }<br /><b>Entry identifier</b><br />{ selectedEntry.id }</p>{ buyerContext?.buyer ? <section><h3>Buyer details</h3><p>{ buyerContext.buyer.name } (#{ buyerContext.buyer.id })<br />{ buyerContext.buyer.email }<br />Billing address: { formatAddress( buyerContext.buyer.billing_address ) || 'Not provided' }<br />Shipping address: { formatAddress( buyerContext.buyer.shipping_address ) || 'Not provided' }</p></section> : buyerContext && <p>{ buyerContext.message }</p> }<p><FieldLabel htmlFor="wp-buyer-blacklist-edit-reason">Reason</FieldLabel><textarea className="large-text" id="wp-buyer-blacklist-edit-reason" required maxLength={ 500 } value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /><small>{ reason.length } / 500</small></p><button className="button button-primary" onClick={ () => update( { reason } ) }>Save changes</button> <button className="button" onClick={ () => update( { is_active: selectedEntry.is_active !== '1' } ) }>{ selectedEntry.is_active === '1' ? 'Deactivate' : 'Activate' }</button><p style={ { textAlign: 'right' } }><button className="button wp-buyer-blacklist-delete-entry" style={ destructiveButtonStyle } onClick={ remove }>Delete entry</button></p><p><a href={ `${ dossierExportUrl }&entry_id=${ selectedEntry.id }` }>Export dossier</a></p><h3>Audit history</h3>{ events.length ? <ul>{ events.map( ( event ) => <li key={ `${ event.created_at }-${ event.action }` }>{ event.action } by { event.actor_name ?? 'System' } at { event.created_at }</li> ) }</ul> : <p>No Audit events yet.</p> }<h3>Blocked attempts ({ attempts.length })</h3>{ attempts.length ? <ul>{ attempts.map( ( attempt ) => <li key={ attempt.id }>{ attempt.outcome } { attempt.identity_value } at { attempt.created_at }</li> ) }</ul> : <p>No Blocked attempts have been recorded.</p> }<button className="button" commandfor={ dialogId } command="close">Close</button></section> }</dialog>
	</main>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) createRoot( root ).render( <App /> );
