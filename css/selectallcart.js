
  const selectAll = document.getElementById("select-all");
  const itemCheckboxes = document.querySelectorAll(".item-checkbox");

  selectAll.addEventListener("change", () => {
    itemCheckboxes.forEach(cb => cb.checked = selectAll.checked);
    updateSubtotal(); // Update subtotal when select-all is changed
  });

  itemCheckboxes.forEach(cb => {
    cb.addEventListener("change", updateSubtotal);
  });

  function changeQty(button, change) {
    const qtySpan = button.parentElement.querySelector('.qty');
    let currentValue = parseInt(qtySpan.innerText);
    currentValue += change;

    // Limit quantity to a maximum of 10
    if (currentValue > 10) {
        currentValue = 10; 
    }

    if (currentValue < 1) currentValue = 1; 
    qtySpan.innerText = currentValue; 
    
    // Update the price for the specific item
    const cartItem = qtySpan.closest('.cart-item');
    const unitPrice = parseFloat(cartItem.dataset.unitPrice); // Get unit price
    const newPrice = unitPrice * currentValue; // Calculate new total price for this item
    cartItem.querySelector('.cart-price').innerText = '₱ ' + newPrice.toLocaleString(); // Update the displayed price

    updateQuantity(cartItem.querySelector('input[name="cart_id"]').value, currentValue); // Update quantity in DB
    updateSubtotal(); // Update overall subtotal
}

  function updateQuantity(cartId, newQuantity) {
    fetch('cart.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ action: 'update_quantity', cart_id: cartId, quantity: newQuantity })
    }).then(response => response.json())
      .then(data => {
          if (!data.success) {
              console.error("Error updating quantity: " + data.error);
          }
      });
  }

  function updateSubtotal() {
    let subtotal = 0;
    let hasCheckedItems = false;

    document.querySelectorAll('.cart-item').forEach(item => {
        const qty = parseInt(item.querySelector('.qty').innerText);
        const unitPrice = parseFloat(item.dataset.unitPrice); // Get unit price
        const price = unitPrice * qty; // Calculate total price for that cart item
        const isChecked = item.querySelector('.item-checkbox').checked;

        if (isChecked) {
            subtotal += price; // Add to subtotal only if checked
            hasCheckedItems = true;
        }
    });

    document.getElementById('subtotal').innerText = hasCheckedItems ? '₱ ' + subtotal.toLocaleString() : '₱ 0.00';
}

  function removeSelectedItems() {
    const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
    const cartIds = [];

    selectedCheckboxes.forEach(checkbox => {
        const cartItem = checkbox.closest('.cart-item');
        const cartId = cartItem.querySelector('input[name="cart_id"]').value;
        cartIds.push(cartId);
    });

    if (cartIds.length > 0) {
        fetch('cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ action: 'remove_multiple', cart_ids: cartIds })
        }).then(response => response.json())
          .then(data => {
              if (data.success) {
                  alert("Selected items removed successfully.");
                  location.reload(); // Reload the page to see the updated cart
              } else {
                  alert("Error removing items: " + data.error);
              }
          }).catch(error => {
              console.error("Fetch error: ", error);
              alert("An error occurred while removing items.");
          });
    } else {
        alert("Please select at least one item to remove.");
    }
  }

  // Handle Book now button
  function bookNow() {
    const selectedCheckboxes = document.querySelectorAll('.item-checkbox:checked');
    const selectedItems = [];

    selectedCheckboxes.forEach(checkbox => {
        const cartItem = checkbox.closest('.cart-item');
        const cartId = cartItem.querySelector('input[name="cart_id"]').value;
        selectedItems.push(cartId);
    });

    if (selectedItems.length === 0) {
        alert('Please select at least one item to proceed with booking.');
        return;
    }

    // Create a form to submit selected items to checkout
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = 'checkout.php';
    form.style.display = 'none'; // Hide the form

    selectedItems.forEach(cartId => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'selected_items[]';
        input.value = cartId;
        form.appendChild(input);
    });

    document.body.appendChild(form);
    form.submit();
  }
