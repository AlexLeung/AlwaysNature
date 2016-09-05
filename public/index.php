<?php
    const OLD_SITE = false;

    $reqURI = $_SERVER['REQUEST_URI'];
    $qsStart = strrpos($reqURI, "?");
    $reqURI = substr($reqURI, 0, $qsStart ? $qsStart : strlen($reqURI)); // remove query string
    $reqURI = substr($reqURI, 1); // remove first '/' char
    $method = $_SERVER['REQUEST_METHOD'];

    if(OLD_SITE) {

    } else {
        $app_path = "../new_site";
        $config = parse_ini_file("$app_path/config.ini");
        foreach($config as $constName => $constVal) define($constName, $constVal);
        session_start();
        include "$app_path/utils.php";
        $mysqli_conn = new mysqli(MYSQL_DOMAIN, MYSQL_USER, MYSQL_PASS, MYSQL_DB);
        if($mysqli_conn->connect_error) die("Connection failed: " . $mysqli_conn->connect_error);
        include "$app_path/initCart.php";
        if($method == "GET") {
            if($reqURI == "admin_".ADMIN_LOCATION) $page = "admin";
            else if(MAINTENANCE) $page = "maintenance"; 
            else if($reqURI == "") $page = "home";
            else if($reqURI == "shop") { $mysqli_conn->close(); header("Location:/shop/"); return; }
            else if($reqURI == "shop/") $page = "shop";
            else if(array_search($reqURI, ["account", "checkout", "ordered"]) !== false) $page = $reqURI;
            else $page = "404";
            include "$app_path/components/page.php";
        } else if(MAINTENANCE && !startsWith($reqURI, "admin/")) {
            echo '{"error":"Under Maintenance"}';
        } else {
            include "$app_path/api.php";
            $API = new API();
            $API_method = str_replace("/", "_", $reqURI);
            if($method == "POST" && method_exists($API, $API_method)) {
                $response = new stdClass();
                try {
                    $API->$API_method($response, $cart, $mysqli_conn, $app_path);
                    echo json_encode($response);
                    $_SESSION['cart'] = json_encode($cart);
                } catch(Exception $e) {
                    echo "{\"error\":\"{$e->getMessage()}\"}";
                }
            } else echo '{"error":"API Call Does Not Exist"}';
        }
        $mysqli_conn->close();
    }
?>