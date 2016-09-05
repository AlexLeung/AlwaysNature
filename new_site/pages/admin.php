<?php 
    $signedIn = isset($_SESSION['admin_expire']) && time() < $_SESSION['admin_expire'];
?>
<div id="admin-login" <?php echo ($signedIn ? "" : "class='active'"); ?>>
    <form>
        <input placeholder="Password 1" type="password" name="pass1" />
        <input placeholder="Password 2" type="password" name="pass2" />
        <div class="g-recaptcha" data-sitekey="<?php echo RECAPTCHA_CLIENT ?>"></div>
        <input type="submit" value="Submit" class='admin-login-submit'/>
    </form>
</div>
<div id="admin-container" <?php echo ($signedIn ? "class='active'" : ""); ?>>
    <h1>Admin Control Panel</h1>
    <div id="sign-out-container">
        <input type="button" value="Sign Out" />
    </div>
    <div class='maintenance-change-container'>
        <span>Maintenance Mode:</span>
        <span class="maintenance-mode-text">OFF</span>
        <span class="vertical-bar"></span>
        <input type="button" class='change' value="Turn On" /> 
    </div>
    <h2 class='products-title'>Products:</h2>
    <input id="new-item-button" type="button" value="New Item" />
    <div id="item-container"></div>
</div>
<script>
    $(function() {
        'use strict';
        var $adminLogin = $("#admin-login");
        var $adminContainer = $("#admin-container");
        var inMaintenance = false;
        var $productsTitle = $adminContainer.find("> .products-title");
        var $signOutButton = $adminContainer.find("> #sign-out-container > input");
        var $maintenanceText = $adminContainer.find("> .maintenance-change-container > .maintenance-mode-text");
        var $maintenanceChangeButton = $adminContainer.find("> .maintenance-change-container > input");
        var $itemContainer = $adminContainer.find("> #item-container");
        var $newItemButton = $adminContainer.find("> input#new-item-button");
        <?php if($signedIn) echo "signInSuccessful();"; ?>
        // Login
        $adminLogin.find("> form").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                method: 'POST',
                url: "/admin/signIn",
                data: {
                    pass1: $(this).find("> input[name='pass1']").val(),
                    pass2: $(this).find("> input[name='pass2']").val(),
                    grecaptcha: grecaptcha.getResponse()
                },
                success: function(data) {
                    var parsedData = JSON.parse(data);
                    if(parsedData.error) alert("Failed to Sign In. Error: " + parsedData.error);
                    else signInSuccessful();
                },
                error: function() {
                    alert("Failed to Sign In! Try refreshing the page.");
                }
            });
        });
        // Logout
        $signOutButton.on("click", function() {
            makeAdminAPICall({
                endpoint: "signOut",
                success: signOut,
                error: function(errorMessage) {
                    alert("Failed to sign out. Error: " + errorMessage);
                }
            });
        });
        // Change Maintenance Status 
        $maintenanceChangeButton.on("click", function() {
            makeAdminAPICall({
                endpoint: 'setMaintenanceStatus',
                data: {maintenance: !inMaintenance},
                success: function(data) {
                    inMaintenance = data.maintenance;
                    updateMaintenanceBox();
                }
            });
        });
        // Adding a new item
        $newItemButton.click(function() {
            makeAdminAPICall({
                endpoint: "addItem",
                success: function() {
                    signInSuccessful();
                    $("html, body").animate({ scrollTop: $(document).height() }, "slow");
                },
                error: function(errorMessage) {
                    alert("Unable to create new item. Error: " + errorMessage);
                }
            });
        });
        // Changing name of an item
        $itemContainer.on("click", "> .item > .name-container > input[type='button']", function() {
            var $curButton = $(this);
            var $parent = $curButton.parent();
            if($curButton.val() == "Edit") {
                $parent.addClass("editing");
                $parent.find("> input[type='text']").val($parent.find("> span.not-editing").html());
            } else if($curButton.val() == "Cancel") {
                $parent.removeClass("editing");
            } else if($curButton.val() == "Submit") {
                var newName = $parent.find("> input[type='text']").val();
                var id = parseInt($parent.parent().attr("data-id"));
                makeAdminAPICall({
                    endpoint: 'setItemName',
                    data: {
                        id: id,
                        name: newName,
                    },
                    success: function(data) {
                        $parent.find("> span.not-editing").html(newName);
                        $parent.removeClass("editing");
                    },
                    error: function(errorMessage) { 
                        alert("Unable to set item name. Error: " + errorMessage);
                    }
                });
            }
        });
        // Activating or deactiving an item
        $itemContainer.on("click", "> .item > .activate-deactivate-container > input", function() {
            var $button = $(this);
            var $actDeactContainer = $button.parent();
            var $statusContainer = $actDeactContainer.find("> span.status");
            var $item = $actDeactContainer.parent();
            var id = parseInt($item.attr("data-id"));
            var statusToSet = $button.val() == "Deactivate" ? "0" : "1";
            makeAdminAPICall({
                endpoint: "changeProductActive",
                data: {
                    id: id,
                    active: statusToSet
                },
                success: function() {
                    var newStatus = statusToSet == "1" ? "Active" : "Not Active";
                    var newButtonValue = statusToSet == "1" ? "Deactivate" : "Activate";
                    $statusContainer.html(newStatus);
                    $button.val(newButtonValue);
                    var product = $item.data("product");
                    product.active = statusToSet;
                    $item.data("product", product);
                },
                error: function(errorMessage) {
                    alert("Unable to change active setting of item. Error: " + errorMessage);
                }
            })
        });
        // Adding an item size
        $itemContainer.on("click", "> .item > .sizes-container > .detail-container > input.add-size-button", function() {
            var $item = $(this).parent().parent().parent();
            var id = parseInt($item.attr("data-id"));
            var size = $item.find("> .sizes-container > .size-button-container > span.active").attr("class").split(" ")[0];
            makeAdminAPICall({
                endpoint: "addProductSize",
                data: {
                    id: id,
                    size: size.substring(0, 1)
                },
                success: function(data) {
                    var product = $item.data("product");
                    product.sizes[size] = {
                        description: data.description,
                        subtitle: data.subtitle
                    }
                    $item.data("product", product);
                    $item.find("> .sizes-container > .detail-container").html(generateSizeDetailHTML(size, product));
                },
                error: function(errorMessage) {
                    alert("Unable to add size to item. Error: " + errorMessage);
                }
            });
        });
        // Removing an item size
        $itemContainer.on("click", "> .item > .sizes-container > .detail-container > input.remove-size-button", function() {
            var $item = $(this).parent().parent().parent();
            var id = parseInt($item.attr("data-id"));
            var size = $item.find("> .sizes-container > .size-button-container > span.active").attr("class").split(" ")[0];
            makeAdminAPICall({
                endpoint: "removeProductSize",
                data: {
                    id: id,
                    size: size.substring(0, 1)
                },
                success: function() {
                    var product = $item.data("product");
                    delete product.sizes[size]
                    $item.data("product", product);
                    $item.find("> .sizes-container > .detail-container").html(generateSizeDetailHTML(size, product));
                },
                error: function(errorMessage) {
                    alert("Unable to remove size to item. Error: " + errorMessage);
                }
            });
        });
        // Changing the subtitle for the size of an item
        $itemContainer.on("click", "> .item > .sizes-container > .detail-container > .subtitle-container > input[type='button']", function() {
            var $curButton = $(this);
            var $parent = $curButton.parent();
            if($curButton.val() == "Edit") {
                $parent.addClass("editing");
                $parent.find("> input[type='text']").val($parent.find("> span.not-editing").html());
            } else if($curButton.val() == "Cancel") {
                $parent.removeClass("editing");
            } else if($curButton.val() == "Submit") {
                var newSubtitle = $parent.find("> input[type='text']").val();
                var $item = $parent.parent().parent().parent();
                var id = parseInt($item.attr("data-id"));
                var size = $item.find("> .sizes-container > .size-button-container > span.active").attr("class").split(" ")[0];
                makeAdminAPICall({
                    endpoint: "setItemSubtitle",
                    data: {
                        id: id,
                        subtitle: newSubtitle,
                        size: size
                    },
                    success: function(data) {
                        var product = $item.data("product");
                        product.sizes[size].subtitle = newSubtitle;
                        $item.data("product", product);
                        $item.find("> .sizes-container > .detail-container").html(generateSizeDetailHTML(size, product));
                    },
                    error: function(errorMessage) {
                        alert("Unable to set item subtitle for size = " + size + ". Error: " + errorMessage);
                    }
                });
            }
        });
        // Changing the description for the size of the item.
        $itemContainer.on("click", "> .item > .sizes-container > .detail-container > .description-container > input[type='button']", function() {
            var $curButton = $(this);
            var $parent = $curButton.parent();
            if($curButton.val() == "Edit") {
                $parent.addClass("editing");
                $parent.find("> textarea").val($parent.find("> p.not-editing").html());
            } else if($curButton.val() == "Cancel") {
                $parent.removeClass("editing");
            } else if($curButton.val() == "Submit") {
                var newDescription = $parent.find("> textarea").val();
                var $item = $parent.parent().parent().parent();
                var id = parseInt($item.attr("data-id"));
                var size = $item.find("> .sizes-container > .size-button-container > span.active").attr("class").split(" ")[0];
                makeAdminAPICall({
                    endpoint: "setItemDescription",
                    data: {
                        id: id,
                        description: newDescription,
                        size: size
                    },
                    success: function(data) {
                        var product = $item.data("product");
                        product.sizes[size].description = newDescription;
                        $item.data("product", product);
                        $item.find("> .sizes-container > .detail-container").html(generateSizeDetailHTML(size, product));
                    },
                    error: function(errorMessage) {
                        alert("Unable to set item description for size = " + size + ". Error: " + errorMessage);
                    }
                });
            }
        });
        // Changing the price of an item of particular size.
        $itemContainer.on("click", "> .item > .sizes-container > .detail-container > .pricing-container > .current-price-container > input[type='button']", function() {
            var $curButton = $(this);
            var $parent = $curButton.parent();
            if($curButton.val() == "Change Price") {
                $parent.addClass("editing");
                $parent.find("> input[type='text']").val($parent.find("> span.not-editing").html());
            } else if($curButton.val() == "Cancel") {
                $parent.removeClass("editing");
            } else if($curButton.val() == "Submit") {
                var newPrice = $parent.find("> input[type='text']").val();
                var $item = $parent.parent().parent().parent().parent();
                var id = parseInt($item.attr("data-id"));
                var size = $item.find("> .sizes-container > .size-button-container > span.active").attr("class").split(" ")[0];
                makeAdminAPICall({
                    endpoint: "addItemPrice",
                    data: {
                        id: id,
                        price: newPrice,
                        size: size
                    },
                    success: function(data) {
                        var product = $item.data("product");
                        console.log(data);
                        console.log(product);
                        product.prices = data.prices;
                        $item.data("product", product);
                        $item.find("> .sizes-container > .detail-container").html(generateSizeDetailHTML(size, product));
                    },
                    error: function(errorMessage) {
                        alert("Unable to set add item price for size = " + size + ". Error: " + errorMessage);
                    }
                });
            }
        });
        // Changing between different sizes for an item.
        $itemContainer.on("click", "> .item > .sizes-container > .size-button-container > span", function() {
            var $curButton = $(this);
            $curButton.parent().find("> span").removeClass("active");
            var newSize = $curButton.attr("class");
            $curButton.addClass("active");
            $curButton.parent().parent().find("> .detail-container").html(
                generateSizeDetailHTML(newSize, $curButton.parent().parent().parent().data('product'))
            );
        });
        function updateMaintenanceBox() {
            if(inMaintenance) {
                $maintenanceText.html("ON");
                $maintenanceChangeButton.val("Turn Off");
            } else {
                $maintenanceText.html("OFF");
                $maintenanceChangeButton.val("Turn On");
            }
        }
        function signInSuccessful() {
            $adminLogin.removeClass("active");
            $adminContainer.addClass("active");
            makeAdminAPICall({
                endpoint: 'getMaintenanceStatus',
                success: function(data) {
                    inMaintenance = data.maintenance;
                    updateMaintenanceBox();
                },
                error: function(errorMessage) { 
                    alert("Unable to fetch maintenance status. Error: " + errorMessage);
                }
            });
            makeAdminAPICall({
                endpoint: 'getDashboardData',
                success: function(data) {
                    $itemContainer.html("");
                    var totalProducts = 0;
                    for(var id in data.products) {
                        ++totalProducts;
                        var curProd = data.products[id];
                        var isActive = curProd.active == "1";
                        var $item = $(""
                        +"<div class='item' data-id='"+id+"'>"
                        +   "<div class='name-container'>"
                        +       "<span>"+totalProducts+".</span>"
                        +       "<span>Name:</span>"
                        +       "<span class='not-editing'>"+curProd.name+"</span>"
                        +       "<input type='button' value='Edit' class='not-editing' />"
                        +       "<input type='text' value='"+curProd.name+"' class='editing' />"
                        +       "<input type='button' value='Submit' class='editing' />"
                        +       "<input type='button' value='Cancel' class='editing' />"
                        +   "</div>"
                        +   "<div class='activate-deactivate-container'>"
                        +       "<span>Status: </span>"
                        +       "<span class='status'>"+(isActive?'Active':'Not Active')+"</span>"
                        +       "<input type='button' class='toggle-active' value='"+(isActive?'Deactivate':'Activate')+"' />"
                        +   "</div>"
                        +   "<div class='sizes-container'>"
                        +       "<div class='size-button-container'>"
                        +           "<span class='large active'>Large</span>"
                        +           "<span class='medium'>Medium</span>"
                        +           "<span class='small'>Small</span>"
                        +           "<div></div>"
                        +       "</div>"
                        +       "<div class='detail-container'>"
                        +           generateSizeDetailHTML('large', curProd)
                        +       "</div>"
                        +   "</div>"
                        +"</div>"
                        );
                        $item.data("product", curProd);
                        $itemContainer.append($item);
                    }
                    $productsTitle.html("Products: " + totalProducts);
                },
                error: function(errorMessage) {
                    alert("Unable to fetch dashboard data. Error: " + errorMessage);
                }
            });
        }
        function generateSizeDetailHTML(size, prod) {
            var sizeUpper = size.charAt(0).toUpperCase()+size.slice(1);
            if(!prod.sizes[size]) {
                return (""
                +"<input type='button' value='Add "+sizeUpper+"' class='add-size-button' />"
                );
            }
            var pricesHTML = "";
            for(var i in prod.prices) {
                var curPrice = prod.prices[i];
                pricesHTML += (""
                +"<div class='row'>"
                +   "<span>$"+curPrice.sizes[size]+"</span>"
                +   "<span>"+curPrice.created+"</span>"
                +"</div>"
                );
            }
            var currentPrice;
            if(prod.prices.length) {
                currentPrice = prod.prices[0].sizes[size]; 
            } else {
                currentPrice = "0.00";
            }
            return (""
            +"<div class='subtitle-container'>"
            +   "<span>Subtitle:</span>"
            +   "<span class='not-editing'>"+prod.sizes[size].subtitle+"</span>"
            +   "<input type='button' value='Edit' class='not-editing' />"
            +   "<input type='text' value='"+prod.sizes[size].subtitle+"' class='editing'/>"
            +   "<input type='button' value='Submit' class='editing'/>"
            +   "<input type='button' value='Cancel' class='editing'/>"
            +"</div>"
            +"<input type='button' value='Remove "+sizeUpper+"' class='remove-size-button' />"
            +"<div class='description-container'>"
            +   "<div>Description:</div>"
            +   "<p class='not-editing'>"+prod.sizes[size].description+"</p>"
            +   "<div></div>"
            +   "<input type='button' value='Edit' class='not-editing' />"
            +   "<textarea class='editing' maxlength='1000'>"+prod.sizes[size].description+"</textarea>"
            +   "<div></div>"
            +   "<input type='button' value='Submit' class='editing' />"
            +   "<input type='button' value='Cancel' class='editing' />"
            +"</div>"
            +"<div class='pricing-container'>"
            +   "<div class='current-price-container'>"
            +       "<span>Current Price: $</span>"
            +       "<span class='not-editing'>"+currentPrice+"</span>"
            +       "<input type='button' value='Change Price' class='not-editing' />"
            +       "<input type='text' value='"+currentPrice+"' class='editing' />"
            +       "<input type='button' value='Submit' class='editing' />"
            +       "<input type='button' value='Cancel' class='editing' />"
            +   "</div>"
            +   "<div class='prices-listing'>"
            +       "<div class='row'>"
            +           "<span>Price</span>"
            +           "<span>Time Set</span>"
            +       "</div>"
            +       "<div class='prices-holder'>"
            +           pricesHTML
            +       "</div>"
            +   "</div>"
            +"</div>"
            );
            
        }
        function signOut() {
            alert("Session expired. Signing out now");
            $adminLogin.addClass("active");
            $adminContainer.removeClass("active");
        }
        function makeAdminAPICall(params) {
            $.ajax({
                method: "POST",
                data: params.data || {},
                url: '/admin/' + params.endpoint,
                success: function(data) {
                    var parsedData = JSON.parse(data);
                    if(parsedData.error) {
                        if(parsedData.error == "Unauthorized") signOut();
                        else params.error(parsedData.error);
                    } else params.success(parsedData);
                },
                error: function() { alert("Trouble hitting '/admin/"+params.endpoint+"'. Try refreshing page"); }
            })
        }
    });
</script>                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                              