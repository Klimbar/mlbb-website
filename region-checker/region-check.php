<?php
require_once __DIR__ . '/../bootstrap.php';
?>
<style>
    .card {
        width: 100%;
        max-width: 500px;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .spinner-border {
        width: 1.5rem;
        height: 1.5rem;
        margin-right: 0.5rem;
    }

    /* Hide number input arrows/spinners */
    /* For Chrome, Safari, Edge, Opera */
    input::-webkit-outer-spin-button,
    input::-webkit-inner-spin-button {
        -webkit-appearance: none;
        margin: 0;
    }
    /* For Firefox */
    input[type=number] {
        -moz-appearance: textfield;
    }
</style>
<div class="container main-content">
    <div class="card p-4 mx-auto">
        <h1 class="card-title text-center mb-4">MLBB Region Checker</h1>
        <form id="region-form">
            <div class="mb-3">
                <label for="user-id" class="form-label">User ID</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" id="user-id" class="form-control" required minlength="2" maxlength="15">
            </div>
            <div class="mb-3">
                <label for="zone-id" class="form-label">Zone ID</label>
                <input type="text" inputmode="numeric" pattern="[0-9]*" id="zone-id" class="form-control" required minlength="1" maxlength="5">
            </div>
            <button type="submit" class="btn btn-primary w-100" id="submit-button">
                <span id="button-text">Check Region</span>
                <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="loading-spinner"></span>
            </button>
        </form>
        <div id="result" class="mt-4"></div>
    </div>
</div>

<script nonce="<?= htmlspecialchars($nonce) ?>">
    const COOLDOWN_PERIOD_MS = 30 * 1000; // 30 seconds
    const LAST_REQUEST_KEY = 'mlbb_last_request_time';
    let countdownTimeout = null;

    // Function to update the button's state (text, disabled)
    function updateButtonDisplay() {
        const lastRequestTime = localStorage.getItem(LAST_REQUEST_KEY);
        if (!lastRequestTime) {
            enableButton();
            return;
        }

        const currentTime = Date.now();
        const timeLeft = Math.ceil((COOLDOWN_PERIOD_MS - (currentTime - parseInt(lastRequestTime, 10))) / 1000);
        
        if (timeLeft > 0) {
            disableButton(timeLeft);
            countdownTimeout = setTimeout(updateButtonDisplay, 1000);
        } else {
            enableButton();
            localStorage.removeItem(LAST_REQUEST_KEY);
        }
    }

    function disableButton(timeLeft) {
        const submitButton = document.getElementById('submit-button');
        const buttonText = document.getElementById('button-text');
        submitButton.disabled = true;
        buttonText.textContent = `Wait ${timeLeft}s`;
    }

    function enableButton() {
        const submitButton = document.getElementById('submit-button');
        const buttonText = document.getElementById('button-text');
        submitButton.disabled = false;
        buttonText.textContent = 'Check Region';
        if (countdownTimeout) {
            clearTimeout(countdownTimeout);
            countdownTimeout = null;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
            updateButtonDisplay();
        });

    const userIdInput = document.getElementById('user-id');
    const zoneIdInput = document.getElementById('zone-id');

    function filterNumericInput(event) {
        event.target.value = event.target.value.replace(/[^0-9]/g, '');
    }

    function restrictNumericInput(event) {
        // Allow control keys (Backspace, Delete, ArrowLeft, ArrowRight, Tab, Enter, Escape)
        if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Escape'].includes(event.key)) {
            return true;
        }

        // Allow only digits (0-9)
        if (event.key >= '0' && event.key <= '9') {
            return true;
        }

        // Prevent all other characters
        event.preventDefault();
        return false;
    }

    userIdInput.addEventListener('input', filterNumericInput);
    userIdInput.addEventListener('keydown', restrictNumericInput);

    zoneIdInput.addEventListener('input', filterNumericInput);
    zoneIdInput.addEventListener('keydown', restrictNumericInput);

        document.getElementById('region-form').addEventListener('submit', function(event) {
        event.preventDefault();

        const submitButton = document.getElementById('submit-button');
        const buttonText = document.getElementById('button-text');
        const loadingSpinner = document.getElementById('loading-spinner');
        const resultDiv = document.getElementById('result');

        // Show loading spinner
        buttonText.classList.add('d-none');
        loadingSpinner.classList.remove('d-none');
        resultDiv.innerHTML = ''; // Clear previous results

        const userId = document.getElementById('user-id').value;
        const zoneId = document.getElementById('zone-id').value;

        fetch(`/api/region-proxy?id=${userId}&zone=${zoneId}`)
            .then(response => response.json())
            .then(data => {
                if (data.username) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success" role="alert">
                            <h4 class="alert-heading">Player Found!</h4>
                            <p><strong>Username:</strong> ${data.username}</p>
                            <p class="mb-0"><strong>Region:</strong> ${data.region}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger" role="alert">
                            <h4 class="alert-heading">Error!</h4>
                            <p>${data.error || 'An unknown error occurred.'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger" role="alert">
                        <h4 class="alert-heading">Network Error!</h4>
                        <p>Could not connect to the server. Please check your internet connection or try again later.</p>
                    </div>
                `;
                console.error('Error:', error);
            })
            .finally(() => {
                loadingSpinner.classList.add('d-none');
                buttonText.classList.remove('d-none');
                
                // Set cooldown
                const currentTime = Date.now();
                localStorage.setItem(LAST_REQUEST_KEY, currentTime);
                updateButtonDisplay();
            });
    });
</script>