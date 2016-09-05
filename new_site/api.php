<?php
class API {

    /* a public function with the name thing_anotherThing corresponds to the route /thing_anotherThing */

    public function checkout($response, $cart, $mysqli, $app_path) {
        include "$app_path/paypal/expresscheckout.php";
    }

    public function incrementItemInCart($response, $cart, $mysqli) {
        if(!isset($_POST['id']) || !isset($_POST['size'])) {
            throw new Exception("'id' and 'size' are both required inputs.");
        }
        $id = $_POST['id'];
        $size = $_POST['size'];
        if(!isset($cart->$id)) {
            $cartSizes = new stdClass();
            $cartSizes->$size = 1;
            $cart->$id = $cartSizes;
        } else if(!isset($cart->$id->$size)) {
            $cart->$id->$size = 1;
        } else $cart->$id->$size++;
    }

    public function decrementItemInCart($response, $cart, $mysqli) {
        if(!isset($_POST['id']) || !isset($_POST['size'])) {
            throw new Exception("'id' and 'size' are both required inputs.");
        }
        $id = $_POST['id'];
        $size = $_POST['size'];
        if(isset($cart->$id)) {
            if(isset($cart->$id->$size)) {
                $cart->$id->$size--;
                if($cart->$id->$size == 0) unset($cart->$id->$size); 
            }
            if(empty((array)$cart->$id)) unset($cart->$id);
        }
    }

    public function addToCart($response, $cart, $mysqli) {
        if(!isset($_POST['id']) || !isset($_POST['size'])) {
            throw new Exception("'id' and 'size' are both required inputs.");
        }
        $id = $_POST['id'];
        $size = $_POST['size'];
        if(!isset($cart->$id)) {
            $cartSizes = new stdClass();
            $cartSizes->$size = 1;
            $cart->$id = $cartSizes;
        } else $cart->$id->$size = 1;
    }

    public function removeFromCart($response, $cart, $mysqli) {
        if(!isset($_POST['id']) || !isset($_POST['size'])) {
            throw new Exception("'id' and 'size' are both required inputs.");
        }
        $id = $_POST['id'];
        $size = $_POST['size'];
        if(isset($cart->$id)) {
            if(isset($cart->$id->$size)) unset($cart->$id->$size);
            if(empty((array)$cart->$id)) unset($cart->$id);
        }
    }

    public function admin_signIn($response, $cart, $mysqli) {
        if(!isset($_POST['pass1']) || !isset($_POST['pass2']) || !isset($_POST['grecaptcha'])) {
            throw new Exception("missing inputs");
        }
        if(strcmp($_POST['pass1'], ADMIN_PASS1) !== 0 || strcmp($_POST['pass2'], ADMIN_PASS2) !== 0) {
            throw new Exception("incorrect password(s)");
        }
        $recaptchaCheck = json_decode(file_get_contents(
            "https://www.google.com/recaptcha/api/siteverify",
            false,
            stream_context_create(['http' => [
                'header' => 'Content-type: application/x-www-form-urlencoded',
                'method' => 'POST',
                'content' => http_build_query([
                    'secret' => RECAPTCHA_SERVER,
                    'response' => $_POST['grecaptcha']
                ])
            ]])
        ), true);
        if(!$recaptchaCheck['success']) {
            throw new Exception("Invalid Recaptcha");
        }
        $_SESSION['admin_expire'] = time() + (60*30); // Expires in 30 minutes
    }

    private function checkAdminTime() {
        if(!isset($_SESSION['admin_expire']) || time() > $_SESSION['admin_expire']) {
            throw new Exception("Unauthorized");
        }
    }

    public function admin_signOut() {
        $this->checkAdminTime();
        unset($_SESSION['admin_expire']);
    }

    public function admin_getMaintenanceStatus($response) {
        $this->checkAdminTime();
        $response->maintenance = MAINTENANCE;
    }

    public function admin_setMaintenanceStatus($response, $cart, $mysqli, $app_path) {
        $this->checkAdminTime();
        $configFilePath = "$app_path/config.ini";
        $config = parse_ini_file($configFilePath);
        $config['MAINTENANCE'] = $_POST['maintenance'] === 'true' ? true : false;
        $newIni = [];
        foreach($config as $key => $val) {
            if(is_string($val)) $newIni[] = $key." = \"".$val."\"";
            else if (is_bool($val)) $newIni[] = $key." = ".($val ? 'true' : 'false');
            else $newIni[] = $key." = ".$val;
        }
        $iniString = implode("\r\n", $newIni);
        if ($fp = fopen($configFilePath, 'w')) {
            $startTime = microtime(TRUE);
            do {
                $canWrite = flock($fp, LOCK_EX);
                // If lock not obtained sleep for 0 - 100 milliseconds, to avoid collision and CPU load
                if(!$canWrite) usleep(round(rand(0, 100)*1000));
            } while ((!$canWrite)and((microtime(TRUE)-$startTime) < 5));
            //file was locked so now we can store information
            if ($canWrite) {
                fwrite($fp, $iniString);
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }
        $response->maintenance = $config['MAINTENANCE'];
    }

    public function admin_getDashboardData($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        $sql = ""
        ."SELECT * FROM products;"
        ."SELECT * FROM product_pricing ORDER BY created_on DESC";
        $results = [];
        if (!$mysqli_conn->multi_query($sql)) {
            echo "Multi query failed: (" . $mysqli_conn->errno . ") " . $mysqli_conn->error;
        }
        do {
            if ($result = $mysqli_conn->store_result()) {
                $results[] = $result->fetch_all(MYSQLI_ASSOC);
                $result->free();
            }
        } while ($mysqli_conn->more_results() && $mysqli_conn->next_result());
        $products = [];
        $sizes = ['l' => 'large', 'm' => 'medium', 's' => 'small'];
        foreach($results[0] as $productRecord) {
            $product = $products[$productRecord['id']] = new stdClass();
            $product->name = $productRecord['name'];
            $product->active = $productRecord['active'];
            $product->sizes = new stdClass();
            $product->prices = [];
            foreach($sizes as $sizeLetter => $size) {
                if(strpos($productRecord['sizes'], $sizeLetter) !== false) {
                    $product->sizes->$size = new stdClass();
                    $product->sizes->$size->description = $productRecord['description_'.$size];
                    $product->sizes->$size->subtitle = $productRecord['subtitle_'.$size];
                }
            }
        }
        foreach($results[1] as $pricingRecord) {
            $prodId = $pricingRecord['product_id'];
            $pricing = $products[$prodId]->prices[] = new stdClass();
            $pricing->created = $pricingRecord['created_on'];
            $pricing->sizes = new stdClass();
            $pricing->sizes->small = $pricingRecord['price_small'];
            $pricing->sizes->medium = $pricingRecord['price_medium'];
            $pricing->sizes->large = $pricingRecord['price_large'];
        }
        $response->products = $products;
    }

    public function admin_setItemName($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['name'])) {
            throw new Exception("'id' and 'name' inputs are required.");
        }
        $name = $mysqli_conn->real_escape_string($_POST['name']);
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $sql = "UPDATE products SET name='$name' WHERE id='$id'";
        if(!$mysqli_conn->query($sql)) throw new Exception("Error Updating record: " . $mysqli_conn->error);
        if($mysqli_conn->affected_rows == 0) throw new Exception("No rows affected");
    }

    public function admin_setItemSubtitle($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['subtitle']) || !isset($_POST['size'])) {
            throw new Exception("'id', 'subtitle', and 'size' inputs are required");
        }
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $subtitle = $mysqli_conn->real_escape_string($_POST['subtitle']);
        $size = $_POST['size'];
        if($size !== "large" && $size !== "medium" && $size !== "small") {
            throw new Exception("size input must either be 'large', 'medium' or 'small'");
        }
        $sql = "UPDATE products SET subtitle_$size='$subtitle' WHERE id='$id'";
        if(!$mysqli_conn->query($sql)) throw new Exception("Error Updating record: " . $mysqli_conn->error);
        if($mysqli_conn->affected_rows == 0) throw new Exception("No rows affected");
    }

    public function admin_setItemDescription($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['description']) || !isset($_POST['size'])) {
            throw new Exception("'id', 'description', and 'size' inputs are required");
        }
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $description = $mysqli_conn->real_escape_string($_POST['description']);
        $size = $_POST['size'];
        if($size !== "large" && $size !== "medium" && $size !== "small") {
            throw new Exception("size input must either be 'large', 'medium' or 'small'");
        }
        $sql = "UPDATE products SET description_$size='$description' WHERE id='$id'";
        if(!$mysqli_conn->query($sql)) throw new Exception("Error Updating record: " . $mysqli_conn->error);
        if($mysqli_conn->affected_rows == 0) throw new Exception("No rows affected");
    }

    public function admin_addItemPrice($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['size']) || !isset($_POST['price'])) {
            throw new Exception("'id', 'size', and 'price' inputs are required");
        }
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $price = $mysqli_conn->real_escape_string($_POST['price']);
        $size = $_POST['size'];
        if($size !== "large" && $size !== "medium" && $size !== "small") {
            throw new Exception("size input must either be 'large', 'medium' or 'small'");
        }
        $sql = "SELECT price_small, price_medium, price_large FROM product_pricing WHERE product_id='$id' ORDER BY created_on DESC LIMIT 1";
        $result = $mysqli_conn->query($sql);
        if(!$result) throw new Exception("Unable to fetch product_pricing. Error: " . $mysqli_conn->error);
        else if($result->num_rows == 0) throw new Exception("No pricing for product with id=$id");
        $pricingRecord = $result->fetch_assoc();
        $pricingRecord['price_'.$size] = $price;
        $price_small = $pricingRecord['price_small'];
        $price_medium = $pricingRecord['price_medium'];
        $price_large = $pricingRecord['price_large'];
        $fields = 'price_small, price_medium, price_large, product_id';
        $sql = "INSERT INTO product_pricing ($fields) VALUES ('$price_small', '$price_medium', '$price_large', '$id')";
        if(!$mysqli_conn->query($sql)) throw new Exception("Unable to insert new pricing. Error: " . $mysqli_conn->error);
        $sql = "SELECT price_small, price_medium, price_large, created_on FROM product_pricing WHERE product_id='$id' ORDER BY created_on DESC";
        $result = $mysqli_conn->query($sql);
        if(!$result) throw new Exception("Unable to fetch product_pricing. Error: " . $mysqli_conn->error);
        else if($result->num_rows == 0) throw new Exception("No pricing for product with id=$id");
        $prices = [];
        while($pricingRecord = $result->fetch_assoc()) {
            $curPrice = $prices[] = new stdClass();
            $curPrice->created = $pricingRecord['created_on'];
            $curPrice->sizes = new stdClass();
            $curPrice->sizes->small = $pricingRecord['price_small'];
            $curPrice->sizes->medium = $pricingRecord['price_medium'];
            $curPrice->sizes->large = $pricingRecord['price_large'];
        }
        $response->prices = $prices;
    }

    public function admin_changeProductActive($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['active'])) {
            throw new Exception("'id' and 'active' inputs are required");
        }
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $active = $_POST['active'] ? 1 : 0;
        $sql = "UPDATE products SET active=$active WHERE id='$id'";
        if(!$mysqli_conn->query($sql)) throw new Exception("Error Updating record: " . $mysqli_conn->error);
        if($mysqli_conn->affected_rows == 0) throw new Exception("No rows affected");
    }

    public function admin_addProductSize($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['size'])) {
            throw new Exception("'id' and 'size' inputs are required");
        }
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $size = $_POST['size'];
        if($size !== "s" && $size !== "m" && $size !== "l") {
            throw new Exception("'size' input must either be 's', 'm', or 'l'");
        }
        $sizes = ['s' => 'small', 'm' => 'medium', 'l' => 'large'];
        $fullSize = $sizes[$size];
        $sql = "SELECT sizes, subtitle_$fullSize, description_$fullSize FROM products WHERE id='$id'";
        $result = $mysqli_conn->query($sql);
        if(!$result) throw new Exception("Unable to fetch sizes. Error: " . $mysqli_conn->error);
        else if($result->num_rows == 0) throw new Exception("No product with id=$id");
        $productRecord = $result->fetch_assoc();
        if(strpos($productRecord['sizes'], $size) === false) {
            $sizesArr = str_split($productRecord['sizes'].$size);
            sort($sizesArr);
            $newSizes = implode('', $sizesArr);
            $sql = "UPDATE products SET sizes='$newSizes' WHERE id='$id'";
            if(!$mysqli_conn->query($sql)) throw new Exception("Error Updating record: " . $mysqli_conn->error);
            if($mysqli_conn->affected_rows == 0) throw new Exception("No rows affected");
        }
        $response->subtitle = $productRecord['subtitle_'.$fullSize];
        $response->description = $productRecord['description_'.$fullSize];
    }

    public function admin_removeProductSize($response, $cart, $mysqli_conn) {
        $this->checkAdminTime();
        if(!isset($_POST['id']) || !isset($_POST['size'])) {
            throw new Exception("'id' and 'size' inputs are required");
        }
        $id = $mysqli_conn->real_escape_string($_POST['id']);
        $size = $_POST['size'];
        if($size !== "s" && $size !== "m" && $size !== "l") {
            throw new Exception("'size' input must either be 's', 'm', or 'l'");
        }
        $sql = "SELECT sizes FROM products WHERE id='$id'";
        $result = $mysqli_conn->query($sql);
        if(!$result) throw new Exception("Unable to fetch sizes. Error: " . $mysqli_conn->error);
        else if($result->num_rows == 0) throw new Exception("No product with id=$id");
        $productRecord = $result->fetch_assoc();
        $sizeIndex = strpos($productRecord['sizes'], $size);
        if($sizeIndex !== false) {
            $newSizes = substr_replace($productRecord['sizes'], "", $sizeIndex, 1);
            $sql = "UPDATE products SET sizes='$newSizes' WHERE id='$id'";
            if(!$mysqli_conn->query($sql)) throw new Exception("Error Updating record: " . $mysqli_conn->error);
            if($mysqli_conn->affected_rows == 0) throw new Exception("No rows affected");
        }
    }

    public function admin_addItem($response, $cart, $mysqli_conn) {
        $sql = "INSERT INTO products (active) VALUES ('0')";
        if(!$mysqli_conn->query($sql)) throw new Exception("Unable to insert new item. Error: " . $mysqli_conn->error);
    }

}
?>