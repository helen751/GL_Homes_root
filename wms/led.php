<?php

// create file if it does not exist
if(!file_exists("led.txt")){
    file_put_contents("led.txt", "0,0");
}

// read current states
$data = file_get_contents("led.txt");
$states = explode(",", $data);

$led1 = $states[0];
$led2 = $states[1];

// handle toggle actions
if(isset($_GET['toggle'])){

    $toggle = $_GET['toggle'];

    // toggle LED1
    if($toggle == "led1"){
        $led1 = ($led1 == "1") ? "0" : "1";
    }

    // toggle LED2
    if($toggle == "led2"){
        $led2 = ($led2 == "1") ? "0" : "1";
    }

    // ALL ON
    if($toggle == "allon"){
        $led1 = "1";
        $led2 = "1";
    }

    // ALL OFF
    if($toggle == "alloff"){
        $led1 = "0";
        $led2 = "0";
    }

    // save new state
    file_put_contents("led.txt", $led1 . "," . $led2);

    // refresh page
    header("Location: index.php");
    exit();
}

?>

<!DOCTYPE html>
<html>

<head>

<title>ESP8266 Internet Control</title>

<style>

body{
    font-family: Arial;
    background:#f2f2f2;
    text-align:center;
    padding-top:40px;
}

.card{
    background:white;
    width:350px;
    margin:auto;
    padding:30px;
    border-radius:15px;
    box-shadow:0px 0px 10px rgba(0,0,0,0.2);
}

h1{
    margin-bottom:30px;
}

.status{
    font-size:20px;
    margin:15px;
}

button{
    width:220px;
    padding:15px;
    margin:10px;
    border:none;
    border-radius:10px;
    font-size:18px;
    cursor:pointer;
}

.green{
    background:green;
    color:white;
}

.red{
    background:red;
    color:white;
}

.blue{
    background:#007bff;
    color:white;
}

.orange{
    background:orange;
    color:white;
}

</style>

</head>

<body>

<div class="card">

<h1>🌍 ESP8266 LED Control</h1>

<div class="status">
LED1:
<?php echo ($led1 == "1") ? "ON ✅" : "OFF ❌"; ?>
</div>

<div class="status">
LED2:
<?php echo ($led2 == "1") ? "ON ✅" : "OFF ❌"; ?>
</div>

<a href="?toggle=led1">
<button class="blue">
Toggle LED1
</button>
</a>

<a href="?toggle=led2">
<button class="orange">
Toggle LED2
</button>
</a>

<a href="?toggle=allon">
<button class="green">
ALL ON
</button>
</a>

<a href="?toggle=alloff">
<button class="red">
ALL OFF
</button>
</a>

</div>

</body>
</html>