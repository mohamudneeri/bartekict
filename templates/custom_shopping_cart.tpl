<div class="brtk-cart-page">
    
    {if $hasItems}
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
    {/if}

    <div class="cart-container">
        <div class="order-status">
            <div class="status-container">
                <span
                    ><i class="icon fa-solid fa-spinner fa-spin"></i
                ></span>
                <h3 class="title">Please wait while processing..</h3>
                <p class="text" id="processingText">
                    Lorem ipsum dolor sit amet consectetur
                </p>
            </div>
        </div>
        
        <div class="inner-element">

            {* ── EMPTY CART ──── *}
            {if !$hasItems}
            
            <div class="empty-cart cart-card">
                <span class="icon"><i class="fa-solid fa-cart-shopping"></i></span>
                <div class="details">
                    <h3 class="empty-title">Your cart is empty.</h3>
                    <p>Time to go shopping for low-priced domains and services at BARTEK!</p>
                </div>
                <div class="buttons-container">
                    <a class="brtk-button" href="https://bartekict.com/">
                        <span class="button-content">
                            <span class="button-text">Keep Shopping</span>
                        </span>
                    </a>
                </div>
            </div>
            {/if}

            {* ── CART CONTENT (only rendered when cart has items) ────────────── *}
            {if $hasItems}
            <div class="cart-wrapper">

                {* ── MAIN COLUMN ───────────────────── *}
                <div class="cart-content">
                    <div class="cart-header">
                        <h2>Your Cart</h2>
                        <input type="hidden" id="isLogged" value="{$isLoggedIn}" />
                    </div>

                    {* ── ACCOUNT CARD ──── *}
                    <div class="cart-card account">
                        <div class="cart-card-body">

                            {* ── LOGGED-IN STATE ──── *}
                            {if $isLoggedIn}
                            
                            <div class="cart-sub-head dynamic-header logged-user-header show">
                                <div class="cart-head-title">
                                    <h3>Your Account & Billing Info</h3>
                                </div>
                            </div>

                            <div class="dynamic-container container-logged-user show">
                                <dl class="content-row">
                                    <dt class="content-col term"><p>Account</p></dt>
                                    <dd class="content-col desc">
                                        <p>{$loggedUser.fullName|escape}</p>
                                        <p>{$loggedUser.email|escape}</p>
                                    </dd>
                                </dl>
                                <dl class="content-row second">
                                    <dt class="content-col term"><p>Billing</p></dt>
                                    <dd class="content-col desc">
                                        <p>{$loggedUser.address|escape}</p>
                                        <p>{$loggedUser.city|escape}{if $loggedUser.state}, {$loggedUser.state|escape}{/if}</p>
                                        <p>{$loggedUser.country|escape}</p>
                                    </dd>
                                </dl>
                            </div>

                            {* ── GUEST STATE (register / login toggle) ──── *}
                            {else}

                            {* Register header *}
                            <div class="cart-sub-head dynamic-header new-user-header">
                                <div class="cart-head-title">
                                    <h3>Personal Information</h3>
                                </div>
                                <div class="cart-head-info">
                                    <span>Have an account?</span>
                                    <a role="button" class="link" id="btnClientLogin">Log in</a>
                                </div>
                            </div>

                            {* Registration form *}
                            <div class="dynamic-container container-new-user">
                                <div class="form-row">
                                    <div class="form-field">
                                        <input type="text" class="input-text" id="inputFirstName" placeholder=" " />
                                        <span class="placeholder">First Name</span>
                                        <p class="form-error first-name"></p>
                                    </div>
                                    <div class="form-field">
                                        <input type="text" class="input-text" id="inputLastName" placeholder=" " />
                                        <span class="placeholder">Last Name</span>
                                        <p class="form-error last-name"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field email">
                                        <input type="email" class="input-text" id="inputEmail" placeholder=" " autocomplete="off" autocomplete="new-email" />
                                        <span class="placeholder">Email</span>
                                        <p class="form-error email"></p>
                                    </div>
                                    <div class="form-field phone">
                                        <div class="input-group">
                                            <div class="group-addon">
                                                <span class="group-text" id="inputPhoneCode">+999</span>
                                            </div>
                                            <input
                                                type="tel"
                                                id="inputPhone"
                                                class="input-text"
                                                placeholder="6XXXXXXXX"
                                            />
                                        </div>
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
                                        <input type="text" class="input-text" id="inputAddress1" placeholder=" " />
                                        <span class="placeholder">Street Address</span>
                                        <p class="form-error address"></p>
                                    </div>
                                    <div class="form-field city">
                                        <input type="text" class="input-text" id="inputCity" placeholder=" " />
                                        <span class="placeholder">City</span>
                                        <p class="form-error city"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field region">
                                        <input type="text" class="input-text" id="inputState" placeholder=" " />
                                        <span class="placeholder">State/Province/Region</span>
                                        <p class="form-error region"></p>
                                    </div>
                                    <div class="form-field zipcode">
                                        <input type="text" class="input-text" id="inputPostcode" placeholder=" " />
                                        <span class="placeholder">Zip/Postal Code</span>
                                        <p class="form-error zipcode"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <select class="drop-box" id="inputCountry">
                                            <option value="">Country</option>
                                            {foreach from=$countries item=country}
                                                <option 
                                                    value="{$country.code|escape}"
                                                    data-phonecode="{$country.phoneCode|escape}" 
                                                    {if $country.code eq $selectedCountry}selected{/if}
                                                >{$country.name|escape}</option>
                                            {/foreach}
                                        </select>
                                        <p class="form-error country"></p>
                                    </div>
                                </div>

                                <div class="cart-sub-head second">
                                    <div class="cart-head-title">
                                        <h3>Account Security</h3>
                                    </div>
                                </div>
                                <p class="form-hint">This password will access your account during login.</p>
                                <div class="form-row">
                                    <div class="form-field">
                                        <input type="password" class="input-text" id="inputNewPassword1" placeholder=" " />
                                        <span class="placeholder">Password</span>
                                        <p class="form-error new-password"></p>
                                    </div>
                                    <div class="form-field">
                                        <input type="password" class="input-text" id="inputNewPassword2" placeholder=" " />
                                        <span class="placeholder">Confirm Password</span>
                                        <p class="form-error confirm-password"></p>
                                    </div>
                                </div>
                            </div>

                            {* Login header — toggled in by JS *}
                            <div class="cart-sub-head dynamic-header login-user-header">
                                <div class="cart-head-title">
                                    <h3>Customer Login</h3>
                                </div>
                                <div class="cart-head-info">
                                    <span>Don't have an account?</span>
                                    <a role="button" class="link" id="btnClientRegister">Register</a>
                                </div>
                            </div>

                            {* Login form *}
                            <div class="dynamic-container container-user-login">
                                <div class="form-row">
                                    <div class="form-field">
                                        <input type="email" class="input-text" id="inputLoginEmail" placeholder=" " />
                                        <span class="placeholder">Email Address</span>
                                        <p class="form-error login-email"></p>
                                    </div>
                                    <div class="form-field">
                                        <input type="password" class="input-text" id="inputLoginPassword" placeholder=" " />
                                        <span class="placeholder">Password</span>
                                        <p class="form-error login-password"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <button type="button" class="brtk-button login-btn" id="btnCheckLogin" disabled>
                                        Login
                                    </button>
                                </div>
                            </div>

                            {/if} {* end guest/logged-in *}

                        </div>
                    </div>

                    {* ──── PAYMENT CARD ──── *}
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
                                        <input type="radio" name="pay_method" id="waafi" value="waafi" />
                                        <span class="circle"></span>
                                        <span class="pay-name">Waafi</span>
                                    </div>
                                    <div class="images">
                                        <div class="single waafi">
                                            <img src="{$WEB_ROOT}/templates/bartek/images/pay-method-waafi.webp" alt="Waafi" />
                                        </div>
                                    </div>
                                </label>

                                <label for="creditcard" class="single-method credit">
                                    <div class="check-wrapper">
                                        <input type="radio" name="pay_method" id="creditcard" value="creditcard" />
                                        <span class="circle"></span>
                                        <span class="pay-name">Credit/Debit Card</span>
                                    </div>
                                    <div class="images">
                                        <div class="single masters">
                                            <img src="{$WEB_ROOT}/templates/bartek/images/pay-method-master.webp" alt="Mastercard" />
                                        </div>
                                        <div class="single visa">
                                            <img src="{$WEB_ROOT}/templates/bartek/images/pay-method-visa.webp" alt="Visa" />
                                        </div>
                                        <div class="single ae">
                                            <img src="{$WEB_ROOT}/templates/bartek/images/pay-method-ae.webp" alt="Amex" />
                                        </div>
                                    </div>
                                </label>
                            </div>

                            {* ── Waafi fields *}
                            <div class="paymethod-fields waafi">
                                <div class="form-row">
                                    <div class="form-field waafi-phone">
                                        <div class="input-group">
                                            <div class="group-addon">
                                                <span class="group-text">+252</span>
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

                            {* ── Credit card fields *}
                            <div class="paymethod-fields creditcard">
                                <div class="form-row">
                                    <div class="form-field c-card-number">
                                        <input type="text" class="input-text" id="cardNumber" placeholder=" " />
                                        <span class="placeholder">Card Number</span>
                                        <p class="form-error card-number"></p>
                                    </div>
                                    <div class="form-field c-expiry">
                                        <input type="text" class="input-text" id="cardExpiry" placeholder=" " />
                                        <span class="placeholder">Expiry Date</span>
                                        <p class="form-error card-expiry"></p>
                                    </div>
                                    <div class="form-field c-cvv">
                                        <input type="text" class="input-text" id="creditCvv" placeholder=" " />
                                        <span class="placeholder">CVV</span>
                                        <p class="form-error card-cvv"></p>
                                    </div>
                                </div>
                                <div class="form-row">
                                    <div class="form-field">
                                        <input type="text" class="input-text" id="nameOnCard" placeholder=" " />
                                        <span class="placeholder">Name on Card</span>
                                        <p class="form-error name-on-card"></p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {* ──── SIDEBAR: Order Summary ──── *}
                <div class="cart-sidebar">
                    <div class="cart-header">
                        <h2>Order Summary</h2>
                    </div>
                    <div class="cart-card">
                        <div class="cart-card-body" id="sidebar-summary">

                            {* Product/hosting items *}
                            {foreach from=$cartProducts item=product}
                            <div class="cart-item" data-type="product" data-key="{$product.pid|escape}">
                                <div class="details">
                                    <h3 class="item-name">{$product.planName|escape}</h3>
                                    <div class="item-desc">
                                        <p>Shared Hosting</p>
                                    </div>
                                    <div class="item-duration">
                                        <select class="drop-box item-billing-cycle" data-type="product" data-key="{$product.pid|escape}">
                                            <option value="monthly"   {if $product.billingcycle == 'monthly'}selected{/if}>Monthly</option>
                                            <option value="quarterly" {if $product.billingcycle == 'quarterly'}selected{/if}>Quarterly</option>
                                            <option value="annually"  {if $product.billingcycle == 'annually'}selected{/if}>Annually</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="item-prices">
                                    <h3 class="price">${$product.price|default:'0.00'}</h3>
                                    <p class="renew-price">Renews at ${$product.renewPrice|default:'0.00'}</p>
                                </div>
                                <button class="remove-btn" title="Remove" data-type="product" data-key="{$product.pid|escape}">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                            {/foreach}
                            
                            {* Domain items *}
                            {foreach from=$cartDomains item=domain}
                            <div class="cart-item" data-type="domain" data-key="{$domain.domain|escape}">
                                <div class="details">
                                    <h3 class="item-name">{$domain.actionText|escape}</h3>
                                    <div class="item-desc">
                                        <p>{$domain.domain|escape}</p>
                                    </div>
                                    {if $domain.freedomain}
                                    <p class="item-free">Free for 1st year</p>
                                    {/if}
                                    
                                    {if !$domain.freedomain}
                                    <div class="item-duration">
                                        <select class="drop-box item-years" data-type="domain" data-key="{$domain.domain|escape}">
                                            {section name=yr loop=6 start=1}
                                            <option value="{$smarty.section.yr.index}" {if $smarty.section.yr.index == $domain.years}selected{/if}>
                                                {$smarty.section.yr.index} {if $smarty.section.yr.index == 1}year{else}years{/if}
                                            </option>
                                            {/section}
                                        </select>
                                    </div>
                                    {/if}
                                </div>
                                <div class="item-prices">
                                    <h3 class="price">${$domain.price|default:'0.00'}</h3>
                                    <p class="renew-price">Renews at ${$domain.renewPrice|default:'0.00'}</p>
                                </div>
                                <button class="remove-btn" title="Remove" data-type="domain" data-key="{$domain.domain|escape}">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                            {/foreach}


                            <div class="divider"></div>

                            <div class="total-row">
                                <span class="total-name">Subtotal</span>
                                <span class="total-price" id="cart-subtotal">${$subtotal}</span>
                            </div>
                            
                            {if $promoDiscount > 0}
                            <div class="total-row savings">
                                <div class="total-left">
                                    <span class="total-name">Promo code "{$promoCode}"</span>
                                    <button class="remove-promo-btn" title="Remove Promotion Code"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                                <span class="total-price" id="cart-savings">- ${$promoDiscount}</span>
                            </div>
                            {/if}

                            {if !$promoCode}
                            <div class="form-row promo-row">
                                <div class="form-field promo-code">
                                    <input type="text" class="input-text" id="inputPromoCode" placeholder=" " />
                                    <span class="placeholder">Enter Promo Code</span>
                                    <p class="form-error promo-code"></p>
                                </div>
                                <div class="form-field promo-btn">
                                    <button class="apply-promo-btn" disabled>Apply</button>
                                </div>
                            </div>
                            {/if}

                            <div class="divider"></div>

                            <div class="total-row grand">
                                <span class="total-name">Today's Total</span>
                                <span class="total-price" id="cart-grand-total">${$grandTotal}</span>
                            </div>

                            <div class="form-row">
                                <button type="button" class="brtk-button complete-btn" id="btnCompleteOrder">
                                    Submit Order
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

            </div>
            {/if}

        </div>
    </div>

</div>

<script src="{$WEB_ROOT}/templates/{$template}/js/brtk_helpers.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/custom_shopcart.js"></script>