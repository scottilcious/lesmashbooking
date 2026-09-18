<?php
$booking_today = new DateTime('today');
$booking_today_value = $booking_today->format('Y-m-d');
$booking_max_value = (clone $booking_today)->modify('+7 days')->format('Y-m-d');
$is_admin_booking_view = isset($memberType) && $memberType === 'admin';
?>
<div class="mb-3">
    <label for="date" class="fs-4">Select date</label>
    <?php if ($is_admin_booking_view): ?>
        <div class="input-group mb-3">
            <input
                type="text"
                class="form-control"
                id="date"
                name="date"
                value="<?= htmlspecialchars($booking_today_value) ?>"
                data-booking-min-date="<?= htmlspecialchars($booking_today_value) ?>"
                data-booking-max-date="<?= htmlspecialchars($booking_max_value) ?>"
                autocomplete="off"
                aria-describedby="checkAvailability"
                required
            >
        </div>
    <?php else: ?>
        <div class="input-group mb-3">
            <input
                type="date"
                class="form-control"
                id="date"
                name="date"
                value="<?= htmlspecialchars($booking_today_value) ?>"
                data-booking-min-date="<?= htmlspecialchars($booking_today_value) ?>"
                data-booking-max-date="<?= htmlspecialchars($booking_max_value) ?>"
                min="<?= htmlspecialchars($booking_today_value) ?>"
                max="<?= htmlspecialchars($booking_max_value) ?>"
                autocomplete="off"
                aria-describedby="checkAvailability"
                required
            >
        </div>
    <?php endif; ?>
</div>
