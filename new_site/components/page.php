<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="shortcut icon" href="/favicon.ico" />
        <link href='https://fonts.googleapis.com/css?family=Open+Sans:300' rel='stylesheet' type='text/css'>
        <link rel="stylesheet" href="/css/style.css" type="text/css" />
        <link rel="stylesheet" href="/css/<?php echo $page; ?>.css" type="text/css" />
        <title>Always Nature | <?php echo ucfirst($page); ?></title>
        <script src="https://code.jquery.com/jquery-3.1.0.min.js"></script>
        <script src='https://www.google.com/recaptcha/api.js'></script>
    </head>
    <body id="<?php echo $page; ?>">
        <?php include "$app_path/components/navbar.php"; ?>
        <div class="page">
            <?php include "$app_path/pages/$page.php"; ?>
        </div>
    </body>
</html>