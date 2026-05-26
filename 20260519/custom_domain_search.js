$(document).ready(function () {
    // DOM elements
    const $cartHeader = $(".bartek-header");
    const $searchForm = $("#brtkDomainSearchForm");
    const $domainInput = $("#domainInput");
    const $resultContainer = $(".result-container");
    const $checkingStatus = $(".checking-status");
    const $availableBlock = $(".result-wrapper.available");
    const $takenBlock = $(".result-wrapper.taken");
    const $suggestionsWrapper = $(".suggestions-wrapper");
    const $suggestionsContent = $suggestionsWrapper.find(".content");
    const $cartSticky = $(".cart-sticky-wrapper");
    const $domainSearchContainer = $(".brtk-domain-search-p");

    // State
    let currentDomain = "";

    // Update cart UI (count, total, sticky visibility)
    function updateCartUI(count, totalFormatted) {
        if (count > 0) {
            $cartSticky.addClass("show");
            $domainSearchContainer.addClass("has-cart-sticky");
            $cartSticky
                .find(".count-items")
                .text(count + (count === 1 ? " item" : " items"));
            $cartSticky.find(".total-price").text(totalFormatted);

            $cartHeader.find(".cart-btn").addClass("has-cart");
            $cartHeader.find(".cart-counter").text(count);
        } else {
            $cartSticky.removeClass("show");
            $domainSearchContainer.removeClass("has-cart-sticky");
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
                } else {
                    alert(
                        "Error adding to cart: " +
                            (res.message || "Unknown error"),
                    );
                    $btn.find(".button-text").text(originalText);
                    $btn.prop("disabled", false);
                }
            },
            "json",
        ).fail(() => {
            alert("Request failed");
            $btn.find(".button-text").text(originalText);
            $btn.prop("disabled", false);
        });
    }

    // Render search results
    function renderResults(data) {
        // Hide checking status
        hideWithClass($checkingStatus);

        // Handle main domain availability
        if (data.status === "available") {
            hideWithClass($takenBlock);
            showWithClass($availableBlock);
            $availableBlock.find(".domain-name").text(data.domain);
            $availableBlock.find(".domain-full").text(data.domain);
            $availableBlock
                .find(".price-div .number")
                .text("$" + data.registerPrice);

            // Bind add to cart for main domain
            const $addBtn = $availableBlock.find(".add-cart");
            const $btnText = $addBtn.find(".button-text");

            if (data.inCart) {
                $btnText.text("Added");
                $addBtn.prop("disabled", true);
                $addBtn.addClass("added");
            } else {
                $btnText.text("Add to cart");
                $addBtn.prop("disabled", false);
                $addBtn.removeClass("added");
                $addBtn.off("click").on("click", function () {
                    addToCart(data.domain, "register", $(this));
                });
            }
        } else {
            hideWithClass($availableBlock);
            showWithClass($takenBlock);
            $takenBlock.find(".domain-name").text(data.domain);
        }

        // Render suggestions
        if (data.suggestions && data.suggestions.length > 0) {
            $suggestionsContent.empty();
            data.suggestions.forEach((sug) => {
                const suggestionHtml = `
                    <div class="single-suggest">
                        <div class="domain-info">
                            <span>${escapeHtml(sug.domain)}</span>
                        </div>
                        <div class="price-div">
                            <span class="number">$${sug.price}</span>
                            <span class="term text">/year</span>
                        </div>
                        <button class="brtk-button add-cart" type="button" ${sug.inCart ? "disabled" : ""}>
                            <span class="button-content">
                                <span><i class="fa-solid fa-cart-plus"></i></span>
                                <span class="button-text">${sug.inCart ? "Added" : "Add to cart"}</span>
                            </span>
                        </button>
                    </div>
                `;
                const $sugItem = $(suggestionHtml);

                // Only bind click event if not already in cart
                if (!sug.inCart) {
                    $sugItem.find(".add-cart").on("click", function () {
                        addToCart(sug.domain, "register", $(this));
                    });
                } else {
                    $sugItem.find(".add-cart").addClass("added");
                }

                $suggestionsContent.append($sugItem);
            });
            // $suggestionsWrapper.show();
        } else {
            // $suggestionsWrapper.hide();
        }

        // Show result container
        $resultContainer.addClass("show");
    }

    // Perform domain search
    function searchDomain(domain) {
        if (!domain || domain.trim() === "") return;
        currentDomain = domain.trim();
        $domainInput.val(currentDomain);

        // Reset UI
        hideWithClass($availableBlock);
        hideWithClass($takenBlock);
        // $suggestionsWrapper.hide();
        showWithClass($checkingStatus);
        showWithClass($resultContainer);

        $(".search-button").addClass("checking");
        $(".search-button").prop("disabled", true);

        $.ajax({
            url: "includes/api/custom_domain_search.php",
            method: "POST",
            data: { domain: currentDomain },
            dataType: "json",
        })
            .done(function (res) {
                if (res.status === "error") {
                    alert("Search failed: Invalid domain");
                    hideWithClass($checkingStatus);
                    return;
                }
                renderResults(res);
            })
            .fail(function () {
                showError("Search request failed.");
                hideWithClass($checkingStatus);
            })
            .always(function () {
                $(".search-button").removeClass("checking");
                $(".search-button").prop("disabled", false);
            });
    }

    // Smooth scroll to search container
    function scrollToSearchContainer() {
        const $searchContainer = $(".search-container");
        if ($searchContainer.length) {
            $searchContainer[0].scrollIntoView({
                behavior: "smooth",
                block: "start",
            });
        }
    }

    // Event: form submit
    $searchForm.on("submit", function (e) {
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
        searchDomain(domain);
    });

    // Error display function
    function showError(message) {
        $(".search-error").addClass("show");
        $(".search-error .text").html(message);
    }

    // Event: "Check Availability" buttons for popular TLDs
    $(".check-btn").on("click", function () {
        const tld = $(this).data("tld");
        let currentValue = $domainInput.val().trim();
        let sld = "";
        if (currentValue.includes(".")) {
            sld = currentValue.split(".")[0];
        } else {
            sld = currentValue;
        }
        if (sld === "") {
            sld = "mydomain";
        }
        const newDomain = sld + tld;
        $domainInput.val(newDomain);

        // Scroll to search container before searching
        scrollToSearchContainer();

        searchDomain(newDomain);
    });

    // Auto-search if ?domain= in URL
    const urlParams = new URLSearchParams(window.location.search);
    const urlDomain = urlParams.get("domain");
    if (urlDomain) {
        $domainInput.val(urlDomain);

        // Scroll to search container before searching
        scrollToSearchContainer();

        searchDomain(urlDomain);
    }

    // Initial cart sync
    // syncCart();
});
