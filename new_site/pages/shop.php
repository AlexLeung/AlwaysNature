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
    $subtitles = sizesList("p", "subtitle");
    $descriptions = sizesList("p", "description");
    $sql = "SELECT p.id, p.name, $subtitles, $descriptions, p.sizes, $pp_prices FROM products AS p INNER JOIN $pp ON p.id = pp.product_id WHERE p.active='1'";
    
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
            $item->descriptions = (object)[
                'l' => $itemRecord['description_large'],
                'm' => $itemRecord['description_medium'],
                's' => $itemRecord['description_small']
            ];
            $item->subtitles = (object)[
                'l' => $itemRecord['subtitle_large'],
                'm' => $itemRecord['subtitle_medium'],
                's' => $itemRecord['subtitle_small']
            ];
            array_push($items, $item);
        }
    }
?>
<div class="container">
    <p></p>
    <div id="product-listing"></div>
</div>
<div id="modal-backdrop">
    <div id="modal">
        <div id='close-modal' onclick="window.location.hash='/'">Back To Products</div>
        <div id='gallery'>
            <div class='image-container'>
                <div class='added-to-cart'>L, M, S added to cart</div>
            </div>
            <div class='gallery-nav'>
                <span class='index-0'></span>
                <span class='index-1'></span>
                <span class='index-2'></span>
                <span class='index-3'></span>
            </div>            
        </div>
        <div id='detail-view'>
            <h1 class='prod-title'></h1>
            <h2 class='prod-subtitle'></h2>
            <p  class='prod-desc'></p>
            <div class='size-selection'>
                <span class='l'>Large</span>
                <span class='m'>Medium</span>
                <span class='s'>Small</span>
            </div>
            <p class='price'></p>
            <div class='add-to-cart submit-button'>
                Add to Cart
                <div class="cover">
                    <?php $loadingRingSize = 40; ?>
                    <?php include './img/loadingRing.svg.php'; ?>
                </div>
            </div>
            <div class='remove-from-cart submit-button'>
                Remove from Cart
                <div class="cover">
                    <?php include './img/loadingRing.svg.php'; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
    (function() {
        'use strict';
        var $productListing;
        var $shop;
        var $modal;
        var $detailView;
        var $sizeSelection;
        var $galleryNav;
        var $imageContainer;
        var $window;
        var items = (<?php echo json_encode($items); ?>);
        var cart = (<?php echo json_encode($cart); ?>);
        var possibleSizes = ["l", "m", "s"];
        $(function() {
            $productListing = $("#product-listing");
            $shop = $("#shop");
            $modal = $("#modal");
            $detailView = $modal.find("> #detail-view");
            $sizeSelection = $detailView.find("> .size-selection");
            $galleryNav = $modal.find("> #gallery > .gallery-nav");
            $imageContainer = $modal.find("> #gallery > .image-container");
            $window = $(window);
            var curItem;
            var hash = window.location.hash;
            for(var i = 0; i < items.length; ++i) {
                curItem = items[i];
                if(hash == "#/" + curItem.id) openQuickView(curItem);
                $productListing.append(generateItemHTML(curItem));
            }
            $("#modal-backdrop").click(function() {
                window.location.hash='/';
            });
            $modal.click(function(event) {
                event.stopPropagation();
            });
            $sizeSelection.on("click", "> span", function() {
                changeItemSize($(this).attr("class").split(" ")[0]);
            });
            $galleryNav.on("click", "> span", function() {
                var indexClass = $(this).attr("class").split(" ")[0];
                changeGalleryImage(parseInt(indexClass[indexClass.length - 1]));
            });
            $detailView.on('click', '> .submit-button', function() {
                var $this = $(this);
                if(!$this.hasClass("in-progress")) {
                    var curSelectedId = selectedItem.id;
                    var curSelectedSize = selectedItemSize;
                    if($this.hasClass("add-to-cart")) {
                        $this.addClass("in-progress");
                        $.ajax({
                            method: "POST",
                            url: "/addToCart",
                            data: {
                                id: curSelectedId,
                                size: curSelectedSize
                            },
                            success: function(data) {
                                $this.removeClass("in-progress");
                                if(JSON.parse(data).error) {
                                    alert("Failed to Add to Cart. Try refreshing the page.");
                                } else {
                                    if(!cart[curSelectedId]) cart[curSelectedId] = {};
                                    cart[curSelectedId][curSelectedSize] = 1;
                                    reflectCartChange(curSelectedId);
                                }
                            },
                            error: function() {
                                $this.removeClass("in-progress");
                                alert("Failed to Add to Cart! Try refreshing the page.");
                            }
                        });
                    } else if($this.hasClass("remove-from-cart")) {
                        $this.addClass("in-progress");
                        $.ajax({
                            method: "POST",
                            url: "/removeFromCart",
                            data: {
                                id: curSelectedId,
                                size: curSelectedSize
                            },
                            success: function(data) {
                                $this.removeClass("in-progress");
                                if(JSON.parse(data).error) {
                                    alert("Failed to Remove from Cart. Try refreshing the page.");
                                } else {
                                    delete cart[curSelectedId][curSelectedSize];
                                    if($.isEmptyObject(cart[curSelectedId])) delete cart[curSelectedId];
                                    reflectCartChange(curSelectedId);
                                }
                            },
                            error: function(data) {
                                $this.removeClass("in-progress");
                                alert("Failed to Remove from Cart! Try refreshing the page.");
                            }
                        });
                    }
                }
            });
            $window.on("hashchange", function() {
                hash = window.location.hash;
                if(!hash.match(/^#\/[0-9]+$/)) {
                    closeQuickView();
                    return;
                }
                for(var i = 0; i < items.length; ++i) {
                    curItem = items[i];
                    if(hash == "#/" + curItem.id) openQuickView(curItem);
                }
            });
        });
        function generateItemHTML(item) {
            var itemSizesHTML = "";
            for(var i = 0; i < possibleSizes.length; ++i) {
                var curSize = possibleSizes[i];
                if(item.sizes.indexOf(curSize) == -1) continue;
                itemSizesHTML += (""
                    +"<span class='price'>"
                    +   curSize.toUpperCase() + ":$" + item.prices[curSize] 
                    +"</span>"
                );
            }
            var cartItem = cart[item.id]; 
            var added = cartItem ? " added" : "";
            var addedToCartMessage = cartItem ? getAddedToCartMessage(cartItem) : "";
            return (""
                +"<div class='item"+added+"' id='item-"+item.id+"' style=\"background-image:url('/img/prods/"+item.id+"/cover.jpg');\">"
                +   "<div class='added-to-cart'>"+addedToCartMessage+"</div>"
                +   "<div class='footer' onclick=\"window.location.hash='/"+item.id+"'\">"
                +       "<span class='quick-view'>Quick View</span>"
                +       "<span class='prices'>"
                +           itemSizesHTML
                +       "</span>"
                +   "</div>"
                +"</div>"
            );
        }
        var selectedItem;
        var selectedItemSize;
        var selectedGalleryImage;
        function openQuickView(item) {
            selectedItem = item;
            $shop.addClass("modal-active");
            $detailView.find("> .prod-title").html(item.title);
            var addedActiveSize = false;
            for(var i = 0; i < possibleSizes.length; ++i) {
                var curSize = possibleSizes[i];
                if(item.sizes.indexOf(curSize) == -1) {
                    $sizeSelection.find("> span." + curSize).removeClass("enabled");  
                } else {
                    $sizeSelection.find("> span." + curSize).addClass("enabled");
                    if(!addedActiveSize) {
                        changeItemSize(curSize);
                        addedActiveSize = true;
                    }
                }
            }
        }
        function closeQuickView() {
            $shop.removeClass("modal-active");
        }
        function changeItemSize(size) {
            selectedItemSize = size;
            $sizeSelection.find("> span").removeClass("active");
            $sizeSelection.find("> span." + size).addClass("active");
            $detailView.find("> .prod-subtitle").html(selectedItem.subtitles[size]);
            $detailView.find("> .prod-desc").html(selectedItem.descriptions[size]);
            $detailView.find("> .price").html("$" + selectedItem.prices[size]);
            changeGalleryImage(0);
            reflectCartChange(selectedItem.id);
        }
        var actualSizes = {s:"small", m:"medium", l:"large"};
        function changeGalleryImage(index) {
            selectedGalleryImage = index;
            $galleryNav.find("> span").removeClass("active").eq(index).addClass("active");
            $imageContainer.css("background-image", 
                "url('/img/prods/"+selectedItem.id+"/"+actualSizes[selectedItemSize]+"/"+(index+1)+".jpg')"
            );
        }
        function reflectCartChange(id) {
            var $prodListingItem = $productListing.find("> #item-"+id);
            $detailView.removeClass("added");
            if(cart[id]) {
                var addedToCartMessage = getAddedToCartMessage(cart[id]);
                $prodListingItem.addClass("added").find("> .added-to-cart").html(addedToCartMessage);
                if(id == selectedItem.id) {
                    $modal.addClass("added");
                    $imageContainer.find("> .added-to-cart").html(addedToCartMessage);
                    if(cart[id][selectedItemSize]) {
                        $detailView.addClass("added");
                    }
                }
            } else {
                $prodListingItem.removeClass("added");
                if(id == selectedItem.id) {
                    $modal.removeClass("added");
                }
            }
        }
        function getAddedToCartMessage(cartItem) {
            var curSizes = [];
            for(var size in cartItem) curSizes.push(size.toUpperCase());
            curSizes.sort();
            return curSizes.join(", ") + " added to cart";
        }
    })();
</script>