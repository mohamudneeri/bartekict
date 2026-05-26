<div class="brtk-cart-page">
    <div class="brtk-subhero">
        <div class="inner-element">
            <div class="hero-heading-3">
                <h3 class="title">Review & Checkout</h3>
            </div>
            <div class="hero-heading-1">
                <h1 class="title">
                    Final Step to <span>Launch</span> Your Online Presence.
                </h1>
            </div>
        </div>
    </div>

    <div class="cart-container">
        <div class="inner-element">
            <!-- <div class="empty-cart cart-card"> -->

            {php}
            $isEmpty = $this->get_template_vars('cartEmpty');

            if ($isEmpty) {
            echo '<div class="empty-cart cart-card">';
            }
            {/php}
                <span class="icon"
                    ><i class="fa-solid fa-cart-shopping"></i
                ></span>
                <div class="details">
                    <h3 class="empty-title">Your cart is empty.</h3>
                    <p>
                        Time to go shopping for low-priced domains and services
                        at BARTEK!
                    </p>
                </div>
                <div class="buttons-container">
                    <a class="brtk-button" href="https://bartekict.com/">
                        <span class="button-content">
                            <span class="button-text">Keep Shopping</span>
                        </span>
                    </a>
                </div>
            {php}
            if ($isEmpty) {
            echo '</div>';
            }
            {/php}
            <!-- </div> -->

            <!-- CONTENT AREA -->
            <div class="cart-wrapper">
                <div class="cart-content">
                    <div class="cart-header">
                        <h2>Your Cart</h2>
                    </div>

                    <div class="cart-card account">
                        <div class="cart-card-body">
                            <div
                                class="cart-sub-head dynamic-header new-user-header"
                            >
                                <div class="cart-head-title">
                                    <h3>Personal Information</h3>
                                </div>
                                <div class="cart-head-info">
                                    <span>Have an account?</span>
                                    <a
                                        role="button"
                                        class="link"
                                        id="btnClientLogin"
                                        >Log in</a
                                    >
                                </div>
                            </div>

                            <div class="dynamic-container container-new-user">
                                <div class="form-row">
                                    <div class="form-field">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputFirstName"
                                        />
                                        <span class="placeholder"
                                            >First Name</span
                                        >
                                        <p class="form-error first-name"></p>
                                    </div>
                                    <div class="form-field">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputLastName"
                                        />
                                        <span class="placeholder"
                                            >Last Name</span
                                        >
                                        <p class="form-error last-name"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field email">
                                        <input
                                            type="email"
                                            class="input-text"
                                            id="inputEmail"
                                        />
                                        <span class="placeholder">Email</span>
                                        <p class="form-error email"></p>
                                    </div>
                                    <div class="form-field phone">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputPhone"
                                        />
                                        <span class="placeholder"
                                            >Phone Number</span
                                        >
                                        <p class="form-error phone-num"></p>
                                    </div>
                                </div>

                                <div class="cart-sub-head second">
                                    <div class="cart-head-title">
                                        <h3>Billing Address</h3>
                                    </div>
                                </div>

                                <div class="form-row">
                                    <div class="form-field address">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputAddress1"
                                        />
                                        <span class="placeholder"
                                            >Street Address</span
                                        >
                                        <p class="form-error address"></p>
                                    </div>
                                    <div class="form-field city">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputCity"
                                        />
                                        <span class="placeholder">City</span>
                                        <p class="form-error city"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field region">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputState"
                                        />
                                        <span class="placeholder"
                                            >State/Province/Region</span
                                        >
                                        <p class="form-error region"></p>
                                    </div>
                                    <div class="form-field zipcode">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="inputPostcode"
                                        />
                                        <span class="placeholder"
                                            >Zip/Postal Code</span
                                        >
                                        <p class="form-error zipcode"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <select
                                            class="drop-box"
                                            id="inputCountry"
                                        >
                                            <option value="">Country</option>
                                            <option value="so">Somalia</option>
                                            <option value="ke">Kenya</option>
                                        </select>
                                        <p class="form-error country"></p>
                                    </div>
                                </div>

                                <div class="cart-sub-head second">
                                    <div class="cart-head-title">
                                        <h3>Account Security</h3>
                                    </div>
                                </div>
                                <p class="form-hint">
                                    This password will access your account
                                    during login.
                                </p>
                                <div class="form-row">
                                    <div class="form-field">
                                        <input
                                            type="password"
                                            class="input-text"
                                            id="inputNewPassword1"
                                        />
                                        <span class="placeholder"
                                            >Password</span
                                        >
                                        <p class="form-error new-password"></p>
                                    </div>
                                    <div class="form-field">
                                        <input
                                            type="password"
                                            class="input-text"
                                            id="inputNewPassword2"
                                        />
                                        <span class="placeholder"
                                            >Confirm Password</span
                                        >
                                        <p
                                            class="form-error confirm-password"
                                        ></p>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="cart-sub-head dynamic-header login-user-header"
                            >
                                <div class="cart-head-title">
                                    <h3>Customer Login</h3>
                                </div>
                                <div class="cart-head-info">
                                    <span>Don't have an account?</span>
                                    <a
                                        role="button"
                                        class="link"
                                        id="btnClientRegister"
                                        >Register</a
                                    >
                                </div>
                            </div>

                            <div class="dynamic-container container-user-login">
                                <div class="form-row">
                                    <div class="form-field">
                                        <input
                                            type="email"
                                            class="input-text"
                                            id="inputLoginEmail"
                                        />
                                        <span class="placeholder"
                                            >Email Address</span
                                        >
                                        <p class="form-error login-email"></p>
                                    </div>
                                    <div class="form-field">
                                        <input
                                            type="password"
                                            class="input-text"
                                            id="inputLoginPassword"
                                        />
                                        <span class="placeholder"
                                            >Password</span
                                        >
                                        <p
                                            class="form-error login-password"
                                        ></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <button
                                        type="button"
                                        class="brtk-button login-btn"
                                        id="btnCheckLogin"
                                        disabled
                                    >
                                        Login
                                    </button>
                                </div>
                            </div>

                            <div
                                class="cart-sub-head dynamic-header logged-user-header"
                            >
                                <div class="cart-head-title">
                                    <h3>Your Account & Billing Info</h3>
                                </div>
                            </div>

                            <div
                                class="dynamic-container container-logged-user"
                            >
                                <dl class="content-row">
                                    <dt class="content-col term">
                                        <p>Account</p>
                                    </dt>
                                    <dd class="content-col desc">
                                        <p>Personal Name</p>
                                        <p>email@website.com</p>
                                    </dd>
                                </dl>
                                <dl class="content-row second">
                                    <dt class="content-col term">
                                        <p>BIlling</p>
                                    </dt>
                                    <dd class="content-col desc">
                                        <p>Street address</p>
                                        <p>City, State</p>
                                        <p>Country</p>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="cart-card payment">
                        <div class="cart-card-body">
                            <div class="cart-sub-head">
                                <div class="cart-head-title">
                                    <h3>Payment Information</h3>
                                </div>
                            </div>

                            <p class="form-error payment-meth"></p>

                            <div class="cartpay-methods">
                                <label for="waafi" class="single-method">
                                    <div class="check-wrapper">
                                        <input
                                            type="radio"
                                            name="pay_method"
                                            id="waafi"
                                            value="waafi"
                                        />
                                        <span class="circle"></span>
                                        <span class="pay-name">Waafi</span>
                                    </div>
                                    <div class="images">
                                        <div class="single waafi">
                                            <img
                                                src="https://billing.bartekict.com/templates/bartek/images/pay-method-waafi.webp"
                                                alt=""
                                            />
                                        </div>
                                    </div>
                                </label>
                                <label
                                    for="creditcard"
                                    class="single-method credit"
                                >
                                    <div class="check-wrapper">
                                        <input
                                            type="radio"
                                            name="pay_method"
                                            id="creditcard"
                                            value="creditcard"
                                        />
                                        <span class="circle"></span>
                                        <span class="pay-name"
                                            >Credit/Debit Card</span
                                        >
                                    </div>
                                    <div class="images">
                                        <div class="single masters">
                                            <img
                                                src="https://billing.bartekict.com/templates/bartek/images/pay-method-master.webp"
                                                alt=""
                                            />
                                        </div>
                                        <div class="single visa">
                                            <img
                                                src="https://billing.bartekict.com/templates/bartek/images/pay-method-visa.webp"
                                                alt=""
                                            />
                                        </div>
                                        <div class="single ae">
                                            <img
                                                src="https://billing.bartekict.com/templates/bartek/images/pay-method-ae.webp"
                                                alt=""
                                            />
                                        </div>
                                    </div>
                                </label>
                            </div>

                            <div class="paymethod-fields waafi">
                                <div class="form-row">
                                    <div class="form-field waafi-phone">
                                        <div class="input-group">
                                            <div class="group-addon">
                                                <span class="group-text"
                                                    >+252</span
                                                >
                                            </div>
                                            <input
                                                type="text"
                                                id="inputWaafiPhone"
                                                class="input-text"
                                                autocapitalize="none"
                                                placeholder="6XXXXXXXX"
                                            />
                                        </div>
                                        <p class="form-error waafi-number"></p>
                                    </div>
                                </div>
                            </div>

                            <div class="paymethod-fields creditcard">
                                <div class="form-row">
                                    <div class="form-field c-card-number">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="cardNumber"
                                        />
                                        <span class="placeholder"
                                            >Card Number</span
                                        >
                                        <p class="form-error card-number"></p>
                                    </div>
                                    <div class="form-field c-expiry">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="cardExpiry"
                                        />
                                        <span class="placeholder"
                                            >Expiry Date</span
                                        >
                                        <p class="form-error card-expiry"></p>
                                    </div>
                                    <div class="form-field c-cvv">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="creditCvv"
                                        />
                                        <span class="placeholder">CVV</span>
                                        <p class="form-error card-cvv"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <input
                                            type="text"
                                            class="input-text"
                                            id="nameOnCard"
                                        />
                                        <span class="placeholder"
                                            >Name on Card</span
                                        >
                                        <p class="form-error name-on-card"></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- SIDEBAR: Order summary -->
                <div class="cart-sidebar">
                    <div class="cart-header">
                        <h2>Order Summary</h2>
                    </div>
                    <div class="cart-card">
                        <div class="cart-card-body" id="sidebar-summary">
                            <div class="cart-item">
                                <div class="details">
                                    <h3 class="item-name">
                                        Domain Registration
                                    </h3>
                                    <div class="item-desc">
                                        <p>mohamudneeri.com</p>
                                    </div>
                                    <div class="item-duration">
                                        <select class="drop-box" id="">
                                            <option>1 year</option>
                                            <option>2 years</option>
                                            <option>3 years</option>
                                            <option>4 years</option>
                                            <option>5 years</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="item-prices">
                                    <h3 class="price">0.00</h3>
                                    <p class="renew-price">Renews at $0.00</p>
                                </div>
                                <button class="remove-btn" title="Remove">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>

                            <div class="cart-item">
                                <div class="details">
                                    <h3 class="item-name">
                                        Shared Web Hosting
                                    </h3>
                                    <div class="item-desc">
                                        <p>Starter Plan</p>
                                    </div>
                                    <div class="item-duration">
                                        <select class="drop-box" id="">
                                            <option>1 year</option>
                                            <option>2 years</option>
                                            <option>3 years</option>
                                            <option>4 years</option>
                                            <option>5 years</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="item-prices">
                                    <h3 class="price">$0.00</h3>
                                    <p class="renew-price">Renews at $0.00</p>
                                </div>
                                <button class="remove-btn" title="Remove">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>

                            <div class="divider"></div>

                            <div class="total-row">
                                <span class="total-name">Subtotal</span>
                                <span class="total-price">$0.00</span>
                            </div>
                            <div class="total-row savings">
                                <span class="total-name">Total Savings</span>
                                <span class="total-price">- $0.00</span>
                            </div>

                            <div class="form-row promo-row">
                                <div class="form-field promo-code">
                                    <input type="text" class="input-text" />
                                    <span class="placeholder"
                                        >Enter Promo Code</span
                                    >
                                </div>
                                <div class="form-field promo-btn">
                                    <button class="apply-promo-btn" disabled>
                                        Apply
                                    </button>
                                </div>
                            </div>

                            <div class="divider"></div>

                            <div class="total-row grand">
                                <span class="total-name">Today's Total</span>
                                <span class="total-price">$0.00</span>
                            </div>

                            <div class="form-row">
                                <button
                                    type="button"
                                    class="brtk-button complete-btn"
                                    id="btnCompleteOrder"
                                >
                                    Submit Order
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script
    src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo="
    crossorigin="anonymous"
></script>
<script src="../assets/js/brtk_helpers.js"></script>
<script src="../assets/js/custom_shopcart.js"></script>
