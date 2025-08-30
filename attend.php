<?php
$host = "localhost";
$db = "glhorgia_users";  
$user = "glhorgia_admin";     
$pass = "GLHOMES_DB_ADMIN06";  


$conn = new mysqli($host, $user, $pass, $db);

$swal_success = "";
$swal_error = "";

if (isset($_GET['trxref']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $ref = $_GET['trxref'];
    $amount = $_GET['amount'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($ref),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer sk_live_9c7dd14bedec1d3c18abc60e6bcdb5a269f8ca24",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] === true) {
        $payment_status = $result['data']['status'];

        if ($payment_status === 'success') {
            $ref = $conn->real_escape_string($ref);
            $conn->query("UPDATE mindset_shift_attendees SET payment_status = 1, payment_reference = '$ref', payment_amount = '$amount' WHERE id = $id");

            // Retrieve user info for email
            $result = $conn->query("SELECT fullname, email FROM mindset_shift_attendees WHERE id = $id");
            $user = $result->fetch_assoc();
            $name = $user['fullname'];
            $email = $user['email'];

            // Email content
            $subject = "GL Homes Masterclass Payment Confirmation";
            $message = "
            Dear $name,\n
            Thank you for registering for the GL Homes Masterclass.
            Your payment has been confirmed successfully.\n
            Payment Reference: $ref
            We will keep in touch and share the event invite and access link with you before the masterclass start date.\n
            Best regards,
            GL Homes Team";

            $headers = "From: masterclass@glhomesltd.com\r\n" .
                    "Reply-To: masterclass@glhomesltd.com\r\n" .
                    "X-Mailer: PHP/" . phpversion();

            // Send email
            mail($email, $subject, $message, $headers);

            // Trigger success alert
            $swal_success = "Payment successful! A confirmation email has been sent to you.";
        }
        else {
            $swal_error = "Transaction failed or was not completed.";
        }
    }
    else {
        $swal_error = "Failed to verify transaction. Please try again.";
    } 
}
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">

<head>
    <meta charset="utf-8" />
    <title> GL Homes Limited</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta
        content="GL Homes Limited is a leading real estate, construction and Automation tech company specializing in residential and commercial properties. With a commitment to quality and innovation, we create exceptional living spaces and infrastructure that enhance communities and lifestyles. "
        name="description" />
    <meta content="GL Homes" name="author" />
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <link href="css/common.css" rel="stylesheet">
    <link href="css/theme-02.css" rel="stylesheet">


     <link rel="stylesheet" href="assets2/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets2/css/animate.min.css">
    <link rel="stylesheet" href="assets2/css/magnific-popup.css">
    <link rel="stylesheet" href="assets2/css/fontawesome-all.min.css">
    <link rel="stylesheet" href="assets2/css/tg-flaticon.css">
    <link rel="stylesheet" href="assets2/css/swiper-bundle.min.css">
    <link rel="stylesheet" href="assets2/css/default.css">
    <link rel="stylesheet" href="assets2/css/default-icons.css">
    <link rel="stylesheet" href="assets2/css/odometer.css">
    <link rel="stylesheet" href="assets2/css/aos.css">
    <link rel="stylesheet" href="assets2/css/tg-cursor.css">
    <link rel="stylesheet" href="assets2/css/main.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/css/intlTelInput.css" />
    <style>
        /* Align phone input inside your form design */
        .intl-tel-input {
            width: 100%;
        }
        .intl-tel-input .form-control {
            height: 100%;
        }
        .intl-tel-input input {
            width: 100%;
            padding-left: 58px !important; /* adjusts for dial code */
        }
    </style>


    <!-- favicon -->
    <link rel="shortcut icon" href="images/Logo-sm.png">

    <link href="https://fonts.googleapis.com/css2?family=League+Spartan:wght@200;300;400;500;600;700&display=swap" rel="stylesheet" />

    <!-- Tailwind css Cdn -->
    <script src="https://cdn.tailwindcss.com"></script>

    <script src="tailwind.config.js"></script>
    <!-- SweetAlert2 CSS + JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- custom - css include -->
		<link rel="stylesheet" type="text/css" href="assets/css/style.css">


    <!-- icon - css include -->
		<link rel="stylesheet" type="text/css" href="assets/css/unicons.css">
		<link rel="stylesheet" type="text/css" href="assets/css/flaticon.css">
		<link rel="stylesheet" type="text/css" href="assets/css/fontawesome-all.css">


    <!-- carousel - css include -->
		<link rel="stylesheet" type="text/css" href="assets/css/animate.css">
		<link rel="stylesheet" type="text/css" href="assets/css/owl.carousel.min.css">
		<link rel="stylesheet" type="text/css" href="assets/css/owl.theme.default.min.css">

		<!-- magnific popup - css include -->
		<link rel="stylesheet" type="text/css" href="assets/css/magnific-popup.css">

		<!-- scroll animation - css include -->
		<link rel="stylesheet" type="text/css" href="assets/css/aos.css">

        <style>
            .service-section .service-grid-item {
    border-color: #e3e8fe;
}
        </style>

</head>

<body class="font-body">

     <!-- header-area -->
    <header class="transparent-header">
        <div id="header-fixed-height"></div>
        <div id="sticky-header" class="tg-header__area tg-header__area-two">
            <div class="container">
                <div class="row">
                    <div class="col-12">
                        <div class="tgmenu__wrap">
                            <nav class="tgmenu__nav">
                                <div class="logo">
                                    <a href="index"><img src="images/Logo-sm.png" alt="Logo"></a>
                                </div>
                                <div class="tgmenu__navbar-wrap tgmenu__main-menu d-none d-lg-flex">
                                    <ul class="navigation">
                                        <li class="active"><a href="index">Home</a></li>
                                        <li><a href="index#about">About us</a></li>
                                        <li class="active menu-item-has-children tg-mega-menu-has-children"><a href="#">Our Companies</a>
                                            <div class="tg-mega-menu-wrap">
                                                <div class="row row-cols-1 row-cols-lg-6 row-cols-xl-6">
                                                    <div class="col">
                                                        <div class="mega-menu-item">
                                                            <div class="mega-menu-thumb">
                                                                <a href="https://realestate.glhomesltd.com"><img src="images/r1.webp" alt="img"></a>
                                                            </div>
                                                            <div class="mega-menu-content">
                                                                <h4 class="title"><a href="https://realestate.glhomesltd.com">GL Real Estate</a></h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="mega-menu-item active">
                                                            <div class="mega-menu-thumb">
                                                                <a href="https://construction.glhomesltd.com"><img src="images/c2.webp" alt="img"></a>
                                                            </div>
                                                            <div class="mega-menu-content">
                                                                <h4 class="title"><a href="https://construction.glhomesltd.com">GL Homes Construction</a></h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col">
                                                        <div class="mega-menu-item">
                                                            <div class="mega-menu-thumb">
                                                                <a href="https://tech.glhomesltd.com"><img src="images/t4.webp" alt="img"></a>
                                                            </div>
                                                            <div class="mega-menu-content">
                                                                <h4 class="title"><a href="https://tech.glhomesltd.com">GL Homes Tech</a></h4>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                </div>
                                            </div>
                                        </li>
                                        
                                        <li class="menu-item-has-children"><a href="#">Masterclasses</a>
                                            <ul class="sub-menu">
                                                <li><a href="index#msplan">Access Plans</a></li>
                                                <li><a href="index#masterclass">Beyond the Horizon</a></li>
                                                <li><a href="index#msclass">Mindset Shift</a></li>
                                            </ul>
                                        </li>
                                        
                                    </ul>
                                </div>
                                <div class="tgmenu__action tgmenu__action-two">
                                    <ul class="list-wrap">
                                        <li class="header-btn header-btn-two">
                                            <a href="attend" class="tg-btn">Register now</a>
                                        </li>
                                    </ul>
                                </div>
                                <div class="mobile-nav-toggler"><i class="tg-flaticon-menu"></i></div>
                            </nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Mobile Menu  -->
        <div class="tgmobile__menu">
            <nav class="tgmobile__menu-box">
                <div class="close-btn"><i class="tg-flaticon-close-1"></i></div>
                <div class="nav-logo">
                    <a href="index"><img src="images/Logo-sm.png" alt="Logo"></a>
                </div>
                <div class="tgmobile__menu-outer">
                    <!--Here Menu Will Come Automatically Via Javascript / Same Menu as in Header-->
                </div>
                <div class="social-links">
                    <ul class="list-wrap">
                        <li><a target="_blank" href="https://www.facebook.com/share/1LakCWxsgr/"><i class="fab fa-facebook-f"></i></a></li>
                        <li><a target="_blank" href="https://wa.me/+2349067274433"><i class="fab fa-whatsapp"></i></a></li>
                        <li><a target="_blank" href="https://youtube.com/@GLHOMESLTD"><i class="fab fa-youtube"></i></a></li>
                    </ul>
                </div>
            </nav>
        </div>
        <div class="tgmobile__menu-backdrop"></div>
        <!-- End Mobile Menu -->

    </header>
    <!-- header-area-end -->
    
    <main class="main-area fix">

<section class="banner__area-two fix">
            <div class="container">
    <div class="container mx-auto">
            <div class="text-center text-dark relative">
                <h2 class="text-5xl font-semibold capitalize text-transparent bg-clip-text bg-gradient-to-r from-red-700 via-blue-600 to-blue-400 mt-2" id="ready-text">Are you ready?</h2>
                <p class="text-base mt-2 mb-7" id="ready-text2">Countdown to MINDSET SHIFT Business Masterclass</p>
                <div id="countdown" class="my-10 z-30">
                    <div class="flex flex-wrap items-center justify-center">
                        <div>
                            <div class="sm:h-40 sm:w-48 h-32 w-32 flex items-center justify-center bg-white/10 border border-white/20">
                                <div>
                                    <span id="days" class="text-3xl md:text-6xl"></span>
                                    <p class="text-xs font-semibold uppercase mt-5">days</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="sm:h-40 sm:w-48 h-32 w-32 flex items-center justify-center bg-white/10 border border-white/20">
                                <div>
                                    <span id="hours" class="text-3xl md:text-6xl"></span>
                                    <p class="text-xs font-semibold uppercase mt-5">Hours</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="sm:h-40 sm:w-48 h-32 w-32 flex items-center justify-center bg-white/10 border border-white/20">
                                <div>
                                    <span id="minutes" class="text-3xl md:text-6xl"></span>
                                    <p class="text-xs font-semibold uppercase mt-5">Minutes</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <div class="sm:h-40 sm:w-48 h-32 w-32 flex items-center justify-center bg-white/10 border border-white/20">
                                <div>
                                    <span id="seconds" class="text-3xl md:text-6xl"></span>
                                    <p class="text-xs font-semibold uppercase mt-5">Seconds</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    



    <div class="forny-container mt-4">
        
<div class="forny-inner">
    <div class="forny-form" id="register">
        <div class="text-center">
            <h2 class="text-4xl/snug font-semibold text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-blue-600 to-blue-400 mb-2">Register for Mindset Shift</h2>
            <p class="text-1xl text-center font-semibold mb-5">Fill in your details and proceed to payment. <br>
            <strong>NOTE:</strong>We will contact you after payment</p>
        </div>
        <form>
     <div class="form-group">
        <div class="input-group">
            <input required class="form-control" name="name" id="name" type="text" placeholder="Full Name">   
        </div>
    </div>        
    <div class="form-group">
        <div class="input-group">
            <input required class="form-control" name="email" id="email" type="email" placeholder="Email Address">
        </div>
    </div>

    <!-- Phone Number Input -->
    <div class="form-group">
        <div class="input-group">
            <input required class="form-control" id="phone" type="tel" name="phone" placeholder="Phone Number">
        </div>
    </div>

    <!-- Gender Dropdown -->
<div class="form-group">
    <div class="input-group">
        <select id="gender" class="form-control" required>
            <option value="">Select Gender</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Other">Other</option>
        </select>
    </div>
</div>
<!-- Gender Dropdown -->
<div class="form-group">
    <div class="input-group">
        <select id="category" class="form-control" required>
            <option value="">Which Category can you Identify in?</option>
             <option value="Startup Owner">Startup Owner</option>
            <option value="Business Aspirant">Business Aspirant</option>
            <option value="Business Scale-up">Business Scale-up</option>
            <option value="Business Consultancy">Business Consultancy</option>
             <option value="Business Mentorship">Business Mentorship</option>
        </select>
    </div>
</div>
    <!-- Country Dropdown -->
    <div class="form-group">
        <div class="input-group">
            <select id="country" class="form-control" required>
                <option value="">Select Country</option>
            </select>
        </div>
    </div>

    <!-- State Dropdown -->
    <div class="form-group">
        <div class="input-group">
            <select id="state" class="form-control" required>
                <option value="">Select State/Province</option>
            </select>
        </div>
    </div>

    <!-- City Input -->
    <div class="form-group">
        <div class="input-group">
            <input type="text" class="form-control" id="city" placeholder="Enter your city" required>
        </div>
    </div>
    <div class="mt-3 custom-control custom-checkbox">
        <input type="checkbox" class="custom-control-input" id="executive">
        <label class="custom-control-label text-danger" for="executive">Include Executive Benefits (NGN 250,000)
        </label>
    </div>

    <div class="form-group" id="executive-options" style="display: none; margin-top: 10px;">
        <div class="input-group">
            
            <select id="executiveChoice" class="form-control">
                <option value="">Select the Speaker to Engage:</option>
                <option value="Mr. Michael Hadi Ango">Mr. Michael Hadi Ango (Chairman, FCT Federal Inland Revenue Service)</option>
                <option value="Mr. Alex Alozie">Mr. Alex Alozie (Executive Director, UBA North Bank) </option>
                <option value="Dr. Nnaemeka Onyeka Obiaraeri">Dr. Nnaemeka Onyeka Obiaraeri</option>
                <option value="Mr. Emmanuel O. Emmanuel">Mr. Emmanuel O. Emmanuel (CEO, GL Homes Limited)</option>
            </select>
        </div>
    </div>
    

    <div class="row mt-6 mb-6">
        <div class="col-6 d-flex align-items-center"></div>
    </div>
    <strong class="text-1xl text-blue-600 mb-3">Any issue? message masterclass@glhomesltd.com</strong><br><br>
<button class="btn text-white bg-blue-600 hover:bg-blue-700 btn-block col-xl-4 col-lg-4 col-md-4 col-12" id="pn" type="button" onclick="pay_now(this, 'NGN');">Pay in Naira</button>
    <!-- <div class="row"> -->
        
        <!-- <div class="line col-xl-4 col-lg-4 col-12 col-md-4 mt-5 mb-3">
                <span>or </span>
            </div>
         <button class="btn text-white bg-blue-600 hover:bg-blue-700 btn-block col-4 col-xl-4 col-lg-4 col-12 col-md-4" id="pn2" type="button" onclick="pay_now(this, 'USD');">Pay in USD</button> -->
    <!-- </div> -->
</form>

    </div>
</div>

    </div>
            </div>
</section>

<section class="cta__area-two">
            <div class="container">
                <div class="cta__inner-wrap-two">
                    <div class="row align-items-center">
                        <div class="col-lg-6">
                            <div class="cta__content-three">
                                <h2 class="title">Message us for any issues regarding this class or registration</h2>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="cta__content-right">
                                <div class="cta__contact">
                                    <div class="icon">
                                        <i class="flaticon-telephone"></i>
                                    </div>
                                    <div class="content">
                                        <span>Do you have any Question?</span>
                                        <a href="mailto:masterclass@glhomesltd.com" style="font-size: 18px;">masterclass@glhomesltd.com</a>
                                    </div>
                                </div>
                                <a href="tel:+2349067274433" class="tg-btn tg-border-btn ">Call us <img src="assets2/img/icons/right_arrow.svg" alt="" class="injectable"></a>
                            </div>
                        </div>
                    </div>
                    <div class="cta__shape-three">
                        <img src="assets2/img/images/h2_cta_shape.svg" alt="shape">
                    </div>
                </div>
            </div>
        </section>
</main>
    <!-- Section End -->



    <!-- footer-area -->
    <footer class="footer__area-two fix">
        <div class="container">
            <div class="footer__top-two">
                <div class="row">
                    <div class="col-xl-3 col-lg-4 col-md-6">
                        <div class="footer__widget">
                            <!-- <div class="footer__logo-two">
                                <a href="index"><img src="images/Logo-sm.png" alt="logo"></a>
                            </div> -->
                            <div class="footer__contact">
                                <ul class="list-wrap">
                                    <li><i class="flaticon-placeholder"></i>91 Okigwe Road, Aba, Abia State. Nigeria</li>
                                    <li><i class="flaticon-telephone"></i><a href="tel:+2349067274433">+234 (0)90 6727 4433</a></li>
                                    <li><i class="flaticon-envelope"></i><a href="mailto:masterclass@glhomesltd">masterclass@glhomesltd</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6">
                        <div class="footer__widget">
                            <h4 class="footer__widget-title footer__widget-title-two">Quick Links</h4>
                            <ul class="footer__widget-link footer__widget-link-two list-wrap">
                                <li><a href="index">Home </a></li>
                                <li><a href="index#about">About us</a></li>
                                <li><a href="attend">Register</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-2 col-lg-4 col-md-6 col-sm-6">
                        <div class="footer__widget">
                            <h4 class="footer__widget-title footer__widget-title-two">Masterclass</h4>
                            <ul class="footer__widget-link footer__widget-link-two list-wrap">
                                <li><a href="index#masterclass">Beyond the Horizon</a></li>
                                <li><a href="index#msclass">Mindset Shift</a></li>
                                <li><a href="index#testimonial">Testimonials</a></li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-xl-4 col-lg-4 col-md-6">
                        <div class="footer__widget">
                            <h4 class="footer__widget-title footer__widget-title-two">Company Links</h4>
                            <div class="footer__instagram">
                                <ul class="list-wrap">
                                    <!-- <li>
                                        <a href="https://realestate.glhomesltd.com/" target="_blank"><img src="images/r1.webp" alt="img"></a>
                                    </li> -->
                                    <li>
                                        <a href="https://tech.glhomesltd.com" target="_blank"><img src="images/companies/tech.png" alt="img"></a>
                                    </li>
                                    <li>
                                        <a href="https://construction.glhomesltd.com" target="_blank"><img src="images/companies/const.jpeg" alt="img"></a>
                                    </li>
                                    
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer__bottom-two">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <div class="copyright__content-two">
                            <p> ©
                            <script>document.write(new Date().getFullYear())</script> GL Homes Limited. By <a class="font-medium" href="https://tech.glhomesltd">GL Homes Tech</a>
                        </p>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <div class="footer__shape-wrap-two">
            <img src="assets2/img/images/h2_footer_shape01.svg" alt="shape">
            <img src="assets2/img/images/h2_footer_shape02.svg" alt="shape">
            <img src="assets2/img/images/h2_footer_shape03.svg" alt="shape">
        </div>
    </footer>
    <!-- footer-area-end -->




 <script src="assets/js/jquery-3.3.1.min.js"></script>
    <script src="js2/bootstrap.min.js"></script>
    <script src="js2/main.js"></script>
    <script src="js2/demo.js"></script>
    <script src="assets/js/popper.min.js"></script>
    <!-- carousel - jquery include -->
		<script src="assets/js/owl.carousel.min.js"></script>

		<!-- magnific popup - jquery include -->
		<script src="assets/js/jquery.magnific-popup.min.js"></script>

		<!-- scroll animation - jquery include -->
		<script src="assets/js/aos.js"></script>
		<script src="assets/js/parallax.min.js"></script>

        <!-- multy countdown - jquery include -->
		<script src="assets/js/jquery.countdown.js"></script>

		<!-- counter - jquery include -->
		<script src="assets/js/waypoints.min.js"></script>
		<script src="assets/js/jquery.counterup.min.js"></script>

		<!-- google - jquery include -->
        <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyAhrdEzlfpnsnfq4MgU1e1CCsrvVx2d59s"></script>
        <script src="assets/js/gmaps.js"></script>

		<!-- mCustomScrollbar for sidebar menu - jquery include -->
        <script src="assets/js/jquery.mCustomScrollbar.js"></script>

		<!-- custom - jquery include -->
		<script src="assets/js/custom.js"></script>
        <!-- JS here -->
    <script src="assets2/js/vendor/jquery-3.6.0.min.js"></script>
    <script src="assets2/js/bootstrap.min.js"></script>
    <script src="assets2/js/jquery.magnific-popup.min.js"></script>
    <script src="assets2/js/jquery.odometer.min.js"></script>
    <script src="assets2/js/jquery.appear.js"></script>
    <script src="assets2/js/swiper-bundle.min.js"></script>
    <script src="assets2/js/jquery.parallaxScroll.min.js"></script>
    <script src="assets2/js/jquery.marquee.min.js"></script>
    <script src="assets2/js/tg-cursor.min.js"></script>
    <script src="assets2/js/ajax-form.js"></script>
    <script src="assets2/js/svg-inject.min.js"></script>
    <script src="assets2/js/wow.min.js"></script>
    <script src="assets2/js/aos.js"></script>
    <script src="assets2/js/main.js"></script>
    <script>
        SVGInject(document.querySelectorAll("img.injectable"));
    </script>
<script>
document.getElementById("executive").addEventListener("change", function() {
    const inputDiv = document.getElementById("executive-options");
    if (this.checked) {
      inputDiv.style.display = "block"; // Show input when checked
    } else {
      inputDiv.style.display = "none"; // Hide input when unchecked
    }
  });
</script>
    <script>
        var accepting_registration = true

        if (!accepting_registration){
            document.querySelector('.forny-container').innerHTML = `
                <div class="text-center">
                    <h2 class="text-2xl font-semibold text-red-600">Masterclass Registration is currently closed.</h2>
                    <p class="text-gray-600 mb-2">Please keep an eye on your email to get our notifications.</p>
                </div>
            `;

            countdown.style.display = 'none'; // Hide countdown if registration is closed
            document.getElementById('ready-text').innerText = "Beyond the Horizon Masterclass Ended.";
            document.getElementById('ready-text2').innerText = "We are not accepting any registrations at this time. We will let you know when the next masterclass is available.";
        }

        </script>
        
    <script>

        //Show and hide hamburguer menu in small screens 
        const menu = document.getElementById("menu");
        const ulMenu = document.getElementById("ulMenu");

        function menuToggle() {
            menu.classList.toggle('navbar-show')
        }

        // Browser resize listener
        window.addEventListener("resize", menuResize);

        // Rezise menu if user changing the width with responsive menu opened
        function menuResize() {
            // first get the size from the window
            const window_size = window.innerWidth || document.body.clientWidth;
            if (window_size > 640) {
                menu.classList.remove('navbar-show');
            }
        }
    </script>
<script>
let userCountry = '';

// Detect country
fetch('https://ipapi.co/json/')
  .then(res => res.json())
  .then(data => {
    userCountry = data.country_name;
    console.log("User is at:", userCountry); // Optional debug
  })
  .catch(err => {
    console.error("Geolocation error:", err);
    userCountry = 'Unknown';
  });
</script>
<!-- Phone Input Script -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/intlTelInput.min.js"></script>
<script>
    const phoneInput = document.querySelector("#phone");
    window.intlTelInput(phoneInput, {
        separateDialCode: true,
        preferredCountries: ["ng", "us", "gb"],
        utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.19/js/utils.min.js"
    });
</script>

<!-- Countries & States using CountriesNow API -->
<script>
    const countrySelect = document.getElementById('country');
    const stateSelect = document.getElementById('state');

    // Fetch and populate countries
    fetch('https://countriesnow.space/api/v0.1/countries/positions')
        .then(res => res.json())
        .then(data => {
            data.data.forEach(country => {
                const opt = document.createElement('option');
                opt.value = country.name;
                opt.textContent = country.name;
                countrySelect.appendChild(opt);
            });
        });

    // Fetch and populate states
    countrySelect.addEventListener('change', function () {
        const country = this.value;
        stateSelect.innerHTML = '<option value="">Loading...</option>';
        fetch('https://countriesnow.space/api/v0.1/countries/states', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ country })
        })
        .then(res => res.json())
        .then(data => {
            stateSelect.innerHTML = '<option value="">Select State/Province</option>';
            data.data.states.forEach(state => {
                const opt = document.createElement('option');
                opt.value = state.name;
                opt.textContent = state.name;
                stateSelect.appendChild(opt);
            });
        });
    });
</script>
<script>
    //adding async to the form submission script
  const payNairaButton = document.getElementById("pn");
    // const payUsdButton = document.getElementById("pn2");

async function pay_now(button, currency) {

    const fullname = document.getElementById("name").value.trim();
    const email = document.getElementById("email").value.trim();
    const phoneInput = window.intlTelInputGlobals.getInstance(document.getElementById("phone"));
    const phone = phoneInput.getNumber();
    const country = document.getElementById("country").value;
    const state = document.getElementById("state").value;
    const city = document.getElementById("city").value.trim();
    const gender = document.getElementById("gender").value;
    const category = document.getElementById("category").value;
    var executive = 0;
    var executiveChoice = "";


    // Validation
    if (!fullname) return alert("Please enter your full name.");
    if (!email || !/^\S+@\S+\.\S+$/.test(email)) return alert("Please enter a valid email.");
    if (!phone) return alert("Please enter a valid phone number.");
    if (!gender) return alert("Please select your gender.");
    if (!category) return alert("Please choose a category you belong!");
    if (!country) return alert("Please select a country.");
    if (!state) return alert("Please select a state.");
    if (!city) return alert("Please enter your city.");

    if(!document.getElementById("executive").checked){
        executive = 0;
        executiveChoice = "";
    } else {
        executive = 1;
        executiveChoice = document.getElementById("executiveChoice").value;
        if (!executiveChoice) return alert("Please select the speaker you want to engage with.");
    }

    const data = {
        fullname,
        email,
        phone,
        phone_full: phone,
        country,
        state,
        gender,
        city,
        category,
        currency,
        executive,
        executiveChoice
    };
    // pay now button should be disabled to prevent multiple clicks and show please wait message
    
    //disabling the 2 buttons of pay with Naira and USD
//   if (currency == "NGN" && userCountry !== "Nigeria") {
//         Swal.fire({
//             icon: 'warning',
//             title: 'Unavailable',
//             text: 'Payment in Naira is only available to users located in Nigeria. Please use the USD option.',
//             confirmButtonColor: '#d33'
//         });
//     }
//     else{
        payNairaButton.disabled = true;
        //payUsdButton.disabled = true;
        button.textContent = "Please wait...";

        await fetch("process.php", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(data)
        })
        .then(res => res.json())
        .then(response => {
            if (response.status === "success") {
                window.location.href = response.payment_link;
            } else {
                alert("Error: " + response.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("An error occurred. Please try again.");
        });

        payNairaButton.disabled = false;
        //payUsdButton.disabled = false;
        button.textContent = "Pay in " + currency;
    //}
}
</script>

<script>
<?php if (!empty($swal_success)): ?>
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: '<?= addslashes($swal_success) ?>',
        confirmButtonColor: '#3085d6'
    });
<?php elseif (!empty($swal_error)): ?>
    Swal.fire({
        icon: 'error',
        title: 'Oops...',
        text: '<?= addslashes($swal_error) ?>',
        confirmButtonColor: '#d33'
    });
<?php endif; ?>
</script>
<script src="js/theme.js"></script>
</body>

</html>