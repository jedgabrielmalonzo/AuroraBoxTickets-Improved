function removeFromWishlist(parkId) {
            fetch('wishlist.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ action: 'remove', parkId: parkId }),
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'removed') {
                    // Optionally refresh the wishlist or remove the item from the DOM
                    location.reload(); // Reloads the page to reflect changes
                }
            });
        }
 