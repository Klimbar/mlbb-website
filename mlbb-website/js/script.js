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

  // Global variables
  let selectedProduct = null;
  let playerVerified = false;
  let playerData = null;
  let selectedPaymentMethod = null;

  // Load products on page load
  loadProducts();

  // Load available products
  async function loadProducts() {
    try {
      const response = await fetch("api.php?action=getProducts");

      if (!response.ok) {
        // Handles HTTP errors like 404, 500, 502 etc.
        throw new Error(
          `Network response was not ok: ${response.status} ${response.statusText}`
        );
      }

      const data = await response.json();

      if (data.status === 200) {
        // Per API docs, products are in data.data.product
        displayProducts(data.data.product || []);
      } else {
        // Handles application-level errors from our API
        showError(
          "Failed to load products: " + (data.message || "Unknown API error")
        );
      }
    } catch (error) {
      // Handles fetch errors (e.g., network down) or errors thrown from response.ok check
      showError("Error loading products: " + error.message);
    }
  }

  // Display products in the grid
  function displayProducts(products) {
    productsContainer.innerHTML = "";

    if (products.length === 0) {
      productsContainer.innerHTML =
        "<p>No diamond packages are available at this time.</p>";
      return;
    }

    products.forEach((product) => {
      const productCard = document.createElement("div");
      productCard.className = "product-card";
      productCard.innerHTML = `
                <h3>${product.spu}</h3>
                <p>Price: $${product.price}</p>
            `;

      productCard.addEventListener("click", () => {
        document.querySelectorAll(".product-card").forEach((card) => {
          card.classList.remove("selected");
        });
        productCard.classList.add("selected");
        selectedProduct = product;

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
        showError("Please enter both Player ID and Zone ID");
        return;
      }

      if (!selectedProduct) {
        showError("Please select a diamond package first");
        return;
      }

      try {
        const response = await fetch("api.php?action=verifyPlayer", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            userid,
            zoneid,
            productid: selectedProduct.id, // API uses 'id' for productid
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

          playerInfo.innerHTML = `
                        <p>Player: ${data.username}</p>
                        <p>Zone: ${data.zone}</p>
                        <p>Price Adjustment: ${data.change_price}x</p>
                    `;
          // Store the price multiplier
          playerData.price_multiplier = data.change_price || 1;

          playerInfo.classList.remove("hidden");

          if (selectedProduct) {
            showPaymentSection();
          }
        } else {
          showError("Player verification failed: " + data.message);
        }
      } catch (error) {
        showError("Error verifying player: " + error.message);
      }
    });
  }

  // Show payment section
  function showPaymentSection() {
    const finalPrice =
      selectedProduct.price * (playerData.price_multiplier || 1);
    selectedProductDiv.innerHTML = `
            <h3>Selected Package</h3>
            <p>${selectedProduct.spu}</p>
            <p>Base Price: $${selectedProduct.price}</p>
            <p><strong>Final Price: $${finalPrice.toFixed(2)}</strong></p>
        `;
    paymentSection.classList.remove("hidden");
  }

  // Handle payment method selection
  if (paymentMethodButtons) {
    paymentMethodButtons.addEventListener("click", (event) => {
      const target = event.target.closest(".payment-method-btn");
      if (!target) return;

      // Remove selected class from all buttons
      document.querySelectorAll(".payment-method-btn").forEach((btn) => {
        btn.classList.remove("selected");
      });

      // Add selected class to the clicked button
      target.classList.add("selected");
      selectedPaymentMethod = target.dataset.value;
    });
  }

  // Process payment

  if (payNowBtn) {
    payNowBtn.addEventListener("click", async function () {
      // Use the value from the selected button
      const paymentMethod = selectedPaymentMethod;

      if (!selectedProduct || !playerVerified) {
        showError("Please complete all steps first");
        return;
      }

      if (!paymentMethod) {
        // This now checks if a button has been clicked
        showError("Please select a payment method.");
        return;
      }

      payNowBtn.disabled = true;
      payNowBtn.textContent = "Processing...";
try {
        const response = await fetch("/payments/process.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            productid: selectedProduct.id, // API uses 'id' for productid
            userid: playerData.userid,
            zoneid: playerData.zoneid,
            payment_method: paymentMethod,
          }),
        });

        const data = await response.json();

        if (data.status === "success" && data.payment_url) {
          // Redirect to payment gateway
          window.location.href = data.payment_url;
        } else {
          showError("Failed to initiate payment: " + data.message);
          payNowBtn.disabled = false;
          payNowBtn.textContent = "Pay Now";
        }
      } catch (error) {
        showError("Error creating order: " + error.message);
        // Ensure the button is re-enabled even if the fetch itself fails
        payNowBtn.disabled = false;
        payNowBtn.textContent = "Pay Now";
      }
    });
  }

  // Show error message
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
});
