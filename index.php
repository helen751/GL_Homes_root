<?php
$host = "localhost";
$db = "glhorgia_users";  
$user = "glhorgia_admin";     
$pass = "GLHOMES_DB_ADMIN06";  


$conn = new mysqli($host, $user, $pass, $db);

$swal_success = "";
$swal_error = "";

if (isset($_GET['tx_ref']) && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    $ref = $_GET['tx_ref'];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://api.flutterwave.com/v3/transactions/verify_by_reference?tx_ref=$ref",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer FLWSECK-ed3fa365dbdbbe4554832ea097659f65-197ba0c5ed0vt-X",
            "Content-Type: application/json"
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);
    $result = json_decode($response, true);

    if (isset($result['status']) && $result['status'] === 'success') {
        $payment_status = $result['data']['status'];

        if ($payment_status === 'successful') {
            $ref = $conn->real_escape_string($ref);
            $conn->query("UPDATE masterclass_registrations_01 SET payment_status = 1, payment_reference = '$ref' WHERE id = $id");

            // Retrieve user info for email
            $result = $conn->query("SELECT fullname, email FROM masterclass_registrations_01 WHERE id = $id");
            $user = $result->fetch_assoc();
            $name = $user['fullname'];
            $email = $user['email'];

            // Email content
            $subject = "GL Homes Masterclass Payment Confirmation";
            $message = "
            Dear $name,\n\n
            Thank you for registering for the GL Homes Masterclass.\n
            Your payment has been confirmed successfully.\n\n
            Payment Reference: $ref\n
            We will keep in touch and share the event invite and access link with you before the masterclass start date.\n\n
            Best regards,\n
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

    <!-- Section Start -->
    <section class="section py-14 bg-white" id="home">
        <div class="container mx-auto px-10">
            <div class="grid lg:grid-cols-2 gap-10 items-center">
                <div class="mx-2">
                    <h1 class="text-4xl/snug font-semibold text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-blue-600 to-blue-400 mb-4">
                        GL HOMES PRESENTS: BEYOND THE HORIZON 2025 
                        </h1>
                        <span class="text-2xl text-gray-600">A Business Masterclass Like No Other!
                        </span>
                    
                    <p class="text-base text-zinc-800 max-w-lg mb-6">
                        Are you ready to break limits and unlock new levels in business?
                        Join GL Homes for BEYOND THE HORIZON 2025,  a power-packed masterclass designed to equip entrepreneurs, aspiring business leaders, and visionaries with the tools to build, scale, and dominate in today’s marketplace!<br>

                        ✓ Learn from top industry experts<br>
                        ✓ Discover proven strategies for business growth<br>
                        ✓ Network with forward-thinking minds<br>
                        ✓ Gain clarity, confidence, and direction for 2025 and beyond!<br>

                    </p>
                    <strong class="text-2xl text-blue-600 mb-4">Date: SUNDAY, 20<sup>th</sup> July, 2025</strong><br>
                    <strong class="text-2xl text-blue-600 mb-4">Time: 7:00PM WAT</strong><br>
                    <strong class="text-2xl text-blue-600 mb-4">Venue: Google Meet</strong><br>
                    <strong class="text-2xl text-blue-600 mb-4">Registration Fee: 5,000 NGN(Nigerians only) or $25 USD(outside Nigeria)</strong><br>
                    <strong class="text-2xl text-blue-600 mb-4">Limited Seats Available!</strong><br><br>
                    <p class="text-base text-zinc-800 max-w-lg mb-6">
                        Don’t miss this opportunity to transform your business mindset and strategies for the year ahead.
                        Your next level is not behind you… it’s BEYOND THE HORIZON!

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
                    <img src="images/bms-july.webp" alt="GL Homes class" class="max-w-full w-auto">
                </div>
            </div>

            
        </div>
    </section>
    <div class="forny-container">
        
<div class="forny-inner">
    <div class="forny-form" id="register">
        <div class="text-center">
            <h2 class="text-4xl/snug font-semibold text-transparent bg-clip-text bg-gradient-to-r from-red-500 via-blue-600 to-blue-400 mb-2">Register for GLHOMES BMC</h2>
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

    <div class="row mt-6 mb-6">
        <div class="col-6 d-flex align-items-center"></div>
    </div>

    <div class="row">
        <button class="btn text-white bg-blue-600 hover:bg-blue-700 btn-block col-xl-4 col-lg-4 col-md-4 col-12" id="pn" type="button" onclick="pay_now(this, 'NGN');">Pay in Naira</button>
        <div class="line col-xl-4 col-lg-4 col-12 col-md-4 mt-5 mb-3">
                <span>or </span>
            </div>
         <button class="btn text-white bg-blue-600 hover:bg-blue-700 btn-block col-4 col-xl-4 col-lg-4 col-12 col-md-4" id="pn2" type="button" onclick="pay_now(this, 'USD');">Pay in USD</button>
    </div>
</form>

    </div>
</div>

    </div>
    <!-- Section End -->

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
 <script src="js2/"></script>
    <script src="js2/bootstrap.min.js"></script>
    <script src="js2/main.js"></script>
    <script src="js2/demo.js"></script>
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
    const payUsdButton = document.getElementById("pn2");

async function pay_now(button, currency) {

    const fullname = document.getElementById("name").value.trim();
    const email = document.getElementById("email").value.trim();
    const phoneInput = window.intlTelInputGlobals.getInstance(document.getElementById("phone"));
    const phone = phoneInput.getNumber();
    const country = document.getElementById("country").value;
    const state = document.getElementById("state").value;
    const city = document.getElementById("city").value.trim();
    const gender = document.getElementById("gender").value;

    // Validation
    if (!fullname) return alert("Please enter your full name.");
    if (!email || !/^\S+@\S+\.\S+$/.test(email)) return alert("Please enter a valid email.");
    if (!phoneInput.isValidNumber()) return alert("Please enter a valid phone number.");
    if (!gender) return alert("Please select your gender.");
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
        currency: currency,
    };
    // pay now button should be disabled to prevent multiple clicks and show please wait message
    
    //disabling the 2 buttons of pay with Naira and USD
  if (userCountry !== "Nigeria") {
        Swal.fire({
            icon: 'warning',
            title: 'Unavailable',
            text: 'Payment in Naira is only available to users located in Nigeria. Please use the USD option.',
            confirmButtonColor: '#d33'
        });
        return;
    }
    else{
        payNairaButton.disabled = true;
        payUsdButton.disabled = true;
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
        payUsdButton.disabled = false;
        button.textContent = "Pay in " + currency;
    }
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

</body>

</html>