<?php
/**
 * Admin view — All Linked Contacts (Family Social Graph overview).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$contacts = KYC_DB::get_all_linked_contacts();
?>
<div class="wrap">
	<h1>Linked Contacts</h1>
	<p>All family/friend links added by your customers. A 🔗 badge means both sides have linked each other, confirming the relationship.</p>

	<?php if ( empty( $contacts ) ) : ?>
		<p>No linked contacts yet. Contacts appear here once customers add family members from their profile page.</p>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th>Customer</th>
				<th>Customer Phone</th>
				<th>Linked Person</th>
				<th>Their Phone</th>
				<th>Relationship</th>
				<th>Birthday</th>
				<th>Anniversary</th>
				<th>Mutual?</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $contacts as $row ) :
				$owner_name = trim( $row['owner_first'] . ' ' . $row['owner_last'] );
			?>
			<tr>
				<td><?php echo esc_html( $owner_name ); ?></td>
				<td>
					<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D/', '', $row['owner_phone'] ) ); ?>" target="_blank">
						<?php echo esc_html( $row['owner_phone'] ); ?>
					</a>
				</td>
				<td><?php echo esc_html( $row['linked_name'] ); ?></td>
				<td><?php echo esc_html( $row['linked_phone'] ); ?></td>
				<td><?php echo esc_html( $row['relationship'] ); ?></td>
				<td><?php echo $row['dob'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row['dob'] ) ) ) : '—'; ?></td>
				<td><?php echo $row['anniversary'] ? esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row['anniversary'] ) ) ) : '—'; ?></td>
				<td style="text-align: center; font-size: 18px;">
					<?php if ( $row['is_mutual'] ) : ?>
						<span title="Mutual link confirmed">🔗</span>
					<?php else : ?>
						<span style="color: #aaa;" title="Linked, but the other person hasn't registered yet">·</span>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
