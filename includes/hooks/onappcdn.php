<?php
/**
 * Adds payment to OnApp CP after invoice is marked as paid in WHMCS
 *
 * @param array $vars invoice ID
 *
 * @return void
 */
function onappcdn_invoice_paid( $vars ) {
	if( ! defined( 'ONAPP_WRAPPER_INIT' ) ) {
		define( 'ONAPP_WRAPPER_INIT', dirname( __FILE__ ) .
				'/../wrapper/OnAppInit.php' );
	}

	if( file_exists( ONAPP_WRAPPER_INIT ) ) {
		require_once ONAPP_WRAPPER_INIT;
	}

	$invoice_id = $vars[ 'invoiceid' ];

	$query = 'SELECT
				i.subtotal AS amount,
				onappc.onapp_user_id,
				s.ipaddress,
				s.username,
				s.password,
				s.hostname,
				c.currency,
				curr.rate,
				h.id AS hostingid
			FROM
				tblinvoices AS i
			LEFT JOIN
				tblhosting  AS h
				ON i.notes = h.id
			LEFT JOIN
				tblservers AS s
				ON s.id = h.server
			LEFT JOIN
				tblonappcdnclients AS onappc
				ON h.id = onappc.service_id
			LEFT JOIN
				tblclients AS c
				ON c.id = h.userid
			LEFT JOIN
				tblcurrencies AS curr
				ON curr.id = c.currency
			WHERE
				i.id = $invoice_id';

	$result = full_query( $query );

	if( ! $result ) {
		return;
	}

	$row = mysql_fetch_assoc( $result );

// if main product invoice then don't add payment.
	if( ! $row[ 'hostingid' ] ) {
		return;
	}

	if( ! isset( $onapp ) ) {
		$onapp = new OnApp_Factory(
			( $row[ 'hostname' ] ) ? $row[ 'hostname' ] : $row[ 'ipaddress' ],
			$row[ 'username' ],
			decrypt( $row[ 'password' ] )
		);
	}

	$payment = $onapp->factory( 'Payment', true );
	$payment->_user_id = $row[ 'onapp_user_id' ];
	$payment->_amount = round( $row[ 'amount' ] / $row[ 'rate' ], 2 );
	$payment->_invoice_number = $invoice_id;
	$payment->save();
}

add_hook( 'InvoicePaid', 1, 'onappcdn_invoice_paid' );