<?php
/**
 * Admin view — Upcoming Birthdays & Anniversaries.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$look_ahead = absint( get_option( 'kyc_reminder_look_ahead', 30 ) );
$upcoming   = KYC_DB::get_upcoming_dates( $look_ahead );
?>
<div class="wrap">
	<h1>Upcoming Dates</h1>
	<p>Birthdays and anniversaries in the next <strong><?php echo esc_html( $look_ahead ); ?> days</strong>. Configure the look-ahead window in <a href="<?php echo esc_url( admin_url( 'admin.php?page=kyc-settings' ) ); ?>">Settings</a>.</p>

	<?php if ( empty( $upcoming ) ) : ?>
		<div class="notice notice-info inline">
			<p>No upcoming dates in the next <?php echo esc_html( $look_ahead ); ?> days. 🎉</p>
		</div>
	<?php else : ?>
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th style="width: 90px;">Days Until</th>
				<th style="width: 120px;">Date</th>
				<th>Event</th>
				<th>Linked Person</th>
				<th>Relationship</th>
				<th>Customer to Notify</th>
				<th>WhatsApp</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $upcoming as $row ) :
				$owner_name = trim( $row['owner_first'] . ' ' . $row['owner_last'] );
				$badge_color = $row['days_until'] === 0 ? '#d63638' : ( $row['days_until'] <= 3 ? '#d68900' : '#007017' );
				$days_label  = $row['days_until'] === 0 ? 'TODAY' : $row['days_until'] . 'd';
			?>
			<tr>
				<td>
					<span style="font-weight: bold; color: <?php echo esc_attr( $badge_color ); ?>; font-size: 15px;">
						<?php echo esc_html( $days_label ); ?>
					</span>
				</td>
				<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $row['event_date'] ) ) ); ?></td>
				<td>
					<?php echo $row['event_type'] === 'Birthday' ? '🎂' : '💍'; ?>
					<?php echo esc_html( $row['event_type'] ); ?>
				</td>
				<td><?php echo esc_html( $row['linked_name'] ); ?></td>
				<td><?php echo esc_html( $row['relationship'] ); ?></td>
				<td><?php echo esc_html( $owner_name ); ?> <small>(<?php echo esc_html( $row['owner_phone'] ); ?>)</small></td>
				<td>
					<a href="https://wa.me/<?php echo esc_attr( preg_replace( '/\D/', '', $row['owner_phone'] ) ); ?>?text=<?php echo rawurlencode( "Hi {$owner_name}, {$row['linked_name']}'s {$row['event_type']} is coming up on " . date_i18n( get_option( 'date_format' ), strtotime( $row['event_date'] ) ) . "! 🎉" ); ?>" target="_blank" class="button button-small">
						💬 WhatsApp
					</a>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php endif; ?>
</div>
