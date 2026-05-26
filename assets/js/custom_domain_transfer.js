$(document).ready(function () {
    // DOM elements
    const $cartHeader = $(".bartek-header");
    const $transferForm = $("#brtkDomainTransferForm");
    const $domainInput = $("#domainInput");
    const $resultContainer = $(".result-container");
    const $checkingStatus = $(".checking-status");
    const $eligibleBlock = $(".result-wrapper.eligible");
    const $notEligibleBlock = $(".result-wrapper.not-eligible");
    const $cartSticky = $(".cart-sticky-wrapper");
    const $domainTransferContainer = $(".brtk-domain-transfer-p");
    const $authCodeInput = $("#authCodeInput");
    const $authCodeVerifyBtn = $("#authCodeVerify");
    const $eligibleDomainSpan = $(".eligible-domain");
    const $transferError = $(".transfer-error");

    // Not eligible block elements
    const $notEligibleReasons = $notEligibleBlock.find(
        ".failed-reasons-container",
    );
    const $currentRegistrarSpan = $notEligibleBlock.find(".registrar-name");

    // State
    let currentDomain = "";
    let currentDomainData = null;
    let authCodeVerified = false;

    // Helper functions (if brtk_helpers.js is not loaded)
    function showWithClass($element) {
        $element.addClass("show");
    }

    function hideWithClass($element) {
        $element.removeClass("show");
    }

    // Update cart UI (count, total, sticky visibility)
    function updateCartUI(count, totalFormatted) {
        if (count > 0) {
            showWithClass($cartSticky);
            $domainTransferContainer.addClass("has-cart-sticky");
            $cartSticky
                .find(".count-items")
                .text(count + (count === 1 ? " item" : " items"));
            $cartSticky.find(".total-price").text(totalFormatted);

            if ($cartHeader.length) {
                $cartHeader.find(".cart-btn").addClass("has-cart");
                $cartHeader.find(".cart-counter").text(count);
            }
        } else {
            hideWithClass($cartSticky);
            $domainTransferContainer.removeClass("has-cart-sticky");
            if ($cartHeader.length) {
                $cartHeader.find(".cart-btn").removeClass("has-cart");
            }
        }
    }

    // Sync cart from server
    function syncCart() {
        $.getJSON("includes/api/custom_cart_manage.php", { action: "get" })
            .done(function (res) {
                if (res.status === "success") {
                    updateCartUI(res.count, res.totalFormatted);
                }
            })
            .fail(function () {
                console.error("Cart sync failed");
            });
    }

    // Add to cart handler
    function addToCart(domain, type, $btn) {
        const originalText = $btn.find(".button-text").text();
        $btn.find(".button-text").text("Adding...");
        $btn.prop("disabled", true);

        $.post(
            "includes/api/custom_cart_manage.php",
            {
                action: "add",
                domain: domain,
                type: type,
            },
            function (res) {
                if (res.status === "success") {
                    updateCartUI(res.count, res.totalFormatted);
                    $btn.find(".button-text").text("Added!");
                    $btn.addClass("added");

                    // Show success message
                    showSuccess("Domain added to cart successfully!");

                    // Reset after 3 seconds
                    setTimeout(function () {
                        if ($btn.find(".button-text").text() === "Added!") {
                            $btn.find(".button-text").text("Add to cart");
                            $btn.removeClass("added");
                            $btn.prop("disabled", false);
                        }
                    }, 3000);
                } else if (res.status === "exists") {
                    showError("This domain is already in your cart.");
                    $btn.find(".button-text").text(originalText);
                    $btn.prop("disabled", false);
                } else {
                    showError(
                        "Error adding to cart: " +
                            (res.message || "Unknown error"),
                    );
                    $btn.find(".button-text").text(originalText);
                    $btn.prop("disabled", false);
                }
            },
            "json",
        ).fail(function () {
            showError("Request failed. Please try again.");
            $btn.find(".button-text").text(originalText);
            $btn.prop("disabled", false);
        });
    }

    // Display reasons for ineligibility
    function displayIneligibilityReasons(data) {
        const reasons = data.reasons || [];
        const $failedStatusDiv = $notEligibleBlock.find(".failed-status");
        const $reasonsContainer = $notEligibleBlock.find(".failed-reason");

        // Clear existing reasons
        $reasonsContainer.empty();

        // Define reason messages
        const reasonMessages = {
            not_registered: {
                dstatus: "Domain Not Registered",
                message: `Domain name <strong>${data.domain}</strong> is not registered. You can register this domain instead.`,
            },
            already_in_whmcs: {
                dstatus: "Domain Already in System",
                message: `This domain <strong>${data.domain}</strong> is already in our system. If you need to move a domain to another user, please use the 'Change Ownership' feature in the domain management menu.`,
            },
            locked: {
                dstatus: "Domain is Locked",
                message: `Domain <strong>${data.domain}</strong> is locked at your current registrar. Please unlock it first before transferring.`,
            },
            "60_day_lock": {
                dstatus: "60-Day Lock Period",
                message: `Domain <strong>${data.domain}</strong> was recently registered or transferred (within 60 days). ICANN regulations require a 60-day waiting period before transfer.`,
            },
        };

        // Display each reason
        reasons.forEach((reason) => {
            const reasonData = reasonMessages[reason] || {
                dstatus: "Transfer Not Possible",
                message: `Domain <strong>${data.domain}</strong> is not eligible for transfer at this time.`,
            };

            const reasonHtml = `<p>${reasonData.message}</p>`;
            $reasonsContainer.append(reasonHtml);
            $failedStatusDiv.html(`${reasonData.dstatus}`);
        });

        // Show current registrar if available
        if (data.currentRegistrar && data.currentRegistrar !== "Unknown") {
            $(".divider.current").show();
            $notEligibleBlock.find(".current-registrar").show();
            $currentRegistrarSpan.text(data.currentRegistrar);
        } else {
            $(".divider.current").hide();
            $notEligibleBlock.find(".current-registrar").hide();
        }
    }

    // Render transfer results
    function renderResults(data) {
        // Hide checking status
        hideWithClass($checkingStatus);

        // Check if domain is eligible for transfer
        if (data.eligible === true && data.unlocked === true) {
            // Show eligible block
            hideWithClass($notEligibleBlock);
            showWithClass($eligibleBlock);

            // Update domain name in header
            $eligibleDomainSpan.text(data.domain);

            // Update lock status UI
            const $lockStatusSpan = $eligibleBlock.find(".lock-div .status");
            if ($lockStatusSpan.length === 0) {
                // If lock-div doesn't exist, create it or use alternative
                $eligibleBlock.find(".failed-status.unlocked").text("Unlocked");
            } else {
                $lockStatusSpan
                    .removeClass("locked")
                    .addClass("unlocked")
                    .text("Unlocked");
            }

            // Update transfer price
            const transferPrice = data.transferPrice || "0.00";
            $eligibleBlock.find(".price-div .number").text("$" + transferPrice);

            // Show/hide auth code section based on needsAuthCode flag
            if (data.needsAuthCode) {
                $eligibleBlock.find(".auth-code-section").show();
            } else {
                $eligibleBlock.find(".auth-code-section").hide();
            }

            // Check if already in cart
            const $addBtn = $eligibleBlock.find(".add-cart");
            if (data.inCart) {
                $addBtn.find(".button-text").text("Added");
                $addBtn.prop("disabled", true);
                $addBtn.addClass("added");
            } else {
                $addBtn.find(".button-text").text("Add to cart");
                $addBtn.prop("disabled", false);
                $addBtn.removeClass("added");
                $addBtn.off("click").on("click", function () {
                    if (data.needsAuthCode && !authCodeVerified) {
                        showAuthError(
                            "Please verify your authorization code first.",
                        );
                        return;
                    }
                    addToCart(data.domain, "transfer", $(this));
                });
            }

            // Reset auth code verification state for new domain
            authCodeVerified = false;
            $authCodeInput.val("");
            $authCodeVerifyBtn.removeClass("verified").text("Verify Auth Code");
            $authCodeVerifyBtn.prop("disabled", false);
        } else {
            // Show not eligible block with reasons
            hideWithClass($eligibleBlock);
            showWithClass($notEligibleBlock);

            // Display specific reasons for ineligibility
            displayIneligibilityReasons(data);
        }

        // Show result container
        showWithClass($resultContainer);
    }

    // Perform domain transfer eligibility check
    function checkTransferEligibility(domain) {
        if (!domain || domain.trim() === "") return;
        currentDomain = domain.trim();
        $domainInput.val(currentDomain);

        // Reset UI
        hideWithClass($eligibleBlock);
        hideWithClass($notEligibleBlock);
        showWithClass($checkingStatus);
        showWithClass($resultContainer);
        authCodeVerified = false;

        // Hide any previous errors
        hideWithClass($transferError);
        $(".auth-error").removeClass("show");

        // Disable transfer button while checking
        const $transferBtn = $(".transfer-button");
        $transferBtn.addClass("checking");
        $transferBtn.prop("disabled", true);

        $.ajax({
            url: "includes/api/custom_domain_transfer.php",
            method: "POST",
            data: { domain: currentDomain },
            dataType: "json",
            timeout: 30000, // 30 second timeout
        })
            .done(function (res) {
                console.log(res);

                if (res.status === "error") {
                    showError(res.message || "Transfer check failed.");
                    hideWithClass($resultContainer);
                    return;
                }

                currentDomainData = res;
                renderResults(res);
            })
            .fail(function (jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error:", textStatus, errorThrown);
                showError(
                    "Unable to check domain eligibility. Please try again.",
                );
                hideWithClass($resultContainer);
            })
            .always(function () {
                hideWithClass($checkingStatus);
                $transferBtn.removeClass("checking");
                $transferBtn.prop("disabled", false);
            });
    }

    // Verify authorization code
    function verifyAuthCode(code, domain) {
        if (!code || code.trim() === "") {
            showAuthError("Please enter an authorization code.");
            return false;
        }

        $authCodeVerifyBtn.prop("disabled", true);
        $authCodeVerifyBtn.text("Verifying...");

        $.ajax({
            url: "includes/api/custom_domain_transfer.php",
            method: "POST",
            data: {
                action: "verify_auth",
                domain: domain,
                auth_code: code.trim(),
            },
            dataType: "json",
            timeout: 15000, // 15 second timeout
        })
            .done(function (res) {
                if (res.status === "success" && res.verified === true) {
                    authCodeVerified = true;
                    $authCodeVerifyBtn.addClass("verified").text("Verified!");

                    // Show success message
                    showSuccess("Authorization code verified successfully!");

                    // Enable add to cart button if domain is eligible and not in cart
                    if (
                        currentDomainData &&
                        currentDomainData.eligible === true &&
                        !currentDomainData.inCart
                    ) {
                        const $addBtn = $eligibleBlock.find(".add-cart");
                        $addBtn.prop("disabled", false);
                    }
                } else {
                    authCodeVerified = false;
                    $authCodeVerifyBtn
                        .removeClass("verified")
                        .text("Verify Auth Code");

                    // Clear the invalid code
                    $authCodeInput.val("");

                    showAuthError(
                        res.message ||
                            "Invalid authorization code. Please check with your current registrar.",
                    );
                }
            })
            .fail(function () {
                authCodeVerified = false;
                $authCodeVerifyBtn
                    .removeClass("verified")
                    .text("Verify Auth Code");
                showAuthError("Verification request failed. Please try again.");
            })
            .always(function () {
                $authCodeVerifyBtn.prop("disabled", false);
            });
    }

    // Success message display
    function showSuccess(message) {
        const $successMsg = $(".success-message");
        if ($successMsg.length) {
            $successMsg.find(".text").html(message);
            showWithClass($successMsg);
            setTimeout(function () {
                hideWithClass($successMsg);
            }, 5000);
        } else {
            // Fallback to alert if no success message element
            console.log("Success:", message);
        }
    }

    // Error display function
    function showError(message) {
        showWithClass($transferError);
        $transferError.find(".text").html(message);

        // Auto-hide after 5 seconds
        setTimeout(function () {
            hideWithClass($transferError);
        }, 5000);
    }

    // Auth error display function
    function showAuthError(message) {
        $(".auth-error").addClass("show");
        $(".auth-error .text").html(message);

        // Auto-hide after 5 seconds
        setTimeout(function () {
            $(".auth-error").removeClass("show");
        }, 5000);
    }

    // Smooth scroll to transfer container
    function scrollToTransferContainer() {
        const $transferContainer = $(".transfer-container");
        if ($transferContainer.length) {
            $transferContainer[0].scrollIntoView({
                behavior: "smooth",
                block: "start",
            });
        }
    }

    // Event: form submit
    $transferForm.on("submit", function (e) {
        e.preventDefault();
        const domain = $domainInput.val().trim();

        // Validate empty
        if (!domain) {
            showError("Please enter a domain name.");
            return;
        }

        // Basic sanitization (remove spaces & unsafe chars)
        const sanitizedDomain = domain
            .toLowerCase()
            .replace(/[^a-z0-9.-]/g, "");

        // Domain regex validation
        const domainRegex = /^(?!-)[a-z0-9-]{1,63}(?<!-)\.[a-z]{2,}$/;

        if (!domainRegex.test(sanitizedDomain)) {
            showError("Please enter a valid domain (e.g. example.com).");
            return;
        }

        checkTransferEligibility(sanitizedDomain);
    });

    // Event: Auth code verify button
    $authCodeVerifyBtn.on("click", function () {
        if (
            !currentDomain ||
            !currentDomainData ||
            currentDomainData.eligible !== true
        ) {
            showError("Please check domain eligibility first.");
            return;
        }

        const authCode = $authCodeInput.val().trim();
        if (!authCode) {
            showAuthError("Please enter your authorization code.");
            return;
        }

        verifyAuthCode(authCode, currentDomain);
    });

    // Allow Enter key in auth code input to trigger verification
    $authCodeInput.on("keypress", function (e) {
        if (e.which === 13) {
            // Enter key
            e.preventDefault();
            $authCodeVerifyBtn.trigger("click");
        }
    });

    // Auto-check if ?domain= in URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlDomain = urlParams.get("domain");
    if (urlDomain) {
        $domainInput.val(urlDomain);
        scrollToTransferContainer();
        setTimeout(function () {
            checkTransferEligibility(urlDomain);
        }, 500); // Small delay to ensure DOM is ready
    }

    // Initial cart sync (uncomment when cart management is ready)
    // syncCart();
});
