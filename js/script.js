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
  const useridInput = document.getElementById("userid");
  const zoneidInput = document.getElementById("zoneid");
  const pageTitle = document.getElementById("page-title");

  // Global variables
  let selectedProduct = null;
  let playerVerified = false;
  let playerData = null;
  let selectedPaymentMethod = "pay0"; // Set default payment method
  let allProducts = []; // Store all products fetched from the API
  let finalPrice = 0; // Declare finalPrice globally

  // Helper function to escape HTML and prevent XSS attacks
  const escapeHTML = (str) => {
    const p = document.createElement('p');
    p.textContent = str;
    return p.innerHTML;
  };

  // Function to restrict input to numbers
  const sanitizeNumericInput = (event) => {
    // Replaces any character that is not a digit with an empty string.
    // This allows pasting numbers and cleans up any non-numeric characters automatically.
    event.target.value = event.target.value.replace(/[^0-9]/g, '');
  };

  // Attach event listeners for number restriction
  if (useridInput) {
    useridInput.addEventListener('input', sanitizeNumericInput);
  }

  if (zoneidInput) {
    zoneidInput.addEventListener('input', sanitizeNumericInput);
  }

  // Load products on page load
  loadProducts();

  // Category data with images
  const categories = [
    { id: 'diamonds', name: 'Diamonds', image: 'diamond.webp' },
    { id: 'weekly_pass', name: 'Weekly Pass', image: 'weekly_pass.webp' },
    { id: 'twilight_pass', name: 'Twilight Pass', image: 'twilight_pass.jpg' },
    { id: 'double_diamonds', name: 'First Recharge', image: 'first_recharge.png' }
  ];

  // Display category cards
  function displayCategories() {
    const categoryContainer = document.getElementById('category-cards-container');
    if (!categoryContainer) return;

    categoryContainer.innerHTML = ''; // Clear existing content

    categories.forEach(category => {
      const categoryCard = document.createElement('div');
      categoryCard.className = 'col';
      categoryCard.innerHTML = `
        <div class="card h-100 category-card" data-category="${category.id}">
          <img src="${BASE_URL}/assets/${category.image}" class="card-img-top category-card-img" alt="${category.name}">
          <div class="card-body">
            <h5 class="card-title category-card-title">${category.name}</h5>
          </div>
        </div>
      `;
      categoryContainer.appendChild(categoryCard);

      categoryCard.addEventListener('click', () => {
        // Remove 'selected' class from all category cards
        document.querySelectorAll('.category-card').forEach(card => {
          card.classList.remove('selected');
        });
        // Add 'selected' class to the clicked card
        categoryCard.querySelector('.category-card').classList.add('selected');
        filterAndDisplayProducts(category.id);
      });
    });

    // Select the first category by default
    if (categories.length > 0) {
      const firstCategoryCard = categoryContainer.querySelector('.category-card');
      if (firstCategoryCard) {
        firstCategoryCard.classList.add('selected');
      }
    }
  }

  // Call displayCategories after products are loaded
  loadProducts().then(() => {
    displayCategories();
  });

  // Highlight active navbar link
  const currentPath = window.location.pathname;

  // Normalize path: remove trailing slash unless it's the root path
  const normalizePath = (path) => {
    if (path.length > 1 && path.endsWith('/')) {
      return path.slice(0, -1);
    }
    return path;
  };

  const normalizedCurrentPath = normalizePath(currentPath);

  document.querySelectorAll('.navbar-nav .nav-link').forEach(link => {
    const linkPath = new URL(link.href).pathname;
    const normalizedLinkPath = normalizePath(linkPath);

    link.classList.remove('active'); // Clear all active classes first

    // Exact match after normalization
    if (normalizedLinkPath === normalizedCurrentPath) {
      link.classList.add('active');
      return;
    }

    // Check for section match (e.g., /admin matches /admin/dashboard.php)
    // Ensure the linkPath is a prefix and the next character is a '/'
    if (normalizedCurrentPath.startsWith(normalizedLinkPath + '/')) {
      link.classList.add('active');
      return;
    }
  });

  // Load available products
  async function loadProducts() {
    try {
      const response = await fetch(`${BASE_URL}/api?action=getProducts`);
      

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

  // Mapping of product IDs to image filenames
  const productImageMap = {
    22590: 'double_diamonds.avif',
    22591: 'double_diamonds.avif',
    22592: 'double_diamonds.avif',
    22593: 'double_diamonds.avif',
    33: 'twilight_pass.jpg',
    16642: 'weekly_pass.webp',
    13: 'few_diamonds.webp',
    23: 'few_diamonds.webp',
    25: 'many_diamonds.webp',
    26: 'many_diamonds.webp',
    27: 'chest.webp',
    28: 'chest.webp',
    29: 'large_chest.webp',
    30: 'large_chest.webp'
  };

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
      
      const isOutOfStock = product.is_out_of_stock == 1; // Assuming 1 for true, 0 for false
      const outOfStockClass = isOutOfStock ? 'out-of-stock' : '';
      const outOfStockText = isOutOfStock ? '<div class="out-of-stock-overlay">Out of Stock</div>' : '';
      
      const imageUrl = product.image ? `${BASE_URL}/${product.image}` : `${BASE_URL}/assets/mlbb_card.webp`;
      const imageTag = imageUrl ? `<img src="${imageUrl}" class="card-img-top" alt="${product.spu}">` : '';
      const productDescription = product.description || 'Diamond Pack';

      productCard.innerHTML = `
        <div class="card h-100 product-card ${outOfStockClass}">
          ${imageTag}
          <div class="card-body">
            <h5 class="card-title mb-0">${product.spu}</h5>
            <p class="card-text product-subtitle mb-0">${productDescription}</p>
            <p class="card-text product-price mb-0">₹${product.price}</p>
          </div>
          ${outOfStockText}
        </div>
      `;

      const cardElement = productCard.querySelector('.product-card');

      if (!isOutOfStock) {
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

          // Set Pay Now button to loading state immediately
          if (payNowBtn) {
            payNowBtn.disabled = true;
            payNowBtn.textContent = "Loading...";
          }

          // Always scroll to player details section when a product is selected
          pageTitle.scrollIntoView({ behavior: 'smooth', block: 'start' });

          if (playerVerified) {
            showPaymentSection();
          }
        });
      } else {
        // Optionally, make out-of-stock cards visually unclickable
        cardElement.style.cursor = 'not-allowed';
      }

      productsContainer.appendChild(productCard);
    });
  }

  // Verify player details

  if (verifyBtn) {
    verifyBtn.addEventListener("click", async function () {

      const userid = useridInput.value;
      const zoneid = zoneidInput.value;


      if (!userid || !zoneid) {
        playerVerificationError.textContent = "Please enter both Player ID and Zone ID";
        playerVerificationError.classList.remove("hidden");
        return;
      }

      // Validate that userid and zoneid are numbers
      if (isNaN(parseInt(userid)) || !/^[0-9]+$/.test(userid)) {
        playerVerificationError.textContent = "Player ID must be a number.";
        playerVerificationError.classList.remove("hidden");
        return;
      }

      if (isNaN(parseInt(zoneid)) || !/^[0-9]+$/.test(zoneid)) {
        playerVerificationError.textContent = "Zone ID must be a number.";
        playerVerificationError.classList.remove("hidden");
        return;
      }


      playerInfo.innerHTML = `<p class="ign-display">IGN: Loading username...</p>`;
      playerVerificationError.classList.add("hidden"); // Hide previous error if any
      

      try {
        const response = await fetch(`${BASE_URL}/api?action=verifyPlayer`, {
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

          playerInfo.innerHTML = `<p class="ign-display">IGN: ${escapeHTML(data.username)}</p>`;
          playerVerificationError.classList.add("hidden"); // Hide error on success
          // Store the price multiplier
          playerData.price_multiplier = data.change_price || 1;

          if (selectedProduct && window.isLoggedIn) {
            showPaymentSection();
          } else if (!window.isLoggedIn && playerInfo) {
            playerInfo.innerHTML += `<p class="error">Please <a href="${escapeHTML(BASE_URL)}/auth/login">login</a> to purchase diamonds.</p>`;
          }
        } else {
          playerVerificationError.textContent = escapeHTML(data.message || "Player Not Found");
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
    payNowBtn.disabled = true; // Disable button during loading
    payNowBtn.textContent = "Loading..."; // Set button text to loading

    let finalPrice = 0; // Initialize finalPrice

    try {
      // Use Promise.all to ensure a minimum loading time
      const [response, ] = await Promise.all([
        fetch(`${BASE_URL}/api?action=verifyPlayer`, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams({
            userid: playerData.userid,
            zoneid: playerData.zoneid,
            productid: selectedProduct.id,
          }),
        }),
        new Promise(resolve => setTimeout(resolve, 500)) // Minimum 500ms loading time
      ]);

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

    // Calculate final price after fetching adjustment (or on error)
    if (!selectedProduct || !playerData) {
        console.error("selectedProduct or playerData is undefined in showPaymentSection.");
        return; // Exit if essential data is missing
    }
    finalPrice = selectedProduct.price * (playerData.price_multiplier || 1);

    selectedProductDiv.innerHTML = `
            <h3>Order Summary</h3>
            <p><strong>Player ID:</strong> ${escapeHTML(playerData.userid)}</p>
            <p><strong>Zone ID:</strong> ${escapeHTML(playerData.zoneid)}</p>
            <p><strong>IGN:</strong> ${escapeHTML(playerData.username)}</p>
            <hr>
            <h4>Selected Package</h4>
            <p>${escapeHTML(selectedProduct.spu)}</p>
            <p><strong>Price: ₹${escapeHTML((finalPrice || 0).toFixed(2))}</strong></p>
        `;
    payNowBtn.disabled = false; // Re-enable button
    payNowBtn.innerHTML = `Pay Now (₹${(finalPrice || 0).toFixed(2)})`; // Set button text with price
  }

  // Handle payment method selection
  // Removed payment method selection as per user request

  // Process payment

  if (payNowBtn) {
    payNowBtn.addEventListener("click", async function () {
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

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
        const bodyParams = {
          productid: selectedProduct.id, // API uses 'id' for productid
          userid: playerData.userid,
          zoneid: playerData.zoneid,
        };
        if (csrfToken) {
          bodyParams.csrf_token = csrfToken;
        }
        const response = await fetch(`${BASE_URL}/payments/process`, {
          method: "POST",
          headers: {
            "Content-Type": "application/x-www-form-urlencoded",
          },
          body: new URLSearchParams(bodyParams),
        });

        const data = await response.json();

        if (data.status === "success" && data.payment_url) {
          // Redirect to payment gateway
          window.location.href = data.payment_url;
        } else {
          // If it's a CSRF token error, refresh the token
          if (data.message && data.message.includes("security token")) {
            try {
              const tokenResponse = await fetch(`${BASE_URL}/auth/refresh_csrf.php`);
              const tokenData = await tokenResponse.json();
              if (tokenData.new_token) {
                document.querySelector('meta[name="csrf-token"]').content = tokenData.new_token;
                // Update the hidden input field if it exists
                const csrfInput = document.querySelector('input[name="csrf_token"]');
                if (csrfInput) {
                  csrfInput.value = tokenData.new_token;
                }
              }
            } catch (tokenError) {
              console.error("Failed to refresh CSRF token:", tokenError);
            }
          }
          showPaymentError("Failed to initiate payment: " + data.message);
          payNowBtn.disabled = false;
          payNowBtn.textContent = "Pay Now";
        }
      } catch (error) {
        showPaymentError("Error creating order: " + error.message);
        // Ensure the button is re-enabled even if the fetch itself fails
        payNowBtn.disabled = false;
        payNowBtn.textContent = "Pay Now";
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
    if (orderStatus) {
      orderStatus.innerHTML = `<p>Error: ${escapeHTML(message)}</p>`;
      orderStatus.classList.remove("hidden");
      orderStatus.classList.add("error");
      // For better UX, automatically hide the error message after a few seconds
      setTimeout(() => {
        orderStatus.classList.add("hidden");
        orderStatus.innerHTML = "";
      }, 5000); // Hides after 5 seconds
    } else {
      console.error("Error: orderStatus element not found.", message);
    }
  }

  // Show error message for payment-related errors
  function showPaymentError(message) {
    if (paymentErrorStatus) {
      paymentErrorStatus.innerHTML = `<p>Error: ${escapeHTML(message)}</p>`;
      paymentErrorStatus.classList.remove("hidden");
      paymentErrorStatus.classList.add("error");
      // For better UX, automatically hide the error message after a few seconds
      setTimeout(() => {
        paymentErrorStatus.classList.add("hidden");
        paymentErrorStatus.innerHTML = "";
      }, 5000); // Hides after 5 seconds
    } else {
      console.error("Error: paymentErrorStatus element not found.", message);
    }
  }
});

// Initialize Bootstrap Carousel
const myCarousel = document.querySelector('#carouselExampleIndicators');
if (myCarousel) {
  const carousel = new bootstrap.Carousel(myCarousel, {
    interval: 5000, // Auto-scroll every 5 seconds
    wrap: true // Continue scrolling from the last item to the first
  });
}