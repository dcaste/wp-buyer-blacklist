import apiFetch from '@wordpress/api-fetch';
import { createRoot, useEffect, useRef, useState } from '@wordpress/element';

type Entry = { id: string; match_type: string; value: string; reason: string; is_active: string; created_at: string };
type AuditEvent = { action: string; actor_id: string | null; actor_name: string | null; created_at: string };
type BlockedAttempt = { id: string; identity_value: string; outcome: string; created_at: string };
type Address = { address_line_1: string; address_line_2: string; city: string; state: string; postcode: string; country: string };
type ImportResult = { token: string; valid: number; created: number | null; duplicates: { row: number; reason: string }[]; rejected: { row: number; reason: string }[] };

const emptyAddress: Address = { address_line_1: '', address_line_2: '', city: '', state: '', postcode: '', country: '' };
const pageSize = 20;
const labelStyle = { display: 'block', fontWeight: 600, marginBottom: '4px' };
const statusBadgeStyle = {
	active: { backgroundColor: '#f6f7f7', border: '1px solid #c3c4c7', borderRadius: '2em', color: '#50575e', fontSize: '12px', fontWeight: 500, padding: '.15em .5em', whiteSpace: 'nowrap' },
	inactive: { backgroundColor: '#fcf0f1', border: '1px solid #d63638', borderRadius: '2em', color: '#b32d2e', fontSize: '12px', fontWeight: 500, padding: '.15em .5em', whiteSpace: 'nowrap' },
};
const matchTypeLabel = ( matchType: string ) => ( { email: 'Email', customer_id: 'Customer ID', billing_address: 'Billing address', shipping_address: 'Shipping address' }[ matchType ] ?? matchType );

function FieldLabel( { children }: { children: React.ReactNode } ) {
	return <label><span style={ labelStyle }>{ children }</span></label>;
}

export function App() {
	const [ entries, setEntries ] = useState< Entry[] >( [] );
	const [ events, setEvents ] = useState< AuditEvent[] >( [] );
	const [ attempts, setAttempts ] = useState< BlockedAttempt[] >( [] );
	const [ selectedEntry, setSelectedEntry ] = useState< Entry | null >( null );
	const [ matchType, setMatchType ] = useState( 'email' );
	const [ email, setEmail ] = useState( '' );
	const [ address, setAddress ] = useState< Address >( emptyAddress );
	const [ reason, setReason ] = useState( '' );
	const [ message, setMessage ] = useState( '' );
	const [ importResult, setImportResult ] = useState< ImportResult | null >( null );
	const [ page, setPage ] = useState( 1 );
	const dialog = useRef< HTMLDialogElement >( null );
	const root = document.getElementById( 'wp-buyer-blacklist-admin' );
	const tab = root?.dataset.tab ?? 'manage';
	const dossierExportUrl = root?.dataset.dossierExportUrl ?? '';
	const exampleCsvUrl = root?.dataset.exampleCsvUrl ?? '';
	const selectedIds = new URLSearchParams( window.location.search ).getAll( 'entry_ids[]' ).map( Number );
	const dialogId = 'wp-buyer-blacklist-entry-dialog';
	const load = () => apiFetch< Entry[] >( { path: '/wp-buyer-blacklist/v1/email-entries' } ).then( ( loaded ) => { setEntries( loaded ); setPage( 1 ); } );
	const inspect = async ( entry: Entry ) => {
		setSelectedEntry( entry );
		setReason( entry.reason );
		const [ auditEvents, blockedAttempts ] = await Promise.all( [ apiFetch< AuditEvent[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }` } ), apiFetch< BlockedAttempt[] >( { path: `/wp-buyer-blacklist/v1/email-entries/${ entry.id }/blocked-attempts` } ) ] );
		setEvents( auditEvents );
		setAttempts( blockedAttempts );
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
		if ( ! selectedEntry || ! window.confirm( `Delete ${ selectedEntry.value }? This cannot be undone.` ) ) return;
		try {
			await apiFetch( { path: `/wp-buyer-blacklist/v1/email-entries/${ selectedEntry.id }`, method: 'DELETE', data: { confirm: true } } );
			dialog.current?.close();
			setSelectedEntry( null ); setEvents( [] ); setAttempts( [] ); setMessage( 'Blacklist entry deleted.' ); load();
		} catch ( error: any ) { setMessage( error.message ); }
	};
	const addressField = ( field: keyof Address, label: string, required = true ) => <p><FieldLabel>{ label }</FieldLabel><input className="regular-text" aria-label={ label } required={ required } value={ address[ field ] } onChange={ ( event ) => setAddress( { ...address, [ field ]: event.target.value } ) } /></p>;
	const visibleEntries = selectedIds.length ? entries.filter( ( entry ) => selectedIds.includes( Number( entry.id ) ) ) : entries;
	const pages = Math.max( 1, Math.ceil( visibleEntries.length / pageSize ) );
	const pageEntries = visibleEntries.slice( ( page - 1 ) * pageSize, page * pageSize );
	if ( tab === 'import-export' ) return <section><h2>Import and export</h2><p><a className="button" href={ root?.dataset.exportUrl }>Export Blacklist CSV</a> <a className="button" href={ exampleCsvUrl }>Download example CSV</a></p><p><FieldLabel>CSV file</FieldLabel><input className="regular-text" aria-label="CSV file" type="file" accept=".csv,text/csv" onChange={ validateImport } /></p>{ importResult && <><p>{ importResult.valid } valid, { importResult.duplicates.length } duplicate, { importResult.rejected.length } rejected.</p>{ importResult.token && <button className="button button-primary" onClick={ confirmImport }>Confirm import</button> }<ul>{ [ ...importResult.duplicates, ...importResult.rejected ].map( ( row ) => <li key={ `${ row.row }-${ row.reason }` }>Row { row.row }: { row.reason }</li> ) }</ul>{ importResult.created !== null && <p>Created { importResult.created } entries.</p> }</> }{ message && <p role="status">{ message }</p> }</section>;
	return <><form onSubmit={ submit }><h2>Add Blacklist entry</h2><p><FieldLabel>Match type</FieldLabel><select aria-label="Match type" value={ matchType } onChange={ ( event ) => setMatchType( event.target.value ) }><option value="email">Email</option><option value="billing_address">Billing address</option><option value="shipping_address">Shipping address</option></select></p>{ matchType === 'email' && <p><FieldLabel>Email</FieldLabel><input className="regular-text" aria-label="Email" type="email" required value={ email } onChange={ ( event ) => setEmail( event.target.value ) } /></p> }{ matchType.includes( 'address' ) && <>{ addressField( 'address_line_1', 'Address line 1' ) }{ addressField( 'address_line_2', 'Address line 2', false ) }{ addressField( 'city', 'City' ) }{ addressField( 'state', 'State' ) }{ addressField( 'postcode', 'Postcode' ) }{ addressField( 'country', 'Country' ) }</> }<p><FieldLabel>Reason</FieldLabel><textarea className="large-text" aria-label="Reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></p><button className="button button-primary" type="submit">Add entry</button></form>{ message && <p role="status">{ message }</p> }<h2>Blacklist entries</h2><table className="widefat striped"><thead><tr><th>Match type</th><th>Canonical value</th><th>Reason</th><th>Status</th><th>Actions</th></tr></thead><tbody>{ pageEntries.map( ( entry ) => <tr key={ entry.id }><td>{ matchTypeLabel( entry.match_type ?? 'email' ) }</td><td>{ entry.value }</td><td>{ entry.reason }</td><td><span style={ entry.is_active === '1' ? statusBadgeStyle.active : statusBadgeStyle.inactive }>{ entry.is_active === '1' ? 'Active' : 'Inactive' }</span></td><td><button className="button" commandfor={ dialogId } command="show-modal" onClick={ () => inspect( entry ) }>Manage</button></td></tr> ) }</tbody></table><div className="tablenav"><div className="tablenav-pages"><button className="button" disabled={ page === 1 } onClick={ () => setPage( page - 1 ) }>Previous</button> <span>{ page } of { pages }</span> <button className="button" disabled={ page === pages } onClick={ () => setPage( page + 1 ) }>Next</button></div></div><dialog ref={ dialog } id={ dialogId } closedby="closerequest" onClose={ () => setSelectedEntry( null ) }>{ selectedEntry && <section><h2>Manage { selectedEntry.value }</h2><p><FieldLabel>Reason</FieldLabel><textarea className="large-text" aria-label="Edit reason" required value={ reason } onChange={ ( event ) => setReason( event.target.value ) } /></p><button className="button button-primary" onClick={ () => update( { reason } ) }>Save reason</button> <button className="button" onClick={ () => update( { is_active: selectedEntry.is_active !== '1' } ) }>{ selectedEntry.is_active === '1' ? 'Deactivate' : 'Activate' }</button> <button className="button-link-delete" onClick={ remove }>Delete entry</button><p><a href={ `${ dossierExportUrl }&entry_id=${ selectedEntry.id }` }>Export dossier</a></p><h3>Audit history</h3><ul>{ events.map( ( event ) => <li key={ `${ event.created_at }-${ event.action }` }>{ event.action } by { event.actor_name ?? 'System' } at { event.created_at }</li> ) }</ul><h3>Blocked attempts</h3><ul>{ attempts.map( ( attempt ) => <li key={ attempt.id }>{ attempt.outcome } { attempt.identity_value } at { attempt.created_at }</li> ) }</ul><button className="button" commandfor={ dialogId } command="close">Close</button></section> }</dialog></>;
}

const root = document.getElementById( 'wp-buyer-blacklist-admin' );
if ( root ) createRoot( root ).render( <App /> );
