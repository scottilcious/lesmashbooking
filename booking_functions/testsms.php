<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS</title>
</head>
<body>
    <?php //Support version greater than or equal 7.X.X 
        /*$curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://portal-otp.smsmkt.com/api/send-message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_HTTPHEADER => array(
                "Content-Type: application/json",
                "api_key:" . SMSMKT_API_KEY,
                "secret_key:" . SMSMKT_SECRET_KEY,
            ),
            CURLOPT_POSTFIELDS =>json_encode(array(
            "message"=>"test",
            "phone"=>"0839526698",
            "sender"=>"Lesmashclub",
            )),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        echo $response;
        */
        ?>
    <form action="testsms.php">
        <input type="text">
    </form>
</body>
</html>