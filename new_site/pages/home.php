<div class="title big">
    <?php $svgHeight = 120; $svgWidth = 360; ?>
    <?php include "./img/alwaysnature.svg.php"; ?>
</div>
<div class="title little">
    <?php $svgHeight = 60; $svgWidth = 180; ?>
    <?php include "./img/alwaysnature.svg.php"; ?>
</div>
<div id="filler"></div>
<div id="remainder-of-page">
    <div id="about">
        <div class="left-cont"></div>
        <div class="right-cont"></div>
        <h1>Contact us at info@alwaysnature.com</h1>
    </div>
</div>
<script>
    (function() {
        'use strict';
        var $window;
        var $filler;
        var $navbar;
        $(function() {
            $window = $(window);
            $filler = $("#filler");
            $navbar = $("#navbar");
            checkIfScrolledPastFiller();
            $window.scroll(checkIfScrolledPastFiller);
            $window.resize(checkIfScrolledPastFiller);
        });
        function checkIfScrolledPastFiller() {
            var fillerHeight = $filler.outerHeight();
            $navbar[($window.scrollTop() > fillerHeight ? "remove" : "add") + "Class"]("not-scrolled-past");
        }
    })();
</script>