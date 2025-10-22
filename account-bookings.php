<div id="bookings" class="acc-card">
    <h3>Bookings</h3>
    <p class="acc-sub">Your recent activity bookings.</p>

    <?php
        // Setup pagination
        $bookings_per_page = 6;
        $booking_page = isset($_GET['booking_page']) ? max(1, intval($_GET['booking_page'])) : 1;
        $total_bookings = count($bookings);
        $booking_start = ($booking_page - 1) * $bookings_per_page;
        $paginated_bookings = array_slice($bookings, $booking_start, $bookings_per_page);
    ?>

    <?php if (empty($bookings)): ?>
        <p>No bookings found. <a href="home.php">Start exploring!</a></p>
    <?php else: ?>
        <div class="acc-list">
            <?php foreach ($paginated_bookings as $booking): ?>
                <div class="acc-item">
                    <img src="<?= htmlspecialchars($booking['park_picture'] ?? 'images/default-park.jpg') ?>" 
                         class="acc-thumb" alt="Booking">
                    <div class="acc-item-details">
                        <div class="acc-item-title">
                            <?= htmlspecialchars($booking['ticket_name'] ?? 'Park Ticket') ?> 
                            <?php if ($booking['park_name']): ?>
                                at <?= htmlspecialchars($booking['park_name']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="acc-item-sub">
                            Order #<?= $booking['id'] ?> – 
                            <span class="acc-booking-status status-<?= $booking['status'] ?>">
                                <?= ucfirst($booking['status']) ?>
                            </span>
                            <br>
                            <small>Booked on: <?= date('M j, Y', strtotime($booking['created_at'])) ?></small>
                            <?php if (!empty($booking['visit_date'])): ?>
                                <br><small>Visit Date: <?= date('M j, Y', strtotime($booking['visit_date'])) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div style="text-align: right;">
                        <div style="font-weight: 600;">
₱<?= number_format($booking['price']) ?>
                        </div>
                        <small>Qty: <?= $booking['quantity'] ?></small>

                        <?php if ($booking['status'] === 'confirmed'): ?>
                            <br>
                            <button type="button" 
                                    class="btn btn-danger btn-sm mt-2" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#refundModal"
                                    data-booking-id="<?= $booking['id'] ?>"
                                    data-booking-title="<?= htmlspecialchars($booking['ticket_name'] ?? 'Park Ticket') ?>"
                                    data-payment-ref="<?= htmlspecialchars($booking['payment_ref']) ?>">
                                Request Refund
                            </button>
                        <?php elseif ($booking['status'] === 'refunded'): ?>
                            <br><span class="badge bg-success mt-2">Refunded</span>
                        <?php elseif ($booking['status'] === 'refund_requested'): ?>
                            <br><span class="badge bg-warning mt-2">Refund Pending</span>
                        <?php elseif ($booking['status'] === 'refund_denied'): ?>
                            <br><span class="badge bg-danger mt-2">Refund Denied</span>
                        <?php elseif ($booking['status'] === 'refund_approved_pending'): ?>
                            <br><span class="badge bg-info mt-2">Refund Processing</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_bookings > $bookings_per_page): ?>
            <div class="pagination">
                <?php if ($booking_page > 1): ?>
                    <a href="?booking_page=<?= $booking_page - 1 ?>#bookings" class="page-link">Prev</a>
                <?php endif; ?>
                <span class="page-info">
                    Page <?= $booking_page ?> of <?= ceil($total_bookings / $bookings_per_page) ?>
                </span>
                <?php if ($booking_page * $bookings_per_page < $total_bookings): ?>
                    <a href="?booking_page=<?= $booking_page + 1 ?>#bookings" class="page-link">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Refund Request Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="refundModalLabel">Request Refund</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <form id="refundForm" method="POST" action="process_refund_request.php">
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Booking Details:</h6>
                        <p id="bookingDetails" class="text-muted"></p>
                    </div>

                    <div class="mb-3">
                        <label for="refundReason" class="form-label">
                            Reason for Refund <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="refundReason" name="refund_reason" required>
                            <option value="">Select a reason...</option>
                            <option value="event_cancelled">Event was cancelled</option>
                            <option value="unable_to_attend">Unable to attend</option>
                            <option value="emergency">Emergency situation</option>
                            <option value="weather_conditions">Bad weather conditions</option>
                            <option value="health_issues">Health issues</option>
                            <option value="change_of_plans">Change of plans</option>
                            <option value="duplicate_booking">Duplicate booking</option>
                            <option value="service_quality">Poor service quality</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="mb-3" id="otherReasonDiv" style="display: none;">
                        <label for="otherReason" class="form-label">Please specify:</label>
                        <textarea class="form-control" id="otherReason" name="other_reason" rows="3"
                            placeholder="Please provide details..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label for="additionalComments" class="form-label">
                            Additional Comments (Optional)
                        </label>
                        <textarea class="form-control" id="additionalComments" name="additional_comments" rows="3"
                            placeholder="Any additional information..."></textarea>
                    </div>

                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Note:</strong> Your refund request will be reviewed by our admin team. You will receive 
                        an email notification once the request is processed. Processing time is typically 3–5 business days.
                    </div>
                </div>

                <div class="modal-footer">
                    <input type="hidden" id="bookingId" name="booking_id">
                    <input type="hidden" id="paymentRef" name="payment_ref">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Refund Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Handle refund modal
document.addEventListener('DOMContentLoaded', function() {
    const refundModal = document.getElementById('refundModal');
    const refundReasonSelect = document.getElementById('refundReason');
    const otherReasonDiv = document.getElementById('otherReasonDiv');
    const otherReasonTextarea = document.getElementById('otherReason');

    // Show/hide "Other" reason textarea
    refundReasonSelect.addEventListener('change', function() {
        if (this.value === 'other') {
            otherReasonDiv.style.display = 'block';
            otherReasonTextarea.required = true;
        } else {
            otherReasonDiv.style.display = 'none';
            otherReasonTextarea.required = false;
        }
    });

    // Populate modal with booking data
    refundModal.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const bookingId = button.getAttribute('data-booking-id');
        const bookingTitle = button.getAttribute('data-booking-title');
        const paymentRef = button.getAttribute('data-payment-ref');

        document.getElementById('bookingId').value = bookingId;
        document.getElementById('paymentRef').value = paymentRef;
        document.getElementById('bookingDetails').textContent = `${bookingTitle} (Order #${bookingId})`;
    });

    // Reset form when modal is hidden
    refundModal.addEventListener('hidden.bs.modal', function() {
        document.getElementById('refundForm').reset();
        otherReasonDiv.style.display = 'none';
        otherReasonTextarea.required = false;
    });
});
</script>
