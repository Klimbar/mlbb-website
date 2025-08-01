document.addEventListener("DOMContentLoaded", function () {
  // DOM Elements
  const productsContainer = document.getElementById("products");
  const playerForm = document.getElementById("playerForm");
  const verifyBtn = document.getElementById("verifyBtn");
  const playerInfo = document.getElementById("playerInfo");
  const paymentSection = document.getElementById("paymentSection");
  const selectedProductDiv = document.getElementById("selectedProduct");
  const payNowBtn = document.getElementById("payNowBtn");
  const paymentMethodButtons = document.getElementById("paymentMethodButtons");
  const orderStatus = document.getElementById("orderStatus");
  const paymentErrorStatus = document.getElementById("paymentErrorStatus");
  const useLastBtn = document.getElementById("useLastBtn");
  const playerVerificationError = document.getElementById("playerVerificationError");

  // Global variables
  let selectedProduct = null;
  let playerVerified = false;
  let playerData = null;
  let selectedPaymentMethod = "pay0"; // Set default payment method
  let allProducts = []; // Store all products fetched from the API

  // Load products on page load
  loadProducts();

  // Add event listeners to category buttons
  document.querySelectorAll('.btn-group button').forEach(button => {
    button.addEventListener('click', function() {
      // Remove active class from all buttons
      document.querySelectorAll('.btn-group button').forEach(btn => btn.classList.remove('active'));
      // Add active class to the clicked button
      this.classList.add('active');
      const category = this.dataset.category;
      filterAndDisplayProducts(category);
    });
  });

  // Load available products
  async function loadProducts() {
    try {
      const response = await fetch(`${BASE_URL}/api.php?action=getProducts`);
      

      if (!response.ok) {
        throw new Error(
          `Network response was not ok: ${response.status} ${response.statusText}`
        );
      }

      const data = await response.json();
      console.log('API Data:', data);

      if (data.status === 200) {
        allProducts = data.data.product || []; // Store all products
        filterAndDisplayProducts('diamonds'); // Display diamonds products initially
      } else {
        showError(
          "Failed to load products: " + (data.message || "Unknown API error")
        );
      }
    } catch (error) {
      showError("Error loading products: " + error.message);
    }
  }

  // Define product IDs for each category
  const doubleDiamondsIds = [22590, 22591, 22592, 22593];
  const regularDiamondsIds = [13, 23, 25, 26, 27, 28, 29, 30];
  const weeklyPassId = 16642;
  const twilightPassId = 33;

  // Filter and display products based on category
  function filterAndDisplayProducts(category) {
    let productsToDisplay = allProducts.filter(product => {
      const productId = parseInt(product.id); // Ensure product.id is a number
      switch (category) {
        case 'double_diamonds':
          return doubleDiamondsIds.includes(productId);
        case 'diamonds':
          return regularDiamondsIds.includes(productId);
        case 'weekly_pass':
          return productId === weeklyPassId;
        case 'twilight_pass':
          return productId === twilightPassId;
        default:
          return false; // Should not happen with current button setup
      }
    });
    displayProducts(productsToDisplay);
  }

  // Display products in the grid
  function displayProducts(products) {
    productsContainer.innerHTML = "";

    if (products.length === 0) {
      productsContainer.innerHTML =
        "<p>No diamond packages are available for this category.</p>";
      return;
    }

    products.forEach((product) => {
      const productCard = document.createElement("div");
      productCard.className = "col";
      productCard.innerHTML = `
        <div class="card h-100 product-card">
          <div class="card-body">
            <h5 class="card-title">${product.spu}</h5>
            <p class="card-text">₹${product.price}</p>
          </div>
        </div>
      `;

      const cardElement = productCard.querySelector('.product-card');

      productCard.addEventListener("click", () => {
        // Remove .selected from all other cards
        document.querySelectorAll(".product-card").forEach((card) => {
          card.classList.remove("selected");
        });
        // Add .selected to the clicked card
        cardElement.classList.add("selected");
        selectedProduct = product;

        if (selectedProductDiv) {
          selectedProductDiv.innerHTML = ''; // Clear immediately
        }

        // Always scroll to player details section when a product is selected
        window.scrollTo({ top: 0, behavior: 'smooth' });

        if (playerVerified) {
          showPaymentSection();
        }
      });

      productsContainer.appendChild(productCard);
    });
  }

  // Verify player details

  if (verifyBtn) {
    verifyBtn.addEventListener("click", async function () {
      const userid = document.getElementById("userid").value;
      const zoneid = document.getElementById("zoneid").value;


      if (!userid || !zoneid) {
        playerVerificationError.textContent = "Please enter both Player ID and Zone ID";
        playerVerificationError.classList.remove("hidden");
        return;
      }

      if (!window.isLoggedIn) {
        playerVerificationError.textContent = "Please login to purchase diamonds.";
        playerVerificationError.classList.remove("hidden");
        return; // Stop execution if the user is not logged in
      }

      playerInfo.innerHTML = `<p class="ign-display">IGN: Loading username...</p>`;
      playerVerificationError.classList.add("hidden"); // Hide previous error if any
      

      try {
        const response = await fetch(`${BASE_URL}/api.php?action=verifyPlayer`, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            userid,
            zoneid,
            productid: selectedProduct ? selectedProduct.id : '22590', // Use selected product ID or default
          }),
        });

        const data = await response.json();

        if (data.status === 200) {
          playerVerified = true;
          playerData = {
            userid,
            zoneid,
            username: data.username,
            zone: data.zone,
          };

          // Save to cookies for persistence (30 days expiration)
          const d = new Date();
          d.setTime(d.getTime() + (30 * 24 * 60 * 60 * 1000));
          const expires = "expires=" + d.toUTCString();
          document.cookie = `last_player_id=${userid}; ${expires}; path=/`;
          document.cookie = `last_zone_id=${zoneid}; ${expires}; path=/`;

          playerInfo.innerHTML = `<p class="ign-display">IGN: ${data.username}</p>`;
          playerVerificationError.classList.add("hidden"); // Hide error on success
          // Store the price multiplier
          playerData.price_multiplier = data.change_price || 1;

          if (selectedProduct && window.isLoggedIn) {
            showPaymentSection();
          }
        } else {
          playerVerificationError.textContent = "Player Not Found";
          playerVerificationError.classList.remove("hidden");
          playerInfo.innerHTML = ''; // Clear on error
        }
      } catch (error) {
        playerVerificationError.textContent = "Error verifying player: Please try again later.";
        playerVerificationError.classList.remove("hidden");
        playerInfo.innerHTML = ''; // Clear on error
      }
    });
  }

  // Show payment section
  async function showPaymentSection() {
    if (!window.isLoggedIn) {
        return;
    }
    paymentSection.classList.remove("d-none");
    paymentSection.scrollIntoView({ behavior: 'smooth', block: 'start' });

    selectedProductDiv.innerHTML = '<p>Loading details...</p>'; // Show loading message

    // Re-verify player with the currently selected product to get accurate price adjustment
    try {
      const response = await fetch(`${BASE_URL}/api.php?action=verifyPlayer`, {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: new URLSearchParams({
          userid: playerData.userid,
          zoneid: playerData.zoneid,
          productid: selectedProduct.id,
        }),
      });

      const data = await response.json();

      if (data.status === 200) {
        playerData.price_multiplier = data.change_price || 1; // Update multiplier
      } else {
        // Log error but don't prevent display, use default multiplier
        console.error("Failed to get updated price adjustment: " + data.message);
        playerData.price_multiplier = 1;
      }
    } catch (error) {
      console.error("Error fetching updated price adjustment: " + error.message);
      playerData.price_multiplier = 1;
    }

    
  const finalPrice =
      selectedProduct.price * (playerData.price_multiplier || 1);
    selectedProductDiv.innerHTML = `
            <h3>Order Summary</h3>
            <p><strong>Player ID:</strong> ${playerData.userid}</p>
            <p><strong>Zone ID:</strong> ${playerData.zoneid}</p>
            <p><strong>IGN:</strong> ${playerData.username}</p>
            <hr>
            <h4>Selected Package</h4>
            <p>${selectedProduct.spu}</p>
            <p><strong>Price: ₹${finalPrice.toFixed(2)}</strong></p>
        `;
    payNowBtn.innerHTML = `Pay Now (₹${finalPrice.toFixed(2)})`;
  }

  // Handle payment method selection
  // Removed payment method selection as per user request

  // Process payment

  if (payNowBtn) {
    payNowBtn.addEventListener("click", async function () {
      // Use the value from the selected button
      const paymentMethod = selectedPaymentMethod;

      if (!selectedProduct || !playerVerified) {
        showPaymentError("Please complete all steps first");
        return;
      }

      if (!window.isLoggedIn) {
        showPaymentError("Please login to purchase diamonds.");
        return;
      }

      if (!paymentMethod) {
        // This now checks if a button has been clicked
        showPaymentError("Please select a payment method.");
        return;
      }

      payNowBtn.disabled = true;
      payNowBtn.textContent = "Processing...";
try {
        const response = await fetch(`${BASE_URL}/payments/process.php`, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            productid: selectedProduct.id, // API uses 'id' for productid
            userid: playerData.userid,
            zoneid: playerData.zoneid,
          }),
        });

        const data = await response.json();

        if (data.status === "success" && data.payment_url) {
          // Redirect to payment gateway
          window.location.href = data.payment_url;
        } else {
          showPaymentError("Failed to initiate payment: " + data.message);
          payNowBtn.disabled = false;
          payNowBtn.innerHTML = `Pay Now (₹${finalPrice.toFixed(2)})`;
        }
      } catch (error) {
        showPaymentError("Error creating order: " + error.message);
        // Ensure the button is re-enabled even if the fetch itself fails
        payNowBtn.disabled = false;
        payNowBtn.innerHTML = `Pay Now (₹${finalPrice.toFixed(2)})`;
      }
    });
  }

  if (useLastBtn) {
    useLastBtn.addEventListener("click", function() {
      document.getElementById("userid").value = window.lastPlayerId;
      document.getElementById("zoneid").value = window.lastZoneId;
    });
  }

  // Show error message for player verification and general errors
  function showError(message) {
    orderStatus.innerHTML = `<p>Error: ${message}</p>`;
    orderStatus.classList.remove("hidden");
    orderStatus.classList.add("error");
    // For better UX, automatically hide the error message after a few seconds
    setTimeout(() => {
      orderStatus.classList.add("hidden");
      orderStatus.innerHTML = "";
    }, 5000); // Hides after 5 seconds
  }

  // Show error message for payment-related errors
  function showPaymentError(message) {
    paymentErrorStatus.innerHTML = `<p>Error: ${message}</p>`;
    paymentErrorStatus.classList.remove("hidden");
    paymentErrorStatus.classList.add("error");
    // For better UX, automatically hide the error message after a few seconds
    setTimeout(() => {
      paymentErrorStatus.classList.add("hidden");
      paymentErrorStatus.innerHTML = "";
    }, 5000); // Hides after 5 seconds
  }
});
