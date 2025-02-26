// public/js/cart.js

function updateQuantity(lineId, change) {
  fetch(`/cart/update/${lineId}`, {
      method: 'POST',
      headers: {
          'X-CSRF-TOKEN': '{{ csrf_token('update') }}',
          'Accept': 'application/json',
          'Content-Type': 'application/json'
      },
      body: JSON.stringify({ change: change })
  })
  .then(response => {
      if (response.ok) {
          return response.json();
      } else {
          return response.json().then(data => {
              throw new Error(data.error || 'An error occurred while updating the quantity.');
          });
      }
  })
  .then(data => {
      // Update the quantity and total for the specific line
      document.getElementById(`quantity-${lineId}`).innerText = data.quantity;
      document.getElementById(`total-${lineId}`).innerText = `${data.lineTotal} Dt`;

      // Update the cart total
      document.getElementById('cart-total').innerText = `${data.cartTotal} Dt`;

      // Update the cart count in the icon
      document.getElementById('cart-count').innerText = data.cartCount;
  })
  .catch(error => {
      console.error('Error:', error);
      alert(error.message);
  });
}

function removeFromCart(lineId) {
  if (!confirm('Are you sure you want to remove this item from your cart?')) {
      return;
  }

  fetch(`/cart/remove/${lineId}`, {
      method: 'DELETE',
      headers: {
          'X-CSRF-TOKEN': '{{ csrf_token('delete') }}',
          'Accept': 'application/json',
          'Content-Type': 'application/json'
      }
  })
  .then(response => {
      if (response.ok) {
          // Reload the cart content in the modal
          fetchCartContent();
      } else {
          return response.json().then(data => {
              throw new Error(data.error || 'An error occurred while removing the item.');
          });
      }
  })
  .catch(error => {
      console.error('Error:', error);
      alert(error.message);
  });
}

function fetchCartContent() {
  fetch('/cart/content')
  .then(response => response.text())
  .then(html => {
      document.getElementById('cart-content').innerHTML = html;
  })
  .catch(error => {
      console.error('Error:', error);
  });
}

// Fetch cart content when the modal is opened
document.getElementById('cartModal').addEventListener('show.bs.modal', fetchCartContent);