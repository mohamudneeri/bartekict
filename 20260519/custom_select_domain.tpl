<div class="brtk-sel-domainp">
    <div class="brtk-subhero">
        <div class="inner-element">
            <div class="hero-heading-3">
                <h3 class="title">Domain Configuration</h3>
            </div>
            <div class="hero-heading-1">
                <h1 class="title">
                    Connect a <span>domain</span> to your hosting
                </h1>
            </div>
        </div>
    </div>

    <div class="choose-container">
        <div class="inner-element">
            <div class="choose-wrapper">

                <!-- LEFT SECTION - Hosting Plan Display -->
                <div class="left-section">
                    <div class="image-element">
                        <img
                            loading="lazy"
                            decoding="async"
                            src="{$selectedPlan.image}"
                            alt="{$selectedPlan.name}"
                        />
                    </div>

                    <div class="heading">
                        <h5 class="heading-title">{$selectedPlan.name}</h5>
                    </div>
                    
                    <div class="text-editor">
                        <p>{$selectedPlan.description}</p>
                    </div>

                    <div class="price-wrapper">
                        <h2 class="price-value">${$selectedPlan.price}</h2>
                        <p class="price-period">/{$selectedPlan.cycle|lower}</p>

                        <span class="discount-info">Save 0%</span>
                    </div>

                    <div class="divider"></div>

                    <ul class="list-wrapper">
                        {foreach from=$selectedPlan.features|default:[] item=feature}
                            <li class="list-item">
                                <span class="list-icon"></span>
                                <span class="list-text">{$feature}</span>
                            </li>
                        {/foreach}
                    </ul>
                </div>

                <!-- RIGHT SECTION - Domain Selection -->
                <div class="content-section">
                    <div class="domain-types">
                        {if $hasCartDomains}
                            <label for="cartdomain" class="single-dtype">
                                <input type="radio" name="domain_type" id="cartdomain" value="cartdomain">
                                <span class="circle"></span>
                                <span class="type-desc">Use a domain in cart</span>
                            </label>
                        {/if}

                        <label for="newdomain" class="single-dtype">
                            <input type="radio" name="domain_type" id="newdomain" value="newdomain" {if !$hasCartDomains}checked{/if}>
                            <span class="circle"></span>
                            <span class="type-desc">Register a new domain</span>
                        </label>

                        <label for="transferdomain" class="single-dtype">
                            <input type="radio" name="domain_type" id="transferdomain" value="transferdomain">
                            <span class="circle"></span>
                            <span class="type-desc">Transfer your domain from another registrar</span>
                        </label>

                        <label for="owndomain" class="single-dtype">
                            <input type="radio" name="domain_type" id="owndomain" value="owndomain">
                            <span class="circle"></span>
                            <span class="type-desc">I will use my existing domain and update my nameservers</span>
                        </label>
                    </div>

                    <div class="type-content">
                        <!-- Panel 1: Cart Domain -->
                        <div class="domain-panel" id="domainInCartPanel">
                            <h3 class="panel-title">Domain from Cart</h3>
                            <p class="panel-desc text">Select one of the domains currently in your cart and connect it instantly to your hosting package.</p>
                            <div class="panel-form">
                                <div class="form-row">
                                    <div class="form-field">
                                        <select id="inputInCartDomain" class="drop-box cart-domain">
                                            <option value="">Select a domain...</option>
                                            {foreach from=$cartDomains item=domain}
                                                <option value="{$domain.domain}">{$domain.domain}</option>
                                            {/foreach}
                                        </select>
                                        <p class="form-error err-cart-domain"></p>
                                    </div>
                                    <div class="form-field">
                                        <button type="button" class="brtk-button" id="btnUseCartDomain">Use</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Panel 2: New Domain Registration -->
                        <div class="domain-panel" id="newDomainPanel">
                            <h3 class="panel-title">Search Your New Domain</h3>
                            <p class="panel-desc text">Enter your preferred domain name and check availability in real time before continuing.</p>
                            <div class="panel-form">
                                <div class="form-row">
                                    <div class="form-field domain-field">
                                        <div class="input-group">
                                            <div class="group-addon"><span class="group-text">www.</span></div>
                                            <input type="text" id="inputNewDomain" class="input-text" autocapitalize="none" placeholder="example">
                                        </div>
                                        <p class="form-error err-new-domain"></p>
                                    </div>
                                    <div class="form-field tld-field">
                                        <div class="input-group">
                                            <select id="inputNewDomainTld" class="drop-box tld-input">
                                                {foreach from=$registerTlds item=tld}
                                                    <option value="{$tld}">{$tld}</option>
                                                {/foreach}
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row second buttons">
                                    <button type="button" class="brtk-button" id="btnCheckAvailability">Check Availability</button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel 3: Transfer Domain -->
                        <div class="domain-panel" id="transferDomainPanel">
                            <h3 class="panel-title">Transfer domain from another registrar</h3>
                            <p class="panel-desc text">Enter your domain name and authorization (EPP) code to start the transfer process.</p>
                            <div class="panel-form">
                                <div class="form-row">
                                    <div class="form-field domain-field">
                                        <div class="input-group">
                                            <div class="group-addon"><span class="group-text">www.</span></div>
                                            <input type="text" id="inputTrnsfrDomain" class="input-text" autocapitalize="none" placeholder="example">
                                        </div>
                                        <p class="form-error err-transfer-domain"></p>
                                    </div>
                                    <div class="form-field tld-field">
                                        <div class="input-group">
                                            <select id="inputTrnsfrDomainTld" class="drop-box tld-input">
                                                {foreach from=$transferTlds item=tld}
                                                    <option value="{$tld}">{$tld}</option>
                                                {/foreach}
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-row second">
                                    <div class="form-field domain-field">
                                        <div class="input-group">
                                            <div class="group-addon"><span class="group-text">EPP / Auth Code</span></div>
                                            <input type="text" id="inputAuthCode" class="input-text">
                                        </div>
                                        <p class="form-error err-transfer-epp-code"></p>
                                    </div>
                                </div>
                                <div class="form-row second buttons">
                                    <button type="button" class="brtk-button" id="btnTransferDomain">Transfer</button>
                                </div>
                            </div>
                        </div>

                        <!-- Panel 4: Use Own Domain -->
                        <div class="domain-panel" id="useOwnDomainPanel">
                            <h3 class="panel-title">Use my existing domain</h3>
                            <p class="panel-desc text">Enter the domain you already own and update its nameservers to connect it to your hosting account.</p>
                            <div class="panel-form">
                                <div class="form-row">
                                    <div class="form-field domain-field">
                                        <div class="input-group">
                                            <div class="group-addon"><span class="group-text">www.</span></div>
                                            <input type="text" id="inputOwnDomain" class="input-text" autocapitalize="none" placeholder="example.com">
                                        </div>
                                        <p class="form-error err-own-domain"></p>
                                    </div>
                                    <div class="form-field second buttons">
                                        <button type="button" class="brtk-button" id="btnUseOwnDomain">Use</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="{$WEB_ROOT}/templates/{$template}/js/brtk_helpers.js"></script>
<script src="{$WEB_ROOT}/templates/{$template}/js/custom_select_domain.js"></script>