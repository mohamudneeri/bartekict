$(document).ready(function () {
    // ==================== Constants ====================
    const API_SELECT = "includes/api/custom_domain_select.php";
    const API_CART   = "includes/api/custom_cart_manage.php";

    // ==================== DOM elements ====================
    const $domainInCartPanel   = $("#domainInCartPanel");
    const $newDomainPanel      = $("#newDomainPanel");
    const $transferDomainPanel = $("#transferDomainPanel");
    const $useOwnDomainPanel   = $("#useOwnDomainPanel");

    // Form elements — Cart domain
    const $domainTypeRadios  = $('input[name="domain_type"]');
    const $inputInCartDomain = $("#inputInCartDomain");
    const $btnUseCartDomain  = $("#btnUseCartDomain");

    // Form elements — New domain
    const $inputNewDomain       = $("#inputNewDomain");
    const $inputNewDomainTld    = $("#inputNewDomainTld");
    const $btnCheckAvailability = $("#btnCheckAvailability");

    // Form elements — Transfer
    const $inputTrnsfrDomain    = $("#inputTrnsfrDomain");
    const $inputTrnsfrDomainTld = $("#inputTrnsfrDomainTld");
    const $inputAuthCode        = $("#inputAuthCode");
    const $btnTransferDomain    = $("#btnTransferDomain");

    // Form elements — Own domain
    const $inputOwnDomain  = $("#inputOwnDomain");
    const $btnUseOwnDomain = $("#btnUseOwnDomain");

    // Error message elements
    const $errCartDomain      = $(".err-cart-domain");
    const $errNewDomain       = $(".err-new-domain");
    const $errTransferDomain  = $(".err-transfer-domain");
    const $errTransferEppCode = $(".err-transfer-epp-code");
    const $errOwnDomain       = $(".err-own-domain");

    // ==================== Utilities ====================

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g,  "&amp;")
            .replace(/</g,  "&lt;")
            .replace(/>/g,  "&gt;")
            .replace(/"/g,  "&quot;")
            .replace(/'/g,  "&#039;");
    }

    function hideAllErrors() {
        $(".form-error").removeClass("show");
        $(".input-text, .drop-box").removeClass("invalid");
    }

    function showError($el, message) {
        hideAllErrors();
        $el.text(message).addClass("show");
    }

    function validateFullDomain(domain) {
        return /^(?!-)[a-z0-9-]{1,63}(?<!-)\.[a-z]{2,}$/.test(domain.toLowerCase());
    }

    /**
     * Fetch the currently selected hosting plan from session.
     * Always resolves (never rejects) with { pid, slug, cycle } or null.
     *
     * Wrapped in a native Promise because jqXHR has .then()/.fail() but NOT
     * .catch(), which throws when used directly in a promise chain.
     */
    function getSelectedHostingPlan() {
        return new Promise(function (resolve) {
            $.post(API_CART, { action: "get_selected_hosting_plan" }, null, "json")
                .then(function (res) {
                    resolve((res.status === "success" && res.plan) ? res.plan : null);
                })
                .fail(function () {
                    resolve(null);
                });
        });
    }

    /**
     * Add a domain to cart, attaching the currently selected hosting plan if one exists.
     * Returns a native Promise so callers can safely use .then()/.catch().
     *
     * @param {object} cartData  Base POST fields: { action, domain, type [, authcode] }
     */
    function addToCartWithPlan(cartData) {
        return getSelectedHostingPlan().then(function (plan) {
            const postData = Object.assign({}, cartData);
            if (plan) {
                postData.pid   = plan.pid;
                postData.slug  = plan.slug;
                postData.cycle = plan.cycle;
            }
            return new Promise(function (resolve, reject) {
                $.post(API_CART, postData, null, "json")
                    .then(resolve)
                    .fail(reject);
            });
        });
    }

    // ==================== Panel switching ====================

    function switchDomainPanel(selectedValue) {
        $domainInCartPanel.hide();
        $newDomainPanel.hide();
        $transferDomainPanel.hide();
        $useOwnDomainPanel.hide();
        hideAllErrors();

        switch (selectedValue) {
            case "cartdomain":     $domainInCartPanel.show();   break;
            case "newdomain":      $newDomainPanel.show();      break;
            case "transferdomain": $transferDomainPanel.show(); break;
            case "owndomain":      $useOwnDomainPanel.show();   break;
        }
    }

    $domainTypeRadios.on("change", function () {
        switchDomainPanel($(this).attr("id"));
        $(".success-message-wrapper").removeClass("show");
        $(".transfer-success-wrapper").removeClass("show");
    });

    // ==================== Use Cart Domain ====================

    $btnUseCartDomain.on("click", function () {
        const selectedDomain = $inputInCartDomain.val();

        if (!selectedDomain) {
            showError($errCartDomain, "Please select a domain from your cart.");
            $inputInCartDomain.addClass("invalid");
            return;
        }

        // Fetch the hosting plan first, then include it with the domain selection.
        getSelectedHostingPlan().then(function (plan) {
            const postData = { action: "set_selected_domain", domain: selectedDomain };
            if (plan) {
                postData.pid   = plan.pid;
                postData.slug  = plan.slug;
                postData.cycle = plan.cycle;
            }

            return new Promise(function (resolve, reject) {
                $.post(API_CART, postData, null, "json").then(resolve).fail(reject);
            });
        })
        .then(function (res) {
            if (res.status === "success") {
                window.location.href = "shoppingcart.php";
            } else {
                showError($errCartDomain, "Failed to select domain. Please try again.");
            }
        })
        .catch(function () {
            showError($errCartDomain, "Request failed. Please try again.");
        });
    });

    // ==================== Check New Domain Availability ====================

    $btnCheckAvailability.on("click", function () {
        const sld = $inputNewDomain.val().trim().toLowerCase();
        const tld = $inputNewDomainTld.val();

        $(".success-message-wrapper").removeClass("show");

        if (!sld) {
            showError($errNewDomain, "Please enter a domain name.");
            $inputNewDomain.addClass("invalid");
            return;
        }

        const sanitizedSld = sld.replace(/[^a-z0-9-]/g, "");

        if (!sanitizedSld || sanitizedSld.length < 1 || sanitizedSld.length > 63) {
            showError(
                $errNewDomain,
                "Domain name must be 1–63 characters and can only contain letters, numbers, and hyphens."
            );
            $inputNewDomain.addClass("invalid");
            return;
        }

        if (sanitizedSld.startsWith("-") || sanitizedSld.endsWith("-")) {
            showError($errNewDomain, "Domain name cannot start or end with a hyphen.");
            $inputNewDomain.addClass("invalid");
            return;
        }

        const fullDomain = sanitizedSld + tld;

        $btnCheckAvailability.prop("disabled", true).text("Checking…");

        $.ajax({
            url:      API_SELECT,
            method:   "POST",
            data:     { action: "check_new", domain: fullDomain },
            dataType: "json",
        })
        .done(function (res) {
            if (res.status === "error") {
                showError($errNewDomain, res.message || "Invalid domain format.");
                $inputNewDomain.addClass("invalid");
                return;
            }

            if (res.status === "available") {
                const message =
                    "Domain <strong>" + escapeHtml(fullDomain) + "</strong>" +
                    " is available for $" + res.registerPrice + "/year!";
                $errNewDomain.removeClass("show").text("");
                showAvailabilitySuccess(
                    $errNewDomain,
                    message,
                    fullDomain,
                    "register",
                    $btnCheckAvailability,
                    res.inCart
                );
            } else {
                showError(
                    $errNewDomain,
                    "Domain " + escapeHtml(fullDomain) + " is already taken. Please try another name."
                );
                $inputNewDomain.addClass("invalid");
            }
        })
        .fail(function () {
            showError($errNewDomain, "Search request failed. Please try again.");
            $inputNewDomain.addClass("invalid");
        })
        .always(function () {
            $btnCheckAvailability.prop("disabled", false).text("Check Availability");
        });
    });

    /**
     * Show an inline success banner with an "Add to Cart" / "Continue to Cart" button.
     * Attaches the selected hosting plan when writing to cart.
     */
    function showAvailabilitySuccess($errorEl, message, domain, type, $triggerBtn, alreadyInCart) {
        $triggerBtn.closest(".form-row").siblings(".success-message-wrapper").remove();

        const $wrapper = $('<div class="success-message-wrapper show"></div>');
        const $msg     = $('<p class="success-message"></p>').html(message);
        const btnLabel = alreadyInCart ? "Continue to Cart" : "Add to Cart";
        const $cartBtn = $('<button type="button" class="brtk-button add-to-cart-success"></button>').text(btnLabel);

        $wrapper.append($msg).append($cartBtn);
        $triggerBtn.closest(".form-row").after($wrapper);

        if (alreadyInCart) {
            $cartBtn.on("click", function () {
                window.location.href = "shoppingcart.php";
            });
        } else {
            $cartBtn.on("click", function () {
                const $btn = $(this);
                $btn.text("Adding…").prop("disabled", true);

                addToCartWithPlan({ action: "add", domain: domain, type: type })
                    .then(function (res) {
                        if (res.status === "success" || res.status === "exists") {
                            $btn.text("Go to Cart").prop("disabled", false);
                            $btn.off("click").on("click", function () {
                                window.location.href = "shoppingcart.php";
                            });
                        } else {
                            $btn.text("Add to Cart").prop("disabled", false);
                            alert("Error adding to cart: " + (res.message || "Unknown error"));
                        }
                    })
                    .catch(function () {
                        $btn.text("Add to Cart").prop("disabled", false);
                        alert("Request failed. Please try again.");
                    });
            });
        }

        // Auto-dismiss after 60 s
        setTimeout(function () {
            $wrapper.fadeOut(function () { $(this).remove(); });
        }, 60000);
    }

    // ==================== Transfer Domain ====================
    // Flow:
    //   1. check_transfer → custom_domain_select.php  (eligibility + WHOIS)
    //   2. verify_auth    → custom_domain_select.php  (EPP code acceptance)
    //   3. Show "Add to Cart" banner — user clicks to write to cart
    //   4. add (transfer) → custom_cart_manage.php    (cart write, with hosting plan)

    $btnTransferDomain.on("click", function () {
        const sld      = $inputTrnsfrDomain.val().trim().toLowerCase();
        const tld      = $inputTrnsfrDomainTld.val();
        const authCode = $inputAuthCode.val().trim();

        if (!sld) {
            showError($errTransferDomain, "Please enter a domain name.");
            $inputTrnsfrDomain.addClass("invalid");
            return;
        }

        const sanitizedSld = sld.replace(/[^a-z0-9-]/g, "");

        if (!sanitizedSld || sanitizedSld.length < 1 || sanitizedSld.length > 63) {
            showError($errTransferDomain, "Domain name must be 1–63 characters long.");
            $inputTrnsfrDomain.addClass("invalid");
            return;
        }

        const fullDomain = sanitizedSld + tld;

        if (!validateFullDomain(fullDomain)) {
            showError($errTransferDomain, "Please enter a valid domain name (e.g. example.com).");
            $inputTrnsfrDomain.addClass("invalid");
            return;
        }

        if (!authCode) {
            showError($errTransferEppCode, "Please enter the EPP/Auth code for domain transfer.");
            $inputAuthCode.addClass("invalid");
            return;
        }

        $btnTransferDomain.prop("disabled", true).text("Checking…");

        // Step 1 — eligibility
        $.ajax({
            url:      API_SELECT,
            method:   "POST",
            data:     { action: "check_transfer", domain: fullDomain },
            dataType: "json",
        })
        .done(function (res) {
            if (res.status === "error") {
                showError($errTransferDomain, res.message || "Could not check domain.");
                $inputTrnsfrDomain.addClass("invalid");
                $btnTransferDomain.prop("disabled", false).text("Transfer");
                return;
            }

            if (!res.registered) {
                showError(
                    $errTransferDomain,
                    "This domain is not registered. You can register it as a new domain instead."
                );
                $inputTrnsfrDomain.addClass("invalid");
                $btnTransferDomain.prop("disabled", false).text("Transfer");
                return;
            }

            if (res.inWhmcs) {
                showError($errTransferDomain, "This domain is already managed in your account.");
                $inputTrnsfrDomain.addClass("invalid");
                $btnTransferDomain.prop("disabled", false).text("Transfer");
                return;
            }

            if (!res.eligible) {
                let msg = "This domain cannot be transferred at this time.";
                if (res.reasons.includes("60_day_lock")) {
                    msg = "This domain was registered less than 60 days ago and cannot be transferred yet.";
                } else if (res.reasons.includes("locked")) {
                    msg = "This domain is locked. Please unlock it at your current registrar.";
                }
                showError($errTransferDomain, msg);
                $inputTrnsfrDomain.addClass("invalid");
                $btnTransferDomain.prop("disabled", false).text("Transfer");
                return;
            }

            // Step 2 — verify auth code
            $btnTransferDomain.text("Verifying code…");

            $.ajax({
                url:      API_SELECT,
                method:   "POST",
                data:     { action: "verify_auth", domain: fullDomain, auth_code: authCode },
                dataType: "json",
            })
            .done(function (authRes) {
                if (!authRes.verified) {
                    showError(
                        $errTransferEppCode,
                        authRes.message || "Invalid authorization code."
                    );
                    $inputAuthCode.addClass("invalid");
                    $btnTransferDomain.prop("disabled", false).text("Transfer");
                    return;
                }

                // Step 3 — both checks passed; show banner, let user click Add to Cart
                $btnTransferDomain.prop("disabled", false).text("Transfer");
                hideAllErrors();

                showTransferEligibleSuccess(fullDomain, authCode, res.transferPrice, res.inCart);
            })
            .fail(function () {
                showError($errTransferEppCode, "Could not verify auth code. Please try again.");
                $btnTransferDomain.prop("disabled", false).text("Transfer");
            });
        })
        .fail(function () {
            showError($errTransferDomain, "Failed to check domain status. Please try again.");
            $btnTransferDomain.prop("disabled", false).text("Transfer");
        });
    });

    /**
     * Shown after eligibility + auth code checks pass.
     * Mirrors showAvailabilitySuccess — shows price and an "Add to Cart" button.
     * The cart write only happens when the user clicks.
     */
    function showTransferEligibleSuccess(domain, authCode, transferPrice, alreadyInCart) {
        $transferDomainPanel.find(".transfer-success-wrapper").remove();

        const $container = $transferDomainPanel.find(".panel-form");
        const $wrapper   = $('<div class="transfer-success-wrapper show"></div>');
        const priceText  = transferPrice
            ? " — $" + Number(transferPrice).toFixed(2) + "/year"
            : "";
        const $msg = $(
            '<p class="success-message">Domain <strong>' + escapeHtml(domain) + "</strong>" +
            " is eligible for transfer" + priceText + ".</p>"
        );
        const btnLabel = alreadyInCart ? "Continue to Cart" : "Add to Cart";
        const $cartBtn = $('<button type="button" class="brtk-button add-to-cart-success"></button>').text(btnLabel);

        $wrapper.append($msg).append($cartBtn);
        $container.append($wrapper);

        if (alreadyInCart) {
            $cartBtn.on("click", function () {
                window.location.href = "shoppingcart.php";
            });
        } else {
            $cartBtn.on("click", function () {
                const $btn = $(this);
                $btn.text("Adding…").prop("disabled", true);

                addToCartWithPlan({
                    action:   "add",
                    domain:   domain,
                    type:     "transfer",
                    authcode: authCode,
                })
                .then(function (cartRes) {
                    if (cartRes.status === "success") {
                        // Swap to the "added" confirmation banner
                        $wrapper.remove();
                        showTransferAddedSuccess(domain);

                        // Clear form fields now that it's in the cart
                        $inputTrnsfrDomain.val("");
                        $inputAuthCode.val("");
                    } else if (cartRes.status === "exists") {
                        $btn.text("Continue to Cart").prop("disabled", false);
                        $btn.off("click").on("click", function () {
                            window.location.href = "shoppingcart.php";
                        });
                    } else {
                        $btn.text("Add to Cart").prop("disabled", false);
                        alert("Failed to add domain to cart. Please try again.");
                    }
                })
                .catch(function () {
                    $btn.text("Add to Cart").prop("disabled", false);
                    alert("Request failed. Please try again.");
                });
            });
        }

        // Auto-dismiss after 60 s
        setTimeout(function () {
            $wrapper.fadeOut(function () { $(this).remove(); });
        }, 60000);
    }

    /**
     * Shown after the user clicks "Add to Cart" and the write succeeds.
     * Simple confirmation with a "View Cart" button.
     */
    function showTransferAddedSuccess(domain) {
        const $container = $transferDomainPanel.find(".panel-form");
        const $wrapper   = $('<div class="transfer-success-wrapper show"></div>');
        const $msg       = $(
            '<p class="success-message">Domain <strong>' + escapeHtml(domain) +
            "</strong> has been added to your cart for transfer.</p>"
        );
        const $viewCartBtn = $(
            '<button type="button" class="brtk-button view-cart-btn">View Cart</button>'
        );

        $wrapper.append($msg).append($viewCartBtn);
        $container.append($wrapper);

        $viewCartBtn.on("click", function () {
            window.location.href = "shoppingcart.php";
        });

        // Auto-dismiss after 60 s
        setTimeout(function () {
            $wrapper.fadeOut(function () { $(this).remove(); });
        }, 60000);
    }

    // ==================== Use Own Domain ====================

    $btnUseOwnDomain.on("click", function () {
        let domain = $inputOwnDomain.val().trim().toLowerCase();

        if (!domain) {
            showError($errOwnDomain, "Please enter your domain name.");
            $inputOwnDomain.addClass("invalid");
            return;
        }

        domain = domain.replace(/^www\./, "");

        if (!validateFullDomain(domain)) {
            showError($errOwnDomain, "Please enter a valid domain name (e.g. example.com).");
            $inputOwnDomain.addClass("invalid");
            return;
        }

        $btnUseOwnDomain.prop("disabled", true).text("Processing…");

        // Fetch the hosting plan first, then include it with the domain selection.
        getSelectedHostingPlan().then(function (plan) {
            const postData = { action: "set_own_domain", domain: domain };
            if (plan) {
                postData.pid   = plan.pid;
                postData.slug  = plan.slug;
                postData.cycle = plan.cycle;
            }

            return new Promise(function (resolve, reject) {
                $.post(API_CART, postData, null, "json").then(resolve).fail(reject);
            });
        })
        .then(function (res) {
            if (res.status === "success") {
                window.location.href = "shoppingcart.php";
            } else {
                showError($errOwnDomain, res.message || "Failed to save domain. Please try again.");
                $btnUseOwnDomain.prop("disabled", false).text("Use");
            }
        })
        .catch(function () {
            showError($errOwnDomain, "Request failed. Please try again.");
            $btnUseOwnDomain.prop("disabled", false).text("Use");
        });
    });

    // ==================== Enter key shortcuts ====================

    $inputNewDomain.on("keypress", function (e) {
        hideAllErrors();
        if (e.which === 13) { e.preventDefault(); $btnCheckAvailability.click(); }
    });

    $inputTrnsfrDomain.on("keypress", function (e) {
        hideAllErrors();
        if (e.which === 13) { e.preventDefault(); $btnTransferDomain.click(); }
    });

    $inputAuthCode.on("keypress", function (e) {
        hideAllErrors();
        if (e.which === 13) { e.preventDefault(); $btnTransferDomain.click(); }
    });

    $inputOwnDomain.on("keypress", function (e) {
        hideAllErrors();
        if (e.which === 13) { e.preventDefault(); $btnUseOwnDomain.click(); }
    });

    // ==================== Initial load ====================

    const $checkedRadio = $domainTypeRadios.filter(":checked");
    if ($checkedRadio.length) {
        switchDomainPanel($checkedRadio.attr("id"));
    } else {
        $("#newdomain").prop("checked", true);
        switchDomainPanel("newdomain");
    }
});