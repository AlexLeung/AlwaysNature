<div id="navbar">
    <div class="logo-link">
        <a class="<?php echo $reqURI=='' ?         'active' : '' ?>" href="/" title="Home" style="height: 43px" >
            <?php $svgHeight = 40; $svgWidth = 120; ?>
            <?php include "./img/alwaysnature.svg.php"; ?>
        </a>
    </div>
    <div class="other-links">
        <a class="<?php echo $reqURI=='shop/' ?    'active' : '' ?>" href="/shop/" title="Shop">
            Shop
        </a>
        <a class="<?php echo $reqURI=='account' ?  'active' : '' ?>" href="/account" title="Account">
            Account
        </a>
        <a class="<?php echo $reqURI=='checkout' ? 'active' : '' ?>" href="/checkout" title="Checkout" style="line-height: 0;">
            <?php include "./img/cart.svg"; ?>
        </a>
    </div>
</div>