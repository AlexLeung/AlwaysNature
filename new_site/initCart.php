<?php
    // Here we grab the cart from the session and compare it to the cart in the db.
    // If both are stored, we make sure that they are up to date with each other.
    // Finally, the $cart variable is set to the decoded cart.
    if(isset($_SESSION['cart'])) $cart = json_decode($_SESSION['cart']);
    else {
        $_SESSION['cart'] = '{}';
        $cart = new stdClass();
    }
    if(isset($_SESSION['user_id'])) {
        $userID = $_SESSION['user_id'];
        if(is_int($userID)) {
            $sql = "SELECT id, cart FROM users WHERE id=$userID LIMIT 1";
            $result = $mysqli_conn->query($sql);
            if(!$result) die("MySQL Error: " . $mysqli_conn->error);
            else if($result->num_rows == 0) unset($_SESSION['user_id']);
            else {
                $user = $result->fetch_assoc();
                // Merge carts iff they are unequal.
                if(strcmp($_SESSION['cart'], $user['cart']) !== 0) {
                    $cartToMerge = json_decode($user['cart']);
                    foreach($cartToMerge as $id => $value) {
                        if(isset($cart->$id)) {
                            foreach($value as $size => $quantity) {
                                if(isset($cart->$id->$size)) $cart->$id->$size += $quantity;
                                else $cart->$id->$size = $quantity;
                            }
                        } else $cart->$id = $value;
                    }
                    $_SESSION['cart'] = json_encode($cart);
                    $escapedEncodedCart = $mysqli_conn->real_escape_string($_SESSION['cart']);
                    $sql = "UPDATE users SET cart='$escapedEncodedCart' WHERE id=$userID";
                    if(!$mysqli_conn->query($sql)) echo "Error Updating record: " . $mysqli_conn->error;
                }
            }
        } else unset($_SESSION['user_id']);
    }
?>