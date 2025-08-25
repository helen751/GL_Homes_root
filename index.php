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
            "Authorization: Bearer sk_test_a69ba65a77ac70e96e3694b25d5db08a2a79add9",
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

    <!-- Navbar Start -->
    <div class="flex flex-col bg-white">
        <nav id="nav" class="py-6 md:border-b-0 border-b" role="navigation">
            <div class="container mx-auto px-2 flex items-center flex-no-wrap justify-between">
                <a href="https://glhomesltd.com" class="flex items-center">
                    <img src="images/Logo-sm.png" alt="GL Homes Ltd" class="h-20">
                </a>
                <div
                    class="w-auto transition-all ease-out duration-300 transition-none md:flex-grow flex items-center justify-between opacity-0 opacity-100">
                    <ul class="flex duration-300 ease-out sm:transition-none ms-auto md:mt-0">
                        <li>
                            <a class="pt-3 px-3 md:px-6 font-medium font-secondary block text-blue-500 text-lg" href="#"
                                title="https://glhomesltd.com">GL Homes Limited</a>
                        </li>
                        <li>
                            <a class="pt-3 px-3 md:px-6 font-medium font-secondary block text-black/70 hover:text-blue-500 text-lg"
                                href="#companies" title="About">Our Companies</a>
                        </li>
                        
                    </ul>
                </div>
            </div>
        </nav>
    </div>
    
    
    <!-- features-section - start
		================================================== -->
		<section class="features-section sec-ptb-160 clearfix">
			<div class="container">

				<div class="feature-item mb-0">
					<div class="row justify-content-lg-between justify-content-md-center">

						<div class="col-lg-5 col-md-8 col-sm-12">
							<div class="feature-image-2 text-center">
								<span class="item-image">
									<img src="assets/images/bms.png" alt="image_not_found">
								</span>
								<a class="popup-video" href="https://youtu.be/pmm-1T9Av-g" data-aos="zoom-in" data-aos-delay="100">
									<i class='uil uil-play'></i>
								</a>
							</div>
						</div>

						<div class="col-lg-6 col-md-8 col-sm-12">
							<div class="feature-content p-0">
								<h2 class="feature-item-title">ABOUT US / <span>Who we are</span></h2>
								<p class="mb-0">
									GL HOMES Beyond Horizon Leadership and Business Masterclass is a value-based leadership and entrepreneurial development platform, established in 2017, with a mission to raise transformational leaders, innovators, and entrepreneurs across Africa. It is a faith-rooted initiative focused on disrupting mindsets, raising ethical leaders, and driving sustainable development through practical, principle-based business education.

The platform has continued to expand since inception, never looking back. The first edition featured participants from 8 countries, while the most recent Masterclass welcomed professionals from 5 countries and over 20 cities. This growing community is driven by one common cause— to build Africa by raising 
								</p>

								<div class="service-list ul-li clearfix">
									<ul class="clearfix">
										<li>
											<div class="item-icon" style="background-image: url(assets/images/icons/bg-6.png);">
												<i class='uil uil-compass'></i>
											</div>
											<div class="item-content">
												<h3 class="item-title mb-15">Our Mission</h3>
												<p class="mb-0">
													To build, equip, and mentor bold and transformational business leaders, innovators, and entrepreneurs who are driven by purpose and values, capable of disrupting the status quo and igniting sustainable change in Africa and beyond.

												</p>
											</div>
										</li>
										<li>
											<div class="item-icon" style="background-image: url(assets/images/icons/bg-7.png);">
												<i class='uil uil-lightbulb-alt'></i>
											</div>
											<div class="item-content">
												<h3 class="item-title mb-15">Our Vision</h3>
												<p class="mb-0">
													To empower and impact visionary leaders and
                                                    entrepreneurs with a sound mindset, strategies, and skills
                                                    needed to scale up their businesses, inspire high-
                                                    performance teams, and create lasting impact in a rapidly
                                                    evolving global economy.
												</p>
											</div>
										</li>
										
									</ul>
								</div>

							</div>
						</div>

					</div>
				</div>

			</div>
		</section>
		<!-- features-section - end
		================================================== -->


		<!-- service-section - start
		================================================== -->
		<section id="service-section" class="service-section sec-ptb-160 pt-0 clearfix">
			<div class="container">
				<div class="row">

					

					<div class="col-lg-4 col-md-12 col-sm-12">
						<div class="service-grid-item text-center">
							<span class="item-icon" style="background-image: url(assets/images/icons/bg-2.png);">
								<i class='uil uil-users-alt'></i>
							</span>
							<h2 class="item-title mb-30">Participants</h2>
							<p>
								<div class="counter-items-list ul-li-center clearfix">
						<ul class="clearfix">
							<li>
								<h2 style="all: revert;"><span class="count-text">200</span>+</h2>
							</li>
						</ul>
					</div>
							</p>
						</div>
					</div>

					<div class="col-lg-4 col-md-12 col-sm-12">
						<div class="service-grid-item text-center">
							<span class="item-icon" style="background-image: url(assets/images/icons/bg-3.png);">
								<i class='uil uil-globe'></i>
							</span>
							<h2 class="item-title mb-30">Demographics</h2>
							<p>
								<div class="counter-items-list ul-li-center clearfix">
						<ul class="clearfix">
							<li class="mr-3">
								<h3 style="all: revert;"><span class="count-text">13</span>+</h3>
								<small class="counter-title">Countries</small>
							</li>
							<li>
								<h3 style="all: revert;"><span class="count-text">30</span>+</h3>
								<small class="counter-title">Cities</small>
							</li>
						</ul>
					</div>
							</p>
						</div>
					</div>

                    <div class="col-lg-4 col-md-12 col-sm-12">
						<div class="service-grid-item text-center">
							<span class="item-icon" style="background-image: url(assets/images/icons/bg-1.png);">
								<i class="uil uil-layer-group-slash"></i>
							</span>
							<h2 class="item-title mb-30">Testimonials</h2>
							<p>
								<div class="counter-items-list ul-li-center clearfix">
						<ul class="clearfix">
							<li>
								<h2 style="all: revert;"><span class="count-text">30</span>+</h2>
							</li>
						</ul>
					</div>
							</p>
						</div>
					</div>
					
				</div>
			</div>
		</section>
		<!-- service-section - end
		================================================== -->




    <!-- Section Start -->
    <section class="section py-2 bg-white" id="home">
        <div class="container mx-auto px-10">
            <h1 class="text-4xl/snug font-semibold text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-blue-600 to-blue-400 mb-4 text-center">
                        GL HOMES PRESENTS: MINDSET SHIFT 2025 
                        </h1>
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                <div class="mx-2">
                    
                        <span class="text-2xl text-gray-600">A Business Masterclass Like No Other!
                        </span>
                    
                    <p class="text-base text-zinc-800 max-w-lg mb-2">
                        Are you ready for a mindset shift towards your business?
                        This exclusive Masterclass brings together visionary leaders across governance, banking, and investment to share practical insights on building sustainable systems, global leadership, and innovative investment opportunities. Participants will gain actionable strategies that bridge ethics, finance, and real estate to unlock long-term impact.<br>

                        ✓ Ethical governance as the foundation for sustainable business growth<br>
                        ✓ Expanding leadership influence from regional to global markets<br>
                        ✓ Unlocking wealth through agricultural investment in real estate<br>
                        ✓ Practical strategies to build transparency, trust, and long-term impact<br>

                    </p>
                    <strong class="text-2xl text-zinc-600 mb-1">Date: Sunday, 21 <sup>st</sup> September 2025</strong><br>
                    <strong class="text-2xl text-zinc-600 mb-1">Time: 6:00PM WAT</strong><br>
                    <strong class="text-2xl text-zinc-600 mb-1">Venue: Google Meet</strong><br>
                    <strong class="text-2xl text-zinc-600 mb-1">Registration Fee: 
                        <strong class="text-red-600 line-through">₦25,000 NGN</strong>
                        <strong class="text-blue-600">₦10,000 (early rate – price will increase as date approaches!)</strong> 
                        <!-- <strong class="text-zinc-600">or</strong>
                        <strong class="text-red-600 line-through">25 USD</strong>
                        <strong class="text-blue-600">$10 USD(outside Nigeria)</strong>  -->
                    </strong><br>
                    <strong class="text-2xl text-blue-600 mb-4">Limited Seats Available!</strong><br><br>
                    <p class="text-base text-zinc-800 max-w-lg mb-6">
                        Don’t miss this opportunity to transform your business mindset and strategies for the year ahead.

                    </p>
                    <a class="w-auto inline-flex items-center font-semibold gap-2 px-6 py-2.5 rounded-full transition-all duration-300 text-white bg-blue-600 hover:bg-blue-700"
                        href="#register">
                        Register below <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" data-lucide="eye" class="lucide lucide-eye h-5 w-5">
                            <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </a>
                </div>
                <div class="max-w-lg mx-auto">
                    <img src="images/ms.webp" alt="GL Homes class" class="max-w-full w-auto">
                </div>
            </div>

            
        </div>
    </section>

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
    <div class="form-group">
        <div class="input-group">
            <input type="checkbox" class="form-control" id="executuve"> Include Executive Benefits (+ NGN 50,000)
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
    <!-- Section End -->

<!-- team-section - start
		================================================== -->
		<section class="team-section sec-ptb-160 bg-light-gray clearfix">
			<div class="container">

				<div class="row justify-content-center">
					<div class="col-lg-6 col-md-8 col-sm-12">
						<div class="section-title text-center">
							<h2 class="title-text mb-30">Meet with GLHOMES Masterclass Team</h2>
							<p class="paragraph-text mb-0">
								We would like to take the opportunity to introduce to our experienced team, bringing this masterclass to live.
							</p>
						</div>
					</div>
				</div>

				<div class="row">

					<div class="col-lg-3 col-md-6 col-sm-12">
						<div class="team-member-grid text-center">
							<div class="member-image image-container clearfix">
								<img src="assets/images/team/emmanuel2.webp" alt="CEO GLHomes Limited">
								<ul class="member-social-links clearfix">
									<li><a href="https://www.facebook.com/share/1Yku9SmJvz/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
									<li><a href="#!"><i class="fab fa-linkedin-in"></i></a></li>
								</ul>
							</div>
							<div class="member-info">
								<h3 class="member-name">Emmanuel Onyedikachi</h3>
								<span class="member-title">Visionary/Founder</span>
							</div>
						</div>
					</div>

					<div class="col-lg-3 col-md-6 col-sm-12">
						<div class="team-member-grid text-center">
							<div class="member-image image-container clearfix">
								<img src="assets/images/team/pauline.webp" alt="GL Homes Masterclass Team">
								<ul class="member-social-links clearfix">
									<li><a href="#!"><i class="fab fa-facebook-f"></i></a></li>
									<li><a href="#!"><i class="fab fa-linkedin-in"></i></a></li>
								</ul>
							</div>
							<div class="member-info">
								<h3 class="member-name">Dr Pauline Ogbo</h3>
								<span class="member-title">Business Advisor</span>
							</div>
						</div>
					</div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
						<div class="team-member-grid text-center">
							<div class="member-image image-container clearfix">
								<img src="assets/images/team/comfort.webp" alt="GL Homes Masterclass Team">
								<ul class="member-social-links clearfix">
									<li><a href="https://www.facebook.com/share/1716a4iDaA/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
									<li><a href="https://www.linkedin.com/in/comfort-okereke-ijeoma?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=android_app" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>
								</ul>
							</div>
							<div class="member-info">
								<h3 class="member-name">Comfort Ijeoma Okereke</h3>
								<span class="member-title">Project Manager</span>
							</div>
						</div>
					</div>

                    <div class="col-lg-3 col-md-6 col-sm-12">
						<div class="team-member-grid text-center">
							<div class="member-image image-container clearfix">
								<img src="assets/images/team/helen.webp" alt="GL Homes Masterclass Team">
								<ul class="member-social-links clearfix">
									<li><a href="https://www.facebook.com/share/1Dx5sqpAvF/" target="_blank"><i class="fab fa-facebook-f"></i></a></li>
									<li><a href="https://www.linkedin.com/in/helen-okereke-432323205" target="_blank"><i class="fab fa-linkedin-in"></i></a></li>
								</ul>
							</div>
							<div class="member-info">
								<h3 class="member-name">Helen ugoeze Okereke</h3>
								<span class="member-title">Tech Specialist</span>
							</div>
						</div>
					</div>
                    </div>

			</div>
		</section>
		<!-- team-section - end
		================================================== -->


    <!-- testimonial-section - start
		================================================== -->
		<section id="testimonial-section" class="testimonial-section sec-ptb-160 pb-0 clearfix">
			<div class="container">

				<div class="section-title mb-100 text-center">
					<span class="sub-title mb-15">Partcipants Testimonials</span>
					<h2 class="title-text mb-0">Stories of some of Our Participants</h2>
				</div>

				<div id="testimonial-carousel" class="testimonial-carousel owl-carousel owl-theme">
					<div class="item item-style-2 clearfix">
						<div class="hero-image">
							<img src="assets/images/testimonial/sharon.jpg" alt="image_not_found">
							<span class="icon" data-aos="zoom-in" data-aos-duration="450"><i class="flaticon-quotation"></i></span>
							<small class="design-image">
								<img src="assets/images/testimonial/design-image-1.png" alt="image_not_found">
							</small>
							<small class="shape-image">
								<img src="assets/images/testimonial/shape-1.png" alt="Innocent Sharon Chidinma">
							</small>
						</div>
						<div class="testimonial-content">
							<div class="hero-info mb-5">
								<h4 class="hero-name">Innocent Sharon Chidinma</h4>
								<span class="hero-title">BML Attendee</span>
								<div class="rating-star ul-li clearfix">
									<ul class="clearfix">
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
									</ul>
								</div>
							</div>
							<p class="paragraph-text mb-0">
								I attended the (Beyond the Horizon) and I must say it was both awesome and impactful.

What stood out the most was the practical approach the team adopted throughout the session. Rather than overwhelming participants with theory, they focused on actionable insights and real-world examples that made the concepts easy to grasp, regardless of one’s prior investment knowledge.

Overall, this session was a valuable experience. I left feeling more informed, inspired, and ready to take smarter steps toward financial independence. I highly recommend any future sessions by GLHomes  to anyone looking to grow their investment awareness.
							</p>
						</div>
					</div>

					<div class="item item-style-2 clearfix">
						<div class="hero-image">
							<img src="assets/images/testimonial/evelyn.jpg" alt="GLHomes masterclass testimonial">
							<span class="icon" data-aos="zoom-in" data-aos-duration="450"><i class="flaticon-quotation"></i></span>
							<small class="design-image">
								<img src="assets/images/testimonial/design-image-1.png" alt="image_not_found">
							</small>
							<small class="shape-image">
								<img src="assets/images/testimonial/shape-1.png" alt="Evelyn Ogonna Okereke">
							</small>
						</div>
						<div class="testimonial-content">
							<div class="hero-info mb-5">
								<h4 class="hero-name">Evelyn Ogonna Okereke</h4>
								<span class="hero-title">BML Attendee</span>
								<div class="rating-star ul-li clearfix">
									<ul class="clearfix">
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
									</ul>
								</div>
							</div>
							<p class="paragraph-text mb-0">
								Attending the Business masterclass helped me gain insights into key business concepts, developing strategic thinking, enhancing leadership capabilities, and learning actionable techniques for problem-solving and decision-making. 
							</p>
						</div>
					</div>

					<div class="item item-style-2 clearfix">
						<div class="hero-image">
							<img src="assets/images/testimonial/dummy.png" alt="image_not_found">
							<span class="icon" data-aos="zoom-in" data-aos-duration="450"><i class="flaticon-quotation"></i></span>
							<small class="design-image">
								<img src="assets/images/testimonial/design-image-1.png" alt="image_not_found">
							</small>
							<small class="shape-image">
								<img src="assets/images/testimonial/shape-1.png" alt="image_not_found">
							</small>
						</div>
						<div class="testimonial-content">
							<div class="hero-info mb-5">
								<h4 class="hero-name">Chinwuba Onyinye</h4>
								<span class="hero-title">BML Attendee</span>
								<div class="rating-star ul-li clearfix">
									<ul class="clearfix">
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
										<li class="rated"><i class="fas fa-star"></i></li>
									</ul>
								</div>
							</div>
							<p class="paragraph-text mb-0">
								I appreciate the power packed insight of the class, and mostly I appreciate the brains behind the success of the class infact, the team deserves an accolade.
                                <br>
                                I am so grateful for the opportunity to be part of this class, it was a great experience and I look forward to more of such classes.
							</p>
						</div>
					</div>
				</div>

			</div>
		</section>
		<!-- testimonial-section - end
		================================================== -->





    <!-- shuffle portfolio -->
    <section class="section" id="companies">
        <div class="mx-4 md:mx-16 bg-slate-100 p-6 md:p-16 rounded-[40px]">
            <h3 class="text-3xl text-center font-semibold mb-10">VISIT OUR COMPANIES</h3>
            <div class="flex flex-wrap justify-center">
                <div class="w-full md:w-1/2 xl:w-1/3 p-3">
                    <div class="mx-auto bg-white px-4 pt-4 pb-2 rounded-xl">
                        <a href="https://realestate.glhomesltd.com/" target="_blank">
                            <img src="images/r1.webp" class="rounded" alt="GL Homes Real Estate">
                        </a>
                        <h4 class="text-lg text-center mt-4 font-medium">GL Homes Real Estate</h4>
                    </div>
                </div>

                <div class="w-full md:w-1/2 xl:w-1/3 p-3">
                    <div class="mx-auto bg-white px-4 pt-4 pb-2 rounded-xl">
                        <a href="https://tech.glhomesltd.com" target="_blank">
                            <img src="images/t4.webp" class="rounded" alt="GL Homes Tech">
                        </a>
                        <h4 class="text-lg text-center mt-4 font-medium">GL Homes Tech Website</h4>
                    </div>
                </div>

                <div class="w-full md:w-1/2 xl:w-1/3 p-3">
                    <div class="mx-auto bg-white px-4 pt-4 pb-2 rounded-xl">
                        <a href="https://construction.glhomesltd.com" target="_blank">
                            <img src="images/c2.webp" class="rounded" alt="GL Homes Construction">
                        </a>
                        <h4 class="text-lg text-center mt-4 font-medium">GL Homes construction Website</h4>
                    </div>
                </div>

            </div>
        </div>
    </section>


    <footer class="py-8">
        <div class="container mx-auto px-10">

            <div class="flex">
                <div class="w-full">
                    <div class="text-center">
                        <p class="text-muted"> ©
                            <script>document.write(new Date().getFullYear())</script> GL Homes Limited. By <a class="font-medium" href="https://tech.glhomesltd">GL Homes Tech</a>
                        </p>

                    </div>
                </div>
            </div>
            <!-- end row -->

        </div>
    </footer>
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

    // Validation
    if (!fullname) return alert("Please enter your full name.");
    if (!email || !/^\S+@\S+\.\S+$/.test(email)) return alert("Please enter a valid email.");
    if (!phone) return alert("Please enter a valid phone number.");
    if (!gender) return alert("Please select your gender.");
    if (!category) return alert("Please choose a category you belong!");
    if (!country) return alert("Please select a country.");
    if (!state) return alert("Please select a state.");
    if (!city) return alert("Please enter your city.");

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
        currency
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