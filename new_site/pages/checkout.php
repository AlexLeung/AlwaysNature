<?php
    function sizesList($table, $column) {
        $columnList = array_map(function($size) use ($table, $column) {
            if($table !== "") return "${table}.${column}_${size}";
            else return "${column}_${size}";
        }, ['large', 'medium', 'small']);
        return implode(", ", $columnList);
    }

    $prices = sizesList("", "price");
    $opp = "("."SELECT product_id, $prices, created_on FROM product_pricing ORDER BY created_on DESC) AS opp";
    
    $opp_prices = sizesList("opp", "price");
    $pp =  "("."SELECT opp.product_id, $opp_prices, opp.created_on FROM $opp GROUP BY product_id) AS pp";
    
    $pp_prices = sizesList("pp", "price");
    $sql = "SELECT p.id, p.name, p.sizes, $pp_prices FROM products AS p INNER JOIN $pp ON p.id = pp.product_id WHERE p.active='1'";

    $result = $mysqli_conn->query($sql);
    if(!$result) die("MySQL Error: " . $mysqli_conn->error);
    else if($result->num_rows == 0) die("No Products Listed");
    else {
        $items = [];
        while($itemRecord = $result->fetch_assoc()) {
            $item = new stdClass();
            $item->id = $itemRecord['id'];
            $item->sizes = $itemRecord['sizes'];
            $item->prices = (object)[
                'l' => $itemRecord['price_large'],
                'm' => $itemRecord['price_medium'],
                's' => $itemRecord['price_small']
            ];
            $item->title = $itemRecord['name'];
            array_push($items, $item);
        }
    }
?>
<div id="cart-display">
    <div>
        <div class="container">
            <div id="your-order">
                <span>Your Order:</span>
                <span class='order-summary'></span>
            </div>
            <div id="item-container"></div>
            <div id="totals-section">
                <div class="row ship-n-handle">
                    <div class="left-col">
                        Shipping & Handling
                    </div>
                    <div class="right-col shipping-cost">
                        $Price
                    </div>
                    <div class="mid-col">
                        <select class="shipping-type">
                            <option>
                                Standard
                            </option>
                            <option>
                                Expedited
                            </option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="left-col">
                        Tax
                    </div>
                    <div class="mid-col"></div>
                    <div class="right-col tax-cost" >
                        $Price
                    </div>
                </div>
                <div class="row">
                    <div class="left-col">
                        <strong>Total</strong>
                    </div>
                    <div class="mid-col"></div>
                    <div class="right-col total-cost">
                        <strong>$Price</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div id="shipping-info-display">
    <span class="comming-soon">
        <div>Checkout Comming Soon</div>
    </span>
    <div>
        <input type="text" placeholder="Full Name" />
        <input type="text" placeholder="Email" />
        <input type="text" placeholder="Repeat Email" />
        <div class="divider"></div>
        <input type="text" placeholder="Shipping Address Line 1" />
        <input type="text" placeholder="Shipping Address Line 2" />
        <div class="split-text">
            <input type="text" placeholder="City, State" />
            <input type="text" placeholder="Zip Code" />
        </div>
        <div class="divider" style="display:none"></div>
        <input type="text" style="display:none" placeholder="Card Number" />
        <div class="split-text" style="display:none">
            <input type="text" placeholder="Exp. Date" />
            <input type="text" placeholder="CVV" />
        </div>
        <div class="checkbox-holder">
            <input type="checkbox" id="billing-shipping-address-checkbox" checked />
            <label for="billing-shipping-address-checkbox">
                <div class="check"></div>
            </label>
            <span>
                Billing Address is same as Shipping Address
            </span>
        </div>
        <div class="billing-addr">
            <input type="text" placeholder="Billing Address Line 1" />
            <input type="text" placeholder="Billing Address Line 2" />
            <div class="split-text">
                <input type="text" placeholder="City, State" />
                <input type="text" placeholder="Zip Code" />
            </div>
        </div>
        <div class="error-box">
            Invalid Email
        </div>
        <form action="https://www.paypal.com/cgi-bin/webscr" method="post" class="place-order">
            <div class="submit-text">Place Order with</div>
            <div class="paypal-image"></div>
            <div class="cart-items">
                <input type="hidden" name="item_name_1" value="Little Bird M">
                <input type="hidden" name="amount_1" value="115.24">
                <input type="hidden" name="quantity_1" value="2">
                <input type="hidden" name="shipping_1" value="1.2">
            </div>
            <input type="hidden" name="cmd" value="_cart">
            <input type="hidden" name="upload" value="1">
            <input type="hidden" name="business" value="info@alwaysnature.com">
            <input type="hidden" name="currency_code" value="USD">
        </form>
        <!--<input type="button" class="place-order-button" value="Place Order" />-->
    </div>
</div>
<script>
    $(function() {
        'use strict';
        var cart = (<?php echo json_encode($cart); ?>);
        var itemsFromServer = (<?php echo json_encode($items); ?>);
        var items = {};
        for(var i in itemsFromServer) {
            var curItem = itemsFromServer[i];
            items[curItem.id] = curItem;
        }
        var sizes = {l:'large',m:'medium',s:'small'};
        var angleDownSvg = getAngleDown();
        var angleUpSvg = getAngleUp();
        var $shippingInfoDisplayContainer = $("#shipping-info-display > div");
        var $paypalSubmitForm = $shippingInfoDisplayContainer.find("> form.place-order");
        var $billingAddrContainer = $shippingInfoDisplayContainer.find("> .billing-addr");
        var $billingEqualsShippingCheckbox = $shippingInfoDisplayContainer.find("#billing-shipping-address-checkbox");
        var $cartDisplayContainer = $("#cart-display > div > .container");
        var $orderSummaryContainer = $cartDisplayContainer.find("> #your-order > .order-summary");
        var $itemsContainer = $cartDisplayContainer.find("> #item-container");
        var $totalsContainer = $cartDisplayContainer.find("> #totals-section");
        var $shippingType = $totalsContainer.find("> .row > .mid-col > select.shipping-type");
        var $shippingCostContainer = $totalsContainer.find("> .row > .right-col.shipping-cost");
        var $taxCostContainer = $totalsContainer.find("> .row > .right-col.tax-cost");
        var $totalCostContainer = $totalsContainer.find("> .row > .right-col.total-cost");
        $billingEqualsShippingCheckbox.change(renderBillingAddressContainer);
        renderBillingAddressContainer();
        renderItemsInCart();
        $shippingType.change(renderItemsInCart);
        $itemsContainer.on("click", "> .item > .detail-view > .row > .mid-col.quantity > .quantity-adjust > .active", function() {
            var $curControl = $(this);
            var size = $curControl.parent().attr('data-size');
            var id  = $curControl.parent().attr('data-id');
            if($curControl.hasClass("incr")) {
                $.ajax({
                    method: 'POST',
                    url: '/incrementItemInCart',
                    data: {
                        id: id,
                        size: size
                    },
                    success: function(data) {
                        if(JSON.parse(data).error) {
                            alert("Failed to Increment Item. Try refreshing the page.");
                        } else {
                            if(!cart[id]) cart[id] = {};
                            if(!cart[id][size]) cart[id][size] = 0; 
                            cart[id][size]++;
                            renderItemsInCart();
                        }
                    },
                    error: function() {
                        alert("Failed to Increment Item! Try refreshing the page.");
                    }
                });
            } else if($curControl.hasClass("decr")) {
                $.ajax({
                    method: 'POST',
                    url: '/decrementItemInCart',
                    data: {
                        id: id,
                        size: size
                    },
                    success: function(data) {
                        if(JSON.parse(data).error) {
                            alert("Failed to Decrement Item. Try refreshing the page.");
                        } else {
                            if(cart[id][size] && cart[id][size] > 1) {
                                cart[id][size]--;
                            } else {
                                delete cart[id][size];
                                if($.isEmptyObject(cart[id])) delete cart[id];
                            }
                            renderItemsInCart();
                        }
                    },
                    error: function() {
                        alert("Failed to Decrement Item! Try refreshing the page.");
                    }
                });
            } else {
                alert("Action unavailable. Try refreshing the page.");
            }
        });
        $paypalSubmitForm.click(function() {
            $paypalSubmitForm.trigger("submit");
        });
        console.log("cart below");
        console.log(cart);
        console.log("items below");
        console.log(items);
        function renderBillingAddressContainer() {
            var isChecked = $billingEqualsShippingCheckbox.is(":checked");
            if(!isChecked) $billingAddrContainer.addClass("active");
            else $billingAddrContainer.removeClass("active");
        }
        function renderItemsInCart() {
            $itemsContainer.find("> .item").remove();
            var html = "";
            var totalBags = 0;
            var totalPrice = 0;
            for(var i in cart) {
                var cartItem = cart[i];
                var itemObj = items[i];
                if(!itemObj) continue;
                html += getItemHTML(cartItem, itemObj);
                for(var size in cartItem) {
                    if(itemObj.sizes.indexOf(size) != -1) {
                        totalBags += cartItem[size];
                        totalPrice += itemObj.prices[size] * cartItem[size]; 
                    }
                }
            }
            $itemsContainer.html(html);
            var taxRate = .065;
            var shippingRates = {
                standard: 1,
                expedited: 2
            };
            var shippingType = $shippingType.val().toLowerCase();
            var shippingCost = priceFormat(totalBags * shippingRates[shippingType]);
            $shippingCostContainer.html("$"+shippingCost);
            totalPrice += shippingCost;
            var taxCost = priceFormat(totalPrice * taxRate);
            $taxCostContainer.html("$"+taxCost);
            totalPrice += taxCost;
            $totalCostContainer.html("$"+priceFormat(totalPrice));
            var totalFormattedPrice = priceFormat(totalPrice);
            $orderSummaryContainer.html(totalBags+" Bags, $"+totalFormattedPrice);
        }
        function priceFormat(price) {
            return parseFloat(parseFloat(Math.round(price * 100) / 100).toFixed(2));
        }
        function getItemHTML(cartItem, itemObj) {
            return (""
            +"<div class='item'>"
            +   "<div class='image-container' style='background-image:url(\"/img/prods/"+itemObj.id+"/cover.jpg\")'></div>"
            +   "<div class='detail-view'>"
            +       "<div class='row item-title active'>"
            +           "<div class='left-col'><strong>"+itemObj.title+"</strong></div>"
            +       "</div>"
            +       getItemSizeRowHTML('l', cartItem, itemObj)
            +       getItemSizeRowHTML('m', cartItem, itemObj)
            +       getItemSizeRowHTML('s', cartItem, itemObj)
            +   "</div>"
            +"</div>"
            );
            function getItemSizeRowHTML(size, cartItem, itemObj) {
                if(itemObj.sizes.indexOf(size) == -1)
                return (""
                +"<div class='row'>"
                +   "<div class='left-col'>"+capString(sizes[size])+"</div>"
                +   "<div class='right-col'>&nbsp;</div>"
                +   "<div class='mid-col'>size not sold</div>"
                +"</div>"
                );
                var quantity = cartItem[size] || 0;
                var price = itemObj.prices[size] * quantity;
                return (""
                +"<div class='row "+sizes[size]+" active'>"
                +   "<div class='left-col size-col'>"+capString(sizes[size])+"</div>"
                +   "<div class='right-col'>"+(price ? "$"+priceFormat(price) : '&nbsp;')+"</div>"
                +   "<div class='mid-col quantity'>"
                +       "<div>Quantity: "+quantity+"</div>"
                +       "<div class='quantity-adjust' data-id='"+itemObj.id+"' data-size='"+size+"'>"
                +           "<div class='incr active'>"+getAngleUp()+"</div>"
                +           "<div class='decr"+(quantity? " active" : "")+"'>"+getAngleDown()+"</div>"
                +       "</div>"
                +   "</div>"
                +"</div>"
                );
                function capString(str) {
                    return str[0].toUpperCase() + str.slice(1);
                }
            }
        }
        function getAngleDown() {
            return (""
            +"<svg width='25' height='12' viewBox='0 0 1792 1792'>"
            +   "<path d='M1395 736q0 13-10 23l-466 466q-10 10-23 10t-23-10l-466-466q-10-10-10-23t10-23l50-50q10-10 23-10t23 10l393 393 393-393q10-10 23-10t23 10l50 50q10 10 10 23z'></path>"
            +"</svg>"
            );
        }
        function getAngleUp() {
            return (""
            +"<svg width='25' height='12' viewBox='0 0 1792 1792'>"
            +   "<path d='M1395 1184q0 13-10 23l-50 50q-10 10-23 10t-23-10l-393-393-393 393q-10 10-23 10t-23-10l-50-50q-10-10-10-23t10-23l466-466q10-10 23-10t23 10l466 466q10 10 10 23z'></path>"
            +"</svg>"
            );
        }
    });
</script>