<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.request-refund-btn').forEach(button => {
        button.addEventListener('click', function() {
            const bookingId = this.getAttribute('data-booking-id');
            document.getElementById('bookingId').value = bookingId;
            const refundModal = new bootstrap.Modal(document.getElementById('refundModal'));
            refundModal.show();
        });
    });

    document.getElementById('submitRefund').addEventListener('click', () => {
        const form = document.getElementById('refundForm');
        const formData = new FormData(form);

        fetch('process_refund.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Refund request submitted successfully!');
                window.location.reload(); // Reload the page to reflect changes
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => console.error('Error:', error));
    });
});
</script>