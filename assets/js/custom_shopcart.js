$(document).ready(() => {
    // ──────────────────────────────────────────────
    // CONFIG
    // ──────────────────────────────────────────────

    const API_URL = "/includes/api/custom_shop_cart.php";
    const CART_API_URL = "/includes/api/custom_cart_manage.php";

    // ──────────────────────────────────────────────
    // STATE
    // ──────────────────────────────────────────────

    let isLoginMode = false;

    // ──────────────────────────────────────────────
    // INIT
    // ──────────────────────────────────────────────

    $(".new-user-header, .container-new-user").addClass("show");
    $(".paymethod-fields").removeClass("show");
    updateCompleteOrderState();

    // ──────────────────────────────────────────────
    // ERROR HELPERS
    // ──────────────────────────────────────────────

    function showError($field, msg) {
        $field.addClass("invalid");
        $field
            .closest(".form-field")
            .find(".form-error")
            .addClass("show")
            .text(msg);
    }

    function hideAllErrors() {
        $(".form-error").removeClass("show").text("");
        $(".input-text, .drop-box").removeClass("invalid");
    }

    // ──────────────────────────────────────────────
    // VALIDATORS MAP
    // ──────────────────────────────────────────────

    const validators = {
        inputFirstName: {
            validate: (v) => v.trim() !== "",
            message: "First name is required.",
        },
        inputLastName: {
            validate: (v) => v.trim() !== "",
            message: "Last name is required.",
        },
        inputEmail: {
            validate: (v) => isValidEmail(v.trim()),
            message: "Please enter a valid email address.",
        },
        inputPhone: {
            validate: (v) => isValidPhone(v.trim()),
            message: "Please enter a valid phone number.",
        },
        inputAddress1: {
            validate: (v) => v.trim() !== "",
            message: "Street address is required.",
        },
        inputCity: {
            validate: (v) => v.trim() !== "",
            message: "City is required.",
        },
        inputState: {
            validate: (v) => v.trim() !== "",
            message: "State/region name is required.",
        },
        inputCountry: {
            validate: (v) => v.trim() !== "",
            message: "Choose your residence country.",
        },
        inputNewPassword1: {
            validate: (v) => isStrongPassword(v),
            message:
                "Password must be at least 8 characters and include a letter and a number.",
        },
        inputNewPassword2: {
            validate: (v) => v === $("#inputNewPassword1").val(),
            message: "Passwords do not match.",
        },
        inputLoginEmail: {
            validate: (v) => isValidEmail(v.trim()),
            message: "Please enter a valid email address.",
        },
        inputLoginPassword: {
            validate: (v) => v.trim().length >= 6,
            message: "Password must be at least 6 characters.",
        },
        inputWaafiPhone: {
            validate: (v) => isValidWaafiPhone(v.trim()),
            message: "Enter a valid Hormuud number (e.g. 612345678).",
        },
        cardNumber: {
            validate: (v) => /^\d{13,19}$/.test(v.replace(/\s/g, "")),
            message: "Enter a valid card number.",
        },
        cardExpiry: {
            validate: (v) => /^(0[1-9]|1[0-2])\/\d{2}$/.test(v),
            message: "Enter expiry as MM/YY.",
        },
        creditCvv: {
            validate: (v) => /^\d{3,4}$/.test(v),
            message: "Enter a valid CVV.",
        },
        nameOnCard: {
            validate: (v) => v.trim() !== "",
            message: "Enter name written on card.",
        },
    };

    /**
     * Validate a single field by its ID (with or without leading #).
     * Returns true if valid, false and shows error if not.
     */
    function validateField(fieldId) {
        // Normalise to bare id string so we can look up in validators map
        const bareId = fieldId.replace(/^#/, "");
        const config = validators[bareId];
        if (!config) return true;

        const $field = $("#" + bareId);
        const value = $field.val() ?? "";

        if (!config.validate(value)) {
            showError($field, config.message);
            scrollToField($field);
            return false;
        }

        $field.removeClass("invalid");
        $field
            .closest(".form-field")
            .find(".form-error")
            .removeClass("show")
            .text("");
        return true;
    }

    /** Smooth-scroll viewport so the invalid field is visible */
    function scrollToField($field) {
        if (!$field || !$field.length) return;
        $("html, body").animate({ scrollTop: $field.offset().top - 120 }, 300);
    }

    // ──────────────────────────────────────────────
    // LIVE VALIDATION — input / blur / change
    // ──────────────────────────────────────────────

    const WATCHED_FIELDS = [
        "#inputLoginEmail",
        "#inputLoginPassword",
        "#inputFirstName",
        "#inputLastName",
        "#inputEmail",
        "#inputPhone",
        "#inputAddress1",
        "#inputCity",
        "#inputState",
        "#inputCountry",
        "#inputNewPassword1",
        "#inputNewPassword2",
        "#inputWaafiPhone",
        "#cardNumber",
        "#cardExpiry",
        "#creditCvv",
        "#nameOnCard",
    ].join(", ");

    $(document).on("input blur change", WATCHED_FIELDS, function () {
        hideAllErrors();
        validateField(this.id);
        updateCompleteOrderState();
    });

    // ──────────────────────────────────────────────
    // PAYMENT METHOD TOGGLE
    // ──────────────────────────────────────────────

    $("input[name='pay_method']").on("change", function () {
        $(".paymethod-fields").removeClass("show");
        $(`.paymethod-fields.${$(this).val()}`).addClass("show");
        hideAllErrors();
        updateCompleteOrderState();
    });

    // ──────────────────────────────────────────────
    // LOGIN ↔ REGISTER TOGGLE
    // ──────────────────────────────────────────────

    function showSection(showSelectors, hideSelectors) {
        $(hideSelectors).removeClass("show");
        $(showSelectors).addClass("show");
    }

    $("#btnClientLogin").on("click", function () {
        showSection(
            ".login-user-header, .container-user-login",
            ".new-user-header, .container-new-user, .login-user-header, .container-user-login",
        );
        isLoginMode = true;
        updateCompleteOrderState();
    });

    $("#btnClientRegister").on("click", function () {
        showSection(
            ".new-user-header, .container-new-user",
            ".new-user-header, .container-new-user, .login-user-header, .container-user-login",
        );
        isLoginMode = false;
        updateCompleteOrderState();
    });

    // ──────────────────────────────────────────────
    // CARD INPUT FORMATTERS
    // ──────────────────────────────────────────────

    $("#cardNumber").on("input", function () {
        const digits = $(this).val().replace(/\D/g, "").slice(0, 19);
        $(this).val(digits.replace(/(.{4})/g, "$1 ").trim());
    });

    $("#cardExpiry").on("input", function () {
        let val = $(this).val().replace(/\D/g, "").slice(0, 4);
        if (val.length >= 3) val = val.slice(0, 2) + "/" + val.slice(2);
        $(this).val(val);
    });

    $("#creditCvv").on("input", function () {
        $(this).val($(this).val().replace(/\D/g, "").slice(0, 4));
    });

    // ──────────────────────────────────────────────
    // PROMO CODE
    // ──────────────────────────────────────────────

    $(".promo-row .input-text").on("input", function () {
        $(".apply-promo-btn").prop("disabled", $(this).val().trim() === "");
    });

    $(".apply-promo-btn").on("click", function () {
        const $btn = $(this);
        const $input = $(".promo-row .input-text");
        const code = $input.val().trim();

        if (!code) {
            showError($input, "Please enter a promo code");
            return;
        }

        $btn.prop("disabled", true).text("Applying...");

        $.post(
            CART_API_URL,
            { action: "apply_promo", promo_code: code },
            null,
            "json",
        )
            .done((res) => {
                if (res.status === "success") {
                    window.location.reload();
                } else {
                    $(".form-error.promo-code")
                        .addClass("show")
                        .text(res.message || "Invalid promo code");
                    $btn.prop("disabled", false).text("Apply");
                }
            })
            .fail(() => {
                $(".form-error.promo-code")
                    .addClass("show")
                    .text("An error occurred. Please try again.");
                $btn.prop("disabled", false).text("Apply");
            });
    });

    $(document).on("click", ".remove-promo-btn", function () {
        $.post(CART_API_URL, { action: "remove_promo" }, null, "json").done(
            (res) => {
                if (res.status === "success") window.location.reload();
            },
        );
    });

    // ──────────────────────────────────────────────
    // BILLING CYCLE / DOMAIN YEARS / REMOVE ITEM
    // ──────────────────────────────────────────────

    function cartPost(data, $trigger, restoreFn) {
        $.post(CART_API_URL, data, null, "json")
            .done((res) => {
                if (res.status === "success") {
                    setTimeout(() => window.location.reload(), 150);
                } else {
                    restoreFn();
                    alert(res.message || "Operation failed.");
                }
            })
            .fail(() => {
                restoreFn();
                alert("An error occurred. Please try again.");
            });
    }

    $(document).on("change", ".item-billing-cycle", function () {
        const $sel = $(this).prop("disabled", true);
        cartPost(
            {
                action: "change_billing_cycle",
                key: $sel.data("key"),
                cycle: $sel.val(),
            },
            $sel,
            () => $sel.prop("disabled", false),
        );
    });

    $(document).on("change", ".item-years", function () {
        const $sel = $(this).prop("disabled", true);
        cartPost(
            {
                action: "change_domain_years",
                key: $sel.data("key"),
                years: parseInt($sel.val(), 10),
            },
            $sel,
            () => $sel.prop("disabled", false),
        );
    });

    $(document).on("click", ".remove-btn", function () {
        const $btn = $(this)
            .prop("disabled", true)
            .html('<i class="fa-solid fa-spinner fa-spin"></i>');
        cartPost(
            {
                action: "remove",
                type: $btn.data("type"),
                key: String($btn.data("key")),
            },
            $btn,
            () =>
                $btn
                    .prop("disabled", false)
                    .html('<i class="fa-solid fa-trash-can"></i>'),
        );
    });

    // ──────────────────────────────────────────────
    // LOGIN BUTTON
    // ──────────────────────────────────────────────

    function isLoginValid() {
        return (
            isValidEmail($("#inputLoginEmail").val().trim()) &&
            $("#inputLoginPassword").val().trim().length >= 6
        );
    }

    $("#inputLoginEmail, #inputLoginPassword").on("input blur", function () {
        $("#btnCheckLogin").prop("disabled", !isLoginValid());
    });

    $("#inputLoginEmail, #inputLoginPassword").on("keypress", function (e) {
        if (e.which === 13) {
            e.preventDefault();
            if (isLoginValid()) $("#btnCheckLogin").trigger("click");
        }
    });

    $("#btnCheckLogin").on("click", function () {
        const $btn = $(this);
        const $email = $("#inputLoginEmail");
        const $password = $("#inputLoginPassword");

        hideAllErrors();

        if (!validateField("inputLoginEmail")) return;
        if (!validateField("inputLoginPassword")) return;

        $btn.prop("disabled", true).text("Logging in...");

        $.post(
            API_URL,
            {
                action: "login",
                email: $email.val().trim(),
                password: $password.val(),
            },
            null,
            "json",
        )
            .done((res) => {
                if (res.status === "success") {
                    setTimeout(() => window.location.reload(), 500);
                } else {
                    showError(
                        $email,
                        res.message || "Login failed. Please try again.",
                    );
                    $btn.prop("disabled", false).text("Login");
                }
            })
            .fail(() => {
                showError($email, "An error occurred. Please try again.");
                $btn.prop("disabled", false).text("Login");
            })
            .always(() =>
                $("#inputLoginEmail, #inputLoginPassword").trigger("input"),
            );
    });

    // ──────────────────────────────────────────────
    // FORM VALIDITY CHECKS
    // ──────────────────────────────────────────────

    function isRegisterFormValid() {
        return (
            $("#inputFirstName").val().trim() !== "" &&
            $("#inputLastName").val().trim() !== "" &&
            isValidEmail($("#inputEmail").val().trim()) &&
            isValidPhone($("#inputPhone").val().trim()) &&
            $("#inputAddress1").val().trim() !== "" &&
            $("#inputCity").val().trim() !== "" &&
            $("#inputState").val().trim() !== "" &&
            $("#inputCountry").val().trim() !== "" &&
            isStrongPassword($("#inputNewPassword1").val()) &&
            $("#inputNewPassword1").val() === $("#inputNewPassword2").val()
        );
    }

    function isPaymentValid() {
        const method = $("input[name='pay_method']:checked").val();
        if (!method) return false;

        if (method === "waafi") {
            return isValidWaafiPhone($("#inputWaafiPhone").val().trim());
        }

        if (method === "creditcard") {
            const cardOk = /^\d{13,19}$/.test(
                $("#cardNumber").val().replace(/\s/g, ""),
            );
            const expiryOk = /^(0[1-9]|1[0-2])\/\d{2}$/.test(
                $("#cardExpiry").val(),
            );
            const cvvOk = /^\d{3,4}$/.test($("#creditCvv").val());
            const nameOk = $("#nameOnCard").val().trim() !== "";
            return cardOk && expiryOk && cvvOk && nameOk;
        }

        return true; // other gateways
    }

    function updateCompleteOrderState() {
        if ($(".cart-item").length === 0) return;

        const isLogged = parseInt($("#isLogged").val(), 10) === 1;
        const customerValid = isLogged
            ? true
            : !isLoginMode && isRegisterFormValid();
        const enableButton = customerValid && isPaymentValid();

        // Uncomment when ready to enforce:
        // $("#btnCompleteOrder").prop("disabled", !enableButton);
    }

    // ──────────────────────────────────────────────
    // ORDER STATUS UI
    // ──────────────────────────────────────────────

    /**
     * Show the .order-status overlay and scroll to it.
     */
    function showOrderStatus() {
        $(".order-status").addClass("show");
        $("html, body").animate(
            { scrollTop: $(".order-status").offset().top - 80 },
            350,
        );
    }

    /**
     * Animate through the steps array returned by the backend,
     * then optionally redirect.
     */
    function animateStatusSteps(steps, redirect) {
        const $text = $("#processingText");
        let i = 0;

        function nextStep() {
            if (i >= steps.length) {
                if (redirect) window.location.href = redirect;
                return;
            }
            $text.fadeOut(200, () => {
                $text.text(steps[i++]).fadeIn(200, () => {
                    setTimeout(nextStep, 1400);
                });
            });
        }

        nextStep();
    }

    // ──────────────────────────────────────────────
    // SUBMIT ORDER
    // ──────────────────────────────────────────────

    $("#btnCompleteOrder").on("click", function () {
        hideAllErrors();

        const isLogged = parseInt($("#isLogged").val(), 10) === 1;

        // ── Guest validation ──
        if (!isLogged) {
            if (isLoginMode) {
                // Block: tell user to log in first
                showError(
                    $("#inputLoginEmail"),
                    "Please log in before placing your order.",
                );
                scrollToField($("#inputLoginEmail"));
                return;
            }

            // Register form — validate each field and stop at first failure
            const registerFields = [
                "inputFirstName",
                "inputLastName",
                "inputEmail",
                "inputPhone",
                "inputAddress1",
                "inputCity",
                "inputState",
                "inputCountry",
                "inputNewPassword1",
                "inputNewPassword2",
            ];

            for (const id of registerFields) {
                if (!validateField(id)) return;
            }
        }

        // ── Payment validation ──
        const payMethod = $("input[name='pay_method']:checked").val();

        if (!payMethod) {
            $(".form-error.payment-meth")
                .addClass("show")
                .text("Choose a payment method to continue.");
            $("html, body").animate(
                { scrollTop: $(".form-error.payment-meth").offset().top - 120 },
                300,
            );
            return;
        }

        if (payMethod === "waafi") {
            if (!validateField("inputWaafiPhone")) return;
        }

        if (payMethod === "creditcard") {
            if (!validateField("cardNumber")) return;

            // Extra check: expiry not expired
            const $expiry = $("#cardExpiry");
            if (!validateField("cardExpiry")) return;

            const [m, y] = $expiry.val().split("/").map(Number);
            const now = new Date();
            const expDate = new Date(2000 + y, m - 1, 1);
            if (expDate < new Date(now.getFullYear(), now.getMonth(), 1)) {
                showError($expiry, "This card has expired.");
                scrollToField($expiry);
                return;
            }

            if (!validateField("creditCvv")) return;
            if (!validateField("nameOnCard")) return;
        }

        // ── All valid — collect payload ──
        const payload = { action: "place_order", pay_method: payMethod };

        // Only collect user-input fields when not logged in (session handles the rest)
        if (!isLogged) {
            Object.assign(payload, {
                firstname: $("#inputFirstName").val().trim(),
                lastname: $("#inputLastName").val().trim(),
                email: $("#inputEmail").val().trim(),
                phonenumber: $("#inputPhone").val().trim(),
                address1: $("#inputAddress1").val().trim(),
                city: $("#inputCity").val().trim(),
                state: $("#inputState").val().trim(),
                postcode: $("#inputPostcode").val().trim(),
                country: $("#inputCountry").val().trim(),
                password: $("#inputNewPassword1").val(),
            });
        }

        if (payMethod === "waafi") {
            payload.waafi_phone = $("#inputWaafiPhone").val().trim();
        }

        if (payMethod === "creditcard") {
            payload.card_number = $("#cardNumber").val().replace(/\s/g, "");
            payload.card_expiry = $("#cardExpiry").val();
            payload.card_cvv = $("#creditCvv").val();
            payload.card_name = $("#nameOnCard").val().trim();
        }

        // ── Show processing overlay ──
        $(this).prop("disabled", true);
        showOrderStatus();

        $.post(API_URL, payload, null, "json")
            .done((res) => {
                const steps = Array.isArray(res.steps)
                    ? res.steps
                    : ["Processing your order…"];
                const redirect = res.redirect || null;

                if (res.status === "success") {
                    // Animate through backend steps then redirect
                    animateStatusSteps(steps, redirect);
                } else {
                    // Animate steps (if any), then hide overlay and show error
                    animateStatusSteps(steps, null);
                    setTimeout(
                        () => {
                            $(".order-status").removeClass("show");
                            $("#btnCompleteOrder").prop("disabled", false);

                            const errMsg =
                                res.message ||
                                "Something went wrong. Please try again.";
                            // Show error near payment section as a general error
                            $(".form-error.payment-meth")
                                .addClass("show")
                                .text(errMsg);
                            $("html, body").animate(
                                {
                                    scrollTop:
                                        $(".form-error.payment-meth").offset()
                                            .top - 120,
                                },
                                300,
                            );
                        },
                        steps.length * 1600 + 400,
                    );
                }
            })
            .fail(() => {
                $(".order-status").removeClass("show");
                $("#btnCompleteOrder").prop("disabled", false);
                $(".form-error.payment-meth")
                    .addClass("show")
                    .text(
                        "Network error. Please check your connection and try again.",
                    );
                $("html, body").animate(
                    {
                        scrollTop:
                            $(".form-error.payment-meth").offset().top - 120,
                    },
                    300,
                );
            });
    });
});
